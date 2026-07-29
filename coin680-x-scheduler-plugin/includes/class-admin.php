<?php
/**
 * wp-admin screen: X API credentials + a queue form/list for scheduling posts.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Coin680X_Admin {
    private static $instance = null;

    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('admin_menu', array($this, 'add_menu'));
        add_action('admin_post_coin680x_save_settings', array($this, 'handle_save_settings'));
        add_action('admin_post_coin680x_add_queue', array($this, 'handle_add_queue'));
        add_action('admin_post_coin680x_delete_queue', array($this, 'handle_delete_queue'));
        add_action('admin_post_coin680x_post_now', array($this, 'handle_post_now'));
    }

    public function add_menu() {
        add_menu_page(
            __('Coin680 X Scheduler', 'coin680-x-scheduler'),
            __('X Scheduler', 'coin680-x-scheduler'),
            'manage_options',
            'coin680-x-scheduler',
            array($this, 'render_page'),
            'dashicons-clock',
            81
        );
    }

    public function handle_save_settings() {
        check_admin_referer('coin680x_save_settings');
        if (!current_user_can('manage_options')) { wp_die('Not allowed.'); }
        $settings = array(
            'consumer_key'        => sanitize_text_field(wp_unslash($_POST['consumer_key'] ?? '')),
            'consumer_secret'     => sanitize_text_field(wp_unslash($_POST['consumer_secret'] ?? '')),
            'access_token'        => sanitize_text_field(wp_unslash($_POST['access_token'] ?? '')),
            'access_token_secret' => sanitize_text_field(wp_unslash($_POST['access_token_secret'] ?? '')),
        );
        update_option('coin680x_settings', $settings);
        wp_safe_redirect(add_query_arg('saved', '1', admin_url('admin.php?page=coin680-x-scheduler')));
        exit;
    }

    public function handle_add_queue() {
        check_admin_referer('coin680x_add_queue');
        if (!current_user_can('manage_options')) { wp_die('Not allowed.'); }

        $post_text = sanitize_textarea_field(wp_unslash($_POST['post_text'] ?? ''));
        $media_url = esc_url_raw(wp_unslash($_POST['media_url'] ?? ''));
        $first_comment = sanitize_textarea_field(wp_unslash($_POST['first_comment'] ?? ''));
        $scheduled_local = sanitize_text_field(wp_unslash($_POST['scheduled_at'] ?? '')); // datetime-local, site's own timezone (UTC on this site)

        if ($post_text === '' || $scheduled_local === '') {
            wp_safe_redirect(add_query_arg('error', 'missing_fields', admin_url('admin.php?page=coin680-x-scheduler')));
            exit;
        }

        $scheduled_utc = gmdate('Y-m-d H:i:s', strtotime($scheduled_local . ' UTC'));
        Coin680X_Queue::add($post_text, $media_url, $first_comment, $scheduled_utc);

        wp_safe_redirect(add_query_arg('queued', '1', admin_url('admin.php?page=coin680-x-scheduler')));
        exit;
    }

    public function handle_delete_queue() {
        check_admin_referer('coin680x_delete_queue');
        if (!current_user_can('manage_options')) { wp_die('Not allowed.'); }
        Coin680X_Queue::delete((int) ($_POST['id'] ?? 0));
        wp_safe_redirect(admin_url('admin.php?page=coin680-x-scheduler'));
        exit;
    }

    public function handle_post_now() {
        check_admin_referer('coin680x_post_now');
        if (!current_user_can('manage_options')) { wp_die('Not allowed.'); }
        global $wpdb;
        $id = (int) ($_POST['id'] ?? 0);
        $item = $wpdb->get_row($wpdb->prepare("SELECT * FROM " . Coin680X_Queue::table_name() . " WHERE id = %d", $id));
        if ($item) {
            Coin680X_Queue::instance()->post_item_now($item);
        }
        wp_safe_redirect(admin_url('admin.php?page=coin680-x-scheduler'));
        exit;
    }

    public function render_page() {
        if (!current_user_can('manage_options')) { return; }
        $settings = get_option('coin680x_settings', array());
        $items = Coin680X_Queue::get_recent(50);
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Coin680 X Scheduler', 'coin680-x-scheduler'); ?></h1>
            <p><?php esc_html_e('Schedules posts directly to X via the X API -- no third-party service, no monthly post cap. A background check runs every 5 minutes and posts anything due.', 'coin680-x-scheduler'); ?></p>

            <?php if (isset($_GET['queued'])) : ?><div class="notice notice-success"><p><?php esc_html_e('Post queued.', 'coin680-x-scheduler'); ?></p></div><?php endif; ?>
            <?php if (isset($_GET['saved'])) : ?><div class="notice notice-success"><p><?php esc_html_e('Credentials saved.', 'coin680-x-scheduler'); ?></p></div><?php endif; ?>
            <?php if (isset($_GET['error'])) : ?><div class="notice notice-error"><p><?php esc_html_e('Post text and schedule time are required.', 'coin680-x-scheduler'); ?></p></div><?php endif; ?>

            <div class="card" style="max-width:700px;margin-top:16px;">
                <h2><?php esc_html_e('X API Credentials', 'coin680-x-scheduler'); ?></h2>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="coin680x_save_settings">
                    <?php wp_nonce_field('coin680x_save_settings'); ?>
                    <table class="form-table">
                        <tr><th><?php esc_html_e('Consumer Key', 'coin680-x-scheduler'); ?></th><td><input type="text" name="consumer_key" style="width:100%;" value="<?php echo esc_attr($settings['consumer_key'] ?? ''); ?>"></td></tr>
                        <tr><th><?php esc_html_e('Consumer Secret', 'coin680-x-scheduler'); ?></th><td><input type="text" name="consumer_secret" style="width:100%;" value="<?php echo esc_attr($settings['consumer_secret'] ?? ''); ?>"></td></tr>
                        <tr><th><?php esc_html_e('Access Token', 'coin680-x-scheduler'); ?></th><td><input type="text" name="access_token" style="width:100%;" value="<?php echo esc_attr($settings['access_token'] ?? ''); ?>"></td></tr>
                        <tr><th><?php esc_html_e('Access Token Secret', 'coin680-x-scheduler'); ?></th><td><input type="text" name="access_token_secret" style="width:100%;" value="<?php echo esc_attr($settings['access_token_secret'] ?? ''); ?>"></td></tr>
                    </table>
                    <p><button type="submit" class="button button-primary"><?php esc_html_e('Save Credentials', 'coin680-x-scheduler'); ?></button></p>
                </form>
            </div>

            <div class="card" style="max-width:700px;margin-top:16px;">
                <h2><?php esc_html_e('Queue a New Post', 'coin680-x-scheduler'); ?></h2>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="coin680x_add_queue">
                    <?php wp_nonce_field('coin680x_add_queue'); ?>
                    <table class="form-table">
                        <tr><th><?php esc_html_e('Post Text', 'coin680-x-scheduler'); ?></th><td><textarea name="post_text" rows="4" style="width:100%;" required></textarea></td></tr>
                        <tr><th><?php esc_html_e('Image URL (optional)', 'coin680-x-scheduler'); ?></th><td><input type="url" name="media_url" style="width:100%;" placeholder="https://coin680.com/wp-content/uploads/..."></td></tr>
                        <tr><th><?php esc_html_e('First Comment (optional)', 'coin680-x-scheduler'); ?></th><td><textarea name="first_comment" rows="3" style="width:100%;" placeholder="Link + call to action"></textarea></td></tr>
                        <tr><th><?php esc_html_e('Schedule (site time, UTC)', 'coin680-x-scheduler'); ?></th><td><input type="datetime-local" name="scheduled_at" required></td></tr>
                    </table>
                    <p><button type="submit" class="button button-primary"><?php esc_html_e('Queue Post', 'coin680-x-scheduler'); ?></button></p>
                </form>
            </div>

            <div class="card" style="max-width:900px;margin-top:16px;">
                <h2><?php esc_html_e('Queue', 'coin680-x-scheduler'); ?></h2>
                <table class="widefat striped">
                    <thead><tr>
                        <th><?php esc_html_e('Scheduled (UTC)', 'coin680-x-scheduler'); ?></th>
                        <th><?php esc_html_e('Text', 'coin680-x-scheduler'); ?></th>
                        <th><?php esc_html_e('Status', 'coin680-x-scheduler'); ?></th>
                        <th><?php esc_html_e('Actions', 'coin680-x-scheduler'); ?></th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($items as $item) : ?>
                        <tr>
                            <td><?php echo esc_html($item->scheduled_at); ?></td>
                            <td><?php echo esc_html(wp_trim_words($item->post_text, 12)); ?></td>
                            <td>
                                <?php if ($item->status === 'posted') : ?>
                                    <span style="color:#1a9c4a;font-weight:600;">✓ <?php esc_html_e('Posted', 'coin680-x-scheduler'); ?></span>
                                    <?php if ($item->tweet_id) : ?><br><a href="https://twitter.com/coin680/status/<?php echo esc_attr($item->tweet_id); ?>" target="_blank"><?php esc_html_e('View', 'coin680-x-scheduler'); ?></a><?php endif; ?>
                                <?php elseif ($item->status === 'failed') : ?>
                                    <span style="color:#c11510;font-weight:600;">✕ <?php esc_html_e('Failed', 'coin680-x-scheduler'); ?></span>
                                    <br><small><?php echo esc_html(wp_trim_words($item->error_message, 15)); ?></small>
                                <?php else : ?>
                                    <span style="color:#b8860b;">⏳ <?php esc_html_e('Pending', 'coin680-x-scheduler'); ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($item->status === 'pending') : ?>
                                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline;">
                                    <input type="hidden" name="action" value="coin680x_post_now">
                                    <input type="hidden" name="id" value="<?php echo esc_attr($item->id); ?>">
                                    <?php wp_nonce_field('coin680x_post_now'); ?>
                                    <button type="submit" class="button button-small"><?php esc_html_e('Post Now', 'coin680-x-scheduler'); ?></button>
                                </form>
                                <?php endif; ?>
                                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline;">
                                    <input type="hidden" name="action" value="coin680x_delete_queue">
                                    <input type="hidden" name="id" value="<?php echo esc_attr($item->id); ?>">
                                    <?php wp_nonce_field('coin680x_delete_queue'); ?>
                                    <button type="submit" class="button button-small"><?php esc_html_e('Delete', 'coin680-x-scheduler'); ?></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($items)) : ?>
                        <tr><td colspan="4"><?php esc_html_e('No posts queued yet.', 'coin680-x-scheduler'); ?></td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }
}
