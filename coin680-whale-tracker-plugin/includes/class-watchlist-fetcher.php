<?php
/**
 * Polls Bitquery for NEW activity from specifically watched wallets
 * ("smart money" addresses added via the admin page) -- a different
 * selection principle from Coin680Bitquery_Fetcher's market-wide amount
 * threshold scan: a trade here qualifies because of WHO made it, not how
 * large it is (still subject to a small dust-filter minimum, MIN_USD,
 * which is deliberately much lower than the whale-sized thresholds used
 * elsewhere).
 *
 * Solana + BSC/Ethereum (EVM) only for now -- TRON's DEXTrades schema
 * wasn't confirmed to expose a queryable buyer/seller address field when
 * the original Coin680Bitquery_Fetcher was built (see that class's
 * poll_chain() -- %ADDR_BUY%/%ADDR_SELL% are left blank for the tron
 * query_kind there), so wallet-level filtering isn't attempted for TRON
 * rather than guessing new query syntax that's never been verified
 * against the real API.
 *
 * Added 2026-07-30 after upgrading to a paid Bitquery plan.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Coin680Watchlist_Fetcher {
    private static $instance = null;
    const ENDPOINT = 'https://streaming.bitquery.io/graphql';

    const MIN_USD = 500;
    const ALERT_COOLDOWN_MINUTES = 10;
    const SUPPORTED_KINDS = array('solana', 'evm');

    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('coin680watchlist_poll', array($this, 'poll'));
    }

    public static function table_name() {
        global $wpdb;
        return $wpdb->prefix . 'coin680_watchlist_txns';
    }

    public static function create_table() {
        global $wpdb;
        $table = self::table_name();
        $charset_collate = $wpdb->get_charset_collate();
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta("CREATE TABLE $table (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            wallet_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            chain VARCHAR(20) NOT NULL DEFAULT '',
            tx_hash VARCHAR(120) NOT NULL DEFAULT '',
            trade_index INT NOT NULL DEFAULT 0,
            side VARCHAR(10) NOT NULL DEFAULT '',
            symbol VARCHAR(30) NOT NULL DEFAULT '',
            counter_symbol VARCHAR(30) NOT NULL DEFAULT '',
            dex_name VARCHAR(60) NOT NULL DEFAULT '',
            amount DOUBLE NOT NULL DEFAULT 0,
            amount_usd DOUBLE NOT NULL DEFAULT 0,
            tx_timestamp DATETIME NOT NULL,
            alerted TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY tx_leg (chain, tx_hash(80), trade_index, wallet_id),
            KEY wallet_id (wallet_id),
            KEY tx_timestamp (tx_timestamp)
        ) $charset_collate;");

        if (!wp_next_scheduled('coin680watchlist_poll')) {
            wp_schedule_event(time(), 'coin680x_two_minutes', 'coin680watchlist_poll');
        }
    }

    private function access_token() {
        $settings = get_option('coin680whale_settings', array());
        return $settings['bitquery_access_token'] ?? '';
    }

    private function gql($query) {
        $token = $this->access_token();
        if (!$token) {
            return null;
        }
        $response = wp_remote_post(self::ENDPOINT, array(
            'timeout' => 25,
            'headers' => array(
                'Authorization' => 'Bearer ' . $token,
                'Content-Type'  => 'application/json',
            ),
            'body' => wp_json_encode(array('query' => $query)),
        ));
        if (is_wp_error($response)) {
            return null;
        }
        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (!empty($body['errors'])) {
            return null;
        }
        return $body['data'] ?? null;
    }

    public function poll() {
        if (!$this->access_token() || !class_exists('Coin680Watchlist')) {
            return;
        }
        $wallets = Coin680Watchlist::get_all(true);
        if (empty($wallets)) {
            return;
        }

        // Group by chain so each chain needs only one query per poll cycle
        // covering ALL its watched wallets at once (via a GraphQL `in:`
        // filter), rather than one query per wallet -- keeps point usage
        // roughly flat regardless of how many wallets are being watched on
        // a given chain.
        $by_chain = array();
        foreach ($wallets as $w) {
            $config = Coin680Bitquery_Labels::chain_config($w->chain);
            if (!$config || !in_array($config['query_kind'], self::SUPPORTED_KINDS, true)) {
                continue;
            }
            $by_chain[$w->chain][] = $w;
        }

        foreach ($by_chain as $chain => $chain_wallets) {
            $this->poll_chain($chain, Coin680Bitquery_Labels::chain_config($chain), $chain_wallets);
        }
    }

    private function poll_chain($chain, $config, $wallets) {
        $last_opt = "coin680watchlist_last_time_{$chain}";
        $since = get_option($last_opt) ?: gmdate('Y-m-d\TH:i:s\Z', time() - 15 * MINUTE_IN_SECONDS);
        $now = gmdate('Y-m-d\TH:i:s\Z', time());

        $addresses = wp_list_pluck($wallets, 'address');
        $address_list = wp_json_encode(array_values($addresses));

        $fields = 'Index Buy { Amount AmountInUSD Currency { Symbol } %ADDR_BUY% } Sell { Amount AmountInUSD Currency { Symbol } %ADDR_SELL% } Dex { ProtocolName }';

        if ($config['query_kind'] === 'solana') {
            $trade_fields = str_replace(
                array('%ADDR_BUY%', '%ADDR_SELL%'),
                array('Account { Address }', 'Account { Address }'),
                $fields
            );
            $query = "{ Solana { DEXTrades(limit: {count: 200} orderBy: {descending: Block_Time} where: {Block: {Time: {since: \"{$since}\" till: \"{$now}\"}} any: [{Trade: {Buy: {Account: {Address: {in: {$address_list}}}}}} {Trade: {Sell: {Account: {Address: {in: {$address_list}}}}}}]}) { Transaction { Signature } Trade { {$trade_fields} } Block { Time } } } }";
        } else { // evm
            $trade_fields = str_replace(
                array('%ADDR_BUY%', '%ADDR_SELL%'),
                array('Buyer', 'Seller'),
                $fields
            );
            $network = $config['network'];
            $query = "{ EVM(network: {$network}) { DEXTrades(limit: {count: 200} orderBy: {descending: Block_Time} where: {Block: {Time: {since: \"{$since}\" till: \"{$now}\"}} any: [{Trade: {Buy: {Buyer: {in: {$address_list}}}}} {Trade: {Sell: {Seller: {in: {$address_list}}}}}]}) { Transaction { Hash } Trade { {$trade_fields} } Block { Time } } } }";
        }

        $data = $this->gql($query);
        $rows = ($config['query_kind'] === 'solana') ? ($data['Solana']['DEXTrades'] ?? null) : ($data['EVM']['DEXTrades'] ?? null);

        if (is_array($rows)) {
            $wallet_by_address = array();
            foreach ($wallets as $w) {
                $wallet_by_address[strtolower($w->address)] = $w;
            }
            foreach ($rows as $row) {
                $this->process_trade($chain, $row, $wallet_by_address);
            }
        }

        update_option($last_opt, $now, false);
    }

    private function process_trade($chain, $row, $wallet_by_address) {
        $buy = $row['Trade']['Buy'] ?? null;
        $sell = $row['Trade']['Sell'] ?? null;
        if (!$buy || !$sell) {
            return;
        }
        $buyer_address = strtolower($buy['Buyer'] ?? ($buy['Account']['Address'] ?? ''));
        $seller_address = strtolower($sell['Seller'] ?? ($sell['Account']['Address'] ?? ''));

        // A trade can match a watched wallet on either leg -- handle each
        // match separately since it's a distinct "this wallet did X" event,
        // even in the rare case both sides happen to be watched wallets.
        $matches = array();
        if (isset($wallet_by_address[$buyer_address])) {
            $matches[] = array('wallet' => $wallet_by_address[$buyer_address], 'side' => 'buy');
        }
        if (isset($wallet_by_address[$seller_address])) {
            $matches[] = array('wallet' => $wallet_by_address[$seller_address], 'side' => 'sell');
        }
        if (empty($matches)) {
            return;
        }

        $buy_symbol = $buy['Currency']['Symbol'] ?? '';
        $sell_symbol = $sell['Currency']['Symbol'] ?? '';
        $buy_usd = (float) ($buy['AmountInUSD'] ?? 0);
        $sell_usd = (float) ($sell['AmountInUSD'] ?? 0);
        $tx_hash = $row['Transaction']['Signature'] ?? ($row['Transaction']['Hash'] ?? '');
        if (!$tx_hash) {
            return;
        }
        $trade_index = (int) ($row['Trade']['Index'] ?? 0);
        $dex_name = self::humanize_dex_name($row['Trade']['Dex']['ProtocolName'] ?? '');
        $timestamp = $row['Block']['Time'] ?? null;

        global $wpdb;
        $table = self::table_name();

        foreach ($matches as $match) {
            $wallet = $match['wallet'];
            $side = $match['side'];

            // From the WATCHED WALLET's own perspective: on the buy side,
            // the "subject" token is what it bought; on the sell side, it's
            // what it sold.
            if ($side === 'buy') {
                $symbol = $buy_symbol;
                $counter_symbol = $sell_symbol;
                $amount = (float) ($buy['Amount'] ?? 0);
                $amount_usd = $buy_usd > 0 ? $buy_usd : $sell_usd;
            } else {
                $symbol = $sell_symbol;
                $counter_symbol = $buy_symbol;
                $amount = (float) ($sell['Amount'] ?? 0);
                $amount_usd = $sell_usd > 0 ? $sell_usd : $buy_usd;
            }
            if (!$symbol || $amount_usd < self::MIN_USD) {
                continue;
            }

            $wpdb->query($wpdb->prepare(
                "INSERT IGNORE INTO $table
                (wallet_id, chain, tx_hash, trade_index, side, symbol, counter_symbol, dex_name, amount, amount_usd, tx_timestamp, created_at)
                VALUES (%d,%s,%s,%d,%s,%s,%s,%s,%f,%f,%s,%s)",
                (int) $wallet->id,
                $chain,
                $tx_hash,
                $trade_index,
                $side,
                $symbol,
                $counter_symbol,
                $dex_name,
                $amount,
                $amount_usd,
                $timestamp ? gmdate('Y-m-d H:i:s', strtotime($timestamp)) : current_time('mysql', true),
                current_time('mysql', true)
            ));

            // rows_affected is 0 for a duplicate the UNIQUE KEY silently
            // ignored, 1 for a genuinely new row -- only alert on the latter
            // so re-polling the same time window never double-alerts.
            if ($wpdb->rows_affected > 0) {
                $this->maybe_alert($wallet, $wpdb->insert_id, $chain, $side, $symbol, $counter_symbol, $amount_usd, $dex_name, $tx_hash);
            }
        }
    }

    /**
     * Fires an immediate "Smart Money" alert for a newly-recorded watched-
     * wallet trade, rate-limited per WALLET (not globally) so one
     * hyperactive address (a market maker, a bot) can't flood the channel
     * while other watched wallets still alert normally. The trade is
     * recorded in the table either way regardless of cooldown -- only the
     * individual X alert is skipped.
     */
    private function maybe_alert($wallet, $row_id, $chain, $side, $symbol, $counter_symbol, $amount_usd, $dex_name, $tx_hash) {
        $cooldown_opt = 'coin680watchlist_last_alert_' . (int) $wallet->id;
        $last = get_option($cooldown_opt);
        if ($last && (time() - (int) $last) < self::ALERT_COOLDOWN_MINUTES * MINUTE_IN_SECONDS) {
            return;
        }

        if (class_exists('Coin680Whale_Digest')) {
            Coin680Whale_Digest::instance()->post_watchlist_alert(array(
                'wallet_label'   => $wallet->label ?: (substr($wallet->address, 0, 6) . '...' . substr($wallet->address, -4)),
                'chain'          => $chain,
                'side'           => $side,
                'symbol'         => $symbol,
                'counter_symbol' => $counter_symbol,
                'amount_usd'     => $amount_usd,
                'dex_name'       => $dex_name,
                'url'            => $this->explorer_url($chain, $tx_hash),
            ));
        }

        global $wpdb;
        $wpdb->update(self::table_name(), array('alerted' => 1), array('id' => $row_id), array('%d'), array('%d'));
        update_option($cooldown_opt, time(), false);
    }

    private function explorer_url($chain, $hash) {
        $config = Coin680Bitquery_Labels::chain_config($chain);
        return ($config && $hash) ? sprintf($config['explorer'], $hash) : '';
    }

    private static function humanize_dex_name($slug) {
        if (!$slug) {
            return '';
        }
        return ucwords(str_replace('_', ' ', $slug));
    }

    public static function get_recent($hours = 24, $limit = 100, $offset = 0) {
        global $wpdb;
        $table = self::table_name();
        $wallets_table = Coin680Watchlist::table_name();
        $since = gmdate('Y-m-d H:i:s', time() - $hours * HOUR_IN_SECONDS);
        return $wpdb->get_results($wpdb->prepare(
            "SELECT t.*, w.label AS wallet_label, w.address AS wallet_address
             FROM $table t LEFT JOIN $wallets_table w ON w.id = t.wallet_id
             WHERE t.tx_timestamp >= %s
             ORDER BY t.tx_timestamp DESC LIMIT %d OFFSET %d",
            $since, $limit, $offset
        ));
    }

    public static function count_recent($hours = 24) {
        global $wpdb;
        $table = self::table_name();
        $since = gmdate('Y-m-d H:i:s', time() - $hours * HOUR_IN_SECONDS);
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE tx_timestamp >= %s", $since
        ));
    }
}
