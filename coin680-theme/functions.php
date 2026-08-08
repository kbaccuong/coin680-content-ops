<?php
/**
 * Coin680 theme functions.
 */

if (!defined('ABSPATH')) {
    exit;
}

define('COIN680_VERSION', '1.4.4');

/* ==========================================================================
   Theme setup
   ========================================================================== */

function coin680_setup() {
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('html5', array('search-form', 'comment-form', 'comment-list', 'gallery', 'caption'));
    add_theme_support('custom-logo', array(
        'height'      => 60,
        'width'       => 200,
        'flex-height' => true,
        'flex-width'  => true,
    ));
    add_theme_support('automatic-feed-links');

    register_nav_menus(array(
        'primary' => __('Primary Menu', 'coin680'),
        'footer'  => __('Footer Menu', 'coin680'),
    ));
}
add_action('after_setup_theme', 'coin680_setup');

function coin680_widgets_init() {
    register_sidebar(array(
        'name'          => __('News Sidebar', 'coin680'),
        'id'            => 'news-sidebar',
        'description'   => __('Shows on homepage sidebar and below single article sidebar.', 'coin680'),
        'before_widget' => '<div class="c680-widget">',
        'after_widget'  => '</div>',
        'before_title'  => '<h3 class="c680-widget-title">',
        'after_title'   => '</h3>',
    ));
}
add_action('widgets_init', 'coin680_widgets_init');

function coin680_scripts() {
    wp_enqueue_style('coin680-google-fonts', 'https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700;900&display=swap', array(), null);
    wp_enqueue_style('coin680-style', get_stylesheet_uri(), array(), COIN680_VERSION);
}
add_action('wp_enqueue_scripts', 'coin680_scripts');

/* ==========================================================================
   Auto-create the Coin680 category tree on theme activation
   (see Coin680-Site-Structure-and-Setup.md section 2 -- keeps code and docs
   in sync instead of requiring 20 categories to be created by hand)
   ========================================================================== */

function coin680_create_categories() {
    $structure = array(
        'Crypto Market News' => array('Bitcoin News', 'Market & Analysis', 'Business & Institutions', 'Regulation & Policy'),
        'Bitcoin Academy'    => array('Fundamentals', 'How It Works', 'History & Cycles', 'Wallets & Security', 'Buying & Trading', 'Market Analysis', 'Economics & Macro', 'Regulation & Tax', 'Risk & Psychology', 'Broader Crypto Market'),
        'Exchange Comparison' => array(),
        'Exchange Reviews'   => array('Binance', 'Bybit', 'OKX', 'BingX', 'Gate', 'MEXC'),
    );

    foreach ($structure as $parent_name => $children) {
        $parent_term = term_exists($parent_name, 'category');
        if (!$parent_term) {
            $parent_term = wp_insert_term($parent_name, 'category');
        }
        if (is_wp_error($parent_term)) {
            continue;
        }
        $parent_id = is_array($parent_term) ? $parent_term['term_id'] : $parent_term;

        foreach ($children as $child_name) {
            $child_exists = term_exists($child_name, 'category', $parent_id);
            if (!$child_exists) {
                wp_insert_term($child_name, 'category', array('parent' => $parent_id));
            }
        }
    }
}
add_action('after_switch_theme', 'coin680_create_categories');

/* ==========================================================================
   Crypto price ticker -- CoinGecko public API, no plugin, no API key, free.
   Cached in a transient so the site never calls CoinGecko more than once
   per 60 seconds regardless of traffic (keeps well inside the free rate
   limit and keeps pages fast).
   ========================================================================== */

function coin680_get_crypto_prices($count = 15) {
    $count = max(1, min(250, (int) $count)); // CoinGecko's markets endpoint caps per_page at 250
    $transient_key = 'coin680_crypto_prices_' . $count;
    $cached = get_transient($transient_key);
    if ($cached !== false) {
        return $cached;
    }

    $response = wp_remote_get(
        "https://api.coingecko.com/api/v3/coins/markets?vs_currency=usd&order=market_cap_desc&per_page={$count}&page=1&sparkline=false&price_change_percentage=24h",
        array('timeout' => 8)
    );

    if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
        return false;
    }

    $data = json_decode(wp_remote_retrieve_body($response), true);
    if (empty($data) || !is_array($data)) {
        return false;
    }

    set_transient($transient_key, $data, 60);
    return $data;
}

function coin680_price_ticker() {
    $coins = coin680_get_crypto_prices();
    if (!$coins) {
        return; // fail silently -- never break the page if CoinGecko is unreachable
    }
    ?>
    <div class="c680-ticker-scroll">
        <div class="c680-ticker-track">
            <?php
            // Render the coin list twice back-to-back so the CSS scroll animation can loop seamlessly.
            for ($pass = 0; $pass < 2; $pass++) :
                foreach ($coins as $coin) :
                    if (empty($coin['current_price'])) {
                        continue;
                    }
                    $price = (float) $coin['current_price'];
                    $change = isset($coin['price_change_percentage_24h']) ? (float) $coin['price_change_percentage_24h'] : 0.0;
                    $direction = $change >= 0 ? 'up' : 'down';
                    $symbol = strtoupper($coin['symbol']);
                    $decimals = $price < 1 ? 4 : ($price < 10 ? 3 : 0);
                    ?>
                    <span class="c680-ticker-item">
                        <strong><?php echo esc_html($symbol); ?></strong>
                        $<?php echo esc_html(number_format($price, $decimals)); ?>
                        <span class="c680-ticker-change c680-ticker-<?php echo esc_attr($direction); ?>">
                            <?php echo $change >= 0 ? '&#9650;' : '&#9660;'; ?> <?php echo esc_html(number_format(abs($change), 2)); ?>%
                        </span>
                    </span>
                <?php endforeach;
            endfor;
            ?>
        </div>
    </div>
    <?php
}

/* ==========================================================================
   Fear & Greed Index -- alternative.me public API, free, no key needed.
   Updates once a day upstream, so a 30-minute transient is plenty (avoids
   hammering a third-party free API while still feeling "live enough").
   ========================================================================== */

