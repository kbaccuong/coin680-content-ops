<?php
/**
 * Scheduled-post queue: custom table + WP-Cron processor that posts to X
 * at (or shortly after) the requested time, directly via X's own API --
 * no third-party service, no monthly post cap.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Coin680X_Queue {
    private static $instance = null;

    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('coin680x_process_queue', array($this, 'process_due_items'));
    }

    public static function add_cron_interval($schedules) {
        $schedules['coin680x_five_minutes'] = array(
            'interval' => 300,
            'display'  => __('Every 5 Minutes (Coin680 X Scheduler)', 'coin680-x-scheduler'),
        );
        return $schedules;
    }

    public static function table_name() {
        global $wpdb;
        return $wpdb->prefix . 'coin680_x_queue';
    }

    public static function create_table() {
        global $wpdb;
        $table = self::table_name();
        $charset_collate = $wpdb->get_charset_collate();
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta("CREATE TABLE $table (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            post_text TEXT NOT NULL,
            media_url VARCHAR(500) NOT NULL DEFAULT '',
            first_comment TEXT NOT NULL DEFAULT '',
            scheduled_at DATETIME NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            tweet_id VARCHAR(50) NOT NULL DEFAULT '',
            error_message TEXT NOT NULL DEFAULT '',
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY status_scheduled (status, scheduled_at)
        ) $charset_collate;");

        if (!wp_next_scheduled('coin680x_process_queue')) {
            wp_schedule_event(time(), 'coin680x_five_minutes', 'coin680x_process_queue');
        }
    }

    public static function add($post_text, $media_url, $first_comment, $scheduled_at_utc) {
        global $wpdb;
        $wpdb->insert(self::table_name(), array(
            'post_text'     => $post_text,
            'media_url'     => $media_url,
            'first_comment' => $first_comment,
            'scheduled_at'  => $scheduled_at_utc,
            'status'        => 'pending',
            'created_at'    => current_time('mysql', true),
        ));
        return $wpdb->insert_id;
    }

    public static function get_recent($limit = 50) {
        global $wpdb;
        $table = self::table_name();
        return $wpdb->get_results("SELECT * FROM $table ORDER BY scheduled_at DESC LIMIT " . (int) $limit);
    }

    public static function delete($id) {
        global $wpdb;
        $wpdb->delete(self::table_name(), array('id' => (int) $id));
    }

    private function get_oauth() {
        $settings = get_option('coin680x_settings', array());
        return new Coin680X_OAuth(
            $settings['consumer_key'] ?? '',
            $settings['consumer_secret'] ?? '',
            $settings['access_token'] ?? '',
            $settings['access_token_secret'] ?? ''
        );
    }

    private function upload_media($media_url) {
        $image_response = wp_remote_get($media_url, array('timeout' => 20));
        if (is_wp_error($image_response) || wp_remote_retrieve_response_code($image_response) !== 200) {
            return new WP_Error('media_fetch_failed', 'Could not fetch media_url: ' . $media_url);
        }
        $image_bytes = wp_remote_retrieve_body($image_response);

        $url = 'https://upload.twitter.com/1.1/media/upload.json';
        $oauth = $this->get_oauth();
        $auth_header = $oauth->auth_header('POST', $url);

        $boundary = wp_generate_password(24, false);
        $filename = basename(parse_url($media_url, PHP_URL_PATH));
        if (!$filename) {
            $filename = 'image.png';
        }

        $body = "--$boundary\r\n";
        $body .= "Content-Disposition: form-data; name=\"media\"; filename=\"$filename\"\r\n";
        $body .= "Content-Type: image/png\r\n\r\n";
        $body .= $image_bytes . "\r\n";
        $body .= "--$boundary--\r\n";

        $response = wp_remote_post($url, array(
            'timeout' => 30,
            'headers' => array(
                'Authorization' => $auth_header,
                'Content-Type'  => "multipart/form-data; boundary=$boundary",
            ),
            'body' => $body,
        ));

        if (is_wp_error($response)) {
            return $response;
        }
        $body_json = json_decode(wp_remote_retrieve_body($response), true);
        if (empty($body_json['media_id_string'])) {
            return new WP_Error('media_upload_failed', wp_remote_retrieve_body($response));
        }
        return $body_json['media_id_string'];
    }

    private function post_tweet($text, $media_id = null, $reply_to_id = null) {
        $url = 'https://api.twitter.com/2/tweets';
        $oauth = $this->get_oauth();
        $auth_header = $oauth->auth_header('POST', $url);

        $payload = array('text' => $text);
        if ($media_id) {
            $payload['media'] = array('media_ids' => array($media_id));
        }
        if ($reply_to_id) {
            $payload['reply'] = array('in_reply_to_tweet_id' => $reply_to_id);
        }

        $response = wp_remote_post($url, array(
            'timeout' => 20,
            'headers' => array(
                'Authorization' => $auth_header,
                'Content-Type'  => 'application/json',
            ),
            'body' => wp_json_encode($payload),
        ));

        if (is_wp_error($response)) {
            return $response;
        }
        $body_json = json_decode(wp_remote_retrieve_body($response), true);
        if (empty($body_json['data']['id'])) {
            return new WP_Error('tweet_failed', wp_remote_retrieve_body($response));
        }
        return $body_json['data']['id'];
    }

    public function post_item_now($item) {
        global $wpdb;
        $table = self::table_name();

        $media_id = null;
        if (!empty($item->media_url)) {
            $media_id = $this->upload_media($item->media_url);
            if (is_wp_error($media_id)) {
                $wpdb->update($table, array('status' => 'failed', 'error_message' => $media_id->get_error_message()), array('id' => $item->id));
                return $media_id;
            }
        }

        $tweet_id = $this->post_tweet($item->post_text, $media_id);
        if (is_wp_error($tweet_id)) {
            $wpdb->update($table, array('status' => 'failed', 'error_message' => $tweet_id->get_error_message()), array('id' => $item->id));
            return $tweet_id;
        }

        if (!empty($item->first_comment)) {
            $this->post_tweet($item->first_comment, null, $tweet_id);
            // A failure here doesn't roll back the main post; it's logged but the item is still "posted".
        }

        $wpdb->update($table, array('status' => 'posted', 'tweet_id' => $tweet_id, 'error_message' => ''), array('id' => $item->id));
        return $tweet_id;
    }

    public function process_due_items() {
        global $wpdb;
        $table = self::table_name();
        $now = current_time('mysql', true);
        $due = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE status = 'pending' AND scheduled_at <= %s ORDER BY scheduled_at ASC LIMIT 10",
            $now
        ));
        foreach ($due as $item) {
            $this->post_item_now($item);
        }
    }
}
