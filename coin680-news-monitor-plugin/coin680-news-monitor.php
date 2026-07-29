<?php
/**
 * Plugin Name: Coin680 News Monitor
 * Description: Polls CoinDesk and Cointelegraph's RSS feeds for new headlines and stores them as a reviewable candidate list -- it does NOT auto-write or auto-publish articles. Flags likely cross-source matches and likely duplicates of already-published posts to speed up review. Cross-checking sources, verifying facts, and writing coin680.com's own News articles stays a manual/reviewed step, per Coin680-News-Playbook.md.
 * Version: 1.1.0
 * Author: Coin680
 * License: GPLv2 or later
 * Text Domain: coin680-news-monitor
 */

if (!defined('ABSPATH')) {
    exit;
}

define('COIN680NEWS_DIR', plugin_dir_path(__FILE__));

require_once COIN680NEWS_DIR . 'includes/class-monitor.php';
require_once COIN680NEWS_DIR . 'includes/class-admin.php';

// Registered independently at file-load time so this plugin's own
// activation-time wp_schedule_event() call recognizes the interval even
// if Coin680 X Scheduler / Whale Tracker aren't active. Re-declaring the
// same 300-second interval from multiple plugins is harmless.
function coin680news_add_cron_interval($schedules) {
    if (!isset($schedules['coin680x_five_minutes'])) {
        $schedules['coin680x_five_minutes'] = array(
            'interval' => 300,
            'display'  => __('Every 5 Minutes (Coin680)', 'coin680-news-monitor'),
        );
    }
    return $schedules;
}
add_filter('cron_schedules', 'coin680news_add_cron_interval');

const COIN680NEWS_DB_VERSION = '1.1';

function coin680news_init() {
    Coin680News_Monitor::instance();
    if (is_admin()) {
        Coin680News_Admin::instance();
    }
    if (get_option('coin680news_db_version') !== COIN680NEWS_DB_VERSION) {
        Coin680News_Monitor::create_table();
        update_option('coin680news_db_version', COIN680NEWS_DB_VERSION);
    }
}
add_action('plugins_loaded', 'coin680news_init');

function coin680news_activate() {
    Coin680News_Monitor::create_table();
    if (!wp_next_scheduled('coin680news_poll')) {
        wp_schedule_event(time(), 'coin680x_five_minutes', 'coin680news_poll');
    }
}
register_activation_hook(__FILE__, 'coin680news_activate');

function coin680news_deactivate() {
    wp_clear_scheduled_hook('coin680news_poll');
}
register_deactivation_hook(__FILE__, 'coin680news_deactivate');