function coin680_get_fear_greed_index() {
    $transient_key = 'coin680_fear_greed';
    $cached = get_transient($transient_key);
    if ($cached !== false) {
        return $cached;
    }

    $response = wp_remote_get('https://api.alternative.me/fng/?limit=2&format=json', array('timeout' => 8));
    if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
        return false;
    }

    $data = json_decode(wp_remote_retrieve_body($response), true);
    if (empty($data['data'][0]['value'])) {
        return false;
    }

    $result = array(
        'value'          => (int) $data['data'][0]['value'],
        'classification' => $data['data'][0]['value_classification'],
        'yesterday'      => isset($data['data'][1]['value']) ? (int) $data['data'][1]['value'] : null,
    );

    set_transient($transient_key, $result, 30 * MINUTE_IN_SECONDS);
    return $result;
}

function coin680_fear_greed_widget() {
    $fg = coin680_get_fear_greed_index();
    if (!$fg) {
        return;
    }
    $value = $fg['value'];
    $slug_map = array(
        'Extreme Fear' => 'extreme-fear',
        'Fear'         => 'fear',
        'Neutral'      => 'neutral',
        'Greed'        => 'greed',
        'Extreme Greed' => 'extreme-greed',
    );
    $class_slug = $slug_map[$fg['classification']] ?? 'neutral';
    $trend = '';
    if ($fg['yesterday'] !== null && $fg['yesterday'] !== $value) {
        $trend = $value > $fg['yesterday']
            ? sprintf(__('↑ from %d yesterday', 'coin680'), $fg['yesterday'])
            : sprintf(__('↓ from %d yesterday', 'coin680'), $fg['yesterday']);
    }
    ?>
    <section class="c680-fng-section">
        <div class="c680-fng-card">
            <div class="c680-fng-info">
                <h2 class="c680-fng-title"><?php esc_html_e('Crypto Fear & Greed Index', 'coin680'); ?></h2>
                <p class="c680-fng-desc"><?php esc_html_e('A daily read on market sentiment -- how fearful or greedy crypto traders are right now.', 'coin680'); ?></p>
                <?php if ($trend) : ?>
                    <p class="c680-fng-trend"><?php echo esc_html($trend); ?></p>
                <?php endif; ?>
            </div>
            <div class="c680-fng-gauge">
                <div class="c680-fng-bar-wrap">
                    <div class="c680-fng-bar">
                        <div class="c680-fng-marker" style="left: <?php echo esc_attr($value); ?>%;"></div>
                    </div>
                    <div class="c680-fng-scale">
                        <span><?php esc_html_e('Extreme Fear', 'coin680'); ?></span>
                        <span><?php esc_html_e('Extreme Greed', 'coin680'); ?></span>
                    </div>
                </div>
                <div class="c680-fng-readout c680-fng-<?php echo esc_attr($class_slug); ?>">
                    <span class="c680-fng-value"><?php echo esc_html($value); ?></span>
                    <span class="c680-fng-label"><?php echo esc_html($fg['classification']); ?></span>
                </div>
            </div>
        </div>
    </section>
    <?php
}

/* ==========================================================================
   Bitcoin Halving Countdown -- block height from mempool.space's free public
   API (no key needed). Halving math is generic (every 210,000 blocks, reward
   halves each epoch) so it keeps working correctly at any future halving,
   not just the current one.
   ========================================================================== */

function coin680_get_btc_block_height() {
    $transient_key = 'coin680_btc_block_height';
    $cached = get_transient($transient_key);
    if ($cached !== false) {
        return $cached;
    }

    $response = wp_remote_get('https://mempool.space/api/blocks/tip/height', array('timeout' => 8));
    if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
        return false;
    }

    $height = (int) trim(wp_remote_retrieve_body($response));
    if ($height <= 0) {
        return false;
    }

    set_transient($transient_key, $height, 10 * MINUTE_IN_SECONDS);
    return $height;
}

function coin680_get_halving_info() {
    $height = coin680_get_btc_block_height();
    if (!$height) {
        return false;
    }
    $blocks_per_epoch = 210000;
    $avg_seconds_per_block = 600; // Bitcoin's ~10-minute target block time
    $epoch = intdiv($height, $blocks_per_epoch);
    $next_halving_block = ($epoch + 1) * $blocks_per_epoch;
    $blocks_remaining = $next_halving_block - $height;

    return array(
        'height'             => $height,
        'next_halving_block' => $next_halving_block,
        'blocks_remaining'   => $blocks_remaining,
        'current_reward'     => 50 / pow(2, $epoch),
        'next_reward'        => 50 / pow(2, $epoch + 1),
        'estimated_timestamp' => time() + ($blocks_remaining * $avg_seconds_per_block),
    );
}

/* ==========================================================================
   Altcoin Season Index -- computed in-house from CoinGecko's free markets
   endpoint (price_change_percentage=30d), since CoinGecko's public API does
   not expose a 90d window (the window the well-known blockchaincenter.net
   index uses). Methodology: % of the top ~50 non-stablecoin, non-wrapped
   coins that outperformed Bitcoin over the trailing 30 days. Labeled as a
   30-day index throughout the UI so it's never confused with the 90-day
   original. Cached 6h -- this doesn't need to be fresher than that.
   ========================================================================== */

function coin680_altcoin_season_excluded_symbols() {
    return array(
        'usdt', 'usdc', 'usde', 'pyusd', 'fdusd', 'usdy', 'usyc', 'tusd', 'busd',
        'gusd', 'usdd', 'usds', 'dai', 'pax', 'usdp', 'eurs', 'eurt', 'eurc',
        'xaut', 'paxg', 'wbtc', 'weth', 'steth', 'wsteth', 'cbbtc', 'weeth',
        'reth', 'hbtc', 'tbtc', 'wbeth', 'bsc-usd',
    );
}

