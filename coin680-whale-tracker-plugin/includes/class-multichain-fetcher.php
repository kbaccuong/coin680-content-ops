<?php
/**
 * Scans Ethereum/Polygon/Arbitrum (and, once the Etherscan key is upgraded,
 * more EVM chains -- see Coin680MultiChain_Labels) for large ERC-20 token
 * transfers via the Etherscan V2 unified API, classifies each one as a DEX
 * swap (buy/sell/rotation, read from which side of the swap is a
 * stablecoin), a centralized-exchange in/out (cross-referenced against
 * addresses Whale Alert has already labeled for us -- see cex_label()), or
 * a plain wallet transfer, then stores it in a shape the digest can pull
 * from alongside Coin680Whale_Fetcher's own table.
 *
 * Deliberately narrower than the Whale Alert fetcher for this first
 * version: only tracks ERC-20/token transfers (via the Transfer event),
 * not native coin (ETH/MATIC) transfers -- a fast-follow, not in this
 * build. Only tokens with a known CoinGecko price mapping are considered,
 * since we never estimate a price we can't verify -- an unmapped token is
 * skipped entirely rather than shown without a $ figure.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Coin680MultiChain_Fetcher {
    private static $instance = null;

    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('coin680multichain_poll', array($this, 'poll'));
    }

    public static function table_name() {
        global $wpdb;
        return $wpdb->prefix . 'coin680_multichain_txns';
    }

    public static function create_table() {
        global $wpdb;
        $table = self::table_name();
        $charset_collate = $wpdb->get_charset_collate();
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta("CREATE TABLE $table (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            chain VARCHAR(20) NOT NULL DEFAULT '',
            tx_hash VARCHAR(80) NOT NULL,
            log_index INT NOT NULL DEFAULT 0,
            symbol VARCHAR(30) NOT NULL DEFAULT '',
            token_address VARCHAR(50) NOT NULL DEFAULT '',
            classification VARCHAR(30) NOT NULL DEFAULT '',
            counter_symbol VARCHAR(30) NOT NULL DEFAULT '',
            from_address VARCHAR(50) NOT NULL DEFAULT '',
            to_address VARCHAR(50) NOT NULL DEFAULT '',
            from_owner VARCHAR(100) NOT NULL DEFAULT '',
            from_owner_type VARCHAR(30) NOT NULL DEFAULT '',
            to_owner VARCHAR(100) NOT NULL DEFAULT '',
            to_owner_type VARCHAR(30) NOT NULL DEFAULT '',
            dex_name VARCHAR(40) NOT NULL DEFAULT '',
            amount DOUBLE NOT NULL DEFAULT 0,
            amount_usd DOUBLE NOT NULL DEFAULT 0,
            tx_timestamp DATETIME NOT NULL,
            used_in_digest TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY tx_hash_log (tx_hash, log_index),
            KEY tx_timestamp (tx_timestamp),
            KEY amount_usd (amount_usd)
        ) $charset_collate;");

        if (!wp_next_scheduled('coin680multichain_poll')) {
            wp_schedule_event(time(), 'coin680x_five_minutes', 'coin680multichain_poll');
        }
    }

    private function api_key() {
        $settings = get_option('coin680whale_settings', array());
        return $settings['etherscan_api_key'] ?? '';
    }

    /**
     * Two-tier threshold, same idea as the Whale Alert side: stablecoins
     * and wrapped BTC/ETH/native tokens use the higher bar (routine, high
     * liquidity), everything else uses the lower "small token" bar so a
     * genuinely large move in a smaller token isn't held to the same
     * threshold as a routine USDT transfer.
     */
    private function threshold($price_id) {
        $settings = get_option('coin680whale_settings', array());
        if (Coin680MultiChain_Labels::is_major_price_id($price_id)) {
            return isset($settings['multichain_min_value']) ? (int) $settings['multichain_min_value'] : 100000;
        }
        return isset($settings['multichain_token_min_value']) ? (int) $settings['multichain_token_min_value'] : 10000;
    }

    private function rpc($chainid, $params) {
        $api_key = $this->api_key();
        if (!$api_key) {
            return null;
        }
        $query = array_merge(array('chainid' => $chainid, 'apikey' => $api_key), $params);
        $url = add_query_arg($query, 'https://api.etherscan.io/v2/api');
        $response = wp_remote_get($url, array('timeout' => 25));
        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return null;
        }
        $body = json_decode(wp_remote_retrieve_body($response), true);
        return $body['result'] ?? null;
    }

    private function current_price($price_id) {
        if (!function_exists('coin680_get_crypto_prices')) {
            return 0;
        }
        // coin680_get_crypto_prices() defaults to only the top 15 coins by
        // market cap -- fine for BTC/ETH/stablecoins, but UNI, LINK, ARB
        // etc. routinely fall outside that and would silently fail this
        // lookup (returning 0, which then skips the transfer entirely).
        // 250 is the actual ceiling -- the function itself clamps to that
        // (CoinGecko's own per_page hard limit for this endpoint), so this
        // is the widest coverage possible, not an arbitrary choice.
        $coins = coin680_get_crypto_prices(250);
        if (!$coins) {
            return 0;
        }
        foreach ($coins as $coin) {
            if ($coin['id'] === $price_id) {
                return (float) $coin['current_price'];
            }
        }
        return 0;
    }

    /**
     * Symbol + decimals for a token contract, cached in a wp_option map so
     * we only ever call eth_call for a given contract once.
     */
    private function token_meta($chain, $chainid, $address) {
        $cache = get_option('coin680multichain_token_meta', array());
        $key = $chain . ':' . strtolower($address);
        if (isset($cache[$key])) {
            return $cache[$key];
        }

        $decimals_hex = $this->rpc($chainid, array(
            'module' => 'proxy', 'action' => 'eth_call',
            'to' => $address, 'data' => '0x313ce567', 'tag' => 'latest',
        ));
        $symbol_hex = $this->rpc($chainid, array(
            'module' => 'proxy', 'action' => 'eth_call',
            'to' => $address, 'data' => '0x95d89b41', 'tag' => 'latest',
        ));

        $decimals = $decimals_hex ? hexdec($decimals_hex) : 18;
        $symbol = $this->decode_string($symbol_hex);
        if (!$symbol) {
            $symbol = strtoupper(substr($address, 2, 5));
        }

        $meta = array('symbol' => $symbol, 'decimals' => $decimals);
        $cache[$key] = $meta;
        update_option('coin680multichain_token_meta', $cache, false);
        return $meta;
    }

    /**
     * Minimal ABI-encoded string decoder for a symbol() eth_call response --
     * enough for the standard dynamic-string return format, with a fallback
     * for the handful of older tokens that return a fixed bytes32 instead.
     */
    private function decode_string($hex) {
        if (!$hex || $hex === '0x') {
            return '';
        }
        $hex = substr($hex, 2);
        if (strlen($hex) < 128) {
            $raw = @hex2bin($hex);
            return $raw ? trim(preg_replace('/[^\x20-\x7E]/', '', $raw)) : '';
        }
        $len = hexdec(substr($hex, 64, 64));
        $strHex = substr($hex, 128, $len * 2);
        $raw = @hex2bin($strHex);
        return $raw ? trim($raw) : '';
    }

    /**
     * Whether an address has already been labeled as an exchange by the
     * Whale Alert side of the system (which stores real from/to addresses
     * for every transaction it captures). Address format is shared across
     * all EVM chains, so a label learned from an Ethereum Whale Alert
     * transaction is reused here -- not every exchange reuses the same
     * address on every chain, so this only catches SOME, not all.
     */
    private function cex_label($address) {
        static $cache = array();
        $address = strtolower($address);
        if (array_key_exists($address, $cache)) {
            return $cache[$address];
        }

        global $wpdb;
        $table = Coin680Whale_Fetcher::table_name();
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT from_owner AS owner FROM $table WHERE LOWER(from_address) = %s AND from_owner_type = 'exchange' LIMIT 1",
            $address
        ));
        if (!$row) {
            $row = $wpdb->get_row($wpdb->prepare(
                "SELECT to_owner AS owner FROM $table WHERE LOWER(to_address) = %s AND to_owner_type = 'exchange' LIMIT 1",
                $address
            ));
        }
        $cache[$address] = $row ? $row->owner : null;
        return $cache[$address];
    }

    public function poll() {
        if (!$this->api_key()) {
            return;
        }
        foreach (Coin680MultiChain_Labels::CHAINS as $chain => $config) {
            $this->poll_chain($chain, $config);
        }
    }

    private function poll_chain($chain, $config) {
        $chainid = $config['chainid'];
        $last_block_opt = "coin680multichain_last_block_{$chain}";
        $last_block = (int) get_option($last_block_opt, 0);

        $latest_hex = $this->rpc($chainid, array('module' => 'proxy', 'action' => 'eth_blockNumber'));
        if (!$latest_hex) {
            return;
        }
        $latest_block = hexdec($latest_hex);

        // First run for this chain: start ~30 minutes back rather than
        // scanning the chain's entire history from block zero.
        $start_block = $last_block ? ($last_block + 1) : max(0, $latest_block - 400);

        // Never process more than 2000 blocks in one cycle -- a long gap
        // (e.g. after being paused) gets caught up gradually over several
        // 5-minute cycles instead of one enormous, timeout-prone request.
        $end_block = min($latest_block, $start_block + 2000);
        if ($start_block > $end_block) {
            return;
        }

        // Query PER TOKEN ADDRESS, not one blanket query for every Transfer
        // event on the whole chain. A blanket topic0-only query returns
        // Transfer logs from every ERC-20 contract on the chain -- in any
        // real block range that's dominated overwhelmingly by USDT/USDC
        // (thousands of transfers), so smaller tokens like UNI/LINK were
        // getting crowded out of the (implicitly capped) result set and
        // never seen at all, even though they genuinely had qualifying
        // transfers in that window. Scoping each call to one contract
        // address guarantees every tracked token actually gets checked.
        $token_ids = Coin680MultiChain_Labels::TOKEN_PRICE_IDS[$chain] ?? array();
        $all_logs = array();
        $debug = get_option('coin680multichain_debug', array());
        $debug[$chain] = array('start_block' => $start_block, 'end_block' => $end_block, 'per_token' => array());
        foreach (array_keys($token_ids) as $token_address) {
            $logs = $this->rpc($chainid, array(
                'module' => 'logs', 'action' => 'getLogs',
                'fromBlock' => $start_block, 'toBlock' => $end_block,
                'address' => $token_address,
                'topic0' => Coin680MultiChain_Labels::TRANSFER_TOPIC,
            ));
            $count = is_array($logs) ? count($logs) : ('ERROR: ' . var_export($logs, true));
            $debug[$chain]['per_token'][$token_ids[$token_address]] = $count;
            if (is_array($logs)) {
                $all_logs = array_merge($all_logs, $logs);
            }
        }
        update_option('coin680multichain_debug', $debug, false);

        if ($all_logs) {
            $this->process_logs($chain, $chainid, $all_logs);
        }

        update_option($last_block_opt, $end_block);
    }

    private function process_logs($chain, $chainid, $logs) {
        // Group by transaction so a 2-leg DEX swap (token in + token out in
        // the same tx) can be read together instead of as two unrelated
        // transfers.
        $by_tx = array();
        foreach ($logs as $log) {
            $by_tx[$log['transactionHash']][] = $log;
        }

        global $wpdb;
        $table = self::table_name();

        foreach ($by_tx as $tx_logs) {
            foreach ($tx_logs as $log) {
                $this->process_single_transfer($chain, $chainid, $log, $tx_logs, $table, $wpdb);
            }
        }
    }

    private function process_single_transfer($chain, $chainid, $log, $tx_logs, $table, $wpdb) {
        $token_address = $log['address'];
        $price_id = Coin680MultiChain_Labels::price_id($chain, $token_address);
        if (!$price_id) {
            return; // unpriced token -- skip rather than guess
        }
        $threshold = $this->threshold($price_id);

        $topics = $log['topics'];
        if (count($topics) < 3) {
            return;
        }
        $from = '0x' . substr($topics[1], -40);
        $to = '0x' . substr($topics[2], -40);
        $raw_amount = hexdec($log['data']);
        if ($raw_amount <= 0) {
            return;
        }

        $meta = $this->token_meta($chain, $chainid, $token_address);
        $amount = $raw_amount / (10 ** $meta['decimals']);
        $price = $this->current_price($price_id);

        // Temporary debug: record the biggest USD amount seen for this
        // price_id on this poll, regardless of whether it clears the
        // threshold, so we can see server-side what's actually being
        // computed (price, decimals, amount) vs. what the threshold is.
        $debug = get_option('coin680multichain_debug_amounts', array());
        $usd_for_debug = $price ? $amount * $price : 0;
        if (!isset($debug[$price_id]) || $usd_for_debug > $debug[$price_id]['amount_usd']) {
            $debug[$price_id] = array(
                'amount_usd' => $usd_for_debug,
                'raw_amount' => $raw_amount,
                'decimals'   => $meta['decimals'],
                'price'      => $price,
                'threshold'  => $threshold,
            );
            update_option('coin680multichain_debug_amounts', $debug, false);
        }

        if (!$price) {
            return;
        }
        $amount_usd = $amount * $price;
        if ($amount_usd < $threshold) {
            return;
        }

        $dex_from = Coin680MultiChain_Labels::is_dex_router($chain, $from);
        $dex_to = Coin680MultiChain_Labels::is_dex_router($chain, $to);
        $dex_name = $dex_from ?: $dex_to;
        $classification = 'Wallet Transfer';
        $counter_symbol = '';

        if ($dex_name) {
            // Look for the OTHER leg of this swap: a different token
            // touching the same router/pool in the same transaction.
            $other_leg = null;
            foreach ($tx_logs as $other) {
                if ($other['address'] === $log['address']) {
                    continue;
                }
                $other_topics = $other['topics'];
                if (count($other_topics) < 3) {
                    continue;
                }
                $other_from = '0x' . substr($other_topics[1], -40);
                $other_to = '0x' . substr($other_topics[2], -40);
                if (Coin680MultiChain_Labels::is_dex_router($chain, $other_from) || Coin680MultiChain_Labels::is_dex_router($chain, $other_to)) {
                    $other_leg = $other;
                    break;
                }
            }

            $this_is_stable = Coin680MultiChain_Labels::is_stablecoin($chain, $token_address);

            if ($this_is_stable && $other_leg) {
                $other_is_stable_check = Coin680MultiChain_Labels::is_stablecoin($chain, $other_leg['address']);
                if (!$other_is_stable_check) {
                    // This is the cash leg of a swap -- the other leg (the
                    // actual token being bought/sold) gets its own pass
                    // through process_single_transfer() with proper
                    // Buy/Sell semantics, so skip the stablecoin side here
                    // entirely rather than recording the same swap twice.
                    return;
                }
            }

            if ($other_leg) {
                $other_meta = $this->token_meta($chain, $chainid, $other_leg['address']);
                $counter_symbol = $other_meta['symbol'];
                $other_is_stable = Coin680MultiChain_Labels::is_stablecoin($chain, $other_leg['address']);

                if (!$this_is_stable && $other_is_stable) {
                    // This (volatile) token's own direction relative to the
                    // router says whether it was bought or sold -- arriving
                    // FROM the router/pool is a buy, leaving TO it is a sell.
                    $classification = $dex_to ? 'DEX Sell' : 'DEX Buy';
                } else {
                    $classification = 'DEX Swap';
                }
            } else {
                $classification = 'DEX Swap';
            }
        } else {
            $from_label = $this->cex_label($from);
            $to_label = $this->cex_label($to);
            if ($to_label && !$from_label) {
                $classification = 'Exchange Inflow';
            } elseif ($from_label && !$to_label) {
                $classification = 'Exchange Outflow';
            } elseif ($from_label && $to_label) {
                $classification = 'Exchange to Exchange';
            }
        }

        $from_owner = $this->cex_label($from);
        $to_owner = $this->cex_label($to);
        $timestamp_hex = $log['timeStamp'] ?? null;

        $wpdb->query($wpdb->prepare(
            "INSERT IGNORE INTO $table
            (chain, tx_hash, log_index, symbol, token_address, classification, counter_symbol, from_address, to_address, from_owner, from_owner_type, to_owner, to_owner_type, dex_name, amount, amount_usd, tx_timestamp, created_at)
            VALUES (%s,%s,%d,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%f,%f,%s,%s)",
            $chain,
            $log['transactionHash'],
            hexdec($log['logIndex']),
            $meta['symbol'],
            $token_address,
            $classification,
            $counter_symbol,
            $from,
            $to,
            $from_owner ?: 'unknown',
            $from_owner ? 'exchange' : 'unknown',
            $to_owner ?: 'unknown',
            $to_owner ? 'exchange' : 'unknown',
            $dex_name ?: '',
            $amount,
            $amount_usd,
            $timestamp_hex ? gmdate('Y-m-d H:i:s', hexdec($timestamp_hex)) : current_time('mysql', true),
            current_time('mysql', true)
        ));
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
