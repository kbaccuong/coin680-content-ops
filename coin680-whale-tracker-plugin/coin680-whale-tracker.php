<?php
/**
 * Plugin Name: Coin680 Whale Tracker
 * Description: Polls Whale Alert's API (Bitcoin only) and, via Bitquery's unified GraphQL API, Solana/BSC/Ethereum/TRON directly (with DEX-swap detection), for large on-chain transactions -- classifies them and stores them for building narrative "whale digest" posts. Not an auto-poster on its own for the raw data; the digest composes and posts the actual tweet.
 * Version: 1.3.0
 * Author: Coin680
 * License: GPLv2 or later
 * Text Domain: coin680-whale-tracker
 */

if (!defined('ABSPATH')) {
    exit;
}

define('COIN680WHALE_DIR', plugin_dir_path(__FILE__));

require_once COIN680WHALE_DIR . 'includes/class-fetcher.php';
require_once COIN680WHALE_DIR . 'includes/class-bitquery-labels.php';
require_once COIN680WHALE_DIR . 'includes/class-bitquery-fetcher.php';
require_once COIN680WHALE_DIR . 'includes/class-admin.php';
require_once COIN680WHALE_DIR . 'includes/class-digest.php';

// The old Etherscan-based multichain fetcher (Ethereum/Polygon/Arbitrum/
// BSC/Base/Optimism/Avalanche) is retired as of 1.3.0, replaced by
// Coin680Bitquery_Fetcher above (Solana/BSC/Ethereum/TRON, no per-token
// CoinGecko price map needed, adds Solana on top). The files themselves
// (class-multichain-labels.php, class-multichain-fetcher.php) are left on
// disk rather than deleted -- not required here, so fully inert, but
// available to wire back in without a rebuild if ever needed again.

// Registered independently at file-load time (same pattern/interval name as
// Coin680 X Scheduler) so this plugin's activation-time wp_schedule_event()
// call works even if that other plugin isn't active. Re-registering the
// same interval name from two plugins is harmless -- WordPress just uses
// whichever definition wins the filter chain, and both define it as 300s.
function coin680whale_add_cron_interval($schedules) {
    if (!isset($schedules['coin680x_five_minutes'])) {
        $schedules['coin680x_five_minutes'] = array(
            'interval' => 300,
            'display'  => __('Every 5 Minutes (Coin680)', 'coin680-whale-tracker'),
        );
    }
    return $schedules;
}
add_filter('cron_schedules', 'coin680whale_add_cron_interval');

const COIN680WHALE_DB_VERSION = '1.3';

function coin680whale_init() {
    Coin680Whale_Fetcher::instance();
    Coin680Bitquery_Fetcher::instance();
    Coin680Whale_Digest::instance();
    if (is_admin()) {
        Coin680Whale_Admin::instance();
    }

    // Schema upgrade path for sites where this plugin was already active
    // before the Bitquery migration -- runs the table create/upgrade again
    // (dbDelta is safe to re-run) without requiring a manual deactivate/
    // reactivate, and does the one-time data cleanup described below.
    if (get_option('coin680whale_db_version') !== COIN680WHALE_DB_VERSION) {
        Coin680Whale_Fetcher::create_table();
        Coin680Bitquery_Fetcher::create_table();
        coin680whale_migrate_to_bitquery();
        update_option('coin680whale_db_version', COIN680WHALE_DB_VERSION);
    }
}
add_action('plugins_loaded', 'coin680whale_init');

/**
 * One-time cleanup, run once when upgrading to 1.3.0 (Bitquery migration),
 * per direct request ("xoá đi các dữ liệu... cho nhẹ"): retires the old
 * Etherscan-based multichain data and non-Bitcoin Whale Alert history that
 * are no longer being added to or read from anywhere in the plugin.
 * - Drops wp_coin680_multichain_txns entirely (Etherscan/EVM DEX data,
 *   fully superseded by wp_coin680_bitquery_txns for BSC/Ethereum, and
 *   Coin680MultiChain_Fetcher::poll() is no longer scheduled so this table
 *   would otherwise just sit there permanently unused).
 * - Deletes non-Bitcoin rows from wp_coin680_whale_txns (Whale Alert is
 *   Bitcoin-only going forward -- see class-fetcher.php) rather than
 *   dropping that table outright, since Bitcoin rows in it are still live
 *   data the digest reads from.
 * - Clears the old 'coin680multichain_poll' cron hook so it stops firing
 *   into empty air (its class is no longer loaded).
 */
function coin680whale_migrate_to_bitquery() {
    global $wpdb;
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}coin680_multichain_txns");

    $whale_table = $wpdb->prefix . 'coin680_whale_txns';
    if ($wpdb->get_var("SHOW TABLES LIKE '$whale_table'") === $whale_table) {
        $wpdb->query("DELETE FROM $whale_table WHERE LOWER(blockchain) != 'bitcoin'");
    }

    wp_clear_scheduled_hook('coin680multichain_poll');
}

function coin680whale_activate() {
    Coin680Whale_Fetcher::create_table();
    Coin680Bitquery_Fetcher::create_table();
    update_option('coin680whale_db_version', COIN680WHALE_DB_VERSION);
    if (!wp_next_scheduled('coin680whale_digest_check')) {
        wp_schedule_event(time(), 'coin680x_five_minutes', 'coin680whale_digest_check');
    }
    if (!wp_next_scheduled('coin680whale_daily_recap')) {
        wp_schedule_event(time(), 'daily', 'coin680whale_daily_recap');
    }
}
register_activation_hook(__FILE__, 'coin680whale_activate');

function coin680whale_deactivate() {
    wp_clear_scheduled_hook('coin680whale_poll');
    wp_clear_scheduled_hook('coin680bitquery_poll');
    wp_clear_scheduled_hook('coin680multichain_poll'); // clears any leftover schedule from before 1.3.0
    wp_clear_scheduled_hook('coin680whale_digest_check');
    wp_clear_scheduled_hook('coin680whale_daily_recap');
}
register_deactivation_hook(__FILE__, 'coin680whale_deactivate');
