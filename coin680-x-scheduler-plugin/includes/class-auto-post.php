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
 * Tweet text: if the post has a `_coin680x_custom_tweet` meta value set,
 * that full text is used as-is (title + expanded summary + own analytical
 * perspective, written per-article -- see the coin680-x-tweet-template
 * approved 2026-08-08). Otherwise falls back to the original mechanical
 * composition (title + excerpt/trimmed content + hashtags, no analysis
 * step), kept for posts that don't have a custom tweet prepared.
 *
 * Does NOT squeeze the summary down to fit X's classic 280-character
 * limit -- the @coin680 account is on X Premium, which supports long-form
 * posts well beyond 280 chars via the same API call (no special payload
 * needed), so a curated, complete summary is used as-is rather than being
 * cut off mid-sentence with "..." (changed 2026-07-31 after that exact
 * truncation showed up in a live tweet and was flagged: "tôi yêu cầu là 1
 * bài tóm tắt hoàn chỉnh mà"). Only a generous sanity cap
 * ($max_summary_len) still applies to the fallback path, to stop a
 * runaway/malformed excerpt from producing an absurdly long post.
 *
 * Deliberately DOES NOT include the article link in the tweet text
 * (changed 2026-07-31, per direct cost request). X's pay-per-use API
 * pricing charges $0.20 per post that contains a URL vs. $0.015 for a
 * plain post -- confirmed directly from the account's own X Developer
 * Console usage breakdown ("ContentCreateWithUrl" events dominating the
 * bill). Summary + Featured Image only is treated as good enough for a
 * teaser post; readers who want the full article can find it via the
 * profile/site rather than a direct link in every single post. If a link
 * is ever wanted back, re-add it in $text below.
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

        // Registers _coin680x_custom_tweet so it's settable via the REST
        // API (POST /wp/v2/posts/{id} with {"meta":{"_coin680x_custom_tweet":"..."}}),
        // letting a pre-written, per-article analytical tweet be attached
        // to a post before it publishes.
        add_action('init', array($this, 'register_custom_tweet_meta'));
    }

    public function register_custom_tweet_meta() {
        register_post_meta('post', '_coin680x_custom_tweet', array(
            'show_in_rest'  => true,
            'single'        => true,
            'type'          => 'string',
            'auth_callback' => function () {
                return current_user_can('edit_posts');
            },
        ));
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

        $custom_text = get_post_meta($post_id, '_coin680x_custom_tweet', true);

        if (!empty($custom_text)) {
            $text = html_entity_decode($custom_text, ENT_QUOTES);
        } else {
            $title = html_entity_decode(get_the_title($post), ENT_QUOTES);

            $summary = get_the_excerpt($post);
            if (!$summary) {
                $summary = wp_trim_words(wp_strip_all_tags($post->post_content), 30, '...');
            }
            $summary = html_entity_decode($summary, ENT_QUOTES);

            $hashtags = '#Crypto #News';

            // Sanity cap only -- X Premium (@coin680's plan) supports long-form
            // posts, so we don't need to squeeze into the classic 280-char
            // limit. This just stops a malformed/oversized excerpt from
            // producing an absurdly long post.
            $max_summary_len = 600;
            if ($this->str_len($summary) > $max_summary_len) {
                $summary = $this->str_trim($summary, $max_summary_len - 1) . '...';
            }

            $text = $title;
            if ($summary) {
                $text .= "\n\n{$summary}";
            }
            $text .= "\n\n{$hashtags}";
        }

        // Use the post's own featured image if one is set -- falls back to
        // no image (empty string) rather than guessing/fetching anything
        // else. Coin680X_Queue::upload_media() re-downloads whatever URL is
        // given and uploads it to X, so any public image URL works here.
        $media_url = get_the_post_thumbnail_url($post, 'large');
        if (!$media_url) {
            $media_url = '';
        }

        Coin680X_Queue::add($text, $media_url, '', current_time('mysql', true));
    }
}
