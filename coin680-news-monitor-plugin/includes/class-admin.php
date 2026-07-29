<?php
/**
 * wp-admin screen: a reviewable list of headlines pulled from both source
 * feeds, so checking "what's new" doesn't require live-fetching both sites
 * every time. Writing/publishing coin680.com's own article is still done
 * separately, after independently verifying the story.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Coin680News_Admin {
    private static $instance = null;

    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('admin_menu', array($this, 'add_menu'));
        add_action('admin_post_coin680news_mark', array($this, 'handle_mark'));
        add_action('admin_post_coin680news_poll_now', array($this, 'handle_poll_now'));
    }

    public function add_menu() {
        add_menu_page(
            __('Coin680 News Monitor', 'coin680-news-monitor'),
            __('News Monitor', 'coin680-news-monitor'),
            'manage_options',
            'coin680-news-monitor',
            array($this, 'render_page'),
            'dashicons-rss',
            83
        );
    }

    public function handle_mark() {
        check_admin_referer('coin680news_mark');
        if (!current_user_can('manage_options')) { wp_die('Not allowed.'); }
        $ids = array_map('intval', (array) ($_POST['ids'] ?? array()));
        $status = sanitize_text_field(wp_unslash($_POST['set_status'] ?? 'written'));
        Coin680News_Monitor::mark_status($ids, $status);
        wp_safe_redirect(admin_url('admin.php?page=coin680-news-monitor'));
        exit;
    }

    public function handle_poll_now() {
        check_admin_referer('coin680news_poll_now');
        if (!current_user_can('manage_options')) { wp_die('Not allowed.'); }
        Coin680News_Monitor::instance()->poll();
        wp_safe_redirect(add_query_arg('polled', '1', admin_url('admin.php?page=coin680-news-monitor')));
        exit;
    }

    public function render_page() {
        if (!current_user_can('manage_options')) { return; }
        $items = Coin680News_Monitor::get_recent(48, null, 150);
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Coin680 News Monitor', 'coin680-news-monitor'); ?></h1>
            <p><?php esc_html_e('Live headlines from CoinDesk and Cointelegraph RSS feeds, last 48 hours. This is a candidate list only -- always cross-check at least one more independent source and follow the structural-variety rules before writing a coin680.com article.', 'coin680-news-monitor'); ?></p>

            <?php if (isset($_GET['polled'])) : ?><div class="notice notice-success"><p><?php esc_html_e('Polled both feeds.', 'coin680-news-monitor'); ?></p></div><?php endif; ?>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="coin680news_poll_now">
                <?php wp_nonce_field('coin680news_poll_now'); ?>
                <button type="submit" class="button" style="margin-bottom:12px;"><?php esc_html_e('Poll Feeds Now', 'coin680-news-monitor'); ?></button>
            </form>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="coin680news_mark">
                <input type="hidden" name="set_status" value="written">
                <?php wp_nonce_field('coin680news_mark'); ?>
                <table class="widefat striped">
                    <thead><tr>
                        <th></th>
                        <th><?php esc_html_e('Published', 'coin680-news-monitor'); ?></th>
                        <th><?php esc_html_e('Source', 'coin680-news-monitor'); ?></th>
                        <th><?php esc_html_e('Title', 'coin680-news-monitor'); ?></th>
                        <th><?php esc_html_e('Status', 'coin680-news-monitor'); ?></th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($items as $item) : ?>
                        <tr style="<?php echo $item->status === 'written' ? 'opacity:.5;' : ''; ?>">
                            <td><input type="checkbox" name="ids[]" value="<?php echo esc_attr($item->id); ?>"></td>
                            <td><?php echo esc_html($item->pub_date); ?></td>
                            <td><?php echo esc_html($item->source); ?></td>
                            <td><a href="<?php echo esc_url($item->link); ?>" target="_blank"><?php echo esc_html($item->title); ?></a></td>
                            <td><?php echo esc_html($item->status); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($items)) : ?>
                        <tr><td colspan="5"><?php esc_html_e('No headlines tracked yet in this window.', 'coin680-news-monitor'); ?></td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
                <p><button type="submit" class="button"><?php esc_html_e('Mark Selected as Written', 'coin680-news-monitor'); ?></button></p>
            </form>
        </div>
        <?php
    }
}