function coin680_get_altcoin_season_data() {
    $transient_key = 'coin680_altcoin_season';
    $cached = get_transient($transient_key);
    if ($cached !== false) {
        return $cached;
    }

    $response = wp_remote_get(
        'https://api.coingecko.com/api/v3/coins/markets?vs_currency=usd&order=market_cap_desc&per_page=100&page=1&sparkline=false&price_change_percentage=30d',
        array('timeout' => 12)
    );
    if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
        return false;
    }

    $coins = json_decode(wp_remote_retrieve_body($response), true);
    if (empty($coins) || !is_array($coins)) {
        return false;
    }

    $btc_change = null;
    foreach ($coins as $coin) {
        if ($coin['id'] === 'bitcoin') {
            $btc_change = $coin['price_change_percentage_30d_in_currency'] ?? null;
            break;
        }
    }
    if ($btc_change === null) {
        return false;
    }

    $excluded = coin680_altcoin_season_excluded_symbols();
    $candidates = array();
    foreach ($coins as $coin) {
        if ($coin['id'] === 'bitcoin') {
            continue;
        }
        if (in_array(strtolower($coin['symbol']), $excluded, true)) {
            continue;
        }
        if (!isset($coin['price_change_percentage_30d_in_currency'])) {
            continue;
        }
        $candidates[] = $coin;
        if (count($candidates) >= 50) {
            break;
        }
    }
    if (empty($candidates)) {
        return false;
    }

    $outperforming = 0;
    foreach ($candidates as $coin) {
        if ($coin['price_change_percentage_30d_in_currency'] > $btc_change) {
            $outperforming++;
        }
    }
    $total = count($candidates);
    $index = (int) round(($outperforming / $total) * 100);

    if ($index >= 75) {
        $classification = 'Altcoin Season';
    } elseif ($index <= 25) {
        $classification = 'Bitcoin Season';
    } else {
        $classification = 'Neutral';
    }

    usort($candidates, function ($a, $b) {
        return $b['price_change_percentage_30d_in_currency'] <=> $a['price_change_percentage_30d_in_currency'];
    });

    $result = array(
        'index'          => $index,
        'classification' => $classification,
        'btc_change'     => $btc_change,
        'outperforming'  => $outperforming,
        'total'          => $total,
        'top_performers' => array_slice($candidates, 0, 5),
    );

    set_transient($transient_key, $result, 6 * HOUR_IN_SECONDS);
    return $result;
}

/* ==========================================================================
   Top Gainers & Losers (24h) -- reuses the same cached top-100 CoinGecko
   list as the Crypto Prices hub, so no extra API call. Stablecoins are
   filtered out (they never move enough to be an interesting "top mover").
   ========================================================================== */

function coin680_get_gainers_losers($count = 10) {
    $coins = coin680_get_crypto_prices(100);
    if (!$coins) {
        return false;
    }

    $excluded = coin680_altcoin_season_excluded_symbols();
    $filtered = array();
    foreach ($coins as $coin) {
        if (in_array(strtolower($coin['symbol']), $excluded, true)) {
            continue;
        }
        if (!isset($coin['price_change_percentage_24h']) || $coin['price_change_percentage_24h'] === null) {
            continue;
        }
        $filtered[] = $coin;
    }
    if (empty($filtered)) {
        return false;
    }

    $gainers = $filtered;
    usort($gainers, function ($a, $b) {
        return $b['price_change_percentage_24h'] <=> $a['price_change_percentage_24h'];
    });

    $losers = $filtered;
    usort($losers, function ($a, $b) {
        return $a['price_change_percentage_24h'] <=> $b['price_change_percentage_24h'];
    });

    return array(
        'gainers' => array_slice($gainers, 0, $count),
        'losers'  => array_slice($losers, 0, $count),
    );
}

/* ==========================================================================
   Homepage category section -- reusable block used for every category
   featured on the homepage (Crypto Market News, Business & Institutions,
   Market & Analysis, Bitcoin Academy). Always queries the category directly
   with no offset, so it shows content as soon as that category has any
   posts at all, instead of depending on posts "left over" from other
   sections higher up the page.
   ========================================================================== */

function coin680_category_section($title, $slug, $count = 4) {
    $cat = get_category_by_slug($slug);
    if (!$cat) {
        return;
    }
    $query = new WP_Query(array(
        'category_name'  => $slug,
        'posts_per_page' => $count,
    ));
    ?>
    <section class="c680-category-section">
        <h2 class="c680-section-title"><a href="<?php echo esc_url(get_category_link($cat)); ?>"><?php echo esc_html($title); ?></a></h2>
        <div class="c680-card-grid">
            <?php if ($query->have_posts()) : ?>
                <?php while ($query->have_posts()) : $query->the_post(); ?>
                    <?php get_template_part('template-parts/card', null, array('variant' => 'standard')); ?>
                <?php endwhile; ?>
            <?php else : ?>
                <p><?php esc_html_e('New articles for this section are coming soon.', 'coin680'); ?></p>
            <?php endif; ?>
        </div>
        <a class="c680-more-link" href="<?php echo esc_url(get_category_link($cat)); ?>">
            <?php
            /* translators: %s: category name */
            echo esc_html(sprintf(__('More %s →', 'coin680'), $title));
            ?>
        </a>
    </section>
    <?php
    wp_reset_postdata();
}

/* ==========================================================================
   SEO meta description + Open Graph / Twitter Card tags.
   Coded directly instead of an SEO plugin (Rank Math/Yoast) to avoid
   duplicate/conflicting schema with the JSON-LD already hand-written into
   each Academy/Hub/News article's content -- see Coin680-Master-Content-Prompt.md.
   ========================================================================== */

