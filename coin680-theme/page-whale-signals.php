<?php
/**
 * Template Name: Live Whale Signals
 *
 * Public-facing feed of the same on-chain data the Whale Tracker admin page
 * and @coin680's X posts are built from (Coin680Bitquery_Fetcher +
 * Coin680Watchlist_Fetcher, both in the Coin680 Whale Tracker plugin) --
 * doesn't depend on X's reach/algorithm, and gives the data a permanent,
 * indexable home on the site itself.
 *
 * Initial load is server-rendered (SEO + no-JS fallback, same principle as
 * page-crypto-prices.php / page-gainers-losers.php). On top of that, page 1
 * (the "live" view) polls the /coin680/v1/whale-signals REST endpoint every
 * ~20s and replaces the table body -- a genuinely fresh feed without the
 * WebSocket/persistent-connection infrastructure this shared hosting can't
 * run; the underlying data itself only actually changes as often as the
 * Bitquery poll cron runs (every 2 min), so 20s polling is about giving the
 * PAGE a live feel, not implying faster-than-that real data turnover.
 * Pagination beyond page 1 is browsing history, not a live view, so
 * auto-refresh is intentionally off there.
 *
 * Added 2026-07-30 after upgrading to a paid Bitquery plan; expanded same
 * day with pagination/filters/live-refresh/public watchlist directory per
 * direct follow-up request.
 */
get_header();

$coin680_ws_chain = isset($_GET['chain']) ? sanitize_key($_GET['chain']) : '';
$coin680_ws_valid_chains = class_exists('Coin680Bitquery_Labels') ? array_keys(Coin680Bitquery_Labels::CHAINS) : array();
if ($coin680_ws_chain && !in_array($coin680_ws_chain, $coin680_ws_valid_chains, true)) {
    $coin680_ws_chain = '';
}
$coin680_ws_type = isset($_GET['type']) ? sanitize_key($_GET['type']) : '';
if (!in_array($coin680_ws_type, array('buy', 'sell', 'swap'), true)) {
    $coin680_ws_type = '';
}
$coin680_ws_page = max(1, (int) ($_GET['paged'] ?? 1));

$coin680_ws_result = coin680_ws_query_signals($coin680_ws_chain, $coin680_ws_type, $coin680_ws_page);
$coin680_ws_smart_money_raw = class_exists('Coin680Watchlist_Fetcher') ? Coin680Watchlist_Fetcher::get_recent(24, 15) : array();
$coin680_ws_smart_money = array_map('coin680_ws_format_watchlist_move', $coin680_ws_smart_money_raw);
$coin680_ws_watched_wallets = class_exists('Coin680Watchlist') ? Coin680Watchlist::get_all(true) : array();

