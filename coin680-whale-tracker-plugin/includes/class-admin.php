<?php
/**
 * wp-admin screen: API settings + paginated tables of tracked whale
 * transactions (Whale Alert -- Bitcoin only -- + Bitquery -- Solana/BSC/
 * Ethereum/TRON), sorted by size, so a real digest post can be composed
 * from real data.
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
        add_action('admin_post_coin680bitquery_poll_now', array($this, 'handle_bitquery_poll_now'));
        add_action('admin_post_coin680multichain_test_post', array($this, 'handle_multichain_test_post'));
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
            'bitquery_access_token'      => sanitize_text_field(wp_unslash($_POST['bitquery_access_token'] ?? '')),
            'bitquery_min_value'         => max(50000, (int) ($_POST['bitquery_min_value'] ?? 100000)),
            'bitquery_token_min_value'   => max(1000, (int) ($_POST['bitquery_token_min_value'] ?? 10000)),
            // Whale Alert data collection (Bitcoin only as of 2026-07-30)
            // keeps running either way (harmless) -- this only controls
            // whether the DIGEST POST itself draws from it. Turned off
            // means posts are Bitquery-only (Solana/BSC/Ethereum/TRON, no
            // Bitcoin).
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

    public function handle_bitquery_poll_now() {
        check_admin_referer('coin680bitquery_poll_now');
        if (!current_user_can('manage_options')) { wp_die('Not allowed.'); }
        if (class_exists('Coin680Bitquery_Fetcher')) {
            Coin680Bitquery_Fetcher::instance()->poll();
        }
        wp_safe_redirect(add_query_arg('bq_polled', '1', admin_url('admin.php?page=coin680-whale-tracker')));
        exit;
    }

    public function handle_multichain_test_post() {
        check_admin_referer('coin680multichain_test_post');
        if (!current_user_can('manage_options')) { wp_die('Not allowed.'); }
        $limit = max(1, min(10, (int) ($_POST['mc_test_limit'] ?? 7)));
        $ok = false;
        if (class_exists('Coin680Whale_Digest')) {
            $ok = Coin680Whale_Digest::instance()->post_multichain_test_digest($limit);
        }
        wp_safe_redirect(add_query_arg($ok ? 'mc_test_queued' : 'mc_test_empty', '1', admin_url('admin.php?page=coin680-whale-tracker')));
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
        $base_url = remove_query_arg(array('saved', 'polled', 'bq_polled'));
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

        $bq_page = max(1, (int) ($_GET['bq_page'] ?? 1));
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Coin680 Whale Tracker', 'coin680-whale-tracker'); ?></h1>
            <p><?php esc_html_e('Collects and classifies large on-chain transactions from Whale Alert (Bitcoin) and, via Bitquery, Solana/BSC/Ethereum/TRON DEX swaps directly. Writing the actual digest post/tweet is still a manual, reviewed step -- this page just surfaces real data to write from.', 'coin680-whale-tracker'); ?></p>

            <?php if (isset($_GET['saved'])) : ?><div class="notice notice-success"><p><?php esc_html_e('Settings saved.', 'coin680-whale-tracker'); ?></p></div><?php endif; ?>
            <?php if (isset($_GET['polled'])) : ?><div class="notice notice-success"><p><?php esc_html_e('Polled Whale Alert.', 'coin680-whale-tracker'); ?></p></div><?php endif; ?>
            <?php if (isset($_GET['bq_polled'])) : ?><div class="notice notice-success"><p><?php esc_html_e('Polled Bitquery (Solana/BSC/Ethereum/TRON).', 'coin680-whale-tracker'); ?></p></div><?php endif; ?>
            <?php if (isset($_GET['mc_test_queued'])) : ?><div class="notice notice-success"><p><?php esc_html_e('Multichain test post queued -- it will actually post to X within about 5 minutes via the X Scheduler cron.', 'coin680-whale-tracker'); ?></p></div><?php endif; ?>
            <?php if (isset($_GET['mc_test_empty'])) : ?><div class="notice notice-warning"><p><?php esc_html_e('No unused Bitquery transactions in the last 48h to build a test post from -- poll Bitquery first and try again.', 'coin680-whale-tracker'); ?></p></div><?php endif; ?>

            <?php $bq_debug = get_option('coin680bitquery_debug', array()); if ($bq_debug) : ?>
            <div class="card" style="max-width:1100px;margin-top:16px;background:#fffbe6;">
                <h2><?php esc_html_e('Bitquery Debug (temporary -- remove once data flows normally)', 'coin680-whale-tracker'); ?></h2>
                <pre style="white-space:pre-wrap;font-size:12px;"><?php echo esc_html(print_r($bq_debug, true)); ?></pre>
            </div>
            <?php endif; ?>

            <div class="card" style="max-width:600px;margin-top:16px;">
                <h2><?php esc_html_e('API Settings', 'coin680-whale-tracker'); ?></h2>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="coin680whale_save_settings">
                    <?php wp_nonce_field('coin680whale_save_settings'); ?>
                    <table class="form-table">
                        <tr><th colspan="2"><strong><?php esc_html_e('Whale Alert (Bitcoin only)', 'coin680-whale-tracker'); ?></strong></th></tr>
                        <tr><th><?php esc_html_e('API Key', 'coin680-whale-tracker'); ?></th><td><input type="text" name="api_key" style="width:100%;" value="<?php echo esc_attr($settings['api_key'] ?? ''); ?>"></td></tr>
                        <tr><th><?php esc_html_e('Minimum USD value to track -- BTC', 'coin680-whale-tracker'); ?></th><td><input type="number" name="min_value" min="10000" step="10000" value="<?php echo esc_attr($settings['min_value'] ?? 500000); ?>"></td></tr>
                        <tr><th><?php esc_html_e('Minimum USD value to track -- everything else (unused now that Whale Alert is Bitcoin-only, kept for easy revert)', 'coin680-whale-tracker'); ?></th><td><input type="number" name="altcoin_min_value" min="100000" step="10000" value="<?php echo esc_attr($settings['altcoin_min_value'] ?? 100000); ?>"></td></tr>
                        <tr><th><?php esc_html_e('Mega-transaction breaking alert threshold (USD)', 'coin680-whale-tracker'); ?></th><td><input type="number" name="mega_threshold" min="1000000" step="1000000" value="<?php echo esc_attr($settings['mega_threshold'] ?? 50000000); ?>"></td></tr>
                        <tr>
                            <th><?php esc_html_e('Include Whale Alert (Bitcoin) in posts?', 'coin680-whale-tracker'); ?></th>
                            <td>
                                <label><input type="checkbox" name="include_whale_alert_in_digest" value="1" <?php checked(!isset($settings['include_whale_alert_in_digest']) || !empty($settings['include_whale_alert_in_digest'])); ?>> <?php esc_html_e('Yes, mix Bitcoin (Whale Alert) into posts alongside Bitquery', 'coin680-whale-tracker'); ?></label>
                                <p class="description"><?php esc_html_e('Turn this OFF to make posts Bitquery-only (Solana/BSC/Ethereum/TRON, no Bitcoin). Whale Alert data collection keeps running either way (harmless) so this stays easy to switch back.', 'coin680-whale-tracker'); ?></p>
                            </td>
                        </tr>
                        <tr><th colspan="2"><hr></th></tr>
                        <tr><th colspan="2"><strong><?php esc_html_e('Bitquery (Solana / BSC / Ethereum / TRON)', 'coin680-whale-tracker'); ?></strong></th></tr>
                        <tr><th><?php esc_html_e('Access Token', 'coin680-whale-tracker'); ?></th><td><input type="text" name="bitquery_access_token" style="width:100%;" value="<?php echo esc_attr($settings['bitquery_access_token'] ?? ''); ?>"></td></tr>
                        <tr><th><?php esc_html_e('Minimum USD value to track -- stablecoins/wrapped-native (WSOL/WBNB/WETH/WTRX)', 'coin680-whale-tracker'); ?></th><td><input type="number" name="bitquery_min_value" min="50000" step="10000" value="<?php echo esc_attr($settings['bitquery_min_value'] ?? 100000); ?>"></td></tr>
                        <tr><th><?php esc_html_e('Minimum USD value to track -- everything else (smaller/meme tokens)', 'coin680-whale-tracker'); ?></th><td><input type="number" name="bitquery_token_min_value" min="1000" step="1000" value="<?php echo esc_attr($settings['bitquery_token_min_value'] ?? 10000); ?>"> <p class="description"><?php esc_html_e('Lower on purpose, same idea as the Whale Alert altcoin threshold.', 'coin680-whale-tracker'); ?></p></td></tr>
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
                    <input type="hidden" name="action" value="coin680bitquery_poll_now">
                    <?php wp_nonce_field('coin680bitquery_poll_now'); ?>
                    <button type="submit" class="button"><?php esc_html_e('Poll Bitquery Now', 'coin680-whale-tracker'); ?></button>
                </form>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline-block; margin-left:8px;">
                    <input type="hidden" name="action" value="coin680multichain_test_post">
                    <?php wp_nonce_field('coin680multichain_test_post'); ?>
                    <label><?php esc_html_e('Tokens:', 'coin680-whale-tracker'); ?> <input type="number" name="mc_test_limit" value="7" min="1" max="10" style="width:60px;"></label>
                    <button type="submit" class="button"><?php esc_html_e('Post Multichain Test Now (X)', 'coin680-whale-tracker'); ?></button>
                </form>
            </div>

            <div class="card" style="max-width:1100px;margin-top:16px;">
                <h2><?php esc_html_e('Whale Alert (Bitcoin) -- Last 24 Hours', 'coin680-whale-tracker'); ?></h2>
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

            <?php if (class_exists('Coin680Bitquery_Fetcher')) :
                $bq_chain = sanitize_key($_GET['bq_chain'] ?? '');
                $bq_valid_chains = class_exists('Coin680Bitquery_Labels') ? array_keys(Coin680Bitquery_Labels::CHAINS) : array();
                if ($bq_chain && !in_array($bq_chain, $bq_valid_chains, true)) {
                    $bq_chain = '';
                }
                $bq_total = Coin680Bitquery_Fetcher::count_recent(24, $bq_chain ?: null);
                $bq_items = Coin680Bitquery_Fetcher::get_recent(24, self::PER_PAGE, ($bq_page - 1) * self::PER_PAGE, $bq_chain ?: null);
                $bq_base_url = remove_query_arg(array('saved', 'polled', 'bq_polled', 'bq_page'));
            ?>
            <div class="card" style="max-width:1100px;margin-top:16px;">
                <h2><?php esc_html_e('Bitquery (Solana / BSC / Ethereum / TRON) -- Last 24 Hours', 'coin680-whale-tracker'); ?></h2>
                <p>
                    <strong><?php esc_html_e('Filter by chain:', 'coin680-whale-tracker'); ?></strong>
                    <a class="button <?php echo $bq_chain === '' ? 'button-primary' : ''; ?>" href="<?php echo esc_url(remove_query_arg('bq_chain', $bq_base_url)); ?>"><?php esc_html_e('All', 'coin680-whale-tracker'); ?></a>
                    <?php foreach ($bq_valid_chains as $c) : $cfg = Coin680Bitquery_Labels::chain_config($c); ?>
                        <a class="button <?php echo $bq_chain === $c ? 'button-primary' : ''; ?>" href="<?php echo esc_url(add_query_arg('bq_chain', $c, $bq_base_url)); ?>"><?php echo esc_html($cfg['label'] ?? ucfirst($c)); ?></a>
                    <?php endforeach; ?>
                </p>
                <table class="widefat striped">
                    <thead><tr>
                        <th><?php esc_html_e('Time (UTC)', 'coin680-whale-tracker'); ?></th>
                        <th><?php esc_html_e('Chain / Symbol', 'coin680-whale-tracker'); ?></th>
                        <th><?php esc_html_e('Classification', 'coin680-whale-tracker'); ?></th>
                        <th><?php esc_html_e('DEX', 'coin680-whale-tracker'); ?></th>
                        <th><?php esc_html_e('From', 'coin680-whale-tracker'); ?></th>
                        <th><?php esc_html_e('To', 'coin680-whale-tracker'); ?></th>
                        <th><?php esc_html_e('Amount (USD)', 'coin680-whale-tracker'); ?></th>
                        <th><?php esc_html_e('Used?', 'coin680-whale-tracker'); ?></th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($bq_items as $item) : ?>
                        <tr style="<?php echo $item->used_in_digest ? 'opacity:.5;' : ''; ?>">
                            <td><?php echo esc_html($item->tx_timestamp); ?></td>
                            <td><?php echo esc_html(strtoupper($item->symbol)); ?> <small>(<?php echo esc_html(ucfirst($item->chain)); ?>)</small></td>
                            <td><strong><?php echo esc_html($item->classification); ?></strong><?php echo $item->counter_symbol ? ' <small>vs ' . esc_html($item->counter_symbol) . '</small>' : ''; ?></td>
                            <td><?php echo esc_html($item->dex_name); ?></td>
                            <td><small><?php echo esc_html($item->from_address ? substr($item->from_address, 0, 10) . '...' : ''); ?></small></td>
                            <td><small><?php echo esc_html($item->to_address ? substr($item->to_address, 0, 10) . '...' : ''); ?></small></td>
                            <td>$<?php echo esc_html(number_format($item->amount_usd)); ?></td>
                            <td><?php echo $item->used_in_digest ? '✓' : ''; ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($bq_items)) : ?>
                        <tr><td colspan="8"><?php esc_html_e('No Bitquery transactions tracked yet -- add an access token above and click "Poll Bitquery Now".', 'coin680-whale-tracker'); ?></td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
                <?php $this->render_pager('bq_page', $bq_page, $bq_total, self::PER_PAGE); ?>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }
}