function coin680_seo_meta_tags() {
    $image_width = 0;
    $image_height = 0;

    if (is_singular()) {
        $title = get_the_title();
        $excerpt = has_excerpt() ? get_the_excerpt() : wp_trim_words(wp_strip_all_tags(get_post_field('post_content')), 30, '…');
        $url = get_permalink();
        $image = '';
        if (has_post_thumbnail()) {
            $thumb_id = get_post_thumbnail_id();
            $src = wp_get_attachment_image_src($thumb_id, 'full');
            if ($src) {
                list($image, $image_width, $image_height) = $src;
            }
        }
        $type = 'article';
    } else {
        $title = get_bloginfo('name') . ' — Bitcoin Education, Crypto News & Exchange Reviews';
        $excerpt = 'Coin680 covers Bitcoin fundamentals, daily crypto market news, and exchange reviews in one place.';
        $url = home_url(add_query_arg(array(), $GLOBALS['wp']->request));
        $type = 'website';
        $custom_logo_id = get_theme_mod('custom_logo');
        $image = $custom_logo_id ? wp_get_attachment_image_url($custom_logo_id, 'full') : '';
    }

    // Fall back to the site logo whenever a page has no image of its own,
    // so a share never goes out with a completely blank preview card.
    if (!$image) {
        $custom_logo_id = get_theme_mod('custom_logo');
        $image = $custom_logo_id ? wp_get_attachment_image_url($custom_logo_id, 'full') : '';
        $image_width = 0;
        $image_height = 0;
    }

    $description = esc_attr(wp_trim_words($excerpt, 35, '…'));
    ?>
    <meta name="description" content="<?php echo $description; ?>">
    <meta property="og:type" content="<?php echo esc_attr($type); ?>">
    <meta property="og:title" content="<?php echo esc_attr($title); ?>">
    <meta property="og:description" content="<?php echo $description; ?>">
    <meta property="og:url" content="<?php echo esc_url($url); ?>">
    <meta property="og:site_name" content="<?php echo esc_attr(get_bloginfo('name')); ?>">
    <?php if ($image) : ?>
    <meta property="og:image" content="<?php echo esc_url($image); ?>">
    <?php if ($image_width && $image_height) : ?>
    <meta property="og:image:width" content="<?php echo esc_attr($image_width); ?>">
    <meta property="og:image:height" content="<?php echo esc_attr($image_height); ?>">
    <?php endif; ?>
    <meta property="og:image:alt" content="<?php echo esc_attr($title); ?>">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:image" content="<?php echo esc_url($image); ?>">
    <?php else : ?>
    <meta name="twitter:card" content="summary">
    <?php endif; ?>
    <meta name="twitter:title" content="<?php echo esc_attr($title); ?>">
    <meta name="twitter:description" content="<?php echo $description; ?>">
    <?php
}
add_action('wp_head', 'coin680_seo_meta_tags', 1);

/* ==========================================================================
   Small content helpers
   ========================================================================== */

function coin680_reading_time($post_id = null) {
    $post_id = $post_id ? $post_id : get_the_ID();
    $content = get_post_field('post_content', $post_id);
    $word_count = str_word_count(wp_strip_all_tags($content));
    return max(1, (int) ceil($word_count / 200));
}

function coin680_card_excerpt($length = 20) {
    return wp_trim_words(get_the_excerpt(), $length, '…');
}

/* ==========================================================================
   Author byline / bio box
   Coin680's content lead publishes under the pen name "Mr Whale" -- a real
   person with genuine market experience since 2020, not a fabricated
   credential. Centralized here so the name/bio only needs to change in
   one place if that ever changes.
   ========================================================================== */

function coin680_author_name() {
    return 'Mr Whale';
}

function coin680_author_bio() {
    return 'Mr Whale has been active in the crypto market since 2020 and leads content and research at Coin680.';
}

/* ==========================================================================
   Comments -- anonymous, name-only (no email, no login required).
   `require_name_email` is forced off in code (not just in the DB option) so
   this can't silently regress if that setting is ever touched elsewhere.
   Spam mitigation stays on WordPress's built-in defaults: first-time
   commenters are held for moderation (comment_whitelist), comments with 2+
   links are held too (comment_max_links) -- no extra plugin needed for a
   first pass.
   ========================================================================== */

add_filter('pre_option_require_name_email', '__return_zero');

function coin680_comment_form_fields($fields) {
    $commenter = wp_get_current_commenter();
    $fields['author'] =
        '<p class="comment-form-author"><label for="author">' . esc_html__('Name or Nickname', 'coin680') . ' <span class="required">*</span></label>' .
        '<input id="author" name="author" type="text" value="' . esc_attr($commenter['comment_author']) . '" maxlength="245" required></p>';
    unset($fields['email']);
    unset($fields['url']);
    unset($fields['cookies']);
    return $fields;
}
add_filter('comment_form_default_fields', 'coin680_comment_form_fields');

function coin680_comment_form_args($args) {
    $args['title_reply']          = __('Leave a Comment', 'coin680');
    $args['label_submit']         = __('Post Comment', 'coin680');
    $args['class_submit']         = 'c680-comment-submit';
    $args['comment_notes_before'] = '';
    $args['comment_notes_after']  = '';
    $args['comment_field'] =
        '<p class="comment-form-comment"><label for="comment">' . esc_html__('Comment', 'coin680') . ' <span class="required">*</span></label>' .
        '<textarea id="comment" name="comment" rows="6" maxlength="65525" required></textarea></p>';
    return $args;
}
add_filter('comment_form_defaults', 'coin680_comment_form_args');

function coin680_enqueue_comment_reply() {
    if (is_singular() && comments_open() && get_option('thread_comments')) {
        wp_enqueue_script('comment-reply');
    }
}
add_action('wp_enqueue_scripts', 'coin680_enqueue_comment_reply');

/* ==========================================================================
   Simple view counter -- admin-only visibility (no public-facing display).
   Increments a post meta counter once per page load of a single post,
   skipping logged-in users so admin/editor visits while writing don't
   inflate the count. Shown as a sortable column in Posts list + a "Most
   Viewed" dashboard widget -- both gated behind manage_options.
   ========================================================================== */

function coin680_track_post_view($post_id) {
    if (is_user_logged_in()) {
        return;
    }
    $views = (int) get_post_meta($post_id, 'coin680_views', true);
    update_post_meta($post_id, 'coin680_views', $views + 1);
}

function coin680_add_views_column($columns) {
    if (!current_user_can('manage_options')) {
        return $columns;
    }
    $columns['coin680_views'] = __('Views', 'coin680');
    return $columns;
}
add_filter('manage_post_posts_columns', 'coin680_add_views_column');

function coin680_render_views_column($column, $post_id) {
    if ($column === 'coin680_views') {
        echo esc_html(number_format((int) get_post_meta($post_id, 'coin680_views', true)));
    }
}
add_action('manage_post_posts_custom_column', 'coin680_render_views_column', 10, 2);

function coin680_views_column_sortable($columns) {
    $columns['coin680_views'] = 'coin680_views';
    return $columns;
}
add_filter('manage_edit-post_sortable_columns', 'coin680_views_column_sortable');

