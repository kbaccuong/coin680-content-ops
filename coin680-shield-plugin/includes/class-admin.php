<?php
/**
 * wp-admin settings screen: license unlock, free-tier settings, premium
 * settings (grayed out until unlocked), and a simple blocked-count dashboard.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Coin680_Shield_Admin {
    private static $instance = null;

    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('admin_menu', array($this, 'add_menu'));
        add_action('admin_post_coin680_shield_save_settings', array($this, 'handle_save_settings'));
        add_action('admin_post_coin680_shield_unlock', array($this, 'handle_unlock'));
        add_action('admin_post_coin680_shield_lock', array($this, 'handle_lock'));
    }

    public function add_menu() {
        add_menu_page(
            __('Coin680 Shield', 'coin680-shield'),
            __('Coin680 Shield', 'coin680-shield'),
            'manage_options',
            'coin680-shield',
            array($this, 'render_page'),
            'dashicons-shield',
            80
        );
    }

    public function handle_unlock() {
        check_admin_referer('coin680_shield_unlock');
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Not allowed.', 'coin680-shield'));
        }
        $code = isset($_POST['coin680_shield_code']) ? sanitize_text_field(wp_unslash($_POST['coin680_shield_code'])) : '';
        $ok = Coin680_Shield_License::try_unlock($code);
        $redirect = add_query_arg('unlocked', $ok ? '1' : '0', admin_url('admin.php?page=coin680-shield'));
        wp_safe_redirect($redirect);
        exit;
    }

    public function handle_lock() {
        check_admin_referer('coin680_shield_lock');
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Not allowed.', 'coin680-shield'));
        }
        Coin680_Shield_License::lock();
        wp_safe_redirect(admin_url('admin.php?page=coin680-shield'));
        exit;
    }

    public function handle_save_settings() {
        check_admin_referer('coin680_shield_save_settings');
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Not allowed.', 'coin680-shield'));
        }

        $is_premium = Coin680_Shield_License::is_premium();
        $settings = coin680_shield_get_settings();

        $settings['blocklist']  = isset($_POST['blocklist']) ? sanitize_textarea_field(wp_unslash($_POST['blocklist'])) : $settings['blocklist'];
        $settings['bad_bot_agents'] = isset($_POST['bad_bot_agents']) ? sanitize_textarea_field(wp_unslash($_POST['bad_bot_agents'])) : $settings['bad_bot_agents'];
        $settings['max_links']   = isset($_POST['max_links']) ? max(0, (int) $_POST['max_links']) : $settings['max_links'];
        $settings['min_seconds'] = isset($_POST['min_seconds']) ? max(0, (int) $_POST['min_seconds']) : $settings['min_seconds'];
        $settings['block_xmlrpc']   = !empty($_POST['block_xmlrpc']) ? 1 : 0;
        $settings['block_bad_bots'] = !empty($_POST['block_bad_bots']) ? 1 : 0;

        if ($is_premium) {
            $settings['rate_limit_count']  = isset($_POST['rate_limit_count']) ? max(0, (int) $_POST['rate_limit_count']) : $settings['rate_limit_count'];
            $settings['rate_limit_window'] = isset($_POST['rate_limit_window']) ? max(60, (int) $_POST['rate_limit_window']) : $settings['rate_limit_window'];
            $settings['login_max_attempts'] = isset($_POST['login_max_attempts']) ? max(1, (int) $_POST['login_max_attempts']) : $settings['login_max_attempts'];
            $settings['login_lockout_minutes'] = isset($_POST['login_lockout_minutes']) ? max(1, (int) $_POST['login_lockout_minutes']) : $settings['login_lockout_minutes'];
            $settings['firewall_enabled'] = !empty($_POST['firewall_enabled']) ? 1 : 0;
            $settings['use_captcha']      = !empty($_POST['use_captcha']) ? 1 : 0;
        }

        update_option('coin680_shield_settings', $settings);
        wp_safe_redirect(add_query_arg('saved', '1', admin_url('admin.php?page=coin680-shield')));
        exit;
    }

    public function render_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        $is_premium = Coin680_Shield_License::is_premium();
        $settings = coin680_shield_get_settings();
        $stats = get_option('coin680_shield_stats', array());
        $total_blocked = array_sum($stats);
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Coin680 Shield -- Anti-Bot & Comment Protection', 'coin680-shield'); ?></h1>

            <?php if (isset($_GET['unlocked'])) : ?>
                <?php if ($_GET['unlocked'] === '1') : ?>
                    <div class="notice notice-success"><p><?php esc_html_e('Premium features unlocked. Enjoy!', 'coin680-shield'); ?></p></div>
                <?php else : ?>
                    <div class="notice notice-error"><p><?php esc_html_e('That code is not valid.', 'coin680-shield'); ?></p></div>
                <?php endif; ?>
            <?php endif; ?>
            <?php if (isset($_GET['saved'])) : ?>
                <div class="notice notice-success"><p><?php esc_html_e('Settings saved.', 'coin680-shield'); ?></p></div>
            <?php endif; ?>

            <div class="card" style="max-width:700px;margin-top:16px;">
                <h2><?php esc_html_e('License Status', 'coin680-shield'); ?></h2>
                <?php if ($is_premium) : ?>
                    <p><strong style="color:#1a9c4a;">&#10003; <?php esc_html_e('Premium unlocked -- all features active.', 'coin680-shield'); ?></strong></p>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <input type="hidden" name="action" value="coin680_shield_lock">
                        <?php wp_nonce_field('coin680_shield_lock'); ?>
                        <button type="submit" class="button"><?php esc_html_e('Revert to Free Tier', 'coin680-shield'); ?></button>
                    </form>
                <?php else : ?>
                    <p><?php esc_html_e('You are on the free tier (honeypot, time-trap, and keyword/link filtering are always on). Enter the unlock code below to enable rate limiting, login brute-force protection, the request firewall, and CAPTCHA -- free.', 'coin680-shield'); ?></p>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <input type="hidden" name="action" value="coin680_shield_unlock">
                        <?php wp_nonce_field('coin680_shield_unlock'); ?>
                        <input type="text" name="coin680_shield_code" placeholder="<?php esc_attr_e('Enter unlock code', 'coin680-shield'); ?>" required>
                        <button type="submit" class="button button-primary"><?php esc_html_e('Unlock', 'coin680-shield'); ?></button>
                    </form>
                <?php endif; ?>
            </div>

            <div class="card" style="max-width:700px;margin-top:16px;">
                <h2><?php esc_html_e('Blocked So Far', 'coin680-shield'); ?></h2>
                <p><strong><?php echo esc_html(number_format_i18n($total_blocked)); ?></strong> <?php esc_html_e('total blocked attempts.', 'coin680-shield'); ?></p>
                <?php if (!empty($stats)) : ?>
                <table class="widefat striped" style="max-width:500px;">
                    <thead><tr><th><?php esc_html_e('Reason', 'coin680-shield'); ?></th><th><?php esc_html_e('Count', 'coin680-shield'); ?></th></tr></thead>
                    <tbody>
                    <?php foreach ($stats as $reason => $count) : ?>
                        <tr><td><?php echo esc_html(str_replace('_', ' ', $reason)); ?></td><td><?php echo esc_html(number_format_i18n($count)); ?></td></tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="coin680_shield_save_settings">
                <?php wp_nonce_field('coin680_shield_save_settings'); ?>

                <div class="card" style="max-width:700px;margin-top:16px;">
                    <h2><?php esc_html_e('Free -- Comment & Bot Protection', 'coin680-shield'); ?></h2>
                    <table class="form-table">
                        <tr>
                            <th><?php esc_html_e('Blocked words/phrases (one per line)', 'coin680-shield'); ?></th>
                            <td><textarea name="blocklist" rows="8" style="width:100%;max-width:500px;"><?php echo esc_textarea($settings['blocklist']); ?></textarea></td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e('Max links allowed in a comment', 'coin680-shield'); ?></th>
                            <td><input type="number" min="0" name="max_links" value="<?php echo esc_attr($settings['max_links']); ?>"></td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e('Minimum seconds before a comment can be submitted', 'coin680-shield'); ?></th>
                            <td><input type="number" min="0" name="min_seconds" value="<?php echo esc_attr($settings['min_seconds']); ?>"></td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e('Block XML-RPC', 'coin680-shield'); ?></th>
                            <td><label><input type="checkbox" name="block_xmlrpc" <?php checked(!empty($settings['block_xmlrpc'])); ?>> <?php esc_html_e('Disable xmlrpc.php (common brute-force target)', 'coin680-shield'); ?></label></td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e('Block known bad bots', 'coin680-shield'); ?></th>
                            <td>
                                <label><input type="checkbox" name="block_bad_bots" <?php checked(!empty($settings['block_bad_bots'])); ?>> <?php esc_html_e('Enabled', 'coin680-shield'); ?></label>
                                <br><textarea name="bad_bot_agents" rows="4" style="width:100%;max-width:500px;margin-top:6px;"><?php echo esc_textarea($settings['bad_bot_agents']); ?></textarea>
                                <p class="description"><?php esc_html_e('One user-agent substring per line.', 'coin680-shield'); ?></p>
                            </td>
                        </tr>
                    </table>
                </div>

                <div class="card" style="max-width:700px;margin-top:16px;<?php echo $is_premium ? '' : 'opacity:.5;'; ?>">
                    <h2><?php esc_html_e('Premium -- Rate Limiting, Login & Firewall', 'coin680-shield'); ?><?php if (!$is_premium) : ?> <span style="font-size:12px;font-weight:400;">(<?php esc_html_e('unlock above to edit', 'coin680-shield'); ?>)</span><?php endif; ?></h2>
                    <table class="form-table">
                        <tr>
                            <th><?php esc_html_e('Max comments per IP', 'coin680-shield'); ?></th>
                            <td><input type="number" min="0" name="rate_limit_count" value="<?php echo esc_attr($settings['rate_limit_count']); ?>" <?php disabled(!$is_premium); ?>></td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e('...per this many seconds', 'coin680-shield'); ?></th>
                            <td><input type="number" min="60" name="rate_limit_window" value="<?php echo esc_attr($settings['rate_limit_window']); ?>" <?php disabled(!$is_premium); ?>></td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e('Max failed logins before lockout', 'coin680-shield'); ?></th>
                            <td><input type="number" min="1" name="login_max_attempts" value="<?php echo esc_attr($settings['login_max_attempts']); ?>" <?php disabled(!$is_premium); ?>></td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e('Lockout duration (minutes)', 'coin680-shield'); ?></th>
                            <td><input type="number" min="1" name="login_lockout_minutes" value="<?php echo esc_attr($settings['login_lockout_minutes']); ?>" <?php disabled(!$is_premium); ?>></td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e('Request firewall', 'coin680-shield'); ?></th>
                            <td><label><input type="checkbox" name="firewall_enabled" <?php checked(!empty($settings['firewall_enabled'])); ?> <?php disabled(!$is_premium); ?>> <?php esc_html_e('Block requests containing common attack patterns (SQLi/XSS-style)', 'coin680-shield'); ?></label></td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e('Math CAPTCHA on comments', 'coin680-shield'); ?></th>
                            <td><label><input type="checkbox" name="use_captcha" <?php checked(!empty($settings['use_captcha'])); ?> <?php disabled(!$is_premium); ?>> <?php esc_html_e('Ask commenters to solve a simple sum', 'coin680-shield'); ?></label></td>
                        </tr>
                    </table>
                </div>

                <p><button type="submit" class="button button-primary"><?php esc_html_e('Save Settings', 'coin680-shield'); ?></button></p>
            </form>
        </div>
        <?php
    }
}
