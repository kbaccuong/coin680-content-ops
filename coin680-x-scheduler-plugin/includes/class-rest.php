<?php
/**
 * Small admin-only REST API surface for the X Scheduler queue -- lets an
 * authenticated admin (WP Application Password) inspect the queue, trigger
 * an immediate post, or delete an already-published live tweet, all over
 * REST, without needing direct database/SSH access (both are unavailable
 * on this host). Added 2026-07-31 to fix/replace 3 tweets that went out
 * truncated mid-sentence before a summary-length bug was caught (see
 * Coin680X_AutoPost's docblock for that bug).
 *
 * Registered unconditionally (not inside Coin680X_Admin, which only loads
 * when is_admin() is true) because REST requests to wp-json don't run in
 * an admin context, so rest_api_init needs to fire on every request.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Coin680X_Rest {
    private static $instance = null;

    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('rest_api_init', array($this, 'register_routes'));
    }

    public function permission() {
        return current_user_can('manage_options');
    }

    public function register_routes() {
        register_rest_route('coin680x/v1', '/queue', array(
            'methods'             => 'GET',
            'callback'            => array($this, 'get_queue'),
            'permission_callback' => array($this, 'permission'),
        ));
        register_rest_route('coin680x/v1', '/post-now', array(
            'methods'             => 'POST',
            'callback'            => array($this, 'post_now'),
            'permission_callback' => array($this, 'permission'),
        ));
        register_rest_route('coin680x/v1', '/delete-tweet', array(
            'methods'             => 'POST',
            'callback'            => array($this, 'delete_tweet'),
            'permission_callback' => array($this, 'permission'),
        ));
    }

    public function get_queue($request) {
        $limit = (int) $request->get_param('limit');
        $items = Coin680X_Queue::get_recent($limit ?: 20);
        $out = array();
        foreach ($items as $item) {
            $out[] = array(
                'id'            => (int) $item->id,
                'scheduled_at'  => $item->scheduled_at,
                'status'        => $item->status,
                'tweet_id'      => $item->tweet_id,
                'text_preview'  => substr($item->post_text, 0, 160),
                'error_message' => $item->error_message,
            );
        }
        return rest_ensure_response($out);
    }

    public function post_now($request) {
        $text = sanitize_textarea_field($request->get_param('text'));
        $media_url = esc_url_raw((string) $request->get_param('media_url'));
        if (!$text) {
            return new WP_Error('missing_text', 'text is required', array('status' => 400));
        }
        global $wpdb;
        $id = Coin680X_Queue::add($text, $media_url, '', current_time('mysql', true));
        $item = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . Coin680X_Queue::table_name() . ' WHERE id = %d', $id));
        $result = Coin680X_Queue::instance()->post_item_now($item);
        if (is_wp_error($result)) {
            return new WP_Error('post_failed', $result->get_error_message(), array('status' => 500));
        }
        return rest_ensure_response(array('queue_id' => $id, 'tweet_id' => $result));
    }

    public function delete_tweet($request) {
        $tweet_id = sanitize_text_field((string) $request->get_param('tweet_id'));
        if (!$tweet_id) {
            return new WP_Error('missing_tweet_id', 'tweet_id is required', array('status' => 400));
        }
        $result = Coin680X_Queue::instance()->delete_tweet($tweet_id);
        if (is_wp_error($result)) {
            return new WP_Error('delete_failed', $result->get_error_message(), array('status' => 500));
        }
        return rest_ensure_response(array('deleted' => true, 'tweet_id' => $tweet_id));
    }
}