function coin680_views_column_orderby($query) {
    if (!is_admin() || !$query->is_main_query()) {
        return;
    }
    if ($query->get('orderby') === 'coin680_views') {
        $query->set('meta_key', 'coin680_views');
        $query->set('orderby', 'meta_value_num');
    }
}
add_action('pre_get_posts', 'coin680_views_column_orderby');

function coin680_dashboard_views_widget() {
    if (!current_user_can('manage_options')) {
        return;
    }
    add_meta_box(
        'coin680_top_viewed',
        __('Coin680 -- Most Viewed Articles', 'coin680'),
        'coin680_render_dashboard_views_widget',
        'dashboard',
        'normal',
        'high'
    );
}
add_action('wp_dashboard_setup', 'coin680_dashboard_views_widget');

function coin680_render_dashboard_views_widget() {
    $top = new WP_Query(array(
        'post_type'      => 'post',
        'posts_per_page' => 10,
        'meta_key'       => 'coin680_views',
        'orderby'        => 'meta_value_num',
        'order'          => 'DESC',
    ));
    if (!$top->have_posts()) {
        echo '<p>' . esc_html__('No view data yet.', 'coin680') . '</p>';
        return;
    }
    echo '<table style="width:100%;border-collapse:collapse;">';
    while ($top->have_posts()) {
        $top->the_post();
        $views = (int) get_post_meta(get_the_ID(), 'coin680_views', true);
        echo '<tr style="border-bottom:1px solid #eee;">';
        echo '<td style="padding:6px 0;"><a href="' . esc_url(get_edit_post_link()) . '">' . esc_html(get_the_title()) . '</a></td>';
        echo '<td style="padding:6px 0;text-align:right;font-weight:600;">' . esc_html(number_format($views)) . '</td>';
        echo '</tr>';
    }
    echo '</table>';
    wp_reset_postdata();
}

/* ==========================================================================
   Newsletter signup
   Lightweight, self-hosted subscriber capture (no external email service
   configured yet) -- stores into its own table so it works today without
   depending on Hostinger Reach being connected (checked 2026-07-28: this
   site's Reach connection has no API key set, so its subscription block
   would show visitors "not available"). Emails can be exported later
   (`wp db query "SELECT email FROM {$wpdb->prefix}coin680_subscribers"`)
   whenever a real send provider (Reach, or otherwise) is wired up.
   ========================================================================== */

function coin680_create_subscribers_table() {
    global $wpdb;
    $table = $wpdb->prefix . 'coin680_subscribers';
    $charset_collate = $wpdb->get_charset_collate();
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta("CREATE TABLE $table (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        email VARCHAR(190) NOT NULL,
        source VARCHAR(50) NOT NULL DEFAULT '',
        created_at DATETIME NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY email (email)
    ) $charset_collate;");
}
add_action('after_switch_theme', 'coin680_create_subscribers_table');

function coin680_register_subscribe_route() {
    register_rest_route('coin680/v1', '/subscribe', array(
        'methods'             => 'POST',
        'callback'            => 'coin680_handle_subscribe',
        'permission_callback' => '__return_true',
        'args'                => array(
            'email'  => array('required' => true),
            'source' => array('required' => false),
        ),
    ));
}
add_action('rest_api_init', 'coin680_register_subscribe_route');

function coin680_handle_subscribe(WP_REST_Request $request) {
    global $wpdb;
    $email = sanitize_email($request->get_param('email'));
    if (!$email || !is_email($email)) {
        return new WP_REST_Response(array('success' => false, 'message' => __('Please enter a valid email address.', 'coin680')), 400);
    }
    $source = sanitize_text_field($request->get_param('source'));
    $table = $wpdb->prefix . 'coin680_subscribers';
    $existing = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE email = %s", $email));
    if (!$existing) {
        $wpdb->insert($table, array(
            'email'      => $email,
            'source'     => $source,
            'created_at' => current_time('mysql'),
        ));
    }
    return new WP_REST_Response(array('success' => true, 'message' => __("Thanks -- you're subscribed.", 'coin680')), 200);
}

function coin680_newsletter_form($source = 'article') {
    ?>
    <div class="c680-newsletter-box">
        <div class="c680-newsletter-copy">
            <div class="c680-newsletter-title"><?php esc_html_e('Get the Coin680 Daily Brief', 'coin680'); ?></div>
            <p class="c680-newsletter-desc"><?php esc_html_e('Bitcoin news, market moves, and Academy lessons -- straight to your inbox, no spam.', 'coin680'); ?></p>
        </div>
        <form class="c680-newsletter-form" data-source="<?php echo esc_attr($source); ?>">
            <input type="email" class="c680-newsletter-input" placeholder="<?php esc_attr_e('you@example.com', 'coin680'); ?>" required>
            <button type="submit" class="c680-newsletter-submit"><?php esc_html_e('Subscribe', 'coin680'); ?></button>
        </form>
        <p class="c680-newsletter-status" hidden></p>
    </div>
    <?php
}

function coin680_newsletter_script() {
    ?>
    <script>
    document.querySelectorAll('.c680-newsletter-form').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            var input = form.querySelector('.c680-newsletter-input');
            var status = form.parentElement.querySelector('.c680-newsletter-status');
            var button = form.querySelector('.c680-newsletter-submit');
            button.disabled = true;
            fetch('<?php echo esc_url_raw(rest_url('coin680/v1/subscribe')); ?>', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ email: input.value, source: form.dataset.source })
            })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    status.textContent = data.message;
                    status.hidden = false;
                    status.className = 'c680-newsletter-status ' + (data.success ? 'c680-newsletter-ok' : 'c680-newsletter-error');
                    if (data.success) { form.reset(); }
                    button.disabled = false;
                })
                .catch(function () {
                    status.textContent = '<?php echo esc_js(__('Something went wrong. Please try again.', 'coin680')); ?>';
                    status.hidden = false;
                    status.className = 'c680-newsletter-status c680-newsletter-error';
                    button.disabled = false;
                });
        });
    });
    </script>
    <?php
}
add_action('wp_footer', 'coin680_newsletter_script');

