<?php
/**
 * Login brute-force lockout -- premium feature.
 * Tracks failed login attempts per IP in a transient; once the configured
 * threshold is hit within the window, further attempts from that IP are
 * rejected outright (before WordPress even checks the password) until the
 * lockout period expires.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Coin680_Shield_Login_Protection {
    private static $instance = null;

    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('wp_login_failed', array($this, 'record_failure'));
        add_filter('authenticate', array($this, 'block_if_locked_out'), 30, 1);
    }

    private function key($ip) {
        return 'c680s_login_' . md5($ip);
    }

    public function record_failure() {
        if (!Coin680_Shield_License::is_premium()) {
            return;
        }
        $settings = coin680_shield_get_settings();
        $window = (int) ($settings['login_lockout_minutes'] ?? 15) * MINUTE_IN_SECONDS;
        $ip = coin680_shield_get_client_ip();
        $count = (int) get_transient($this->key($ip));
        set_transient($this->key($ip), $count + 1, $window);
    }

    public function block_if_locked_out($user) {
        if (!Coin680_Shield_License::is_premium()) {
            return $user;
        }
        $settings = coin680_shield_get_settings();
        $max = (int) ($settings['login_max_attempts'] ?? 5);
        $ip = coin680_shield_get_client_ip();
        $count = (int) get_transient($this->key($ip));
        if ($max > 0 && $count >= $max) {
            return new WP_Error(
                'c680s_locked_out',
                sprintf(
                    /* translators: %d: minutes until the IP can try logging in again */
                    esc_html__('Too many failed login attempts. Please try again in about %d minutes.', 'coin680-shield'),
                    (int) ($settings['login_lockout_minutes'] ?? 15)
                )
            );
        }
        return $user;
    }
}
