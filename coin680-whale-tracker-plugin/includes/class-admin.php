<?php
/**
 * wp-admin screen: API settings + a 24h table of tracked whale transactions,
 * sorted by size, so a real digest post can be composed from real data.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Coin680Whale_Admin {
    private static $instance = null;

    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('admin_menu', array($this, 'add_menu'));
        add_action('admin_post_coin680whale_save_settings', array($this, 'handle_save_settings'));
        add_action('admin_post_coin680whale_mark_used', array($this, 'handle_mark_used'));
        add_action('admin_post_coin680whale_poll_now', array($this, 'handle_poll_now'));
    }

    public function add_menu() {
        add_menu_page(
            __('Coin680 Whale Tracker', 'coin680-whale-tracker'),
            __('Whale Tracker', 'coin680-whale-tracker'),
            'manage_options',
            'coin680-whale-tracker',
            array($this, 'render_page'),
            'dashicons-chart-line',
            82
        );
    }

    public function handle_save_settings() {
        check_admin_referer('coin680whale_save_settings');
        if (!current_user_can('manage_options')) { wp_die('Not allowed.'); }
        $settings = array(
            'api_key'   => sanitize_text_field(wp_unslash($_POST['api_key'] ?? '')),
            'min_value' => max(10000, (int) ($_POST['min_value'] ?? 500000)),
        );
        update_option('coin680whale_settings', $settings);
        wp_safe_redirect(add_query_arg('saved', '1', admin_url('admin.php?page=coin680-whale-tracker')));
        exit;
    }

    public function handle_mark_used() {
        check_admin_referer('coin680whale_mark_used');
        if (!current_user_can('manage_options')) { wp_die('Not allowed.'); }
        $ids = array_map('intval', (array) ($_POST['ids'] ?? array()));
        Coin680Whale_Fetcher::mark_used($ids);
        wp_safe_redirect(admin_url('admin.php?page=coin680-whale-tracker'));
        exit;
    }

    public function handle_poll_now() {
        check_admin_referer('coin680whale_poll_now');
        if (!current_user_can('manage_options')) { wp_die('Not allowed.'); }
        Coin680Whale_Fetcher::instance()->poll();
        wp_safe_redirect(add_query_arg('polled', '1', admin_url('admin.php?page=coin680-whale-tracker')));
        exit;
    }

    public function render_page() {
        if (!current_user_can('manage_options')) { return; }
        $settings = get_option('coin680whale_settings', array());
        $items = Coin680Whale_Fetcher::get_recent(24, 100);
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Coin680 Whale Tracker', 'coin680-whale-tracker'); ?></h1>
            <p><?php esc_html_e('Collects and classifies large on-chain transactions from Whale Alert. Writing the actual digest post/tweet is still a manual, reviewed step -- this page just surfaces real data to write from.', 'coin680-whale-tracker'); ?></p>

            <?php if (isset($_GET['saved'])) : ?><div class="notice notice-success"><p><?php esc_html_e('Settings saved.', 'coin680-whale-tracker'); ?></p></div><?php endif; ?>
            <?php if (isset($_GET['polled'])) : ?><div class="notice notice-success"><p><?php esc_html_e('Polled Whale Alert.', 'coin680-whale-tracker'); ?></p></div><?php endif; ?>

            <div class="card" style="max-width:600px;margin-top:16px;">
                <h2><?php esc_html_e('Whale Alert API Settings', 'coin680-whale-tracker'); ?></h2>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="coin680whale_save_settings">
                    <?php wp_nonce_field('coin680whale_save_settings'); ?>
                    <table class="form-table">
                        <tr><th><?php esc_html_e('API Key', 'coin680-whale-tracker'); ?></th><td><input type="text" name="api_key" style="width:100%;" value="<?php echo esc_attr($settings['api_key'] ?? ''); ?>"></td></tr>
                        <tr><th><?php esc_html_e('Minimum USD value to track', 'coin680-whale-tracker'); ?></th><td><input type="number" name="min_value" min="10000" step="10000" value="<?php echo esc_attr($settings['min_value'] ?? 500000); ?>"></td></tr>
                    </table>
                    <p>
                        <button type="submit" class="button button-primary"><?php esc_html_e('Save Settings', 'coin680-whale-tracker'); ?></button>
                    </p>
                </form>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="coin680whale_poll_now">
                    <?php wp_nonce_field('coin680whale_poll_now'); ?>
                    <button type="submit" class="button"><?php esc_html_e('Poll Whale Alert Now', 'coin680-whale-tracker'); ?></button>
                </form>
            </div>

            <div class="card" style="max-width:1100px;margin-top:16px;">
                <h2><?php esc_html_e('Last 24 Hours -- Largest First', 'coin680-whale-tracker'); ?></h2>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="coin680whale_mark_used">
                    <?php wp_nonce_field('coin680whale_mark_used'); ?>
                    <table class="widefat striped">
                        <thead><tr>
                            <th></th>
                            <th><?php esc_html_e('Time (UTC)', 'coin680-whale-tracker'); ?></th>
                            <th><?php esc_html_e('Chain / Symbol', 'coin680-whale-tracker'); ?></th>
                            <th><?php esc_html_e('Classification', 'coin680-whale-tracker'); ?></th>
                            <th><?php esc_html_e('From', 'coin680-whale-tracker'); ?></th>
                            <th><?php esc_html_e('To', 'coin680-whale-tracker'); ?></th>
                            <th><?php esc_html_e('Amount (USD)', 'coin680-whale-tracker'); ?></th>
                            <th><?php esc_html_e('Used?', 'coin680-whale-tracker'); ?></th>
                        </tr></thead>
                        <tbody>
                        <?php foreach ($items as $item) : ?>
                            <tr style="<?php echo $item->used_in_digest ? 'opacity:.5;' : ''; ?>">
                                <td><input type="checkbox" name="ids[]" value="<?php echo esc_attr($item->id); ?>"></td>
                                <td><?php echo esc_html($item->tx_timestamp); ?></td>
                                <td><?php echo esc_html(strtoupper($item->symbol)); ?> <small>(<?php echo esc_html($item->blockchain); ?>)</small></td>
                                <td><strong><?php echo esc_html($item->classification); ?></strong></td>
                                <td><?php echo esc_html($item->from_owner); ?> <small>(<?php echo esc_html($item->from_owner_type); ?>)</small></td>
                                <td><?php echo esc_html($item->to_owner); ?> <small>(<?php echo esc_html($item->to_owner_type); ?>)</small></td>
                                <td>$<?php echo esc_html(number_format($item->amount_usd)); ?></td>
                                <td><?php echo $item->used_in_digest ? '✓' : ''; ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($items)) : ?>
                            <tr><td colspan="8"><?php esc_html_e('No transactions tracked yet in this window.', 'coin680-whale-tracker'); ?></td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                    <p><button type="submit" class="button"><?php esc_html_e('Mark Selected as Used in a Digest', 'coin680-whale-tracker'); ?></button></p>
                </form>
            </div>
        </div>
        <?php
    }
}