/**
 * ---------------------------------------------------------------------
 * Live Whale Signals page support (added 2026-07-30, after upgrading to a
 * paid Bitquery plan). Shared between the initial server-render in
 * page-whale-signals.php and the /coin680/v1/whale-signals REST endpoint
 * (used for the page's ~20s auto-refresh) so formatting/filtering logic
 * lives in exactly one place -- the JS side is a dumb renderer with no
 * business logic of its own, can't drift out of sync with the PHP render.
 * ---------------------------------------------------------------------
 */

/**
 * "3h 12m ago" style relative time -- deliberately more precise than
 * human_time_diff() alone, which collapses to just the largest unit
 * ("3 hours") and loses granularity that matters on a fast-moving feed.
 */
function coin680_ws_time_ago($mysql_datetime) {
    $diff = time() - strtotime($mysql_datetime . ' UTC');
    if ($diff < 60) {
        return __('just now', 'coin680');
    }
    $mins = (int) floor($diff / 60);
    if ($mins < 60) {
        return sprintf(__('%dm ago', 'coin680'), $mins);
    }
    $hours = (int) floor($mins / 60);
    $rem_mins = $mins % 60;
    if ($hours < 24) {
        return $rem_mins > 0
            ? sprintf(__('%1$dh %2$dm ago', 'coin680'), $hours, $rem_mins)
            : sprintf(__('%dh ago', 'coin680'), $hours);
    }
    return sprintf(__('%dd ago', 'coin680'), (int) floor($hours / 24));
}

// Transactions at or above this size get the "big move" badge on the
// public page -- separate from (and higher than) the $100k/$10k inclusion
// thresholds in the Whale Tracker settings, which control what gets
// CAPTURED at all, not what counts as notably large once captured.
define('COIN680_WS_BIG_MOVE_USD', 1000000);

function coin680_ws_format_signal($row) {
    $chain_cfg = class_exists('Coin680Bitquery_Labels') ? Coin680Bitquery_Labels::chain_config($row->chain) : null;
    return array(
        'time_ago'       => coin680_ws_time_ago($row->tx_timestamp),
        'timestamp'      => $row->tx_timestamp,
        'chain_label'    => $chain_cfg['label'] ?? ucfirst($row->chain),
        'symbol'         => strtoupper($row->symbol),
        'counter_symbol' => $row->counter_symbol,
        'classification' => $row->classification,
        'dex_name'       => $row->dex_name,
        'amount_usd'     => (float) $row->amount_usd,
        'amount_fmt'     => '$' . number_format($row->amount_usd),
        'is_big'         => ((float) $row->amount_usd) >= COIN680_WS_BIG_MOVE_USD,
        'tx_url'         => ($chain_cfg && $row->tx_hash) ? sprintf($chain_cfg['explorer'], $row->tx_hash) : '',
    );
}

function coin680_ws_format_watchlist_move($row) {
    $chain_cfg = class_exists('Coin680Bitquery_Labels') ? Coin680Bitquery_Labels::chain_config($row->chain) : null;
    // 'buy'/'sell' = DEX swap legs (original feature); 'received'/'sent' =
    // plain transfers (exchange deposits/withdrawals, wallet-to-wallet
    // moves) added 2026-08-08 so watched-wallet activity outside DEX swaps
    // stops being invisible -- see Coin680Watchlist_Fetcher::process_transfer().
    $side_labels = array(
        'buy'      => __('Bought', 'coin680'),
        'sell'     => __('Sold', 'coin680'),
        'received' => __('Received', 'coin680'),
        'sent'     => __('Sent', 'coin680'),
    );
    return array(
        'time_ago'           => coin680_ws_time_ago($row->tx_timestamp),
        'wallet_label'       => $row->wallet_label ?: (substr($row->wallet_address, 0, 6) . '...' . substr($row->wallet_address, -4)),
        'chain_label'        => $chain_cfg['label'] ?? ucfirst($row->chain),
        'side'               => $row->side,
        'side_label'         => $side_labels[$row->side] ?? ucfirst($row->side),
        'symbol'             => strtoupper($row->symbol),
        'amount_fmt'         => '$' . number_format($row->amount_usd),
        // For transfer rows, dex_name is repurposed to hold the
        // counterparty's label (a known exchange name if it matched
        // Coin680Watchlist_Fetcher::KNOWN_EXCHANGE_WALLETS, otherwise its
        // own truncated address) -- empty for DEX swap rows, which use
        // dex_name for its original purpose (the DEX protocol name) instead.
        'counterparty_label' => in_array($row->side, array('received', 'sent'), true) ? $row->dex_name : '',
    );
}

const COIN680_WS_PER_PAGE = 60;
const COIN680_WS_HOURS = 168; // 7 days of browsable history

/**
 * Transient cache, keyed by the exact chain/type/page combo. The underlying
 * Bitquery data itself only changes once per 5-minute cron poll, so this
 * costs zero real freshness -- but it means any number of visitors polling
 * the auto-refresh within the same cache window share ONE database query
 * instead of one each, which is the only meaningful traffic-driven cost of
 * the live page (Bitquery itself is polled on a fixed cron schedule,
 * completely decoupled from visitor count -- see coin680bitquery_poll /
 * coin680watchlist_poll in the plugin bootstrap).
 *
 * Widened from 15s to 45s and the JS poll from 20s to 60s together on
 * 2026-07-31, after a live concurrency test (direct parallel requests
 * against this exact REST route) showed ~500 errors appearing at 20-25
 * simultaneous requests, most likely full-WP-bootstrap-per-request cost
 * (this plan's single CPU core serializes that work) rather than the cache
 * itself -- widening the poll interval directly cuts how many requests
 * arrive per minute per visitor, independent of the cache.
 */