$coin680_ws_type_labels = array('' => __('All Types', 'coin680'), 'buy' => __('Buys', 'coin680'), 'sell' => __('Sells', 'coin680'), 'swap' => __('Swaps', 'coin680'));
?>
<main class="c680-page c680-prices-page c680-ws-page">
    <h1 class="c680-page-title"><?php echo esc_html(get_the_title() ?: __('Live Whale Signals', 'coin680')); ?></h1>
    <p class="c680-prices-intro">
        <?php esc_html_e('Real on-chain data, not sentiment -- large DEX swaps across Solana, BSC, Ethereum, and TRON, plus moves from specific wallets our team tracks. The same data behind every @coin680 whale-signal post.', 'coin680'); ?>
        <a href="https://x.com/coin680" target="_blank" rel="noopener"><?php esc_html_e('Follow live alerts on X &rarr;', 'coin680'); ?></a>
    </p>

    <?php if (!empty($coin680_ws_watched_wallets)) : ?>
    <section class="c680-ws-section">
        <h2 class="c680-section-title"><?php esc_html_e('Watched Wallets', 'coin680'); ?></h2>
        <p class="c680-prices-intro"><?php esc_html_e('The specific addresses our team currently tracks. Any activity from these wallets shows up in Smart Money Moves below as soon as it happens.', 'coin680'); ?></p>
        <div class="c680-ws-wallet-grid">
            <?php foreach ($coin680_ws_watched_wallets as $w) :
                $cfg = class_exists('Coin680Bitquery_Labels') ? Coin680Bitquery_Labels::chain_config($w->chain) : null;
                $short = substr($w->address, 0, 6) . '...' . substr($w->address, -4);
            ?>
                <div class="c680-ws-wallet-card">
                    <span class="c680-ws-wallet-chain"><?php echo esc_html($cfg['label'] ?? ucfirst($w->chain)); ?></span>
                    <strong class="c680-ws-wallet-label"><?php echo esc_html($w->label ?: $short); ?></strong>
                    <span class="c680-ws-wallet-address"><?php echo esc_html($short); ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <?php if (!empty($coin680_ws_smart_money)) : ?>
    <section class="c680-ws-section">
        <h2 class="c680-section-title"><?php esc_html_e('Smart Money Moves', 'coin680'); ?></h2>
        <p class="c680-prices-intro"><?php esc_html_e('Flagged for WHO made the move, not size alone -- context for your own research, not a signal to copy blindly.', 'coin680'); ?></p>
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
                <?php foreach ($coin680_ws_smart_money as $item) : ?>
                    <tr>
                        <td><?php echo esc_html($item['time_ago']); ?></td>
                        <td class="c680-prices-name"><strong><?php echo esc_html($item['wallet_label']); ?></strong></td>
                        <td><?php echo esc_html($item['chain_label']); ?></td>
                        <td class="<?php echo $item['side'] === 'buy' ? 'c680-ticker-up' : 'c680-ticker-down'; ?>">
                            <?php echo $item['side'] === 'buy' ? '&#9650; ' . esc_html__('Bought', 'coin680') : '&#9660; ' . esc_html__('Sold', 'coin680'); ?>
                            <?php echo esc_html($item['symbol']); ?>
                        </td>
                        <td><?php echo esc_html($item['amount_fmt']); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
    <?php endif; ?>

    <section class="c680-ws-section">
        <div class="c680-ws-heading-row">
            <h2 class="c680-section-title"><?php esc_html_e('Whale Signals', 'coin680'); ?></h2>
            <?php if ($coin680_ws_page === 1) : ?>
                <span class="c680-ws-live-badge"><span class="c680-ws-live-dot"></span><?php esc_html_e('Live', 'coin680'); ?></span>
            <?php endif; ?>
        </div>

        <?php if (!empty($coin680_ws_valid_chains)) : ?>
        <div class="c680-gl-tabs c680-ws-filter-row">
            <a class="c680-gl-tab<?php echo $coin680_ws_chain === '' ? ' c680-gl-tab-active' : ''; ?>" href="<?php echo esc_url(remove_query_arg(array('chain', 'paged'))); ?>"><?php esc_html_e('All Chains', 'coin680'); ?></a>
            <?php foreach ($coin680_ws_valid_chains as $c) :
                $cfg = Coin680Bitquery_Labels::chain_config($c);
            ?>
                <a class="c680-gl-tab<?php echo $coin680_ws_chain === $c ? ' c680-gl-tab-active' : ''; ?>" href="<?php echo esc_url(add_query_arg('chain', $c, remove_query_arg('paged'))); ?>"><?php echo esc_html($cfg['label'] ?? ucfirst($c)); ?></a>
            <?php endforeach; ?>
        </div>
        <div class="c680-gl-tabs c680-ws-filter-row">
            <?php foreach ($coin680_ws_type_labels as $t => $label) : ?>
                <a class="c680-gl-tab<?php echo $coin680_ws_type === $t ? ' c680-gl-tab-active' : ''; ?>" href="<?php echo esc_url($t ? add_query_arg('type', $t, remove_query_arg('paged')) : remove_query_arg(array('type', 'paged'))); ?>"><?php echo esc_html($label); ?></a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div class="c680-prices-table-wrap">
            <table class="c680-prices-table" id="c680-ws-table">
                <thead><tr>
                    <th><?php esc_html_e('When', 'coin680'); ?></th>
                    <th><?php esc_html_e('Chain / Coin', 'coin680'); ?></th>
                    <th><?php esc_html_e('Type', 'coin680'); ?></th>
                    <th><?php esc_html_e('DEX', 'coin680'); ?></th>
                    <th><?php esc_html_e('Amount', 'coin680'); ?></th>
                    <th><?php esc_html_e('Tx', 'coin680'); ?></th>
                </tr></thead>
                <tbody id="c680-ws-tbody">
                    <?php echo coin680_ws_render_rows($coin680_ws_result['items']); ?>
                </tbody>
            </table>
        </div>

        <?php if ($coin680_ws_result['pages'] > 1) : ?>
        <div class="c680-ws-pagination">
            <?php if ($coin680_ws_page > 1) : ?>
                <a class="button" href="<?php echo esc_url(add_query_arg('paged', $coin680_ws_page - 1)); ?>">&laquo; <?php esc_html_e('Prev', 'coin680'); ?></a>
            <?php endif; ?>
            <span class="c680-ws-page-info"><?php echo esc_html(sprintf(__('Page %1$d of %2$d', 'coin680'), $coin680_ws_page, $coin680_ws_result['pages'])); ?></span>
            <?php if ($coin680_ws_page < $coin680_ws_result['pages']) : ?>
                <a class="button" href="<?php echo esc_url(add_query_arg('paged', $coin680_ws_page + 1)); ?>"><?php esc_html_e('Next', 'coin680'); ?> &raquo;</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <p class="c680-prices-updated" id="c680-ws-updated">
            <?php if ($coin680_ws_page === 1) : ?>
                <?php esc_html_e('Auto-refreshing every ~20s.', 'coin680'); ?>
            <?php else : ?>
                <?php esc_html_e('Browsing history -- go to page 1 for the live feed.', 'coin680'); ?>
            <?php endif; ?>
        </p>
    </section>

    <div class="c680-page-content">
        <?php while (have_posts()) : the_post(); the_content(); endwhile; ?>
    </div>
