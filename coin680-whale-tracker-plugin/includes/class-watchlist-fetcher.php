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
 *
 * Extended 2026-08-08: poll_chain()/process_trade() below ONLY ever
 * matched DEX swaps (Bitquery's DEXTrades type -- a trade with a Buy leg
 * and a Sell leg through a DEX contract). A user report plus direct
 * cross-check against Etherscan/Ethplorer confirmed two watched wallets
 * had large, real transfers (thousands of ETH) that never appeared here
 * at all -- because those were plain transfers (exchange deposits/
 * withdrawals, wallet-to-wallet moves), not DEX swaps, and the old code
 * had no path to see anything outside DEXTrades. Added poll_transfers()/
 * process_transfer() to also query Bitquery's plain Transfers type for
 * EVM chains (Solana transfer schema not covered yet -- no Solana wallets
 * watched as of this writing, add support if that changes) so ANY
 * transfer in or out of a watched wallet gets recorded, not just swaps.
 * Doubles the Bitquery query count per poll cycle (one DEXTrades query +
 * one Transfers query per chain instead of just one) -- worth watching
 * against the point budget if the watchlist grows much beyond a handful
 * of wallets (see [[coin680-bitquery-point-budget-2026-07]]).
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

    /**
     * Best-effort, publicly-known exchange hot wallet addresses (lowercase)
     * -- used only to label the counterparty of a plain transfer as e.g.
     * "Binance" instead of a raw truncated address when it happens to
     * match. This list is NOT exhaustive (exchanges rotate/add hot wallets
     * constantly) and a miss just falls back to showing the counterparty's
     * own address, which is still useful -- this is a labeling nicety, not
     * something the matching logic depends on for correctness.
     */
    const KNOWN_EXCHANGE_WALLETS = array(
        // Ethereum mainnet
        '0x28c6c06298d514db089934071355e5743bf21d60' => 'Binance',
        '0x21a31ee1afc51d94c2efccaa2092ad1028285549' => 'Binance',
        '0xdfd5293d8e347dfe59e90efd55b2956a1343963d' => 'Binance',
        '0x71660c4005ba85c37ccec55d0c4493e66fe775d3' => 'Coinbase',
        '0x503828976d22510aad0201ac7ec88293211d923' => 'Coinbase',
        '0x3cd751e6b0078be393132286c442345e5dc49699' => 'Coinbase',
        '0x2910543af39aba0cd09dbb2d50200b3e800a63d2' => 'Kraken',
        '0x6cc5f688a315f3dc28a7781717a9a798a59fda7b' => 'OKX',
        '0xf89d7b9c864f589bbf53a82105107622b35eaa40' => 'Bybit',
        '0x6262998ced04146fa42253a5c0af90ca02dfd2a3' => 'Crypto.com',
        // BSC
        '0x8894e0a0c962cb723c1976a4421c95949be2d4e' => 'Binance',
        '0xf977814e90da44bfa03b6295a0616a897441acec' => 'Binance',
    );

    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('coin680watchlist_poll', array($this, 'poll'));
        add_action('coin680whale_daily_cleanup', array($this, 'cleanup_old'));
    }

    public static function table_name() {
        global $wpdb;
        return $wpdb->prefix . 'coin680_watchlist_txns';
    }

    /**
     * Same retention/housekeeping reasoning as Coin680Bitquery_Fetcher::
     * cleanup_old() -- see that method's docblock. Shares the same daily
     * cron hook (both classes' constructors hook it independently; WP
     * allows multiple callbacks on one action).
     */
    public function cleanup_old($days = 14) {
        global $wpdb;
        $table = self::table_name();
        $cutoff = gmdate('Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS);
        $wpdb->query($wpdb->prepare("DELETE FROM $table WHERE tx_timestamp < %s", $cutoff));
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
            wp_schedule_event(time(), 'coin680x_five_minutes', 'coin680watchlist_poll');
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
            $config = Coin680Bitquery_Labels::chain_config($chain);
            $this->poll_chain($chain, $config, $chain_wallets);
            $this->poll_transfers($chain, $config, $chain_wallets);
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
            $query = "{ Solana { DEXTrades(limit: {count: 50} orderBy: {descending: Block_Time} where: {Block: {Time: {since: \"{$since}\" till: \"{$now}\"}} any: [{Trade: {Buy: {Account: {Address: {in: {$address_list}}}}}} {Trade: {Sell: {Account: {Address: {in: {$address_list}}}}}}]}) { Transaction { Signature } Trade { {$trade_fields} } Block { Time } } } }";
        } else { // evm
            $trade_fields = str_replace(
                array('%ADDR_BUY%', '%ADDR_SELL%'),
                array('Buyer', 'Seller'),
                $fields
            );
            $network = $config['network'];
            $query = "{ EVM(network: {$network}) { DEXTrades(limit: {count: 50} orderBy: {descending: Block_Time} where: {Block: {Time: {since: \"{$since}\" till: \"{$now}\"}} any: [{Trade: {Buy: {Buyer: {in: {$address_list}}}}} {Trade: {Sell: {Seller: {in: {$address_list}}}}}]}) { Transaction { Hash } Trade { {$trade_fields} } Block { Time } } } }";
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
     * Plain-transfer counterpart to poll_chain()/process_trade() above --
     * catches exchange deposits/withdrawals and wallet-to-wallet moves that
     * never go through a DEX contract, so never show up as a DEXTrades Buy/
     * Sell leg. EVM only for now (Solana's Transfers schema differs enough
     * that it wasn't worth guessing at without a Solana wallet actually on
     * the watchlist to test against).
     */
    private function poll_transfers($chain, $config, $wallets) {
        if (!$config || $config['query_kind'] !== 'evm') {
            return;
        }

        $last_opt = "coin680watchlist_last_transfer_time_{$chain}";
        $since = get_option($last_opt) ?: gmdate('Y-m-d\TH:i:s\Z', time() - 15 * MINUTE_IN_SECONDS);
        $now = gmdate('Y-m-d\TH:i:s\Z', time());

        $addresses = wp_list_pluck($wallets, 'address');
        $address_list = wp_json_encode(array_values($addresses));
        $network = $config['network'];

        $query = "{ EVM(network: {$network}) { Transfers(limit: {count: 50} orderBy: {descending: Block_Time} where: {Block: {Time: {since: \"{$since}\" till: \"{$now}\"}} any: [{Transfer: {Sender: {in: {$address_list}}}} {Transfer: {Receiver: {in: {$address_list}}}}]}) { Transaction { Hash } Transfer { Sender Receiver Amount AmountInUSD Currency { Symbol } } Block { Time } } } }";

        $data = $this->gql($query);
        $rows = $data['EVM']['Transfers'] ?? null;

        if (is_array($rows)) {
            $wallet_by_address = array();
            foreach ($wallets as $w) {
                $wallet_by_address[strtolower($w->address)] = $w;
            }
            foreach ($rows as $row) {
                $this->process_transfer($chain, $row, $wallet_by_address);
            }
        }

        update_option($last_opt, $now, false);
    }

    private function process_transfer($chain, $row, $wallet_by_address) {
        $t = $row['Transfer'] ?? null;
        if (!$t) {
            return;
        }
        $sender = strtolower($t['Sender'] ?? '');
        $receiver = strtolower($t['Receiver'] ?? '');
        if (!$sender || !$receiver || $sender === $receiver) {
            return; // self-transfer or malformed row, nothing meaningful to record
        }
        $tx_hash = $row['Transaction']['Hash'] ?? '';
        if (!$tx_hash) {
            return;
        }
        $symbol = $t['Currency']['Symbol'] ?? '';
        $amount = (float) ($t['Amount'] ?? 0);
        $amount_usd = (float) ($t['AmountInUSD'] ?? 0);
        if (!$symbol || $amount_usd < self::MIN_USD) {
            return;
        }
        $timestamp = $row['Block']['Time'] ?? null;

        $matches = array();
        if (isset($wallet_by_address[$sender])) {
            $matches[] = array('wallet' => $wallet_by_address[$sender], 'side' => 'sent', 'counterparty' => $receiver);
        }
        if (isset($wallet_by_address[$receiver])) {
            $matches[] = array('wallet' => $wallet_by_address[$receiver], 'side' => 'received', 'counterparty' => $sender);
        }
        if (empty($matches)) {
            return;
        }

        global $wpdb;
        $table = self::table_name();

        foreach ($matches as $match) {
            $wallet = $match['wallet'];
            $counterparty_label = self::KNOWN_EXCHANGE_WALLETS[$match['counterparty']]
                ?? (substr($match['counterparty'], 0, 6) . '...' . substr($match['counterparty'], -4));

            // trade_index doesn't exist for plain transfers (no Trade.Index
            // field on this Bitquery type) -- the UNIQUE KEY still needs
            // something to distinguish multiple transfer legs that might
            // share the same tx_hash+wallet (e.g. two different tokens
            // moved to the same wallet in one transaction). A deterministic
            // hash of sender+receiver+symbol+amount, offset well above the
            // trade_index range DEX rows use, keeps this collision-safe in
            // all but very unusual repeat-exact-amount cases.
            $synthetic_index = 1000000 + (crc32($sender . $receiver . $symbol . $amount) % 900000);

            $wpdb->query($wpdb->prepare(
                "INSERT IGNORE INTO $table
                (wallet_id, chain, tx_hash, trade_index, side, symbol, counter_symbol, dex_name, amount, amount_usd, tx_timestamp, created_at)
                VALUES (%d,%s,%s,%d,%s,%s,%s,%s,%f,%f,%s,%s)",
                (int) $wallet->id,
                $chain,
                $tx_hash,
                $synthetic_index,
                $match['side'],
                strtoupper($symbol),
                '',
                $counterparty_label,
                $amount,
                $amount_usd,
                $timestamp ? gmdate('Y-m-d H:i:s', strtotime($timestamp)) : current_time('mysql', true),
                current_time('mysql', true)
            ));

            if ($wpdb->rows_affected > 0) {
                $this->maybe_alert($wallet, $wpdb->insert_id, $chain, $match['side'], strtoupper($symbol), '', $amount_usd, $counterparty_label, $tx_hash);
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
