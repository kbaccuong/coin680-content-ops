<?php
/**
 * Comment bot protection -- always active (free tier), except rate limiting
 * and the optional math CAPTCHA, which are premium (see Coin680_Shield_License).
 *
 * Layers, all silent to a genuine human commenter:
 * 1. Honeypot field  -- invisible to sighted users, hidden from screen readers,
 *    bots that auto-fill every input still fill it.
 * 2. Signed time-trap -- a timestamp + HMAC issued when the form renders;
 *    rejects submissions faster than a human could plausibly type, and the
 *    HMAC stops a bot from just forging an old timestamp.
 * 3. Keyword / link blocklist -- independent of WordPress core's own
 *    Disallowed Comment Keys list, so this plugin is fully self-contained.
 * 4. Rate limiting (premium) -- caps comments per IP in a rolling window.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Coin680_Shield_Comment_Protection {
    private static $instance = null;
    const HP_FIELD = 'c680s_hp';
    const TS_FIELD = 'c680s_ts';
    const SIG_FIELD = 'c680s_sig';
    const CAPTCHA_FIELD = 'c680s_captcha';
    const CAPTCHA_ANSWER_FIELD = 'c680s_captcha_a';

    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_filter('comment_form_defaults', array($this, 'inject_fields'), 20);
        add_filter('preprocess_comment', array($this, 'check_submission'));
    }

    public static function default_blocklist() {
        return implode("\n", array(
            'guaranteed profit', 'guaranteed returns', 'double your bitcoin',
            'double your btc', 'free bitcoin generator', 'bitcoin generator',
            'crypto recovery', 'hack recovery', 'fund recovery', 'recovery expert',
            'recovery agent', 'investment manager', 'account manager',
            'whatsapp me', 'contact him on telegram', 'contact her on telegram',
            'binary option', 'forex signals', 'trading signals', 'loan shark',
            'sugar daddy', 'escort service', 't.me/', 'bit.ly', 'tinyurl.com',
            'click here to claim', 'viagra', 'cialis', 'casino online', 'judi online',
        ));
    }

    private function generate_signed_timestamp() {
        $ts = time();
        $sig = hash_hmac('sha256', (string) $ts, wp_salt('auth'));
        return array($ts, $sig);
    }

    private function is_signature_valid($ts, $sig) {
        $ts = (string) $ts;
        $expected = hash_hmac('sha256', $ts, wp_salt('auth'));
        return hash_equals($expected, (string) $sig);
    }

    public function inject_fields($args) {
        list($ts, $sig) = $this->generate_signed_timestamp();

        $extra  = '<p class="c680s-hp-wrap" aria-hidden="true" style="position:absolute !important;left:-9999px !important;top:-9999px !important;height:0;width:0;overflow:hidden;">';
        $extra .= '<label for="' . self::HP_FIELD . '">' . esc_html__('Leave this field empty', 'coin680-shield') . '</label>';
        $extra .= '<input type="text" id="' . self::HP_FIELD . '" name="' . self::HP_FIELD . '" value="" tabindex="-1" autocomplete="off">';
        $extra .= '</p>';
        $extra .= '<input type="hidden" name="' . self::TS_FIELD . '" value="' . esc_attr($ts) . '">';
        $extra .= '<input type="hidden" name="' . self::SIG_FIELD . '" value="' . esc_attr($sig) . '">';

        $settings = coin680_shield_get_settings();
        if (Coin680_Shield_License::is_premium() && !empty($settings['use_captcha'])) {
            $a = wp_rand(1, 9);
            $b = wp_rand(1, 9);
            $captcha_sig = hash_hmac('sha256', $a . '+' . $b, wp_salt('auth'));
            $extra .= '<p class="comment-form-captcha"><label for="' . self::CAPTCHA_FIELD . '">' .
                sprintf(esc_html__('Quick check: what is %1$d + %2$d?', 'coin680-shield'), $a, $b) .
                ' <span class="required">*</span></label>' .
                '<input type="text" id="' . self::CAPTCHA_FIELD . '" name="' . self::CAPTCHA_FIELD . '" required></p>' .
                '<input type="hidden" name="' . self::CAPTCHA_ANSWER_FIELD . '" value="' . esc_attr($a . '+' . $b . '|' . $captcha_sig) . '">';
        }

        $args['comment_field'] = isset($args['comment_field']) ? $args['comment_field'] . $extra : $extra;
        return $args;
    }

    private function reject($reason) {
        $this->bump_stat($reason);
        wp_die(
            esc_html__('Your comment could not be submitted.', 'coin680-shield'),
            esc_html__('Comment Blocked', 'coin680-shield'),
            array('response' => 403, 'back_link' => true)
        );
    }

    private function bump_stat($reason) {
        $stats = get_option('coin680_shield_stats', array());
        if (!isset($stats[$reason])) {
            $stats[$reason] = 0;
        }
        $stats[$reason]++;
        update_option('coin680_shield_stats', $stats);
    }

    public function check_submission($commentdata) {
        // Logged-in users (admins/editors replying) skip all bot checks.
        if (is_user_logged_in()) {
            return $commentdata;
        }

        $settings = coin680_shield_get_settings();

        // 1. Honeypot.
        if (!empty($_POST[self::HP_FIELD])) {
            $this->reject('honeypot');
        }

        // 2. Signed time-trap.
        if (empty($_POST[self::TS_FIELD]) || empty($_POST[self::SIG_FIELD])) {
            $this->reject('missing_token');
        }
        if (!$this->is_signature_valid($_POST[self::TS_FIELD], $_POST[self::SIG_FIELD])) {
            $this->reject('invalid_token');
        }
        $elapsed = time() - (int) $_POST[self::TS_FIELD];
        $min_seconds = isset($settings['min_seconds']) ? (int) $settings['min_seconds'] : 3;
        if ($elapsed < $min_seconds) {
            $this->reject('too_fast');
        }

        // 3. Optional math CAPTCHA (premium only).
        if (Coin680_Shield_License::is_premium() && !empty($settings['use_captcha'])) {
            if (empty($_POST[self::CAPTCHA_FIELD]) || empty($_POST[self::CAPTCHA_ANSWER_FIELD])) {
                $this->reject('captcha_missing');
            }
            list($expr, $sig) = array_pad(explode('|', (string) $_POST[self::CAPTCHA_ANSWER_FIELD], 2), 2, '');
            $expected_sig = hash_hmac('sha256', $expr, wp_salt('auth'));
            if (!hash_equals($expected_sig, $sig)) {
                $this->reject('captcha_tampered');
            }
            $parts = explode('+', $expr);
            $correct = (int) $parts[0] + (int) $parts[1];
            if ((int) trim($_POST[self::CAPTCHA_FIELD]) !== $correct) {
                $this->reject('captcha_wrong');
            }
        }

        // 4. Keyword / link blocklist.
        $content = $commentdata['comment_content'];
        $blocklist = array_filter(array_map('trim', explode("\n", (string) ($settings['blocklist'] ?? ''))));
        foreach ($blocklist as $term) {
            if ($term !== '' && stripos($content, $term) !== false) {
                $this->reject('blocklist');
            }
        }
        $max_links = isset($settings['max_links']) ? (int) $settings['max_links'] : 2;
        if ($max_links > 0) {
            $link_count = preg_match_all('#https?://#i', $content);
            if ($link_count >= $max_links + 1) {
                $this->reject('too_many_links');
            }
        }

        // 5. Rate limiting (premium only).
        if (Coin680_Shield_License::is_premium()) {
            $limit = isset($settings['rate_limit_count']) ? (int) $settings['rate_limit_count'] : 3;
            $window = isset($settings['rate_limit_window']) ? (int) $settings['rate_limit_window'] : 600;
            if ($limit > 0) {
                $ip = coin680_shield_get_client_ip();
                $key = 'c680s_rl_' . md5($ip);
                $count = (int) get_transient($key);
                if ($count >= $limit) {
                    $this->reject('rate_limited');
                }
                set_transient($key, $count + 1, $window);
            }
        }

        return $commentdata;
    }
}
