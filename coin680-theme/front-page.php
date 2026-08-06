<?php
/**
 * Homepage -- CoinDesk-style layout:
 * hero (Crypto Market News) + market data ticker, then 5 dedicated category
 * rows: Crypto Market News, Business & Institutions, Market & Analysis,
 * Bitcoin Academy, Exchange Reviews -- the 5 categories expected to carry
 * the most content over time. Each row queries its own category directly
 * (no offset), so it shows content as soon as that category has any posts.
 * Runs regardless of the Settings > Reading choice (front-page.php always
 * wins for the site front page in the WordPress template hierarchy).
 */

get_header();
?>
<main class="c680-home">

    <?php
    $coin680_hero = new WP_Query(array(
        'category_name'       => 'crypto-market-news',
        'posts_per_page'      => 5,
        'ignore_sticky_posts' => 1,
    ));
    if ($coin680_hero->have_posts()) :
    ?>
    <section class="c680-hero-section">
        <div class="c680-hero-main">
            <?php $coin680_hero->the_post(); ?>
            <?php get_template_part('template-parts/card', null, array('variant' => 'hero', 'cat_name' => __('Crypto Market News', 'coin680'))); ?>
        </div>
        <div class="c680-hero-secondary">
            <?php while ($coin680_hero->have_posts()) : $coin680_hero->the_post(); ?>
                <?php get_template_part('template-parts/card', null, array('variant' => 'compact', 'cat_name' => __('Crypto Market News', 'coin680'))); ?>
            <?php endwhile; ?>
        </div>
    </section>
    <?php
    endif;
    wp_reset_postdata();
    ?>

    <?php coin680_fear_greed_widget(); ?>

    <section class="c680-market-strip">
        <h2 class="c680-section-title"><?php esc_html_e('Market Data', 'coin680'); ?></h2>
        <?php coin680_price_ticker(); ?>
    </section>

    <section class="c680-category-section c680-home-prices-section">
        <h2 class="c680-section-title"><a href="<?php echo esc_url(home_url('/crypto-prices/')); ?>"><?php esc_html_e('Live Prices', 'coin680'); ?></a></h2>
        <?php
        $coin680_home_coins = coin680_get_crypto_prices();
        if ($coin680_home_coins) :
            $coin680_home_coins = array_slice($coin680_home_coins, 0, 6);
        ?>
        <div class="c680-prices-table-wrap">
            <table class="c680-prices-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Coin', 'coin680'); ?></th>
                        <th><?php esc_html_e('Price', 'coin680'); ?></th>
                        <th><?php esc_html_e('24h %', 'coin680'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($coin680_home_coins as $coin680_hc) :
                        $coin680_hp = isset($coin680_hc['current_price']) ? (float) $coin680_hc['current_price'] : 0;
                        $coin680_hchg = isset($coin680_hc['price_change_percentage_24h']) ? (float) $coin680_hc['price_change_percentage_24h'] : 0;
                        $coin680_hdec = $coin680_hp < 1 ? 4 : ($coin680_hp < 10 ? 3 : 2);
                        $coin680_hdir = $coin680_hchg >= 0 ? 'up' : 'down';
                        $coin680_hlink = ($coin680_hc['id'] === 'bitcoin') ? home_url('/bitcoin-price/') : home_url('/crypto-prices/');
                    ?>
                    <tr>
                        <td class="c680-prices-name">
                            <strong><?php echo esc_html(strtoupper($coin680_hc['symbol'])); ?></strong>
                            <a href="<?php echo esc_url($coin680_hlink); ?>"><?php echo esc_html($coin680_hc['name']); ?></a>
                        </td>
                        <td>$<?php echo esc_html(number_format($coin680_hp, $coin680_hdec)); ?></td>
                        <td class="c680-ticker-<?php echo esc_attr($coin680_hdir); ?>">
                            <?php echo $coin680_hchg >= 0 ? '&#9650;' : '&#9660;'; ?> <?php echo esc_html(number_format(abs($coin680_hchg), 2)); ?>%
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <a class="c680-more-link" href="<?php echo esc_url(home_url('/crypto-prices/')); ?>"><?php esc_html_e('View All Prices →', 'coin680'); ?></a>
        <?php endif; ?>
    </section>

    <section class="c680-category-section c680-home-whale-section">
        <h2 class="c680-section-title"><a href="<?php echo esc_url(home_url('/whale-signals/')); ?>"><?php esc_html_e('Live Whale Signals', 'coin680'); ?></a></h2>
        <?php
        $coin680_home_ws = function_exists('coin680_ws_query_signals') ? coin680_ws_query_signals('', '', 1) : array('items' => array());
        $coin680_home_ws_items = array_slice($coin680_home_ws['items'], 0, 6);
        if ($coin680_home_ws_items) :
        ?>
        <div class="c680-prices-table-wrap">
            <table class="c680-prices-table c680-ws-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Time', 'coin680'); ?></th>
                        <th><?php esc_html_e('Asset', 'coin680'); ?></th>
                        <th><?php esc_html_e('Type', 'coin680'); ?></th>
                        <th><?php esc_html_e('Amount', 'coin680'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($coin680_home_ws_items as $coin680_hws) :
                        $coin680_hws_type_class = $coin680_hws['classification'] === 'DEX Buy' ? 'c680-ticker-up' : ($coin680_hws['classification'] === 'DEX Sell' ? 'c680-ticker-down' : '');
                        $coin680_hws_big = $coin680_hws['is_big'] ? ' <span class="c680-ws-big-badge">&#128293;</span>' : '';
                    ?>
                    <tr>
                        <td><?php echo esc_html($coin680_hws['time_ago']); ?></td>
                        <td class="c680-prices-name"><strong><?php echo esc_html($coin680_hws['symbol']); ?></strong> <?php echo esc_html($coin680_hws['chain_label']); ?><?php echo $coin680_hws_big; ?></td>
                        <td class="<?php echo esc_attr($coin680_hws_type_class); ?>"><?php echo esc_html($coin680_hws['classification']); ?></td>
                        <td><?php echo esc_html($coin680_hws['amount_fmt']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <a class="c680-more-link" href="<?php echo esc_url(home_url('/whale-signals/')); ?>"><?php esc_html_e('View All Whale Signals →', 'coin680'); ?></a>
        <?php else : ?>
        <p><?php esc_html_e('No signals in the current window yet -- check back shortly.', 'coin680'); ?></p>
        <?php endif; ?>
    </section>

    <?php
    coin680_category_section(__('Crypto Market News', 'coin680'), 'crypto-market-news', 8);
    coin680_category_section(__('Business & Institutions', 'coin680'), 'business-institutions', 4);
    coin680_category_section(__('Market & Analysis', 'coin680'), 'market-analysis', 4);
    coin680_category_section(__('Learn Bitcoin', 'coin680'), 'bitcoin-academy', 4);
    ?>

    <section class="c680-forum-cta-section">
        <a href="<?php echo esc_url(home_url('/forums/')); ?>" class="c680-forum-cta-card">
            <div class="c680-forum-cta-text">
                <span class="c680-forum-cta-title"><?php esc_html_e('Join the Discussion', 'coin680'); ?></span>
                <span class="c680-forum-cta-desc"><?php esc_html_e('Talk Bitcoin and crypto with the Coin680 community -- market moves, questions, on-chain trends, and more.', 'coin680'); ?></span>
            </div>
            <span class="c680-forum-cta-btn"><?php esc_html_e('Visit Forums', 'coin680'); ?> &rarr;</span>
        </a>
    </section>
    <style>
    .c680-forum-cta-section { margin: 32px 0; }
    .c680-forum-cta-card {
        display: flex; align-items: center; justify-content: space-between; gap: 20px;
        background: linear-gradient(135deg, #111c2d, #1b2942);
        border: 1px solid #c11510; border-radius: 10px;
        padding: 22px 28px; text-decoration: none; color: #fffbf2;
        transition: transform 0.15s ease, box-shadow 0.15s ease;
    }
    .c680-forum-cta-card:hover { transform: translateY(-2px); box-shadow: 0 6px 18px rgba(193,21,16,0.25); }
    .c680-forum-cta-text { display: flex; flex-direction: column; gap: 4px; }
    .c680-forum-cta-title { font-size: 20px; font-weight: 700; color: #fffbf2; }
    .c680-forum-cta-desc { font-size: 14px; color: #c7cede; max-width: 560px; }
    .c680-forum-cta-btn {
        flex-shrink: 0; background: #c11510; color: #fff; font-weight: 600; font-size: 14px;
        padding: 10px 18px; border-radius: 6px; white-space: nowrap;
    }
    @media (max-width: 640px) {
        .c680-forum-cta-card { flex-direction: column; align-items: flex-start; }
    }
    </style>

    <section class="c680-newsletter-section">
        <?php coin680_newsletter_form('homepage'); ?>
    </section>

    <section class="c680-exchange-section">
        <h2 class="c680-section-title"><?php esc_html_e('Exchange Reviews', 'coin680'); ?></h2>
        <p class="c680-affiliate-note">
            <?php esc_html_e('This section contains affiliate links.', 'coin680'); ?>
            <a href="<?php echo esc_url(home_url('/advertising-disclosure/')); ?>"><?php esc_html_e('See our Advertising Disclosure', 'coin680'); ?></a>.
        </p>
        <div class="c680-exchange-grid">
            <?php
            // Pretty Links registration redirects, all 6 exchanges configured as of 2026-07-28.
            $coin680_exchange_links = array(
                'Binance' => 'https://coin680.com/Binance',
                'Bybit'   => 'https://coin680.com/Bybit',
                'OKX'     => 'https://coin680.com/okx',
                'BingX'   => 'https://coin680.com/bingx',
                'Gate'    => 'https://coin680.com/Gate',
                'MEXC'    => 'https://coin680.com/Mexc',
            );
            $coin680_exchanges = array('Binance', 'Bybit', 'OKX', 'BingX', 'Gate', 'MEXC');
            foreach ($coin680_exchanges as $coin680_ex) :
                $coin680_ex_cat = get_category_by_slug(sanitize_title($coin680_ex));
                $coin680_has_link = isset($coin680_exchange_links[$coin680_ex]);
                if (!$coin680_has_link && !$coin680_ex_cat) {
                    continue;
                }
                $coin680_href = $coin680_has_link ? $coin680_exchange_links[$coin680_ex] : get_category_link($coin680_ex_cat);
                $coin680_cta_label = $coin680_has_link ? __('Open Account →', 'coin680') : __('View Reviews →', 'coin680');
            ?>
                <a class="c680-exchange-card" href="<?php echo esc_url($coin680_href); ?>" rel="sponsored nofollow" target="_blank">
                    <span class="c680-exchange-name"><?php echo esc_html($coin680_ex); ?></span>
                    <span class="c680-exchange-cta"><?php echo esc_html($coin680_cta_label); ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </section>

</main>
<?php get_footer(); ?>