function coin680_ws_query_signals($chain, $type, $page) {
    if (!class_exists('Coin680Bitquery_Fetcher')) {
        return array('items' => array(), 'total' => 0, 'pages' => 1, 'page' => 1);
    }
    $page = max(1, (int) $page);
    $cache_key = 'coin680_ws_q_' . md5($chain . '|' . $type . '|' . $page);
    $cached = get_transient($cache_key);
    if ($cached !== false) {
        return $cached;
    }

    $classification_map = array('buy' => 'DEX Buy', 'sell' => 'DEX Sell', 'swap' => 'DEX Swap');
    $classification = $classification_map[$type] ?? null;
    $offset = ($page - 1) * COIN680_WS_PER_PAGE;

    $total = Coin680Bitquery_Fetcher::count_recent(COIN680_WS_HOURS, $chain ?: null, $classification);
    $rows = Coin680Bitquery_Fetcher::get_recent(COIN680_WS_HOURS, COIN680_WS_PER_PAGE, $offset, $chain ?: null, $classification, 'tx_timestamp');

    $result = array(
        'items' => array_map('coin680_ws_format_signal', $rows),
        'total' => $total,
        'pages' => max(1, (int) ceil($total / COIN680_WS_PER_PAGE)),
        'page'  => $page,
    );
    set_transient($cache_key, $result, 45);
    return $result;
}

function coin680_register_whale_signals_route() {
    register_rest_route('coin680/v1', '/whale-signals', array(
        'methods'             => 'GET',
        'callback'            => 'coin680_handle_whale_signals',
        'permission_callback' => '__return_true',
        'args'                => array(
            'chain' => array('required' => false),
            'type'  => array('required' => false),
            'page'  => array('required' => false, 'default' => 1),
        ),
    ));
}
add_action('rest_api_init', 'coin680_register_whale_signals_route');

/**
 * Server-side row renderer -- deliberately mirrors the JS renderRow()
 * function in page-whale-signals.php's inline <script> field-for-field
 * (same classes, same "Big" badge, same column order) so the initial
 * server-rendered table and the first AJAX refresh never visibly "jump" or
 * look inconsistent to a visitor who happens to be watching right as the
 * first refresh lands.
 */
function coin680_ws_render_rows($items) {
    if (empty($items)) {
        return '<tr><td colspan="6">' . esc_html__('No signals in this window yet -- check back shortly.', 'coin680') . '</td></tr>';
    }
    $out = '';
    foreach ($items as $item) {
        $type_class = $item['classification'] === 'DEX Buy' ? 'c680-ticker-up' : ($item['classification'] === 'DEX Sell' ? 'c680-ticker-down' : '');
        $big_badge = $item['is_big'] ? ' <span class="c680-ws-big-badge">&#128293; ' . esc_html__('Big', 'coin680') . '</span>' : '';
        $counter = $item['counter_symbol'] ? ' <small>vs ' . esc_html($item['counter_symbol']) . '</small>' : '';
        $tx_link = $item['tx_url'] ? '<a href="' . esc_url($item['tx_url']) . '" target="_blank" rel="noopener">' . esc_html__('View', 'coin680') . '</a>' : '';
        $out .= '<tr class="' . ($item['is_big'] ? 'c680-ws-row-big' : '') . '">'
            . '<td>' . esc_html($item['time_ago']) . '</td>'
            . '<td class="c680-prices-name"><strong>' . esc_html($item['symbol']) . '</strong> ' . esc_html($item['chain_label']) . $big_badge . '</td>'
            . '<td class="' . esc_attr($type_class) . '">' . esc_html($item['classification']) . $counter . '</td>'
            . '<td>' . esc_html($item['dex_name']) . '</td>'
            . '<td>' . esc_html($item['amount_fmt']) . '</td>'
            . '<td>' . $tx_link . '</td>'
            . '</tr>';
    }
    return $out;
}

function coin680_handle_whale_signals(WP_REST_Request $request) {
    $chain = sanitize_key($request->get_param('chain'));
    $valid_chains = class_exists('Coin680Bitquery_Labels') ? array_keys(Coin680Bitquery_Labels::CHAINS) : array();
    if ($chain && !in_array($chain, $valid_chains, true)) {
        $chain = '';
    }
    $type = sanitize_key($request->get_param('type'));
    $result = coin680_ws_query_signals($chain, $type, $request->get_param('page'));
    $result['updated'] = current_time('mysql', true);
    return new WP_REST_Response($result, 200);
}

/* ==========================================================================
   Social share buttons -- plain share-intent links (X, Facebook, Telegram)
   plus a copy-link button. No external SDK/script, so no third-party
   tracking or extra page weight.
   ========================================================================== */

function coin680_share_buttons() {
    $url = esc_url(get_permalink());
    $title = rawurlencode(get_the_title());
    $encoded_url = rawurlencode(get_permalink());
    ?>
    <div class="c680-share-buttons">
        <span class="c680-share-label"><?php esc_html_e('Share:', 'coin680'); ?></span>
        <a class="c680-share-btn" target="_blank" rel="noopener" href="https://twitter.com/intent/tweet?text=<?php echo $title; ?>&url=<?php echo $encoded_url; ?>" aria-label="<?php esc_attr_e('Share on X', 'coin680'); ?>">X</a>
        <a class="c680-share-btn" target="_blank" rel="noopener" href="https://www.facebook.com/sharer/sharer.php?u=<?php echo $encoded_url; ?>" aria-label="<?php esc_attr_e('Share on Facebook', 'coin680'); ?>">FB</a>
        <a class="c680-share-btn" target="_blank" rel="noopener" href="https://t.me/share/url?url=<?php echo $encoded_url; ?>&text=<?php echo $title; ?>" aria-label="<?php esc_attr_e('Share on Telegram', 'coin680'); ?>">TG</a>
        <button type="button" class="c680-share-btn c680-share-copy" data-url="<?php echo $url; ?>"><?php esc_html_e('Copy Link', 'coin680'); ?></button>
    </div>
    <?php
}

function coin680_share_script() {
    if (!is_singular('post')) {
        return;
    }
    ?>
    <script>
    document.querySelectorAll('.c680-share-copy').forEach(function (btn) {
        btn.addEventListener('click', function () {
            navigator.clipboard.writeText(btn.dataset.url).then(function () {
                var original = btn.textContent;
                btn.textContent = '<?php echo esc_js(__('Copied!', 'coin680')); ?>';
                setTimeout(function () { btn.textContent = original; }, 1500);
            });
        });
    });
    </script>
    <?php
}
add_action('wp_footer', 'coin680_share_script');

