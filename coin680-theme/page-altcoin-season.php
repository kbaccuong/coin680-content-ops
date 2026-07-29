<?php
/**
 * Template Name: Altcoin Season Index
 * In-house 30-day Altcoin Season Index (coin680_get_altcoin_season_data(),
 * cached 6h) -- % of the top ~50 non-stablecoin coins that beat Bitcoin over
 * the trailing 30 days. See functions.php for the full methodology note.
 */
get_header();
$coin680_season = coin680_get_altcoin_season_data();
?>
<main class="c680-page c680-season-page">
    <h1 class="c680-page-title"><?php echo esc_html(get_the_title() ?: __('Altcoin Season Index', 'coin680')); ?></h1>
    <p class="c680-prices-intro"><?php esc_html_e('Is the market favoring Bitcoin or altcoins right now? A 30-day read on how many top coins are outperforming Bitcoin.', 'coin680'); ?></p>

    <?php if ($coin680_season) :
        $index = $coin680_season['index'];
        $class_slug = sanitize_title($coin680_season['classification']);
    ?>
    <div class="c680-season-card">
        <div class="c680-season-gauge-wrap">
            <div class="c680-season-bar">
                <div class="c680-season-marker" style="left: <?php echo esc_attr($index); ?>%;"></div>
            </div>
            <div class="c680-season-scale">
                <span><?php esc_html_e('Bitcoin Season', 'coin680'); ?></span>
                <span><?php esc_html_e('Altcoin Season', 'coin680'); ?></span>
            </div>
        </div>
        <div class="c680-season-readout c680-season-<?php echo esc_attr($class_slug); ?>">
            <span class="c680-season-value"><?php echo esc_html($index); ?></span>
            <span class="c680-season-label"><?php echo esc_html($coin680_season['classification']); ?></span>
        </div>
        <p class="c680-season-detail">
            <?php
            printf(
                /* translators: 1: number of outperforming coins, 2: total coins compared, 3: bitcoin's 30-day % change */
                esc_html__('%1$d of the top %2$d coins have outperformed Bitcoin over the last 30 days. Bitcoin is up %3$s%% in that period.', 'coin680'),
                (int) $coin680_season['outperforming'],
                (int) $coin680_season['total'],
                esc_html(number_format($coin680_season['btc_change'], 1))
            );
            ?>
        </p>
    </div>

    <?php if (!empty($coin680_season['top_performers'])) : ?>
    <h2 class="c680-season-subtitle"><?php esc_html_e('Top 30-Day Outperformers vs. Bitcoin', 'coin680'); ?></h2>
    <div class="c680-prices-table-wrap">
        <table class="c680-prices-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th><?php esc_html_e('Coin', 'coin680'); ?></th>
                    <th><?php esc_html_e('30d %', 'coin680'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php $coin680_rank = 1; foreach ($coin680_season['top_performers'] as $coin680_c) :
                    $coin680_chg = (float) $coin680_c['price_change_percentage_30d_in_currency'];
                ?>
                <tr>
                    <td><?php echo esc_html($coin680_rank++); ?></td>
                    <td class="c680-prices-name"><strong><?php echo esc_html(strtoupper($coin680_c['symbol'])); ?></strong> <?php echo esc_html($coin680_c['name']); ?></td>
                    <td class="c680-ticker-<?php echo $coin680_chg >= 0 ? 'up' : 'down'; ?>">
                        <?php echo $coin680_chg >= 0 ? '&#9650;' : '&#9660;'; ?> <?php echo esc_html(number_format(abs($coin680_chg), 1)); ?>%
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
    <p class="c680-prices-updated"><?php esc_html_e('Index recalculated automatically every few hours from live market data.', 'coin680'); ?></p>
    <?php else : ?>
    <p><?php esc_html_e('Index data is temporarily unavailable. Please check back shortly.', 'coin680'); ?></p>
    <?php endif; ?>

    <div class="c680-page-content">
        <?php while (have_posts()) : the_post(); the_content(); endwhile; ?>
    </div>
</main>
<?php get_footer(); ?>
