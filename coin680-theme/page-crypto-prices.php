<?php
/**
 * Template Name: Crypto Prices Hub
 * Live top-15 coin price table, powered by the same CoinGecko integration used
 * for the homepage ticker (coin680_get_crypto_prices(), cached 60s).
 */
get_header();
$coin680_coins = coin680_get_crypto_prices(90);
?>
<main class="c680-page c680-prices-page">
    <h1 class="c680-page-title"><?php echo esc_html(get_the_title() ?: __('Live Cryptocurrency Prices Today', 'coin680')); ?></h1>
    <p class="c680-prices-intro">Real-time prices for the top 90 cryptocurrencies by market capitalization, updated automatically every 60 seconds. Track Bitcoin, Ethereum, and more in one place.</p>

    <?php if ($coin680_coins) : ?>
    <div class="c680-prices-table-wrap">
        <table class="c680-prices-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th><?php esc_html_e('Coin', 'coin680'); ?></th>
                    <th><?php esc_html_e('Price', 'coin680'); ?></th>
                    <th><?php esc_html_e('24h %', 'coin680'); ?></th>
                    <th class="c680-col-mcap"><?php esc_html_e('Market Cap', 'coin680'); ?></th>
                    <th class="c680-col-vol"><?php esc_html_e('Volume (24h)', 'coin680'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php
                $coin680_rank = 1;
                foreach ($coin680_coins as $coin680_c) :
                    $coin680_price = isset($coin680_c['current_price']) ? (float) $coin680_c['current_price'] : 0;
                    $coin680_change = isset($coin680_c['price_change_percentage_24h']) ? (float) $coin680_c['price_change_percentage_24h'] : 0;
                    $coin680_decimals = $coin680_price < 1 ? 4 : ($coin680_price < 10 ? 3 : 2);
                    $coin680_direction = $coin680_change >= 0 ? 'up' : 'down';
                    $coin680_link = ($coin680_c['id'] === 'bitcoin')
                        ? home_url('/bitcoin-price/')
                        : home_url('/bitcoin-price/?coin=' . rawurlencode($coin680_c['id']));
                ?>
                <tr class="c680-prices-row" data-href="<?php echo esc_url($coin680_link); ?>">
                    <td><?php echo esc_html($coin680_rank++); ?></td>
                    <td class="c680-prices-name">
                        <a href="<?php echo esc_url($coin680_link); ?>" title="<?php echo esc_attr($coin680_c['name']); ?>">
                            <strong><?php echo esc_html(strtoupper($coin680_c['symbol'])); ?></strong>
                        </a>
                    </td>
                    <td>$<?php echo esc_html(number_format($coin680_price, $coin680_decimals)); ?></td>
                    <td class="c680-ticker-<?php echo esc_attr($coin680_direction); ?>">
                        <?php echo $coin680_change >= 0 ? '&#9650;' : '&#9660;'; ?> <?php echo esc_html(number_format(abs($coin680_change), 2)); ?>%
                    </td>
                    <td class="c680-col-mcap">$<?php echo esc_html(number_format((float) ($coin680_c['market_cap'] ?? 0))); ?></td>
                    <td class="c680-col-vol">$<?php echo esc_html(number_format((float) ($coin680_c['total_volume'] ?? 0))); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <p class="c680-prices-updated"><?php esc_html_e('Prices update automatically every 60 seconds. Data provided by CoinGecko.', 'coin680'); ?></p>
    <script>
    document.querySelectorAll('.c680-prices-row').forEach(function (row) {
        row.addEventListener('click', function (e) {
            if (e.target.closest('a')) { return; }
            window.location = row.dataset.href;
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
