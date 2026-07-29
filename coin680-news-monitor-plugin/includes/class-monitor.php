<?php
/**
 * Polls CoinDesk and Cointelegraph RSS feeds and stores new headlines as
 * unreviewed candidates. Purely mechanical collection -- no summarizing,
 * no rewriting, no publishing. Writing coin680.com's own article still
 * requires checking at least one more independent source and following
 * the structural-variety rules in Coin680-News-Playbook.md.
 *
 * Also does two deterministic (non-AI) checks after every poll, purely to
 * save review time -- neither one writes or publishes anything:
 *  - cross-source match: flags when CoinDesk and Cointelegraph appear to be
 *    covering the same event, via keyword-overlap between titles.
 *  - duplicate-vs-published: flags when a candidate headline looks like it
 *    covers the same story as something already published in the
 *    "crypto-market-news" category recently.
 * Both are heuristic (Jaccard similarity over significant title words) and
 * can misfire -- always treat a flag as "check this," not as ground truth.
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

    const STOPWORDS = array(
        'a','an','the','of','to','in','on','for','with','and','or','is','are','was','were',
        'this','that','these','those','its','it\'s','has','have','had','after','before','from',
        'by','as','at','be','been','being','will','would','could','should','can','may','might',
        'must','not','no','but','so','than','then','now','new','says','said','amid','amidst',
        'over','under','into','out','up','down','off','about','more','most','less','least',
        'what','when','where','why','how','who','whom','which','their','them','they','he','she',
        'his','her','you','your','we','our','if','while','still','just','also','both','each',
        'other','some','such','only','own','same','too','very','all','here','there','first',
        'latest','amp','vs','per',
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
            cross_match_id BIGINT UNSIGNED NULL DEFAULT NULL,
            duplicate_post_id BIGINT UNSIGNED NULL DEFAULT NULL,
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
        $this->detect_cross_matches();
        $this->detect_duplicates();
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

    /**
     * Breaks a title into significant lowercase words (drops stopwords and
     * anything under 3 chars). Numbers, tickers, and named entities survive
     * as-is since those are exactly what makes two headlines "the same story."
     */
    private static function keywords($title) {
        $title = strtolower($title);
        $title = preg_replace('/[^a-z0-9$%.\s]/', ' ', $title);
        $words = preg_split('/\s+/', trim($title));
        $words = array_filter($words, function ($w) {
            if (strlen($w) < 3) {
                return false;
            }
            if (in_array($w, self::STOPWORDS, true)) {
                return false;
            }
            return true;
        });
        return array_values(array_unique($words));
    }

    /**
     * Jaccard similarity between two keyword sets. Returns [score 0..1, common count].
     */
    private static function similarity($a, $b) {
        if (empty($a) || empty($b)) {
            return array(0.0, 0);
        }
        $common = array_intersect($a, $b);
        $union = array_unique(array_merge($a, $b));
        $score = count($union) > 0 ? count($common) / count($union) : 0.0;
        return array($score, count($common));
    }

    /**
     * Flags pairs of candidates from DIFFERENT sources, within the last
     * $hours, whose titles look like the same event. Bidirectional link via
     * cross_match_id. Skips anything already matched so re-running on every
     * poll stays cheap.
     */
    public function detect_cross_matches($hours = 48, $threshold = 0.30, $min_common = 2) {
        global $wpdb;
        $table = self::table_name();
        $since = gmdate('Y-m-d H:i:s', time() - $hours * HOUR_IN_SECONDS);
        $items = $wpdb->get_results($wpdb->prepare(
            "SELECT id, source, title, cross_match_id FROM $table WHERE pub_date >= %s ORDER BY pub_date DESC",
            $since
        ));
        if (empty($items)) {
            return;
        }

        $kw = array();
        foreach ($items as $item) {
            $kw[$item->id] = self::keywords($item->title);
        }

        foreach ($items as $item) {
            if (!empty($item->cross_match_id)) {
                continue;
            }
            $best_id = null;
            $best_score = 0.0;
            foreach ($items as $other) {
                if ($other->id === $item->id || $other->source === $item->source || !empty($other->cross_match_id)) {
                    continue;
                }
                list($score, $common) = self::similarity($kw[$item->id], $kw[$other->id]);
                if ($score >= $threshold && $common >= $min_common && $score > $best_score) {
                    $best_score = $score;
                    $best_id = $other->id;
                }
            }
            if ($best_id !== null) {
                $wpdb->update($table, array('cross_match_id' => $best_id), array('id' => $item->id));
                $wpdb->update($table, array('cross_match_id' => $item->id), array('id' => $best_id));
            }
        }
    }

    /**
     * Flags candidates whose title looks like it covers the same story as an
     * already-published coin680.com post in the crypto-market-news category
     * within the last $lookback_days. Only touches candidates that don't
     * already have a duplicate_post_id and aren't already marked 'written'.
     */
    public function detect_duplicates($lookback_days = 14, $threshold = 0.30, $min_common = 2) {
        global $wpdb;
        $table = self::table_name();

        $since_posts = gmdate('Y-m-d H:i:s', time() - $lookback_days * DAY_IN_SECONDS);
        $published = $wpdb->get_results($wpdb->prepare(
            "SELECT p.ID, p.post_title FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->term_relationships} tr ON tr.object_id = p.ID
             INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
             INNER JOIN {$wpdb->terms} t ON t.term_id = tt.term_id
             WHERE p.post_type = 'post' AND p.post_status = 'publish' AND p.post_date_gmt >= %s
               AND tt.taxonomy = 'category' AND t.slug = 'crypto-market-news'",
            $since_posts
        ));
        if (empty($published)) {
            return;
        }

        $pub_kw = array();
        foreach ($published as $p) {
            $pub_kw[$p->ID] = self::keywords($p->post_title);
        }

        $candidates = $wpdb->get_results(
            "SELECT id, title FROM $table WHERE duplicate_post_id IS NULL AND status != 'written'"
        );
        foreach ($candidates as $cand) {
            $ckw = self::keywords($cand->title);
            $best_id = null;
            $best_score = 0.0;
            foreach ($pub_kw as $post_id => $pkw) {
                list($score, $common) = self::similarity($ckw, $pkw);
                if ($score >= $threshold && $common >= $min_common && $score > $best_score) {
                    $best_score = $score;
                    $best_id = $post_id;
                }
            }
            if ($best_id !== null) {
                $wpdb->update($table, array('duplicate_post_id' => $best_id), array('id' => $cand->id));
            }
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
