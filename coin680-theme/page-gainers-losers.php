<?php
/**
 * Template Name: Top Gainers & Losers
 * Both tables render server-side (good for SEO/crawlers); a small JS toggle
 * switches which one is visible client-side, tab-style.
 */
get_header();
$coin680_gl = coin680_get_gainers_losers(10);
?>
<main class="c680-page c680-gl-page">
    <h1 class="c680-page-title"><?php echo esc_html(get_the_title() ?: __('Top Gainers & Losers (24h)', 'coin680')); ?></h1>
    <p class="c680-prices-intro"><?php esc_html_e('The biggest 24-hour movers among the top 100 cryptocurrencies by market capitalization, updated automatically.', 'coin680'); ?></p>

    <?php if ($coin680_gl) : ?>
    <div class="c680-gl-tabs">
        <button type="button" class="c680-gl-tab c680-gl-tab-active" data-tab="gainers"><?php esc_html_e('Top Gainers', 'coin680'); ?></button>
        <button type="button" class="c680-gl-tab" data-tab="losers"><?php esc_html_e('Top Losers', 'coin680'); ?></button>
    </div>

    <div class="c680-gl-panel" data-panel="gainers">
        <div class="c680-prices-table-wrap">
            <table class="c680-prices-table">
                <thead><tr><th>#</th><th><?php esc_html_e('Coin', 'coin680'); ?></th><th><?php esc_html_e('Price', 'coin680'); ?></th><th><?php esc_html_e('24h %', 'coin680'); ?></th></tr></thead>
                <tbody>
                    <?php $coin680_rank = 1; foreach ($coin680_gl['gainers'] as $coin680_c) :
                        $coin680_p = (float) $coin680_c['current_price'];
                        $coin680_dec = $coin680_p < 1 ? 4 : ($coin680_p < 10 ? 3 : 2);
                        $coin680_chg = (float) $coin680_c['price_change_percentage_24h'];
                    ?>
                    <tr>
                        <td><?php echo esc_html($coin680_rank++); ?></td>
                        <td class="c680-prices-name"><strong><?php echo esc_html(strtoupper($coin680_c['symbol'])); ?></strong> <?php echo esc_html($coin680_c['name']); ?></td>
                        <td>$<?php echo esc_html(number_format($coin680_p, $coin680_dec)); ?></td>
                        <td class="c680-ticker-up">&#9650; <?php echo esc_html(number_format($coin680_chg, 2)); ?>%</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="c680-gl-panel" data-panel="losers" hidden>
        <div class="c680-prices-table-wrap">
            <table class="c680-prices-table">
                <thead><tr><th>#</th><th><?php esc_html_e('Coin', 'coin680'); ?></th><th><?php esc_html_e('Price', 'coin680'); ?></th><th><?php esc_html_e('24h %', 'coin680'); ?></th></tr></thead>
                <tbody>
                    <?php $coin680_rank = 1; foreach ($coin680_gl['losers'] as $coin680_c) :
                        $coin680_p = (float) $coin680_c['current_price'];
                        $coin680_dec = $coin680_p < 1 ? 4 : ($coin680_p < 10 ? 3 : 2);
                        $coin680_chg = (float) $coin680_c['price_change_percentage_24h'];
                    ?>
                    <tr>
                        <td><?php echo esc_html($coin680_rank++); ?></td>
                        <td class="c680-prices-name"><strong><?php echo esc_html(strtoupper($coin680_c['symbol'])); ?></strong> <?php echo esc_html($coin680_c['name']); ?></td>
                        <td>$<?php echo esc_html(number_format($coin680_p, $coin680_dec)); ?></td>
                        <td class="c680-ticker-down">&#9660; <?php echo esc_html(number_format(abs($coin680_chg), 2)); ?>%</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <p class="c680-prices-updated"><?php esc_html_e('Prices update automatically every 60 seconds. Excludes stablecoins. Data provided by CoinGecko.', 'coin680'); ?></p>

    <script>
    document.querySelectorAll('.c680-gl-tab').forEach(function (tab) {
        tab.addEventListener('click', function () {
            document.querySelectorAll('.c680-gl-tab').forEach(function (t) { t.classList.remove('c680-gl-tab-active'); });
            tab.classList.add('c680-gl-tab-active');
            var target = tab.dataset.tab;
            document.querySelectorAll('.c680-gl-panel').forEach(function (panel) {
                panel.hidden = panel.dataset.panel !== target;
            });
        });
    });
    </script>
    <?php else : ?>
    <p><?php esc_html_e('Price data is temporarily unavailable. Please check back shortly.', 'coin680'); ?></p>
    <?php endif; ?>

    <div class="c680-page-content">
        <?php while (have_posts()) : the_post(); the_content(); endwhile; ?>
    </div>
</main>
<?php get_footer(); ?>
