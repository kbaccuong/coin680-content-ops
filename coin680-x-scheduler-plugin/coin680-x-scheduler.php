<?php
/**
 * Plugin Name: Coin680 X Scheduler
 * Description: Schedules posts to X (Twitter) directly via the X API -- no third-party service, no monthly post cap. Checks for due posts every 5 minutes via WP-Cron.
 * Version: 1.0.1
 * Author: Coin680
 * License: GPLv2 or later
 * Text Domain: coin680-x-scheduler
 */

if (!defined('ABSPATH')) {
    exit;
}

define('COIN680X_DIR', plugin_dir_path(__FILE__));

require_once COIN680X_DIR . 'includes/class-oauth.php';
require_once COIN680X_DIR . 'includes/class-queue.php';
require_once COIN680X_DIR . 'includes/class-admin.php';
require_once COIN680X_DIR . 'includes/class-auto-post.php';

// Registered at file-load time (not deferred to plugins_loaded) so the
// custom interval is recognized even during the activation request itself,
// when wp_schedule_event() first runs.
add_filter('cron_schedules', array('Coin680X_Queue', 'add_cron_interval'));

const COIN680X_DB_VERSION = '1.0.1';

function coin680x_init() {
    Coin680X_Queue::instance();
    Coin680X_AutoPost::instance();
    if (is_admin()) {
        Coin680X_Admin::instance();
    }

    // Re-runs create_table() (safe/idempotent) on sites where this plugin
    // was already active before the utf8mb4 fix -- forces the queue table's
    // charset conversion without needing a manual deactivate/reactivate.
    if (get_option('coin680x_db_version') !== COIN680X_DB_VERSION) {
        Coin680X_Queue::create_table();
        update_option('coin680x_db_version', COIN680X_DB_VERSION);
    }
}
add_action('plugins_loaded', 'coin680x_init');

register_activation_hook(__FILE__, array('Coin680X_Queue', 'create_table'));

function coin680x_deactivate() {
    wp_clear_scheduled_hook('coin680x_process_queue');
}
register_deactivation_hook(__FILE__, 'coin680x_deactivate');
