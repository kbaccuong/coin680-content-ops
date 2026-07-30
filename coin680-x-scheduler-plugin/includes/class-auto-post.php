<?php
/**
 * Auto-queues an X post whenever a post in a configured category is
 * published (whether published immediately, or via WP-Cron auto-publishing
 * a scheduled post) -- no manual tweet composition needed. Added 2026-07-30
 * per direct request: "publish bài trên web là tự động có bài X tương ứng,
 * không cần tôi soạn tay".
 *
 * Scope: only fires for the configured category (default: Crypto Market
 * News, category ID 2) -- NOT Bitcoin Academy, since that publishes ~9x/day
 * and auto-tweeting every single one would flood the X account. Change
 * `auto_tweet_category_id` in Settings (or set it to 0) to adjust/disable.
 *
 * Tweet text is built purely from what the post itself already has (title,
 * excerpt, permalink) -- no AI call, no external composition step, so it
 * works the same whether the post was written by Claude or anyone else.
 * Uses the post's Excerpt field if set (falls back to a trimmed plain-text
 * version of the content) to leave room for the link and hashtags within
 * X's 280-character limit.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Coin680X_AutoPost {
    private static $instance = null;

    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // publish_post fires exactly once, the first time a post transitions
        // TO 'publish' (covers both an immediate manual publish and a
        // scheduled post being auto-published by wp_publish_scheduled_posts
        // at cron time) -- it does NOT refire on later edits to an already-
        // published post, so this can't double-tweet the same article.
        add_action('publish_post', array($this, 'maybe_queue_tweet'), 10, 2);
    }

    /**
     * mb_strlen()/mb_substr() require the mbstring PHP extension -- not
     * guaranteed to be enabled on every hosting config, and calling an
     * undefined function is a FATAL error (this is what tripped WordPress
     * into Recovery Mode the first time this class ran). Fall back to the
     * plain byte-based versions if mbstring isn't available; slightly less
     * accurate for non-ASCII text but never fatal.
     */
    private function str_len($s) {
        return function_exists('mb_strlen') ? mb_strlen($s) : strlen($s);
    }

    private function str_trim($s, $len) {
        return function_exists('mb_substr') ? mb_substr($s, 0, $len) : substr($s, 0, $len);
    }

    private function target_category_id() {
        $settings = get_option('coin680x_settings', array());
        // Default: Crypto Market News (category ID 2 on coin680.com).
        return isset($settings['auto_tweet_category_id']) ? (int) $settings['auto_tweet_category_id'] : 2;
    }

    public function maybe_queue_tweet($post_id, $post) {
        $category_id = $this->target_category_id();
        if (!$category_id) {
            return; // disabled
        }
        if (!has_category($category_id, $post)) {
            return;
        }
        if (!class_exists('Coin680X_Queue')) {
            return;
        }

        $title = html_entity_decode(get_the_title($post), ENT_QUOTES);
        $link = get_permalink($post);

        $summary = get_the_excerpt($post);
        if (!$summary) {
            $summary = wp_trim_words(wp_strip_all_tags($post->post_content), 30, '...');
        }
        $summary = html_entity_decode($summary, ENT_QUOTES);

        $hashtags = '#Crypto #News';

        // Fit within 280 chars: title + link + hashtags are fixed cost,
        // the summary is trimmed to whatever room is left. Link always
        // counts as ~23 chars on X regardless of actual length (t.co
        // shortening), so budget it at a flat 24 to be safe.
        $fixed_cost = $this->str_len($title) + 24 + $this->str_len($hashtags) + 6; // +6 for spacing/newlines
        $summary_budget = max(0, 280 - $fixed_cost);
        if ($summary_budget < 20) {
            $summary = ''; // no room left -- title + link + hashtags alone
        } elseif ($this->str_len($summary) > $summary_budget) {
            $summary = $this->str_trim($summary, $summary_budget - 1) . '...';
        }

        $text = $title;
        if ($summary) {
            $text .= "\n\n{$summary}";
        }
        $text .= "\n\n{$link}\n\n{$hashtags}";

        Coin680X_Queue::add($text, '', '', current_time('mysql', true));
    }
}
