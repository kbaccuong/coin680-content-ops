<?php
/**
 * Site-wide bot/request hardening.
 * - Bad-bot user-agent blocking and XML-RPC blocking are free.
 * - The request pattern firewall (basic SQLi/XSS-style signatures in the
 *   URL and query string) is premium, off by default, since an aggressive
 *   pattern filter carries some false-positive risk and should be an
 *   explicit opt-in rather than a silent default.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Coin680_Shield_Firewall {
    private static $instance = null;

    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('init', array($this, 'run_checks'), 1);
    }

    public static function default_bad_bots() {
        return implode("\n", array(
            'MJ12bot', 'DotBot', 'PetalBot', 'python-requests', 'libwww-perl',
            'SemrushBot', 'AhrefsBot', 'MauiBot', 'Bytespider',
        ));
    }

    public function run_checks() {
        $settings = coin680_shield_get_settings();

        if (!empty($settings['block_xmlrpc'])) {
            $this->block_xmlrpc();
        }
        if (!empty($settings['block_bad_bots'])) {
            $this->block_bad_bots($settings);
        }
        if (Coin680_Shield_License::is_premium() && !empty($settings['firewall_enabled'])) {
            $this->block_suspicious_requests();
        }
    }

    private function deny($reason) {
        $stats = get_option('coin680_shield_stats', array());
        $stats[$reason] = isset($stats[$reason]) ? $stats[$reason] + 1 : 1;
        update_option('coin680_shield_stats', $stats);
        status_header(403);
        header('Content-Type: text/plain; charset=utf-8');
        die('Forbidden');
    }

    private function block_xmlrpc() {
        if (isset($_SERVER['SCRIPT_FILENAME']) && basename($_SERVER['SCRIPT_FILENAME']) === 'xmlrpc.php') {
            $this->deny('xmlrpc_blocked');
        }
        add_filter('xmlrpc_enabled', '__return_false');
    }

    private function block_bad_bots($settings) {
        $ua = isset($_SERVER['HTTP_USER_AGENT']) ? (string) $_SERVER['HTTP_USER_AGENT'] : '';
        if ($ua === '') {
            return; // empty UA alone isn't reliable enough to block by default
        }
        $list = array_filter(array_map('trim', explode("\n", (string) ($settings['bad_bot_agents'] ?? ''))));
        foreach ($list as $bad) {
            if ($bad !== '' && stripos($ua, $bad) !== false) {
                $this->deny('bad_bot_ua');
            }
        }
    }

    private function block_suspicious_requests() {
        $uri = isset($_SERVER['REQUEST_URI']) ? rawurldecode($_SERVER['REQUEST_URI']) : '';
        $patterns = array(
            '/<script/i',
            '/union\s+select/i',
            '/base64_decode\s*\(/i',
            '/\.\.\/\.\.\/\.\./',
            '/etc\/passwd/i',
            '/eval\s*\(/i',
        );
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $uri)) {
                $this->deny('firewall_pattern');
            }
        }
        foreach ($_GET as $value) {
            if (!is_string($value)) {
                continue;
            }
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $value)) {
                    $this->deny('firewall_pattern');
                }
            }
        }
    }
}
