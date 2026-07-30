<?php
/**
 * Polls Bitquery's unified GraphQL API for large DEX swaps on Solana, BSC,
 * Ethereum, and TRON -- replacing the Etherscan-based Coin680MultiChain_
 * Fetcher for BSC/Ethereum (Solana/TRON are new coverage Etherscan could
 * never provide, being non-EVM). Chosen over the Etherscan approach for
 * two reasons, confirmed via live test calls before building this: (1)
 * Bitquery already computes each trade's Trade.Buy/Sell.AmountInUSD
 * itself, so there's no need to hand-maintain a token-contract ->
 * CoinGecko-id price map the way Coin680MultiChain_Labels::TOKEN_PRICE_IDS
 * did; (2) one DEXTrades result already bundles BOTH legs of a swap
 * (Buy + Sell), so there's no need to separately scan raw Transfer event
 * logs and re-pair them by matching router addresses within the same tx.
 *
 * Scope: DEX swaps only (Buy/Sell/Swap), same as the Etherscan build's
 * MVP -- does not (yet) cross-reference CEX-labeled addresses the way
 * Coin680MultiChain_Fetcher::cex_label() did for EVM chains sharing Whale
 * Alert's stored addresses; Solana/TRON addresses wouldn't match Whale
 * Alert's EVM-format addresses anyway, and adding it back for BSC/
 * Ethereum specifically is a reasonable fast-follow, not in this build.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Coin680Bitquery_Fetcher {
    private static $instance = null;
    const ENDPOINT = 'https://streaming.bitquery.io/graphql';

    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('coin680bitquery_poll', array($this, 'poll'));
    }

    public static function table_name() {
        global $wpdb;
        return $wpdb->prefix . 'coin680_bitquery_txns';
    }

    public static function create_table() {
        global $wpdb;
        $table = self::table_name();
        $charset_collate = $wpdb->get_charset_collate();
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta("CREATE TABLE $table (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            chain VARCHAR(20) NOT NULL DEFAULT '',
            tx_hash VARCHAR(120) NOT NULL DEFAULT '',
            trade_index INT NOT NULL DEFAULT 0,
            symbol VARCHAR(30) NOT NULL DEFAULT '',
            counter_symbol VARCHAR(30) NOT NULL DEFAULT '',
            classification VARCHAR(30) NOT NULL DEFAULT '',
            dex_name VARCHAR(60) NOT NULL DEFAULT '',
            from_address VARCHAR(100) NOT NULL DEFAULT '',
            to_address VARCHAR(100) NOT NULL DEFAULT '',
            amount DOUBLE NOT NULL DEFAULT 0,
            amount_usd DOUBLE NOT NULL DEFAULT 0,
            tx_timestamp DATETIME NOT NULL,
            used_in_digest TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY tx_leg (chain, tx_hash(80), trade_index),
            KEY tx_timestamp (tx_timestamp),
            KEY amount_usd (amount_usd)
        ) $charset_collate;");

        // Every 2 min as of 2026-07-30 (was 5 min) -- see coin680whale_add_
        // cron_interval() in the main plugin file for why.
        if (!wp_next_scheduled('coin680bitquery_poll')) {
            wp_schedule_event(time(), 'coin680x_two_minutes', 'coin680bitquery_poll');
        }
    }

    private function access_token() {
        $settings = get_option('coin680whale_settings', array());
        return $settings['bitquery_access_token'] ?? '';
    }

    private function major_threshold() {
        $settings = get_option('coin680whale_settings', array());
        return isset($settings['bitquery_min_value']) ? (int) $settings['bitquery_min_value'] : 100000;
    }

    private function token_threshold() {
        $settings = get_option('coin680whale_settings', array());
        return isset($settings['bitquery_token_min_value']) ? (int) $settings['bitquery_token_min_value'] : 10000;
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
        if (!$this->access_token()) {
            return;
        }
        foreach (Coin680Bitquery_Labels::CHAINS as $chain => $config) {
            $this->poll_chain($chain, $config);
        }
    }

    private function poll_chain($chain, $config) {
        $last_opt = "coin680bitquery_last_time_{$chain}";
        // First run for this chain: start ~15 minutes back rather than
        // pulling a huge historical backlog.
        $since = get_option($last_opt) ?: gmdate('Y-m-d\TH:i:s\Z', time() - 15 * MINUTE_IN_SECONDS);
        $now = gmdate('Y-m-d\TH:i:s\Z', time());

        // Query at the LOWER of the two thresholds (major/token), on
        // EITHER side of the trade (`any: [...]` is an OR) -- Bitquery's
        // own AmountInUSD can be 0/unpriced on one side for a brand new or
        // illiquid token, so filtering on only one side would miss trades
        // where the OTHER side is the one carrying a real price.
        $min = min($this->major_threshold(), $this->token_threshold());

        // NOTE: these are the FIELDS INSIDE "Trade { ... }" -- the caller
        // wraps them as "Trade { {$trade_fields} }" below. (An earlier
        // version of this string started with a stray "{ Index }" and was
        // interpolated as "Trade {$trade_fields}" with no explicit outer
        // braces -- since PHP's "{$var}" syntax is just an interpolation
        // delimiter, not a literal brace, that closed Trade's selection
        // set right after Index and left Buy/Sell/Dex as invalid top-level
        // siblings, so Bitquery rejected every query and poll() silently
        // did nothing. Fixed 2026-07-30 after the admin table stayed
        // empty even after polling.)
        $fields = 'Index Buy { Amount AmountInUSD Currency { Symbol } %ADDR_BUY% } Sell { Amount AmountInUSD Currency { Symbol } %ADDR_SELL% } Dex { ProtocolName }';

        if ($config['query_kind'] === 'solana') {
            $trade_fields = str_replace(
                array('%ADDR_BUY%', '%ADDR_SELL%'),
                array('Account { Address }', 'Account { Address }'),
                $fields
            );
            $query = "{ Solana { DEXTrades(limit: {count: 200} orderBy: {descending: Block_Time} where: {Block: {Time: {since: \"{$since}\" till: \"{$now}\"}} any: [{Trade: {Buy: {AmountInUSD: {gt: \"{$min}\"}}}} {Trade: {Sell: {AmountInUSD: {gt: \"{$min}\"}}}}]}) { Transaction { Signature } Trade { {$trade_fields} } Block { Time } } } }";
        } elseif ($config['query_kind'] === 'evm') {
            $trade_fields = str_replace(
                array('%ADDR_BUY%', '%ADDR_SELL%'),
                array('Buyer', 'Seller'),
                $fields
            );
            $network = $config['network'];
            $query = "{ EVM(network: {$network}) { DEXTrades(limit: {count: 200} orderBy: {descending: Block_Time} where: {Block: {Time: {since: \"{$since}\" till: \"{$now}\"}} any: [{Trade: {Buy: {AmountInUSD: {gt: \"{$min}\"}}}} {Trade: {Sell: {AmountInUSD: {gt: \"{$min}\"}}}}]}) { Transaction { Hash } Trade { {$trade_fields} } Block { Time } } } }";
        } else { // tron
            $trade_fields = str_replace(array('%ADDR_BUY%', '%ADDR_SELL%'), array('', ''), $fields);
            $query = "{ Tron { DEXTrades(limit: {count: 200} orderBy: {descending: Block_Time} where: {Block: {Time: {since: \"{$since}\" till: \"{$now}\"}} any: [{Trade: {Buy: {AmountInUSD: {gt: \"{$min}\"}}}} {Trade: {Sell: {AmountInUSD: {gt: \"{$min}\"}}}}]}) { Transaction { Hash } Trade { {$trade_fields} } Block { Time } } } }";
        }

        $data = $this->gql($query);
        $rows = null;
        if ($config['query_kind'] === 'solana') {
            $rows = $data['Solana']['DEXTrades'] ?? null;
        } elseif ($config['query_kind'] === 'evm') {
            $rows = $data['EVM']['DEXTrades'] ?? null;
        } else {
            $rows = $data['Tron']['DEXTrades'] ?? null;
        }

        if (is_array($rows)) {
            foreach ($rows as $row) {
                $this->process_trade($chain, $row);
            }
        }

        update_option($last_opt, $now, false);
    }

    private function process_trade($chain, $row) {
        $buy = $row['Trade']['Buy'] ?? null;
        $sell = $row['Trade']['Sell'] ?? null;
        if (!$buy || !$sell) {
            return;
        }
        $buy_symbol = $buy['Currency']['Symbol'] ?? '';
        $sell_symbol = $sell['Currency']['Symbol'] ?? '';
        if (!$buy_symbol || !$sell_symbol) {
            return;
        }
        $buy_usd = (float) ($buy['AmountInUSD'] ?? 0);
        $sell_usd = (float) ($sell['AmountInUSD'] ?? 0);

        $buy_is_numeraire = Coin680Bitquery_Labels::is_numeraire($chain, $buy_symbol);
        $sell_is_numeraire = Coin680Bitquery_Labels::is_numeraire($chain, $sell_symbol);

        // Decide the "subject" token (the one this row reports on) and its
        // USD value. Prefer whichever side has a real priced value; if
        // both do, prefer the NON-numeraire side as the subject (that's
        // the "interesting" asset -- a stablecoin/native-gas token isn't
        // news on its own). If neither side is priced, skip -- never
        // guess a value Bitquery itself couldn't compute.
        if ($buy_is_numeraire && !$sell_is_numeraire) {
            $subject_symbol = $sell_symbol;
            $counter_symbol = $buy_symbol;
            $amount = (float) ($sell['Amount'] ?? 0);
            $amount_usd = $sell_usd > 0 ? $sell_usd : $buy_usd;
            $classification = 'DEX Sell'; // gave up the volatile token for cash/majors
            $is_major = false;
        } elseif ($sell_is_numeraire && !$buy_is_numeraire) {
            $subject_symbol = $buy_symbol;
            $counter_symbol = $sell_symbol;
            $amount = (float) ($buy['Amount'] ?? 0);
            $amount_usd = $buy_usd > 0 ? $buy_usd : $sell_usd;
            $classification = 'DEX Buy'; // acquired the volatile token with cash/majors
            $is_major = false;
        } else {
            // Both numeraire (e.g. USDT-for-USDC) or both non-numeraire
            // (token-for-token rotation) -- ambiguous which side is "the
            // trade", report the larger leg generically as a swap.
            $buy_bigger = $buy_usd >= $sell_usd;
            $subject_symbol = $buy_bigger ? $buy_symbol : $sell_symbol;
            $counter_symbol = $buy_bigger ? $sell_symbol : $buy_symbol;
            $amount = (float) ($buy_bigger ? ($buy['Amount'] ?? 0) : ($sell['Amount'] ?? 0));
            $amount_usd = $buy_bigger ? $buy_usd : $sell_usd;
            if ($amount_usd <= 0) {
                $amount_usd = $buy_bigger ? $sell_usd : $buy_usd;
            }
            $classification = 'DEX Swap';
            $is_major = $buy_is_numeraire && $sell_is_numeraire; // both stablecoin/majors -- routine, use the higher threshold
        }

        if ($amount_usd <= 0) {
            return; // neither side had a usable price -- never guess
        }

        $threshold = $is_major ? $this->major_threshold() : $this->token_threshold();
        if ($amount_usd < $threshold) {
            return;
        }

        $tx_hash = $row['Transaction']['Signature'] ?? ($row['Transaction']['Hash'] ?? '');
        if (!$tx_hash) {
            return;
        }
        $trade_index = (int) ($row['Trade']['Index'] ?? 0);
        $dex_name = $row['Trade']['Dex']['ProtocolName'] ?? '';
        $from_address = $sell['Seller'] ?? ($sell['Account']['Address'] ?? '');
        $to_address = $buy['Buyer'] ?? ($buy['Account']['Address'] ?? '');
        $timestamp = $row['Block']['Time'] ?? null;

        global $wpdb;
        $table = self::table_name();
        $wpdb->query($wpdb->prepare(
            "INSERT IGNORE INTO $table
            (chain, tx_hash, trade_index, symbol, counter_symbol, classification, dex_name, from_address, to_address, amount, amount_usd, tx_timestamp, created_at)
            VALUES (%s,%s,%d,%s,%s,%s,%s,%s,%s,%f,%f,%s,%s)",
            $chain,
            $tx_hash,
            $trade_index,
            $subject_symbol,
            $counter_symbol,
            $classification,
            self::humanize_dex_name($dex_name),
            $from_address,
            $to_address,
            $amount,
            $amount_usd,
            $timestamp ? gmdate('Y-m-d H:i:s', strtotime($timestamp)) : current_time('mysql', true),
            current_time('mysql', true)
        ));
    }

    /**
     * Bitquery's ProtocolName is a lowercase machine slug ("pancakeswap_
     * infinity", "pump_amm", "sun_swap_v2") -- prettify for the tweet copy.
     */
    private static function humanize_dex_name($slug) {
        if (!$slug) {
            return '';
        }
        return ucwords(str_replace('_', ' ', $slug));
    }

    public static function get_recent($hours = 24, $limit = 100, $offset = 0, $chain = null) {
        global $wpdb;
        $table = self::table_name();
        $since = gmdate('Y-m-d H:i:s', time() - $hours * HOUR_IN_SECONDS);
        if ($chain) {
            return $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $table WHERE tx_timestamp >= %s AND chain = %s ORDER BY amount_usd DESC LIMIT %d OFFSET %d",
                $since, $chain, $limit, $offset
            ));
        }
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE tx_timestamp >= %s ORDER BY amount_usd DESC LIMIT %d OFFSET %d",
            $since, $limit, $offset
        ));
    }

    public static function count_recent($hours = 24, $chain = null) {
        global $wpdb;
        $table = self::table_name();
        $since = gmdate('Y-m-d H:i:s', time() - $hours * HOUR_IN_SECONDS);
        if ($chain) {
            return (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table WHERE tx_timestamp >= %s AND chain = %s", $since, $chain
            ));
        }
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE tx_timestamp >= %s", $since
        ));
    }

    public static function mark_used($ids) {
        global $wpdb;
        if (empty($ids)) {
            return;
        }
        $table = self::table_name();
        $placeholders = implode(',', array_fill(0, count($ids), '%d'));
        $wpdb->query($wpdb->prepare("UPDATE $table SET used_in_digest = 1 WHERE id IN ($placeholders)", $ids));
    }
}

