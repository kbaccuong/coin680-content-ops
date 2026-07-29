<?php
/**
 * License / unlock-code gate.
 * Entering the code "coin680" (case-insensitive) in Settings unlocks every
 * premium feature for free -- there is no payment flow, no remote server
 * call, and no expiry; it's a simple local flag stored in wp_options.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Coin680_Shield_License {
    private static $instance = null;

    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public static function is_premium() {
        return get_option('coin680_shield_license_status', 'free') === 'premium';
    }

    public static function try_unlock($code) {
        $code = strtolower(trim((string) $code));
        if ($code === strtolower(COIN680_SHIELD_UNLOCK_CODE)) {
            update_option('coin680_shield_license_status', 'premium');
            return true;
        }
        return false;
    }

    public static function lock() {
        update_option('coin680_shield_license_status', 'free');
    }
}
