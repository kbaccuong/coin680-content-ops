<?php
/**
 * Polls the Whale Alert v1 API on a schedule, classifies each transaction
 * with a simple, deterministic rule (no AI/guessing involved -- just the
 * from/to owner_type fields Whale Alert already provides), and stores
 * everything in our own table. Writing the actual narrative digest post
 * stays a manual/reviewed step (see Coin680-News-Playbook.md) -- this
 * class only handles honest data collection.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Coin680Whale_Fetcher {
    private static $instance = null;

    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('coin680whale_poll', array($this, 'poll'));
    }

    public static function table_name() {
        global $wpdb;
        return $wpdb->prefix . 'coin680_whale_txns';
    }

    public static function create_table() {
        global $wpdb;
        $table = self::table_name();
        $charset_collate = $wpdb->get_charset_collate();
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta("CREATE TABLE $table (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            whale_alert_id VARCHAR(50) NOT NULL,
            blockchain VARCHAR(50) NOT NULL DEFAULT '',
            symbol VARCHAR(20) NOT NULL DEFAULT '',
            transaction_type VARCHAR(20) NOT NULL DEFAULT '',
            classification VARCHAR(30) NOT NULL DEFAULT '',
            from_owner VARCHAR(100) NOT NULL DEFAULT '',
            from_owner_type VARCHAR(30) NOT NULL DEFAULT '',
            to_owner VARCHAR(100) NOT NULL DEFAULT '',
            to_owner_type VARCHAR(30) NOT NULL DEFAULT '',
            amount DOUBLE NOT NULL DEFAULT 0,
            amount_usd DOUBLE NOT NULL DEFAULT 0,
            btc_price_usd DOUBLE NOT NULL DEFAULT 0,
            tx_timestamp DATETIME NOT NULL,
            hash VARCHAR(120) NOT NULL DEFAULT '',
            used_in_digest TINYINT(1) NOT NULL DEFAULT 0,
            mega_alerted TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY whale_alert_id (whale_alert_id),
            KEY tx_timestamp (tx_timestamp),
            KEY amount_usd (amount_usd)
        ) $charset_collate;");

        if (!wp_next_scheduled('coin680whale_poll')) {
            wp_schedule_event(time(), 'coin680x_five_minutes', 'coin680whale_poll');
        }
    }

    /**
     * Real BTC price at the moment we capture a transaction, reused later
     * for honest historical comparisons ("last time we saw a similar move,
     * price has since done X") -- always computed from the same cached
     * CoinGecko feed the rest of the site uses (coin680_get_crypto_prices()
     * in the theme), never invented.
     */
    private function current_btc_price() {
        if (!function_exists('coin680_get_crypto_prices')) {
            return 0;
        }
        $coins = coin680_get_crypto_prices();
        if (!$coins) {
            return 0;
        }
        foreach ($coins as $coin) {
            if ($coin['id'] === 'bitcoin') {
                return (float) $coin['current_price'];
            }
        }
        return 0;
    }

    private function classify($tx) {
        if ($tx['transaction_type'] === 'mint') {
            return 'Mint';
        }
        if ($tx['transaction_type'] === 'burn') {
            return 'Burn';
        }
        $from_exchange = ($tx['from']['owner_type'] ?? '') === 'exchange';
        $to_exchange = ($tx['to']['owner_type'] ?? '') === 'exchange';
        if ($to_exchange && !$from_exchange) {
            return 'Exchange Inflow';
        }
        if ($from_exchange && !$to_exchange) {
            return 'Exchange Outflow';
        }
        if ($from_exchange && $to_exchange) {
            return 'Exchange to Exchange';
        }
        return 'Wallet Transfer';
    }

    public function poll() {
        $settings = get_option('coin680whale_settings', array());
        $api_key = $settings['api_key'] ?? '';
        if (!$api_key) {
            return;
        }
        $min_value = isset($settings['min_value']) ? (int) $settings['min_value'] : 500000;

        $last_poll = get_option('coin680whale_last_poll');
        $start = $last_poll ? (int) $last_poll : (time() - 3600);
        // Whale Alert's free/basic history window is limited; never request further back than 1 hour extra as a safety margin.
        $start = max($start, time() - 3600 * 6);

        $url = add_query_arg(array(
            'api_key'   => $api_key,
            'min_value' => $min_value,
            'start'     => $start,
            'limit'     => 100,
        ), 'https://api.whale-alert.io/v1/transactions');

        $response = wp_remote_get($url, array('timeout' => 20));
        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return;
        }
        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (empty($body['transactions'])) {
            update_option('coin680whale_last_poll', time());
            return;
        }

        global $wpdb;
        $table = self::table_name();
        $latest_ts = $start;
        $btc_price = $this->current_btc_price();
        $mega_threshold = isset($settings['mega_threshold']) ? (int) $settings['mega_threshold'] : 50000000;

        foreach ($body['transactions'] as $tx) {
            $classification = $this->classify($tx);
            $wpdb->query($wpdb->prepare(
                "INSERT IGNORE INTO $table
                (whale_alert_id, blockchain, symbol, transaction_type, classification, from_owner, from_owner_type, to_owner, to_owner_type, amount, amount_usd, btc_price_usd, tx_timestamp, hash, created_at)
                VALUES (%s,%s,%s,%s,%s,%s,%s,%s,%s,%f,%f,%f,%s,%s,%s)",
                $tx['id'],
                $tx['blockchain'],
                $tx['symbol'],
                $tx['transaction_type'],
                $classification,
                $tx['from']['owner'] ?? 'unknown',
                $tx['from']['owner_type'] ?? 'unknown',
                $tx['to']['owner'] ?? 'unknown',
                $tx['to']['owner_type'] ?? 'unknown',
                $tx['amount'],
                $tx['amount_usd'],
                $btc_price,
                gmdate('Y-m-d H:i:s', $tx['timestamp']),
                $tx['hash'],
                current_time('mysql', true)
            ));
            $latest_ts = max($latest_ts, $tx['timestamp']);

            // Only a genuinely new row (not a re-seen duplicate from an
            // overlapping poll window) should ever trigger a breaking alert.
            if ($wpdb->rows_affected > 0 && $tx['amount_usd'] >= $mega_threshold) {
                $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE whale_alert_id = %s", $tx['id']));
                if ($row && class_exists('Coin680Whale_Digest')) {
                    Coin680Whale_Digest::instance()->post_mega_alert($row);
                }
            }
        }

        update_option('coin680whale_last_poll', $latest_ts + 1);
    }

    public static function get_recent($hours = 24, $limit = 100) {
        global $wpdb;
        $table = self::table_name();
        $since = gmdate('Y-m-d H:i:s', time() - $hours * HOUR_IN_SECONDS);
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE tx_timestamp >= %s ORDER BY amount_usd DESC LIMIT %d",
            $since, $limit
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
