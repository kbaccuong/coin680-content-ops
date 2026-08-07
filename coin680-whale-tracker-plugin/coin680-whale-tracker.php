<?php
/**
 * Plugin Name: Coin680 Whale Tracker
 * Description: Polls Whale Alert's API (Bitcoin only) and, via Bitquery's unified GraphQL API, Solana/BSC/Ethereum/TRON directly (with DEX-swap detection), for large on-chain transactions -- classifies them and stores them for building narrative "whale digest" posts. Not an auto-poster on its own for the raw data; the digest composes and posts the actual tweet.
 * Version: 1.4.0
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
require_once COIN680WHALE_DIR . 'includes/class-watchlist.php';
require_once COIN680WHALE_DIR . 'includes/class-watchlist-fetcher.php';
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
    // Added 2026-07-30 after upgrading to a paid Bitquery plan (was hitting
    // the free tier's monthly point cap in under a day at the 5-min rate,
    // so there was no room to poll faster before). 2 minutes was chosen
    // over something more aggressive by estimating from that free-tier burn
    // rate: roughly 2.5x the call volume of 5-min polling, comfortably
    // within the paid plan's headroom with room left over for the new
    // watched-wallet polling below, rather than maximizing frequency and
    // risking burning through the new budget just as fast.
    if (!isset($schedules['coin680x_two_minutes'])) {
        $schedules['coin680x_two_minutes'] = array(
            'interval' => 120,
            'display'  => __('Every 2 Minutes (Coin680)', 'coin680-whale-tracker'),
        );
    }
    return $schedules;
}
add_filter('cron_schedules', 'coin680whale_add_cron_interval');

const COIN680WHALE_DB_VERSION = '1.5';

function coin680whale_init() {
    Coin680Whale_Fetcher::instance();
    Coin680Bitquery_Fetcher::instance();
    Coin680Watchlist_Fetcher::instance();
    Coin680Whale_Digest::instance();
    // Was gated behind is_admin() -- but is_admin() is FALSE during REST
    // API requests (it only reflects the wp-admin/ URL context, not user
    // capability), so Coin680Whale_Admin's rest_api_init hook (added
    // 2026-08-06 for the purge-bad-trades endpoint) never registered on
    // REST requests, making that endpoint 404 even though the code was
    // correct. Instantiating unconditionally, like the other classes
    // above, fixes this -- the class only ever adds action hooks
    // (admin_menu, admin_post_*, rest_api_init) that stay inert unless
    // their own trigger event fires, so there's no real cost to loading it
    // on every request.
    Coin680Whale_Admin::instance();

    // Schema upgrade path for sites where this plugin was already active
    // before the Bitquery migration -- runs the table create/upgrade again
    // (dbDelta is safe to re-run) without requiring a manual deactivate/
    // reactivate, and does the one-time data cleanup described below.
    if (get_option('coin680whale_db_version') !== COIN680WHALE_DB_VERSION) {
        Coin680Whale_Fetcher::create_table();
        Coin680Bitquery_Fetcher::create_table();
        Coin680Watchlist::create_table();
        Coin680Watchlist_Fetcher::create_table();
        coin680whale_migrate_to_bitquery();
        coin680whale_retire_whale_alert_posting();
        update_option('coin680whale_db_version', COIN680WHALE_DB_VERSION);
    }

    // Whale Alert's own automatic polling is stopped as of 1.4.0 (per
    // direct feedback: "bài đăng từ whale_alert quá dày, bỏ luôn phần này
    // đi, chỉ cần Bitquery là đủ") -- the class stays loaded/instantiated
    // (admin.php still reads its table for the "last 24 hours" display and
    // the manual "Poll Whale Alert Now" button still works if ever wanted
    // again) but the recurring cron that captures NEW data and fires
    // breaking mega-alerts is unscheduled here, every load, so it can never
    // silently get re-added by some other code path re-registering it.
    wp_clear_scheduled_hook('coin680whale_poll');

    // Digest-check cron (the periodic job that composes + posts the
    // on-chain "whale signal" tweet from Bitquery data) is STOPPED as of
    // 2026-07-31 -- per direct feedback: tweeting on-chain data to X was
    // running up real X API billing (~$26 incurred), separate from and
    // unrelated to the Bitquery cost fixed earlier the same day. Bitquery
    // polling (coin680bitquery_poll/coin680watchlist_poll below) keeps
    // running unchanged -- it feeds the public /whale-signals/ page
    // directly from the database and does NOT go through this digest/X
    // pipeline, so stopping tweets here has zero effect on Bitquery usage
    // or on the website feature. Same permanent-disable pattern as
    // coin680whale_poll above (unconditional clear on every load, no
    // self-healing re-schedule) so it can't silently come back. The class
    // itself stays loaded (Coin680Whale_Digest::instance() still runs,
    // manual "test digest" buttons in admin still work) -- only this
    // recurring auto-post schedule is off. To re-enable: replace this
    // wp_clear_scheduled_hook() call with the self-healing
    // wp_next_scheduled()/wp_schedule_event() pair it used to be (see git
    // history / GitHub for the prior version) -- no rebuild needed.
    wp_clear_scheduled_hook('coin680whale_digest_check');

    // New replacement path (2026-07-31): a single-transaction "teaser" post
    // with no url/domain text, capped at 3/day via Coin680Whale_Digest::
    // TEASER_INTERVAL_MINUTES (480 = 8h) -- see that class for the full
    // reasoning. Checked every 5 min like the other crons, but the 8h floor
    // inside maybe_post_onchain_teaser() means it only actually posts 3x/day.
    if (!wp_next_scheduled('coin680whale_teaser_check')) {
        wp_schedule_event(time(), 'coin680x_five_minutes', 'coin680whale_teaser_check');
    }

    // Once-daily on-chain roundup (2026-07-31) -- the paid-link counterpart
    // to the free-form teaser above. Fires once/day via WP's native 'daily'
    // schedule (Coin680Whale_Digest::post_daily_roundup() has no internal
    // wait-floor logic, unlike the teaser, since this cron itself already
    // only fires once/day).
    if (!wp_next_scheduled('coin680whale_roundup_daily')) {
        wp_schedule_event(time(), 'daily', 'coin680whale_roundup_daily');
    }

    if (!wp_next_scheduled('coin680whale_daily_recap')) {
        wp_schedule_event(time(), 'daily', 'coin680whale_daily_recap');
    }

    // Added 2026-07-31 -- daily retention cleanup for the two Bitquery-fed
    // tables (wp_coin680_bitquery_txns, wp_coin680_watchlist_txns), which
    // previously grew forever with no trim. Coin680Bitquery_Fetcher and
    // Coin680Watchlist_Fetcher each hook their own cleanup_old() onto this
    // SAME event name in their own constructors -- WP allows multiple
    // callbacks per action, so one daily schedule here is enough to drive
    // both. Disk/backup housekeeping only; does not affect query speed
    // (tx_timestamp is indexed, see cleanup_old()'s docblock).
    if (!wp_next_scheduled('coin680whale_daily_cleanup')) {
        wp_schedule_event(time(), 'daily', 'coin680whale_daily_cleanup');
    }

    // Same self-healing pattern applied to the two market-data polling
    // hooks (added 2026-07-30, proactively -- these had the identical
    // "only ever scheduled once" gap that caused the digest cron bug fixed
    // earlier the same day, just never actually confirmed broken live).
    //
    // Moved to 2-min briefly the same day, then back to 5-min a few hours
    // later after the Bitquery usage dashboard showed the paid plan's
    // 100k-point monthly budget projected to run out ~Aug 13 (17 days
    // before the Aug 30 renewal) at the 2-min rate -- confirming the
    // "comfortably within the paid plan's headroom" estimate above was
    // wrong for this specific plan tier. wp_schedule_event() only takes
    // effect for an event that ISN'T already scheduled, so simply changing
    // the interval name passed below wouldn't retroactively fix a site
    // where coin680bitquery_poll/coin680watchlist_poll were already
    // scheduled at the 2-min interval -- hence the explicit
    // wp_get_scheduled_event() + clear step, so this self-heals to the
    // correct interval on any already-running site without needing a
    // manual deactivate/reactivate.
    $coin680_target_interval = 'coin680x_five_minutes';
    $coin680_bitquery_event = wp_get_scheduled_event('coin680bitquery_poll');
    if ($coin680_bitquery_event && $coin680_bitquery_event->schedule !== $coin680_target_interval) {
        wp_clear_scheduled_hook('coin680bitquery_poll');
    }
    if (!wp_next_scheduled('coin680bitquery_poll')) {
        wp_schedule_event(time(), $coin680_target_interval, 'coin680bitquery_poll');
    }
    $coin680_watchlist_event = wp_get_scheduled_event('coin680watchlist_poll');
    if ($coin680_watchlist_event && $coin680_watchlist_event->schedule !== $coin680_target_interval) {
        wp_clear_scheduled_hook('coin680watchlist_poll');
    }
    if (!wp_next_scheduled('coin680watchlist_poll')) {
        wp_schedule_event(time(), $coin680_target_interval, 'coin680watchlist_poll');
    }
}
add_action('plugins_loaded', 'coin680whale_init');

/**
 * One-time migration (1.4.0): forces the "Include Whale Alert (Bitcoin) in
 * posts?" setting off, so posts are Bitquery-only immediately -- rather
 * than relying on the admin manually unchecking the box (belt-and-
 * suspenders; the checkbox itself, and the ability to re-check it later,
 * both still work exactly as before if this is ever reversed).
 */
