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
        add_action('rest_api_init', array($this, 'register_rest_routes'));
        add_action('admin_post_coin680whale_save_settings', array($this, 'handle_save_settings'));
        add_action('admin_post_coin680whale_mark_used', array($this, 'handle_mark_used'));
        add_action('admin_post_coin680whale_poll_now', array($this, 'handle_poll_now'));
        add_action('admin_post_coin680bitquery_poll_now', array($this, 'handle_bitquery_poll_now'));
        add_action('admin_post_coin680multichain_test_post', array($this, 'handle_multichain_test_post'));
        add_action('admin_post_coin680watchlist_add', array($this, 'handle_watchlist_add'));
        add_action('admin_post_coin680watchlist_remove', array($this, 'handle_watchlist_remove'));
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

    /**
     * Admin-only REST route to purge existing bad rows already inserted
     * before the sanity checks in Coin680Bitquery_Fetcher::process_trade()
     * (same-symbol legs, implausible USD values) were added -- lets these
     * be cleaned up remotely without SSH/DB access. See
     * Coin680Bitquery_Fetcher::MAX_SANE_TRADE_USD for the same ceiling.
     */
    public function register_rest_routes() {
        register_rest_route('coin680whale/v1', '/purge-bad-trades', array(
            'methods'             => 'POST',
            'callback'            => array($this, 'rest_purge_bad_trades'),
            'permission_callback' => function () {
                return current_user_can('manage_options');
            },
        ));
        register_rest_route('coin680whale/v1', '/smart-money', array(
            'methods'             => 'GET',
            'callback'            => array($this, 'rest_smart_money'),
            'permission_callback' => function () {
                return current_user_can('manage_options');
            },
        ));
    }

    /**
     * Admin-only diagnostic: exposes Coin680Watchlist_Fetcher::get_recent()
     * over REST so watched-wallet activity can be verified remotely without
     * a logged-in wp-admin session (Application Password auth only reaches
     * REST, not the wp-admin HTML screens). Added 2026-08-08 after a user
     * report that a watched wallet's past buy/sell activity wasn't showing
     * on the public page -- turned out to be page-whale-signals.php's 24h
     * window, not a recording bug, but this endpoint is the fast way to
     * confirm that distinction (data present but old vs. never recorded)
     * without waiting on a wider public-page window to prove it.
     */
    public function rest_smart_money($request) {
        if (!class_exists('Coin680Watchlist_Fetcher')) {
            return new WP_REST_Response(array('error' => 'Coin680Watchlist_Fetcher not loaded'), 500);
        }
        $hours = max(1, min(336, (int) ($request->get_param('hours') ?: 168)));
        $limit = max(1, min(200, (int) ($request->get_param('limit') ?: 50)));
        $items = Coin680Watchlist_Fetcher::get_recent($hours, $limit);
        $out = array();
        foreach ($items as $item) {
            $out[] = array(
                'time'           => $item->tx_timestamp,
                'wallet_label'   => $item->wallet_label,
                'wallet_address' => $item->wallet_address,
                'chain'          => $item->chain,
                'side'           => $item->side,
                'symbol'         => $item->symbol,
                'counter_symbol' => $item->counter_symbol,
                'amount_usd'     => (float) $item->amount_usd,
                'alerted'        => (bool) $item->alerted,
            );
        }

        // Also return the full watched-wallet list (with untruncated
        // addresses) so it's possible to cross-check a specific wallet
        // against an independent source (Bitquery/Etherscan/BscScan
        // directly) when activity count comes back 0 -- lets you tell
        // "wallet genuinely quiet" apart from "polling isn't running" or
        // "wallet address was entered wrong" without a wp-admin session.
        $wallets_out = array();
        if (class_exists('Coin680Watchlist')) {
            foreach (Coin680Watchlist::get_all(true) as $w) {
                $wallets_out[] = array(
                    'id'      => (int) $w->id,
                    'chain'   => $w->chain,
                    'address' => $w->address,
                    'label'   => $w->label,
                );
            }
        }

        // Last time the watchlist poll cron actually ran, if the fetcher
        // tracks it -- helps distinguish "cron never fires" from "cron
        // fires fine, these specific wallets just haven't traded".
        $last_poll = get_option('coin680watchlist_last_poll_at', '');

        return new WP_REST_Response(array(
            'hours'          => $hours,
            'count'          => count($out),
            'items'          => $out,
            'watched_wallets'=> $wallets_out,
            'last_poll_at'   => $last_poll,
        ), 200);
    }

    public function rest_purge_bad_trades($request) {
        global $wpdb;
        $table = Coin680Bitquery_Fetcher::table_name();
        $ceiling = 50000000; // keep in sync with Coin680Bitquery_Fetcher::MAX_SANE_TRADE_USD
        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM $table WHERE amount_usd > %f OR symbol = counter_symbol",
            $ceiling
        ));
        return new WP_REST_Response(array('deleted' => (int) $deleted), 200);
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

    public function handle_watchlist_add() {
        check_admin_referer('coin680watchlist_add');
        if (!current_user_can('manage_options')) { wp_die('Not allowed.'); }
        $chain = sanitize_key($_POST['wl_chain'] ?? '');
        $address = sanitize_text_field(wp_unslash($_POST['wl_address'] ?? ''));
        $label = sanitize_text_field(wp_unslash($_POST['wl_label'] ?? ''));
        $ok = class_exists('Coin680Watchlist') ? Coin680Watchlist::add($chain, $address, $label) : false;
        wp_safe_redirect(add_query_arg($ok ? 'wl_added' : 'wl_error', '1', admin_url('admin.php?page=coin680-whale-tracker')));
        exit;
    }

    public function handle_watchlist_remove() {
        check_admin_referer('coin680watchlist_remove');
        if (!current_user_can('manage_options')) { wp_die('Not allowed.'); }
        $id = (int) ($_POST['wl_id'] ?? 0);
        if ($id && class_exists('Coin680Watchlist')) {
            Coin680Watchlist::remove($id);
        }
        wp_safe_redirect(add_query_arg('wl_removed', '1', admin_url('admin.php?page=coin680-whale-tracker')));
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
            <?php if (isset($_GET['mc_test_queued'])) : ?><div class="notice notice-success"><p><?php esc_html_e('Post queued -- it will actually post to X within about 5 minutes via the X Scheduler cron.', 'coin680-whale-tracker'); ?></p></div><?php endif; ?>
            <?php if (isset($_GET['mc_test_empty'])) : ?><div class="notice notice-warning"><p><?php esc_html_e('No unused Bitquery transactions in the last 48h to build a post from -- poll Bitquery first and try again.', 'coin680-whale-tracker'); ?></p></div><?php endif; ?>
            <?php if (isset($_GET['wl_added'])) : ?><div class="notice notice-success"><p><?php esc_html_e('Wallet added to watchlist.', 'coin680-whale-tracker'); ?></p></div><?php endif; ?>
            <?php if (isset($_GET['wl_error'])) : ?><div class="notice notice-error"><p><?php esc_html_e('Could not add wallet -- check the chain and address are both filled in.', 'coin680-whale-tracker'); ?></p></div><?php endif; ?>
            <?php if (isset($_GET['wl_removed'])) : ?><div class="notice notice-success"><p><?php esc_html_e('Wallet removed from watchlist.', 'coin680-whale-tracker'); ?></p></div><?php endif; ?>

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
                    <button type="submit" class="button"><?php esc_html_e('Post Multichain Now (X)', 'coin680-whale-tracker'); ?></button>
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

            <?php if (class_exists('Coin680Watchlist')) :
                $watched = Coin680Watchlist::get_all(true);
            ?>
            <div class="card" style="max-width:900px;margin-top:16px;">
                <h2><?php esc_html_e('Watched Wallets ("Smart Money")', 'coin680-whale-tracker'); ?></h2>
                <p class="description"><?php esc_html_e('Add specific wallet addresses to watch. Unlike the tables above (which flag ANY large transaction), a watched wallet is flagged for what it does regardless of size -- checked every 2 minutes alongside the main Bitquery poll, Solana/BSC/Ethereum only (see class-watchlist-fetcher.php docblock for why TRON is not supported here).', 'coin680-whale-tracker'); ?></p>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="coin680watchlist_add">
                    <?php wp_nonce_field('coin680watchlist_add'); ?>
                    <table class="form-table">
                        <tr>
                            <th><?php esc_html_e('Chain', 'coin680-whale-tracker'); ?></th>
                            <td>
                                <select name="wl_chain">
                                    <?php foreach (Coin680Bitquery_Labels::CHAINS as $key => $cfg) :
                                        if (!in_array($cfg['query_kind'], array('solana', 'evm'), true)) { continue; }
                                    ?>
                                        <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($cfg['label']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr><th><?php esc_html_e('Wallet Address', 'coin680-whale-tracker'); ?></th><td><input type="text" name="wl_address" style="width:100%;" required placeholder="0x... or a Solana base58 address"></td></tr>
                        <tr><th><?php esc_html_e('Label (optional)', 'coin680-whale-tracker'); ?></th><td><input type="text" name="wl_label" style="width:100%;" placeholder="e.g. \"Fund X treasury\""></td></tr>
                    </table>
                    <p><button type="submit" class="button button-primary"><?php esc_html_e('Add Wallet', 'coin680-whale-tracker'); ?></button></p>
                </form>

                <table class="widefat striped" style="margin-top:12px;">
                    <thead><tr>
                        <th><?php esc_html_e('Chain', 'coin680-whale-tracker'); ?></th>
                        <th><?php esc_html_e('Address', 'coin680-whale-tracker'); ?></th>
                        <th><?php esc_html_e('Label', 'coin680-whale-tracker'); ?></th>
                        <th><?php esc_html_e('Added', 'coin680-whale-tracker'); ?></th>
                        <th></th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($watched as $w) :
                        $cfg = Coin680Bitquery_Labels::chain_config($w->chain);
                    ?>
                        <tr>
                            <td><?php echo esc_html($cfg['label'] ?? ucfirst($w->chain)); ?></td>
                            <td><small><?php echo esc_html($w->address); ?></small></td>
                            <td><?php echo esc_html($w->label); ?></td>
                            <td><?php echo esc_html($w->created_at); ?></td>
                            <td>
                                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline;">
                                    <input type="hidden" name="action" value="coin680watchlist_remove">
                                    <input type="hidden" name="wl_id" value="<?php echo esc_attr($w->id); ?>">
                                    <?php wp_nonce_field('coin680watchlist_remove'); ?>
                                    <button type="submit" class="button button-small"><?php esc_html_e('Remove', 'coin680-whale-tracker'); ?></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($watched)) : ?>
                        <tr><td colspan="5"><?php esc_html_e('No wallets watched yet -- add one above.', 'coin680-whale-tracker'); ?></td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if (class_exists('Coin680Watchlist_Fetcher')) :
                $sm_page = max(1, (int) ($_GET['sm_page'] ?? 1));
                $sm_total = Coin680Watchlist_Fetcher::count_recent(72);
                $sm_items = Coin680Watchlist_Fetcher::get_recent(72, self::PER_PAGE, ($sm_page - 1) * self::PER_PAGE);
            ?>
            <div class="card" style="max-width:1100px;margin-top:16px;">
                <h2><?php esc_html_e('Smart Money Moves -- Last 72 Hours', 'coin680-whale-tracker'); ?></h2>
                <table class="widefat striped">
                    <thead><tr>
                        <th><?php esc_html_e('Time (UTC)', 'coin680-whale-tracker'); ?></th>
                        <th><?php esc_html_e('Wallet', 'coin680-whale-tracker'); ?></th>
                        <th><?php esc_html_e('Chain', 'coin680-whale-tracker'); ?></th>
                        <th><?php esc_html_e('Action', 'coin680-whale-tracker'); ?></th>
                        <th><?php esc_html_e('Amount (USD)', 'coin680-whale-tracker'); ?></th>
                        <th><?php esc_html_e('Alerted?', 'coin680-whale-tracker'); ?></th>
                    </tr></thead>
                    <tbody>
                    <?php
                    $c680_sm_side_labels = array(
                        'buy'      => __('Bought', 'coin680-whale-tracker'),
                        'sell'     => __('Sold', 'coin680-whale-tracker'),
                        'received' => __('Received', 'coin680-whale-tracker'),
                        'sent'     => __('Sent', 'coin680-whale-tracker'),
                    );
                    ?>
                    <?php foreach ($sm_items as $item) : ?>
                        <tr>
                            <td><?php echo esc_html($item->tx_timestamp); ?></td>
                            <td><?php echo esc_html($item->wallet_label ?: (substr($item->wallet_address, 0, 6) . '...')); ?></td>
                            <td><?php echo esc_html(ucfirst($item->chain)); ?></td>
                            <td>
                                <?php echo esc_html($c680_sm_side_labels[$item->side] ?? ucfirst($item->side)); ?> <?php echo esc_html(strtoupper($item->symbol)); ?><?php echo $item->counter_symbol ? ' <small>for ' . esc_html($item->counter_symbol) . '</small>' : ''; ?>
                                <?php if (in_array($item->side, array('received', 'sent'), true) && $item->dex_name) : ?>
                                    <br><small><?php echo $item->side === 'received' ? esc_html__('from', 'coin680-whale-tracker') : esc_html__('to', 'coin680-whale-tracker'); ?> <?php echo esc_html($item->dex_name); ?></small>
                                <?php endif; ?>
                            </td>
                            <td>$<?php echo esc_html(number_format($item->amount_usd)); ?></td>
                            <td><?php echo $item->alerted ? '✓' : ''; ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($sm_items)) : ?>
                        <tr><td colspan="6"><?php esc_html_e('No smart-money moves recorded yet -- add a wallet above and wait for the next poll cycle (every ~2 min).', 'coin680-whale-tracker'); ?></td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
                <?php $this->render_pager('sm_page', $sm_page, $sm_total, self::PER_PAGE); ?>
            </div>
            <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php
    }
}


