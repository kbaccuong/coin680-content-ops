<?php
/**
 * Template Name: Bitcoin Halving Countdown
 * Live countdown to Bitcoin's next block-reward halving, computed from the
 * current block height (coin680_get_halving_info(), cached 10 min). The
 * on-page timer itself ticks client-side every second from the server-computed
 * target timestamp, so it stays smooth between the 10-minute data refreshes.
 */
get_header();
$coin680_halving = coin680_get_halving_info();
?>
<main class="c680-page c680-halving-page">
    <h1 class="c680-page-title"><?php echo esc_html(get_the_title() ?: __('Bitcoin Halving Countdown', 'coin680')); ?></h1>
    <p class="c680-prices-intro"><?php esc_html_e("Live countdown to Bitcoin's next block reward halving, based on the current block height.", 'coin680'); ?></p>

    <?php if ($coin680_halving) : ?>
    <div class="c680-halving-card">
        <div class="c680-halving-countdown" data-target="<?php echo esc_attr($coin680_halving['estimated_timestamp']); ?>">
            <div class="c680-halving-unit"><span class="c680-halving-num" data-unit="days">--</span><span class="c680-halving-label"><?php esc_html_e('Days', 'coin680'); ?></span></div>
            <div class="c680-halving-unit"><span class="c680-halving-num" data-unit="hours">--</span><span class="c680-halving-label"><?php esc_html_e('Hours', 'coin680'); ?></span></div>
            <div class="c680-halving-unit"><span class="c680-halving-num" data-unit="minutes">--</span><span class="c680-halving-label"><?php esc_html_e('Minutes', 'coin680'); ?></span></div>
            <div class="c680-halving-unit"><span class="c680-halving-num" data-unit="seconds">--</span><span class="c680-halving-label"><?php esc_html_e('Seconds', 'coin680'); ?></span></div>
        </div>
        <p class="c680-halving-eta"><?php esc_html_e('Estimated date:', 'coin680'); ?> <strong><?php echo esc_html(date_i18n('F j, Y', $coin680_halving['estimated_timestamp'])); ?></strong> <?php esc_html_e('(based on the current average block time -- the exact day can shift as network hash rate changes)', 'coin680'); ?></p>
    </div>

    <table class="c680-halving-stats-table">
        <tbody>
            <tr><th><?php esc_html_e('Current Block Height', 'coin680'); ?></th><td>#<?php echo esc_html(number_format($coin680_halving['height'])); ?></td></tr>
            <tr><th><?php esc_html_e('Next Halving Block', 'coin680'); ?></th><td>#<?php echo esc_html(number_format($coin680_halving['next_halving_block'])); ?></td></tr>
            <tr><th><?php esc_html_e('Blocks Remaining', 'coin680'); ?></th><td><?php echo esc_html(number_format($coin680_halving['blocks_remaining'])); ?></td></tr>
            <tr><th><?php esc_html_e('Current Block Reward', 'coin680'); ?></th><td><?php echo esc_html(rtrim(rtrim(number_format($coin680_halving['current_reward'], 8), '0'), '.')); ?> BTC</td></tr>
            <tr><th><?php esc_html_e('Reward After Next Halving', 'coin680'); ?></th><td><?php echo esc_html(rtrim(rtrim(number_format($coin680_halving['next_reward'], 8), '0'), '.')); ?> BTC</td></tr>
        </tbody>
    </table>
    <p class="c680-prices-updated"><?php esc_html_e('Block height updates automatically. Countdown is an estimate based on average block time, not a fixed date.', 'coin680'); ?></p>

    <script>
    (function () {
        var el = document.querySelector('.c680-halving-countdown');
        if (!el) return;
        var target = parseInt(el.dataset.target, 10) * 1000;
        function tick() {
            var diff = Math.max(0, target - Date.now());
            var s = Math.floor(diff / 1000);
            var days = Math.floor(s / 86400);
            var hours = Math.floor((s % 86400) / 3600);
            var minutes = Math.floor((s % 3600) / 60);
            var seconds = s % 60;
            el.querySelector('[data-unit="days"]').textContent = days;
            el.querySelector('[data-unit="hours"]').textContent = String(hours).padStart(2, '0');
            el.querySelector('[data-unit="minutes"]').textContent = String(minutes).padStart(2, '0');
            el.querySelector('[data-unit="seconds"]').textContent = String(seconds).padStart(2, '0');
        }
        tick();
        setInterval(tick, 1000);
    })();
    </script>
    <?php else : ?>
    <p><?php esc_html_e('Block height data is temporarily unavailable. Please check back shortly.', 'coin680'); ?></p>
    <?php endif; ?>

    <div class="c680-page-content">
        <?php while (have_posts()) : the_post(); the_content(); endwhile; ?>
    </div>
</main>
<?php get_footer(); ?>
