<?php
/**
 * Plugin Name: Coin680 Whale Tracker
 * Description: Polls Whale Alert's API for large on-chain transactions, classifies them (mint/burn, exchange inflow/outflow, wallet transfer), and stores them for building narrative "whale digest" posts -- not an auto-poster, just honest data collection.
 * Version: 1.0.0
 * Author: Coin680
 * License: GPLv2 or later
 * Text Domain: coin680-whale-tracker
 */

if (!defined('ABSPATH')) {
    exit;
}

define('COIN680WHALE_DIR', plugin_dir_path(__FILE__));

require_once COIN680WHALE_DIR . 'includes/class-fetcher.php';
require_once COIN680WHALE_DIR . 'includes/class-admin.php';

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

function coin680whale_init() {
    Coin680Whale_Fetcher::instance();
    if (is_admin()) {
        Coin680Whale_Admin::instance();
    }
}
add_action('plugins_loaded', 'coin680whale_init');

register_activation_hook(__FILE__, array('Coin680Whale_Fetcher', 'create_table'));

function coin680whale_deactivate() {
    wp_clear_scheduled_hook('coin680whale_poll');
}
register_deactivation_hook(__FILE__, 'coin680whale_deactivate');