function coin680whale_retire_whale_alert_posting() {
    $settings = get_option('coin680whale_settings', array());
    $settings['include_whale_alert_in_digest'] = 0;
    update_option('coin680whale_settings', $settings);
}

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
    Coin680Watchlist::create_table();
    Coin680Watchlist_Fetcher::create_table();
    update_option('coin680whale_db_version', COIN680WHALE_DB_VERSION);
    // coin680whale_digest_check deliberately NOT scheduled here -- on-chain
    // X auto-posting is stopped as of 2026-07-31 (see coin680whale_init()
    // above for why). Scheduling it here would be immediately undone by
    // that unconditional wp_clear_scheduled_hook() on the very next load
    // anyway, so leaving it out is just less misleading to read.
    if (!wp_next_scheduled('coin680whale_teaser_check')) {
        wp_schedule_event(time(), 'coin680x_five_minutes', 'coin680whale_teaser_check');
    }
    if (!wp_next_scheduled('coin680whale_roundup_daily')) {
        wp_schedule_event(time(), 'daily', 'coin680whale_roundup_daily');
    }
    if (!wp_next_scheduled('coin680whale_daily_recap')) {
        wp_schedule_event(time(), 'daily', 'coin680whale_daily_recap');
    }
    if (!wp_next_scheduled('coin680bitquery_poll')) {
        wp_schedule_event(time(), 'coin680x_five_minutes', 'coin680bitquery_poll');
    }
    if (!wp_next_scheduled('coin680watchlist_poll')) {
        wp_schedule_event(time(), 'coin680x_five_minutes', 'coin680watchlist_poll');
    }
    if (!wp_next_scheduled('coin680whale_daily_cleanup')) {
        wp_schedule_event(time(), 'daily', 'coin680whale_daily_cleanup');
    }
}
register_activation_hook(__FILE__, 'coin680whale_activate');

function coin680whale_deactivate() {
    wp_clear_scheduled_hook('coin680whale_poll');
    wp_clear_scheduled_hook('coin680bitquery_poll');
    wp_clear_scheduled_hook('coin680multichain_poll'); // clears any leftover schedule from before 1.3.0
    wp_clear_scheduled_hook('coin680whale_digest_check');
    wp_clear_scheduled_hook('coin680whale_daily_recap');
    wp_clear_scheduled_hook('coin680watchlist_poll');
    wp_clear_scheduled_hook('coin680whale_daily_cleanup');
    wp_clear_scheduled_hook('coin680whale_teaser_check');
    wp_clear_scheduled_hook('coin680whale_roundup_daily');
}
register_deactivation_hook(__FILE__, 'coin680whale_deactivate');
