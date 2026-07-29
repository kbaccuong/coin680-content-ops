<?php
/**
 * Polls CoinDesk and Cointelegraph RSS feeds and stores new headlines as
 * unreviewed candidates. Purely mechanical collection -- no summarizing,
 * no rewriting, no publishing. Writing coin680.com's own article still
 * requires checking at least one more independent source and following
 * the structural-variety rules in Coin680-News-Playbook.md.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Coin680News_Monitor {
    private static $instance = null;

    const FEEDS = array(
        'coindesk'      => 'https://www.coindesk.com/arc/outboundfeeds/rss/',
        'cointelegraph' => 'https://cointelegraph.com/rss',
    );

    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('coin680news_poll', array($this, 'poll'));
    }

    public static function table_name() {
        global $wpdb;
        return $wpdb->prefix . 'coin680_news_candidates';
    }

    public static function create_table() {
        global $wpdb;
        $table = self::table_name();
        $charset_collate = $wpdb->get_charset_collate();
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta("CREATE TABLE $table (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            source VARCHAR(30) NOT NULL DEFAULT '',
            title TEXT NOT NULL,
            link VARCHAR(500) NOT NULL DEFAULT '',
            summary TEXT NOT NULL DEFAULT '',
            pub_date DATETIME NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'new',
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY link (link(255)),
            KEY pub_date (pub_date),
            KEY status (status)
        ) $charset_collate;");
    }

    public function poll() {
        foreach (self::FEEDS as $source => $url) {
            $this->poll_feed($source, $url);
        }
    }

    private function poll_feed($source, $url) {
        $response = wp_remote_get($url, array(
            'timeout' => 20,
            'headers' => array('User-Agent' => 'Coin680NewsMonitor/1.0 (+https://coin680.com/)'),
        ));
        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return;
        }

        $body = wp_remote_retrieve_body($response);
        $xml = @simplexml_load_string($body, 'SimpleXMLElement', LIBXML_NOCDATA);
        if (!$xml || empty($xml->channel->item)) {
            return;
        }

        global $wpdb;
        $table = self::table_name();

        foreach ($xml->channel->item as $item) {
            $title = trim((string) $item->title);
            $link = trim((string) $item->link);
            if (!$title || !$link) {
                continue;
            }
            $summary = wp_strip_all_tags((string) $item->description);
            $pub_date = strtotime((string) $item->pubDate);

            $wpdb->query($wpdb->prepare(
                "INSERT IGNORE INTO $table (source, title, link, summary, pub_date, status, created_at)
                 VALUES (%s, %s, %s, %s, %s, 'new', %s)",
                $source,
                $title,
                $link,
                wp_trim_words($summary, 40, '…'),
                $pub_date ? gmdate('Y-m-d H:i:s', $pub_date) : current_time('mysql', true),
                current_time('mysql', true)
            ));
        }
    }

    public static function get_recent($hours = 48, $status = null, $limit = 100) {
        global $wpdb;
        $table = self::table_name();
        $since = gmdate('Y-m-d H:i:s', time() - $hours * HOUR_IN_SECONDS);
        if ($status) {
            return $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $table WHERE pub_date >= %s AND status = %s ORDER BY pub_date DESC LIMIT %d",
                $since, $status, $limit
            ));
        }
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE pub_date >= %s ORDER BY pub_date DESC LIMIT %d",
            $since, $limit
        ));
    }

    public static function mark_status($ids, $status) {
        global $wpdb;
        if (empty($ids)) {
            return;
        }
        $table = self::table_name();
        $placeholders = implode(',', array_fill(0, count($ids), '%d'));
        $wpdb->query($wpdb->prepare("UPDATE $table SET status = %s WHERE id IN ($placeholders)", array_merge(array($status), $ids)));
    }
}
