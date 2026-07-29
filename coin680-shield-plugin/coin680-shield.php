<?php
/**
 * Plugin Name: Coin680 Shield -- Anti-Bot & Comment Spam Protection
 * Plugin URI: https://coin680.com/
 * Description: Blocks comment spam bots (honeypot, time-trap, keyword/link filter, rate limiting) and hardens the site against login brute-force and basic bot traffic. Enter the code "coin680" in Settings to unlock all premium features for free.
 * Version: 1.0.0
 * Author: Coin680
 * Author URI: https://coin680.com/
 * License: GPLv2 or later
 * Text Domain: coin680-shield
 */

if (!defined('ABSPATH')) {
    exit;
}

define('COIN680_SHIELD_VERSION', '1.0.0');
define('COIN680_SHIELD_DIR', plugin_dir_path(__FILE__));
define('COIN680_SHIELD_URL', plugin_dir_url(__FILE__));
define('COIN680_SHIELD_UNLOCK_CODE', 'coin680');

require_once COIN680_SHIELD_DIR . 'includes/class-license.php';
require_once COIN680_SHIELD_DIR . 'includes/class-comment-protection.php';
require_once COIN680_SHIELD_DIR . 'includes/class-login-protection.php';
require_once COIN680_SHIELD_DIR . 'includes/class-firewall.php';
require_once COIN680_SHIELD_DIR . 'includes/class-admin.php';

function coin680_shield_init() {
    Coin680_Shield_License::instance();
    Coin680_Shield_Comment_Protection::instance();
    Coin680_Shield_Login_Protection::instance();
    Coin680_Shield_Firewall::instance();
    if (is_admin()) {
        Coin680_Shield_Admin::instance();
    }
}
add_action('plugins_loaded', 'coin680_shield_init');

function coin680_shield_activate() {
    $defaults = array(
        'blocklist'         => Coin680_Shield_Comment_Protection::default_blocklist(),
        'max_links'         => 2,
        'min_seconds'       => 3,
        'rate_limit_count'  => 3,
        'rate_limit_window' => 600,
        'login_max_attempts' => 5,
        'login_lockout_minutes' => 15,
        'block_xmlrpc'      => 1,
        'block_bad_bots'    => 1,
        'firewall_enabled'  => 0,
        'use_captcha'       => 0,
        'bad_bot_agents'    => Coin680_Shield_Firewall::default_bad_bots(),
    );
    if (!get_option('coin680_shield_settings')) {
        add_option('coin680_shield_settings', $defaults);
    }
    if (!get_option('coin680_shield_license_status')) {
        add_option('coin680_shield_license_status', 'free');
    }
}
register_activation_hook(__FILE__, 'coin680_shield_activate');

function coin680_shield_get_settings() {
    $defaults = array(
        'blocklist'         => Coin680_Shield_Comment_Protection::default_blocklist(),
        'max_links'         => 2,
        'min_seconds'       => 3,
        'rate_limit_count'  => 3,
        'rate_limit_window' => 600,
        'login_max_attempts' => 5,
        'login_lockout_minutes' => 15,
        'block_xmlrpc'      => 1,
        'block_bad_bots'    => 1,
        'firewall_enabled'  => 0,
        'use_captcha'       => 0,
        'bad_bot_agents'    => Coin680_Shield_Firewall::default_bad_bots(),
    );
    $saved = get_option('coin680_shield_settings', array());
    return wp_parse_args($saved, $defaults);
}

function coin680_shield_get_client_ip() {
    foreach (array('HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR') as $key) {
        if (!empty($_SERVER[$key])) {
            $ip = trim(explode(',', $_SERVER[$key])[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }
    return '0.0.0.0';
}
