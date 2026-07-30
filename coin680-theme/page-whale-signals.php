<?php
/**
 * Template Name: Live Whale Signals
 *
 * Public-facing feed of the same on-chain data the Whale Tracker admin page
 * and @coin680's X posts are built from (Coin680Bitquery_Fetcher +
 * Coin680Watchlist_Fetcher, both in the Coin680 Whale Tracker plugin) --
 * doesn't depend on X's reach/algorithm, and gives the data a permanent,
 * indexable home on the site itself. Added 2026-07-30 after upgrading to a
 * paid Bitquery plan opened up budget for more frequent polling.
 *
 * Server-rendered on each page load (same pattern as page-crypto-prices.php
 * / page-gainers-losers.php -- no AJAX polling, keeps this consistent with
 * the rest of the theme and avoids adding a new client-side data-fetching
 * pattern for one page). A plain JS reload button gives visitors an obvious
 * way to pull fresh data without needing to know to hit refresh themselves.
 */
get_header();

$coin680_ws_chain = isset($_GET['chain']) ? sanitize_key($_GET['chain']) : '';
$coin680_ws_valid_chains = class_exists('Coin680Bitquery_Labels') ? array_keys(Coin680Bitquery_Labels::CHAINS) : array();
if ($coin680_ws_chain && !in_array($coin680_ws_chain, $coin680_ws_valid_chains, true)) {
    $coin680_ws_chain = '';
}

$coin680_ws_smart_money = class_exists('Coin680Watchlist_Fetcher') ? Coin680Watchlist_Fetcher::get_recent(24, 15) : array();
$coin680_ws_signals = class_exists('Coin680Bitquery_Fetcher') ? Coin680Bitquery_Fetcher::get_recent(24, 40, 0, $coin680_ws_chain ?: null) : array();

/**
 * "3h 12m ago" style relative time from a MySQL UTC datetime string --
 * deliberately not using human_time_diff() alone, since that collapses to
 * just the largest unit ("3 hours") and loses precision that matters for a
 * fast-moving feed like this one.
 */