function coin680_author_box() {
    ?>
    <div class="c680-author-box">
        <div class="c680-author-avatar" aria-hidden="true"><?php echo esc_html(mb_substr(coin680_author_name(), 0, 1)); ?></div>
        <div class="c680-author-info">
            <div class="c680-author-name"><?php esc_html_e('Written by', 'coin680'); ?> <?php echo esc_html(coin680_author_name()); ?></div>
            <p class="c680-author-desc"><?php echo esc_html(coin680_author_bio()); ?> <a href="<?php echo esc_url(home_url('/about/')); ?>"><?php esc_html_e('More about our editorial team →', 'coin680'); ?></a></p>
        </div>
    </div>
    <?php
}

/* ==========================================================================
   Automatic affiliate disclosure banner
   Any post filed under Exchange Comparison or one of the 6 Exchange Reviews
   categories gets a disclosure notice injected above the content automatically,
   linking to /advertising-disclosure/ -- so compliance doesn't rely on every
   article remembering to add it manually.
   ========================================================================== */

function coin680_content_needs_affiliate_disclosure($post_id) {
    $affiliate_slugs = array('exchange-comparison', 'binance', 'bybit', 'okx', 'bingx', 'gate', 'mexc');
    $categories = get_the_category($post_id);
    if (empty($categories)) {
        return false;
    }
    foreach ($categories as $cat) {
        if (in_array($cat->slug, $affiliate_slugs, true)) {
            return true;
        }
    }
    return false;
}

function coin680_maybe_affiliate_banner($content) {
    if (is_singular('post') && in_the_loop() && is_main_query() && coin680_content_needs_affiliate_disclosure(get_the_ID())) {
        $disclosure_url = esc_url(home_url('/advertising-disclosure/'));
        $banner = '<div class="c680-affiliate-banner">This page may contain affiliate links. Coin680 may earn a commission if you sign up through a link on this page, at no extra cost to you. <a href="' . $disclosure_url . '">Read our Advertising Disclosure</a>.</div>';
        return $banner . $content;
    }
    return $content;
}
add_filter('the_content', 'coin680_maybe_affiliate_banner');

/* ==========================================================================
   Admin-only REST endpoint to seed bbPress forum topics programmatically
   (bbPress has no REST API of its own -- confirmed 2026-08-06, no
   forum/topic/reply routes exist under wp-json). Wraps bbPress's own
   bbp_insert_topic() so topic/reply counts and forum relationships stay
   consistent, rather than inserting raw posts by hand.
   ========================================================================== */

function coin680_register_forum_topic_route() {
    register_rest_route('coin680/v1', '/forum-topic', array(
        'methods'             => 'POST',
        'callback'            => 'coin680_handle_create_forum_topic',
        'permission_callback' => function () {
            return current_user_can('manage_options');
        },
    ));
}
add_action('rest_api_init', 'coin680_register_forum_topic_route');

function coin680_register_create_forum_route() {
    register_rest_route('coin680/v1', '/forum', array(
        'methods'             => 'POST',
        'callback'            => 'coin680_handle_create_forum',
        'permission_callback' => function () {
            return current_user_can('manage_options');
        },
    ));
}
add_action('rest_api_init', 'coin680_register_create_forum_route');

function coin680_handle_create_forum($request) {
    if (!function_exists('bbp_insert_forum')) {
        return new WP_Error('bbpress_missing', 'bbPress is not active.', array('status' => 500));
    }
    $params = $request->get_json_params();
    $title = isset($params['title']) ? sanitize_text_field($params['title']) : '';
    $description = isset($params['description']) ? wp_kses_post($params['description']) : '';
    $order = isset($params['menu_order']) ? (int) $params['menu_order'] : 0;

    if (!$title) {
        return new WP_Error('missing_fields', 'title is required.', array('status' => 400));
    }

    $forum_id = bbp_insert_forum(array(
        'post_title'   => $title,
        'post_content' => $description,
        'menu_order'   => $order,
    ));

    if (!$forum_id || is_wp_error($forum_id)) {
        return new WP_Error('create_failed', 'Failed to create the forum.', array('status' => 500));
    }

    return new WP_REST_Response(array(
        'forum_id' => $forum_id,
        'url'      => get_permalink($forum_id),
    ), 200);
}

function coin680_register_forums_list_route() {
    register_rest_route('coin680/v1', '/forums', array(
        'methods'             => 'GET',
        'callback'            => 'coin680_handle_list_forums',
        'permission_callback' => function () {
            return current_user_can('manage_options');
        },
    ));
}
add_action('rest_api_init', 'coin680_register_forums_list_route');

function coin680_handle_list_forums($request) {
    $forums = get_posts(array(
        'post_type'      => 'forum',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'orderby'        => 'ID',
        'order'          => 'ASC',
    ));
    $out = array();
    foreach ($forums as $f) {
        $out[] = array('id' => $f->ID, 'title' => $f->post_title, 'slug' => $f->post_name);
    }
    return new WP_REST_Response($out, 200);
}

function coin680_handle_create_forum_topic($request) {
    if (!function_exists('bbp_insert_topic')) {
        return new WP_Error('bbpress_missing', 'bbPress is not active.', array('status' => 500));
    }

    $params = $request->get_json_params();
    $title = isset($params['title']) ? sanitize_text_field($params['title']) : '';
    $content = isset($params['content']) ? wp_kses_post($params['content']) : '';
    $forum_id = isset($params['forum_id']) ? (int) $params['forum_id'] : 0;

    if (!$title || !$content || !$forum_id || get_post_type($forum_id) !== 'forum') {
        return new WP_Error('missing_fields', 'title, content, and a valid forum_id are required.', array('status' => 400));
    }

    $topic_id = bbp_insert_topic(
        array(
            'post_parent'  => $forum_id,
            'post_title'   => $title,
            'post_content' => $content,
            'post_author'  => get_current_user_id(),
        ),
        array('forum_id' => $forum_id)
    );

    if (!$topic_id || is_wp_error($topic_id)) {
        return new WP_Error('create_failed', 'Failed to create the topic.', array('status' => 500));
    }

    return new WP_REST_Response(array(
        'topic_id' => $topic_id,
        'url'      => get_permalink($topic_id),
    ), 200);
}