</main>

<?php if ($coin680_ws_page === 1) : ?>
<script>
(function () {
    var chain = <?php echo wp_json_encode($coin680_ws_chain); ?>;
    var type = <?php echo wp_json_encode($coin680_ws_type); ?>;
    var endpoint = <?php echo wp_json_encode(rest_url('coin680/v1/whale-signals')); ?>;
    var tbody = document.getElementById('c680-ws-tbody');
    var statusEl = document.getElementById('c680-ws-updated');

    function esc(s) {
        var d = document.createElement('div');
        d.textContent = s == null ? '' : String(s);
        return d.innerHTML;
    }

    function renderRow(item) {
        var typeClass = item.classification === 'DEX Buy' ? 'c680-ticker-up' : (item.classification === 'DEX Sell' ? 'c680-ticker-down' : '');
        var bigBadge = item.is_big ? ' <span class="c680-ws-big-badge">&#128293; Big</span>' : '';
        var counter = item.counter_symbol ? ' <small>vs ' + esc(item.counter_symbol) + '</small>' : '';
        var txLink = item.tx_url ? '<a href="' + esc(item.tx_url) + '" target="_blank" rel="noopener">View</a>' : '';
        return '<tr class="' + (item.is_big ? 'c680-ws-row-big' : '') + '">' +
            '<td>' + esc(item.time_ago) + '</td>' +
            '<td class="c680-prices-name"><strong>' + esc(item.symbol) + '</strong> ' + esc(item.chain_label) + bigBadge + '</td>' +
            '<td class="' + typeClass + '">' + esc(item.classification) + counter + '</td>' +
            '<td>' + esc(item.dex_name) + '</td>' +
            '<td>' + esc(item.amount_fmt) + '</td>' +
            '<td>' + txLink + '</td>' +
            '</tr>';
    }

    function refresh() {
        var url = endpoint + '?page=1';
        if (chain) { url += '&chain=' + encodeURIComponent(chain); }
        if (type) { url += '&type=' + encodeURIComponent(type); }
        fetch(url)
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data || !data.items) { return; }
                if (data.items.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="6"><?php echo esc_js(__('No signals in this window yet -- check back shortly.', 'coin680')); ?></td></tr>';
                } else {
                    tbody.innerHTML = data.items.map(renderRow).join('');
                }
                if (statusEl) {
                    statusEl.textContent = <?php echo wp_json_encode(__('Auto-refreshing every ~20s. Last updated: ', 'coin680')); ?> + new Date().toLocaleTimeString();
                }
            })
            .catch(function () { /* silent -- keep last good render on a transient failure */ });
    }

    setInterval(refresh, 20000);
})();
</script>
<?php endif; ?>

<?php get_footer(); ?>
