<?php
/**
 * Template Name: Single Coin Price
 * Reads the coin id either from the "?coin=" query var (used by links from the
 * Crypto Prices hub table, so all 90 coins share this one page instead of
 * needing a dedicated WP page each) or, if absent, from the "coin680_coin_id"
 * post meta (used by the default /bitcoin-price/ page itself). Matched against
 * the same cached top-100 CoinGecko list used by the Crypto Prices hub.
 */
get_header();
$coin680_query_coin = isset($_GET['coin']) ? sanitize_title(wp_unslash($_GET['coin'])) : '';
$coin680_coin_id = $coin680_query_coin ?: get_post_meta(get_the_ID(), 'coin680_coin_id', true);
$coin680_coins = coin680_get_crypto_prices(100);
$coin680_coin = null;
if ($coin680_coins && $coin680_coin_id) {
    foreach ($coin680_coins as $coin680_c) {
        if ($coin680_c['id'] === $coin680_coin_id) {
            $coin680_coin = $coin680_c;
            break;
        }
    }
}
$coin680_page_title = $coin680_coin
    ? sprintf(/* translators: %s: coin name */ __('%s Price Today', 'coin680'), esc_html($coin680_coin['name']))
    : get_the_title();
?>
<main class="c680-page c680-coin-price-page">
    <h1 class="c680-page-title"><?php echo esc_html($coin680_page_title); ?></h1>
    <p class="c680-prices-back"><a href="<?php echo esc_url(home_url('/crypto-prices/')); ?>">&larr; <?php esc_html_e('All Prices', 'coin680'); ?></a></p>

    <?php if ($coin680_coin) :
        $coin680_price = (float) $coin680_coin['current_price'];
        $coin680_change = isset($coin680_coin['price_change_percentage_24h']) ? (float) $coin680_coin['price_change_percentage_24h'] : 0;
        $coin680_direction = $coin680_change >= 0 ? 'up' : 'down';
        $coin680_decimals = $coin680_price < 1 ? 4 : ($coin680_price < 10 ? 3 : 2);
    ?>
    <div class="c680-coin-price-box">
        <div class="c680-coin-price-main">$<?php echo esc_html(number_format($coin680_price, $coin680_decimals)); ?></div>
        <div class="c680-coin-price-change c680-ticker-<?php echo esc_attr($coin680_direction); ?>">
            <?php echo $coin680_change >= 0 ? '&#9650;' : '&#9660;'; ?> <?php echo esc_html(number_format(abs($coin680_change), 2)); ?>% <?php esc_html_e('(24h)', 'coin680'); ?>
        </div>
    </div>
    <table class="c680-coin-stats-table">
        <tbody>
            <tr><th><?php esc_html_e('Market Cap', 'coin680'); ?></th><td>$<?php echo esc_html(number_format((float) ($coin680_coin['market_cap'] ?? 0))); ?></td></tr>
            <tr><th><?php esc_html_e('24h Trading Volume', 'coin680'); ?></th><td>$<?php echo esc_html(number_format((float) ($coin680_coin['total_volume'] ?? 0))); ?></td></tr>
            <tr><th><?php esc_html_e('Market Cap Rank', 'coin680'); ?></th><td>#<?php echo esc_html($coin680_coin['market_cap_rank'] ?? '-'); ?></td></tr>
            <tr><th><?php esc_html_e('Circulating Supply', 'coin680'); ?></th><td><?php echo esc_html(number_format((float) ($coin680_coin['circulating_supply'] ?? 0))); ?> <?php echo esc_html(strtoupper($coin680_coin['symbol'])); ?></td></tr>
        </tbody>
    </table>
    <p class="c680-prices-updated"><?php esc_html_e('Price updates automatically every 60 seconds. Data provided by CoinGecko.', 'coin680'); ?></p>
    <?php else : ?>
    <p><?php esc_html_e('Price data is temporarily unavailable. Please check back shortly.', 'coin680'); ?></p>
    <?php endif; ?>

    <div class="c680-page-content">
        <?php while (have_posts()) : the_post(); the_content(); endwhile; ?>
    </div>
</main>
<?php get_footer(); ?>