function coin680_ws_time_ago($mysql_datetime) {
    $diff = time() - strtotime($mysql_datetime . ' UTC');
    if ($diff < 60) {
        return esc_html__('just now', 'coin680');
    }
    $mins = (int) floor($diff / 60);
    if ($mins < 60) {
        return sprintf(esc_html__('%dm ago', 'coin680'), $mins);
    }
    $hours = (int) floor($mins / 60);
    $rem_mins = $mins % 60;
    if ($hours < 24) {
        return $rem_mins > 0
            ? sprintf(esc_html__('%1$dh %2$dm ago', 'coin680'), $hours, $rem_mins)
            : sprintf(esc_html__('%dh ago', 'coin680'), $hours);
    }
    return sprintf(esc_html__('%dd ago', 'coin680'), (int) floor($hours / 24));
}
?>
<main class="c680-page c680-prices-page c680-ws-page">
    <h1 class="c680-page-title"><?php echo esc_html(get_the_title() ?: __('Live Whale Signals', 'coin680')); ?></h1>
    <p class="c680-prices-intro">
        <?php esc_html_e('Real on-chain data, not sentiment -- large DEX swaps across Solana, BSC, Ethereum, and TRON, plus moves from specific wallets our team tracks. The same data behind every @coin680 whale-signal post, updated continuously.', 'coin680'); ?>
        <?php if (class_exists('Coin680Bitquery_Fetcher')) : ?>
            <a href="https://x.com/coin680" target="_blank" rel="noopener"><?php esc_html_e('Follow live alerts on X &rarr;', 'coin680'); ?></a>
        <?php endif; ?>
    </p>

    <?php if (!empty($coin680_ws_smart_money)) : ?>
    <section class="c680-ws-section">
        <h2 class="c680-section-title"><?php esc_html_e('Smart Money Moves', 'coin680'); ?></h2>
        <p class="c680-prices-intro"><?php esc_html_e('Activity from specific wallets our team watches -- flagged for WHO made the move, not size alone. Context for your own research, not a signal to copy blindly.', 'coin680'); ?></p>
        <div class="c680-prices-table-wrap">
            <table class="c680-prices-table">
                <thead><tr>
                    <th><?php esc_html_e('When', 'coin680'); ?></th>
                    <th><?php esc_html_e('Wallet', 'coin680'); ?></th>
                    <th><?php esc_html_e('Chain', 'coin680'); ?></th>
                    <th><?php esc_html_e('Action', 'coin680'); ?></th>
                    <th><?php esc_html_e('Amount', 'coin680'); ?></th>
                </tr></thead>
                <tbody>
                <?php foreach ($coin680_ws_smart_money as $item) :
                    $chain_cfg = class_exists('Coin680Bitquery_Labels') ? Coin680Bitquery_Labels::chain_config($item->chain) : null;
                    $wallet_display = $item->wallet_label ?: (substr($item->wallet_address, 0, 6) . '...' . substr($item->wallet_address, -4));
                ?>
                    <tr>
                        <td><?php echo esc_html(coin680_ws_time_ago($item->tx_timestamp)); ?></td>
                        <td class="c680-prices-name"><strong><?php echo esc_html($wallet_display); ?></strong></td>
                        <td><?php echo esc_html($chain_cfg['label'] ?? ucfirst($item->chain)); ?></td>
                        <td class="<?php echo $item->side === 'buy' ? 'c680-ticker-up' : 'c680-ticker-down'; ?>">
                            <?php echo $item->side === 'buy' ? '&#9650; ' . esc_html__('Bought', 'coin680') : '&#9660; ' . esc_html__('Sold', 'coin680'); ?>
                            <?php echo esc_html(strtoupper($item->symbol)); ?>
                        </td>
                        <td>$<?php echo esc_html(number_format($item->amount_usd)); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
    <?php endif; ?>

    <section class="c680-ws-section">
        <h2 class="c680-section-title"><?php esc_html_e('Whale Signals', 'coin680'); ?></h2>
        <?php if (!empty($coin680_ws_valid_chains)) : ?>
        <div class="c680-gl-tabs c680-ws-chain-filter">
            <a class="c680-gl-tab<?php echo $coin680_ws_chain === '' ? ' c680-gl-tab-active' : ''; ?>" href="<?php echo esc_url(remove_query_arg('chain')); ?>"><?php esc_html_e('All Chains', 'coin680'); ?></a>
            <?php foreach ($coin680_ws_valid_chains as $c) :
                $cfg = Coin680Bitquery_Labels::chain_config($c);
            ?>
                <a class="c680-gl-tab<?php echo $coin680_ws_chain === $c ? ' c680-gl-tab-active' : ''; ?>" href="<?php echo esc_url(add_query_arg('chain', $c)); ?>"><?php echo esc_html($cfg['label'] ?? ucfirst($c)); ?></a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div class="c680-prices-table-wrap">
            <table class="c680-prices-table">
                <thead><tr>
                    <th><?php esc_html_e('When', 'coin680'); ?></th>
                    <th><?php esc_html_e('Chain / Coin', 'coin680'); ?></th>
                    <th><?php esc_html_e('Type', 'coin680'); ?></th>
                    <th><?php esc_html_e('DEX', 'coin680'); ?></th>
                    <th><?php esc_html_e('Amount', 'coin680'); ?></th>
                    <th><?php esc_html_e('Tx', 'coin680'); ?></th>
                </tr></thead>
                <tbody>
                <?php foreach ($coin680_ws_signals as $item) :
                    $chain_cfg = class_exists('Coin680Bitquery_Labels') ? Coin680Bitquery_Labels::chain_config($item->chain) : null;
                    $tx_url = ($chain_cfg && $item->tx_hash) ? sprintf($chain_cfg['explorer'], $item->tx_hash) : '';
                    $type_class = 'DEX Buy' === $item->classification ? 'c680-ticker-up' : ('DEX Sell' === $item->classification ? 'c680-ticker-down' : '');
                ?>
                    <tr>
                        <td><?php echo esc_html(coin680_ws_time_ago($item->tx_timestamp)); ?></td>
                        <td class="c680-prices-name"><strong><?php echo esc_html(strtoupper($item->symbol)); ?></strong> <?php echo esc_html($chain_cfg['label'] ?? ucfirst($item->chain)); ?></td>
                        <td class="<?php echo esc_attr($type_class); ?>"><?php echo esc_html($item->classification); ?><?php echo $item->counter_symbol ? ' <small>vs ' . esc_html($item->counter_symbol) . '</small>' : ''; ?></td>
                        <td><?php echo esc_html($item->dex_name); ?></td>
                        <td>$<?php echo esc_html(number_format($item->amount_usd)); ?></td>
                        <td><?php if ($tx_url) : ?><a href="<?php echo esc_url($tx_url); ?>" target="_blank" rel="noopener"><?php esc_html_e('View', 'coin680'); ?></a><?php endif; ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($coin680_ws_signals)) : ?>
                    <tr><td colspan="6"><?php esc_html_e('No signals in this window yet -- check back shortly.', 'coin680'); ?></td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <p class="c680-prices-updated">
        <?php esc_html_e('Data refreshes continuously behind the scenes; reload this page for the latest.', 'coin680'); ?>
        <button type="button" class="c680-ws-refresh" onclick="location.reload();"><?php esc_html_e('Refresh now', 'coin680'); ?></button>
    </p>

    <div class="c680-page-content">
        <?php while (have_posts()) : the_post(); the_content(); endwhile; ?>
    </div>
</main>
<?php get_footer(); ?>
