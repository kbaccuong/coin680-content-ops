<?php
/**
 * Fires only when the plugin is deleted from wp-admin (not on deactivation),
 * per the WordPress.org requirement that plugins clean up after themselves.
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

delete_option('coin680_shield_settings');
delete_option('coin680_shield_license_status');
delete_option('coin680_shield_stats');

global $wpdb;
$wpdb->query(
    "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_c680s\\_%' OR option_name LIKE '\\_transient\\_timeout\\_c680s\\_%'"
);
