<?php
/**
 * wp-admin screen: API settings + paginated tables of tracked whale
 * transactions (Whale Alert + multichain), sorted by size, so a real digest
 * post can be composed from real data.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Coin680Whale_Admin {
    private static $instance = null;
    const PER_PAGE = 30;

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
        add_action('admin_post_coin680multichain_poll_now', array($this, 'handle_multichain_poll_now'));
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
            'api_key'                    => sanitize_text_field(wp_unslash($_POST['api_key'] ?? '')),
            'min_value'                  => max(10000, (int) ($_POST['min_value'] ?? 500000)),
            'altcoin_min_value'          => max(100000, (int) ($_POST['altcoin_min_value'] ?? 100000)),
            'mega_threshold'             => max(1000000, (int) ($_POST['mega_threshold'] ?? 50000000)),
            'etherscan_api_key'          => sanitize_text_field(wp_unslash($_POST['etherscan_api_key'] ?? '')),
            'multichain_min_value'       => max(50000, (int) ($_POST['multichain_min_value'] ?? 100000)),
            'multichain_token_min_value' => max(1000, (int) ($_POST['multichain_token_min_value'] ?? 10000)),
            // Whale Alert data collection keeps running either way (harmless,
            // and cheap to leave on in case you go back) -- this only
            // controls whether the DIGEST POST itself draws from it. Turned
            // off means posts are Etherscan/multichain-only (Ethereum/
            // Polygon/Arbitrum/etc, no Bitcoin/XRP/TRON -- those chains are
            // not EVM and Etherscan can never cover them regardless of plan).
            'include_whale_alert_in_digest' => isset($_POST['include_whale_alert_in_digest']) ? 1 : 0,
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

    public function handle_multichain_poll_now() {
        check_admin_referer('coin680multichain_poll_now');
        if (!current_user_can('manage_options')) { wp_die('Not allowed.'); }
        if (class_exists('Coin680MultiChain_Fetcher')) {
            Coin680MultiChain_Fetcher::instance()->poll();
        }
        wp_safe_redirect(add_query_arg('mc_polled', '1', admin_url('admin.php?page=coin680-whale-tracker')));
        exit;
    }

    /**
     * Renders a simple "Page X of Y -- Prev / Next" control for one of the
     * two tables. $param is the query-string key so the two tables can
     * paginate independently on the same screen.
     */
    private function render_pager($param, $current_page, $total, $per_page) {
        $total_pages = max(1, (int) ceil($total / $per_page));
        if ($total_pages <= 1) {
            return;
        }
        $base_url = remove_query_arg(array('saved', 'polled', 'mc_polled'));
        echo '<p>';
        echo esc_html(sprintf(__('Page %1$d of %2$d (%3$d total)', 'coin680-whale-tracker'), $current_page, $total_pages, $total));
        echo ' &nbsp; ';
        if ($current_page > 1) {
            echo '<a class="button" href="' . esc_url(add_query_arg($param, $current_page - 1, $base_url)) . '">&laquo; ' . esc_html__('Prev', 'coin680-whale-tracker') . '</a> ';
        }
        if ($current_page < $total_pages) {
            echo '<a class="button" href="' . esc_url(add_query_arg($param, $current_page + 1, $base_url)) . '">' . esc_html__('Next', 'coin680-whale-tracker') . ' &raquo;</a>';
        }
        echo '</p>';
    }

    public function render_page() {
        if (!current_user_can('manage_options')) { return; }
        $settings = get_option('coin680whale_settings', array());

        $wa_page = max(1, (int) ($_GET['wa_page'] ?? 1));
        $wa_total = Coin680Whale_Fetcher::count_recent(24);
        $items = Coin680Whale_Fetcher::get_recent(24, self::PER_PAGE, ($wa_page - 1) * self::PER_PAGE);

        $mc_page = max(1, (int) ($_GET['mc_page'] ?? 1));
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Coin680 Whale Tracker', 'coin680-whale-tracker'); ?></h1>
            <p><?php esc_html_e('Collects and classifies large on-chain transactions from Whale Alert and, via Etherscan, EVM chains directly. Writing the actual digest post/tweet is still a manual, reviewed step -- this page just surfaces real data to write from.', 'coin680-whale-tracker'); ?></p>

            <?php if (isset($_GET['saved'])) : ?><div class="notice notice-success"><p><?php esc_html_e('Settings saved.', 'coin680-whale-tracker'); ?></p></div><?php endif; ?>
            <?php if (isset($_GET['polled'])) : ?><div class="notice notice-success"><p><?php esc_html_e('Polled Whale Alert.', 'coin680-whale-tracker'); ?></p></div><?php endif; ?>
            <?php if (isset($_GET['mc_polled'])) : ?><div class="notice notice-success"><p><?php esc_html_e('Polled multichain (Etherscan).', 'coin680-whale-tracker'); ?></p></div><?php endif; ?>

            <div class="card" style="max-width:600px;margin-top:16px;">
                <h2><?php esc_html_e('Whale Alert API Settings', 'coin680-whale-tracker'); ?></h2>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="coin680whale_save_settings">
                    <?php wp_nonce_field('coin680whale_save_settings'); ?>
                    <table class="form-table">
                        <tr><th><?php esc_html_e('API Key', 'coin680-whale-tracker'); ?></th><td><input type="text" name="api_key" style="width:100%;" value="<?php echo esc_attr($settings['api_key'] ?? ''); ?>"></td></tr>
                        <tr><th><?php esc_html_e('Minimum USD value to track -- BTC / ETH / USDT / USDC', 'coin680-whale-tracker'); ?></th><td><input type="number" name="min_value" min="10000" step="10000" value="<?php echo esc_attr($settings['min_value'] ?? 500000); ?>"></td></tr>
                        <tr><th><?php esc_html_e('Minimum USD value to track -- everything else (altcoins)', 'coin680-whale-tracker'); ?></th><td><input type="number" name="altcoin_min_value" min="100000" step="10000" value="<?php echo esc_attr($settings['altcoin_min_value'] ?? 100000); ?>"> <p class="description"><?php esc_html_e('Lower than the major-coin threshold on purpose. $100k is the lowest this Whale Alert API plan allows.', 'coin680-whale-tracker'); ?></p></td></tr>
                        <tr><th><?php esc_html_e('Mega-transaction breaking alert threshold (USD)', 'coin680-whale-tracker'); ?></th><td><input type="number" name="mega_threshold" min="1000000" step="1000000" value="<?php echo esc_attr($settings['mega_threshold'] ?? 50000000); ?>"></td></tr>
                        <tr>
                            <th><?php esc_html_e('Include Whale Alert data in posts?', 'coin680-whale-tracker'); ?></th>
                            <td>
                                <label><input type="checkbox" name="include_whale_alert_in_digest" value="1" <?php checked(!isset($settings['include_whale_alert_in_digest']) || !empty($settings['include_whale_alert_in_digest'])); ?>> <?php esc_html_e('Yes, mix Whale Alert (BTC/XRP/TRON/etc) into posts alongside multichain', 'coin680-whale-tracker'); ?></label>
                                <p class="description"><?php esc_html_e('Turn this OFF to make posts Etherscan/multichain-only. Whale Alert data collection keeps running either way (harmless) so this stays easy to switch back. Note: Bitcoin/XRP/TRON/Solana are not EVM chains -- Etherscan can never cover them regardless of plan, so turning this off means posts lose those coins entirely.', 'coin680-whale-tracker'); ?></p>
                            </td>
                        </tr>
                        <tr><th colspan="2"><hr></th></tr>
                        <tr><th><?php esc_html_e('Etherscan API Key (unified V2 -- Ethereum/Polygon/Arbitrum now, more chains once upgraded)', 'coin680-whale-tracker'); ?></th><td><input type="text" name="etherscan_api_key" style="width:100%;" value="<?php echo esc_attr($settings['etherscan_api_key'] ?? ''); ?>"></td></tr>
                        <tr><th><?php esc_html_e('Minimum USD value to track -- multichain: BTC/ETH-wrapped/stablecoins', 'coin680-whale-tracker'); ?></th><td><input type="number" name="multichain_min_value" min="50000" step="10000" value="<?php echo esc_attr($settings['multichain_min_value'] ?? 100000); ?>"></td></tr>
                        <tr><th><?php esc_html_e('Minimum USD value to track -- multichain: everything else (smaller tokens)', 'coin680-whale-tracker'); ?></th><td><input type="number" name="multichain_token_min_value" min="1000" step="1000" value="<?php echo esc_attr($settings['multichain_token_min_value'] ?? 10000); ?>"> <p class="description"><?php esc_html_e('Lower on purpose, same idea as the altcoin threshold above.', 'coin680-whale-tracker'); ?></p></td></tr>
                    </table>
                    <p>
                        <button type="submit" class="button button-primary"><?php esc_html_e('Save Settings', 'coin680-whale-tracker'); ?></button>
                    </p>
                </form>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline-block;">
                    <input type="hidden" name="action" value="coin680whale_poll_now">
                    <?php wp_nonce_field('coin680whale_poll_now'); ?>
                    <button type="submit" class="button"><?php esc_html_e('Poll Whale Alert Now', 'coin680-whale-tracker'); ?></button>
                </form>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline-block; margin-left:8px;">
                    <input type="hidden" name="action" value="coin680multichain_poll_now">
                    <?php wp_nonce_field('coin680multichain_poll_now'); ?>
                    <button type="submit" class="button"><?php esc_html_e('Poll Multichain (Etherscan) Now', 'coin680-whale-tracker'); ?></button>
                </form>
            </div>

            <div class="card" style="max-width:1100px;margin-top:16px;">
                <h2><?php esc_html_e('Whale Alert -- Last 24 Hours', 'coin680-whale-tracker'); ?></h2>
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
                <?php $this->render_pager('wa_page', $wa_page, $wa_total, self::PER_PAGE); ?>
            </div>

            <?php if (class_exists('Coin680MultiChain_Fetcher')) :
                $mc_total = Coin680MultiChain_Fetcher::count_recent(24);
                $mc_items = Coin680MultiChain_Fetcher::get_recent(24, self::PER_PAGE, ($mc_page - 1) * self::PER_PAGE);
            ?>
            <div class="card" style="max-width:1100px;margin-top:16px;">
                <h2><?php esc_html_e('Multichain (Ethereum/Polygon/Arbitrum) -- Last 24 Hours', 'coin680-whale-tracker'); ?></h2>
                <table class="widefat striped">
                    <thead><tr>
                        <th><?php esc_html_e('Time (UTC)', 'coin680-whale-tracker'); ?></th>
                        <th><?php esc_html_e('Chain / Symbol', 'coin680-whale-tracker'); ?></th>
                        <th><?php esc_html_e('Classification', 'coin680-whale-tracker'); ?></th>
                        <th><?php esc_html_e('DEX / Exchange', 'coin680-whale-tracker'); ?></th>
                        <th><?php esc_html_e('From', 'coin680-whale-tracker'); ?></th>
                        <th><?php esc_html_e('To', 'coin680-whale-tracker'); ?></th>
                        <th><?php esc_html_e('Amount (USD)', 'coin680-whale-tracker'); ?></th>
                        <th><?php esc_html_e('Used?', 'coin680-whale-tracker'); ?></th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($mc_items as $item) : ?>
                        <tr style="<?php echo $item->used_in_digest ? 'opacity:.5;' : ''; ?>">
                            <td><?php echo esc_html($item->tx_timestamp); ?></td>
                            <td><?php echo esc_html(strtoupper($item->symbol)); ?> <small>(<?php echo esc_html(ucfirst($item->chain)); ?>)</small></td>
                            <td><strong><?php echo esc_html($item->classification); ?></strong><?php echo $item->counter_symbol ? ' <small>vs ' . esc_html($item->counter_symbol) . '</small>' : ''; ?></td>
                            <td><?php echo esc_html($item->dex_name); ?></td>
                            <td><small><?php echo esc_html($item->from_owner !== 'unknown' ? $item->from_owner : substr($item->from_address, 0, 10) . '...'); ?></small></td>
                            <td><small><?php echo esc_html($item->to_owner !== 'unknown' ? $item->to_owner : substr($item->to_address, 0, 10) . '...'); ?></small></td>
                            <td>$<?php echo esc_html(number_format($item->amount_usd)); ?></td>
                            <td><?php echo $item->used_in_digest ? '✓' : ''; ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($mc_items)) : ?>
                        <tr><td colspan="8"><?php esc_html_e('No multichain transactions tracked yet -- add an Etherscan API key above and click "Poll Multichain (Etherscan) Now".', 'coin680-whale-tracker'); ?></td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
                <?php $this->render_pager('mc_page', $mc_page, $mc_total, self::PER_PAGE); ?>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }
}
