<?php
/**
 * Builds and posts the recurring "Whale Signal" digest to X, plus standalone
 * breaking alerts for mega transactions and a once-daily recap.
 *
 * No fixed time cadence -- checked every 5 min, but only actually posts once
 * at least MIN_COINS_TO_POST distinct coins have accumulated since the last
 * post (however long that takes). Prioritizes a genuinely varied post over a
 * guaranteed posting interval; per direct feedback, a thin 1-2-coin post
 * every 30 minutes was worse than an occasional longer gap for a fuller
 * post. Pulls from TWO sources: Coin680Whale_Fetcher (Whale Alert -- BTC,
 * ETH, XRP, TRX, and other chains Whale Alert covers, with real exchange
 * labels) and Coin680MultiChain_Fetcher (Ethereum/Polygon/Arbitrum via
 * Etherscan, with DEX-swap detection) -- normalized into one pool so coin
 * diversity and "no duplicate coin" rules apply across both together.
 * Uses only real, already-classified transactions -- no fabricated
 * correlations or invented price reactions. The per-classification framing
 * is standard, widely used analyst shorthand, not a specific claim about
 * what will happen next. Historical comparisons only ever cite numbers
 * actually stored in our own table at the time each transaction was
 * captured.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Coin680Whale_Digest {
    private static $instance = null;
    const MIN_COINS_TO_POST = 3;

    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('coin680whale_digest_check', array($this, 'maybe_post_digest'));
        add_action('coin680whale_daily_recap', array($this, 'post_daily_recap'));
    }

    private static function explorer_url($blockchain, $hash) {
        $map = array(
            'bitcoin'    => 'https://mempool.space/tx/%s',
            'ethereum'   => 'https://etherscan.io/tx/%s',
            'tron'       => 'https://tronscan.org/#/transaction/%s',
            'solana'     => 'https://solscan.io/tx/%s',
            'ripple'     => 'https://xrpscan.com/tx/%s',
            'litecoin'   => 'https://blockchair.com/litecoin/transaction/%s',
            'bitcoin cash' => 'https://blockchair.com/bitcoin-cash/transaction/%s',
            'binancechain' => 'https://bscscan.com/tx/%s',
            'polygon'    => 'https://polygonscan.com/tx/%s',
            'avalanche'  => 'https://snowtrace.io/tx/%s',
            'cardano'    => 'https://cardanoscan.io/transaction/%s',
            'stellar'    => 'https://stellar.expert/explorer/public/tx/%s',
            'eos'        => 'https://bloks.io/transaction/%s',
            'algorand'   => 'https://allo.info/tx/%s',
            'polkadot'   => 'https://polkadot.subscan.io/extrinsic/%s',
            'arbitrum'   => 'https://arbiscan.io/tx/%s',
            'optimism'   => 'https://optimistic.etherscan.io/tx/%s',
            'base'       => 'https://basescan.org/tx/%s',
        );
        $key = strtolower($blockchain);
        if (isset($map[$key]) && $hash) {
            return sprintf($map[$key], $hash);
        }
        return '';
    }

    /**
     * Names the actual exchange(s)/DEX whenever we have a real label --
     * "left Bybit" / "moved onto OKX" / "acquired via a swap on Uniswap" --
     * rather than the generic "an exchange"/"a DEX", falling back to
     * generic phrasing only when there's no label to offer. Works on either
     * a Whale Alert row or a Coin680MultiChain_Fetcher row -- both store
     * the same from_owner/from_owner_type/to_owner/to_owner_type columns.
     */
    private static function classification_blurb($raw) {
        $from = $raw->from_owner ?? '';
        $to = $raw->to_owner ?? '';
        $from_named = (($raw->from_owner_type ?? '') === 'exchange' && $from && strtolower($from) !== 'unknown');
        $to_named = (($raw->to_owner_type ?? '') === 'exchange' && $to && strtolower($to) !== 'unknown');
        $dex_name = $raw->dex_name ?? '';
        $counter_symbol = $raw->counter_symbol ?? '';

        switch ($raw->classification) {
            case 'Exchange Outflow':
                return $from_named
                    ? "left {$from} for an unlabeled wallet -- often read as accumulation, not selling"
                    : 'left an exchange for an unlabeled wallet -- often read as accumulation, not selling';
            case 'Exchange Inflow':
                return $to_named
                    ? "moved onto {$to} -- often read as potential sell-side pressure"
                    : 'moved onto an exchange -- often read as potential sell-side pressure';
            case 'Exchange to Exchange':
                if ($from_named && $to_named) {
                    return "moved from {$from} to {$to}";
                }
                return 'moved directly between two exchanges';
            case 'Mint':
                return 'was freshly minted -- new supply entering circulation';
            case 'Burn':
                return 'was burned, reducing circulating supply';
            case 'Wallet Transfer':
                return 'moved between two unlabeled wallets -- purpose unclear';
            case 'DEX Buy':
                return $dex_name
                    ? "was acquired via a direct swap on {$dex_name} -- read as a fresh position being opened, bypassing centralized exchange order books entirely"
                    : 'was acquired via a direct on-chain swap -- bypassing centralized exchange order books entirely';
            case 'DEX Sell':
                return $dex_name
                    ? "was sold via a direct swap on {$dex_name} -- read as profit-taking or de-risking, converted straight to stablecoin without routing through a CEX"
                    : 'was sold via a direct on-chain swap -- converted straight to stablecoin without routing through a CEX';
            case 'DEX Swap':
                $counter = $counter_symbol ? " for {$counter_symbol}" : '';
                return $dex_name
                    ? "was swapped{$counter} via {$dex_name} -- a direct asset-for-asset rotation, not a simple transfer"
                    : "was swapped{$counter} on-chain -- a direct asset-for-asset rotation, not a simple transfer";
            default:
                return 'moved on-chain';
        }
    }

    /**
     * X treats a leading $ before a ticker as a "cashtag" (clickable/
     * searchable, same idea as a # hashtag) -- every coin symbol printed
     * in a post should go through this rather than a bare strtoupper().
     */
    private static function cashtag($symbol) {
        return '$' . strtoupper($symbol);
    }

    private function current_btc_price() {
        if (!function_exists('coin680_get_crypto_prices')) {
            return 0;
        }
        $coins = coin680_get_crypto_prices();
        if (!$coins) {
            return 0;
        }
        foreach ($coins as $coin) {
            if ($coin['id'] === 'bitcoin') {
                return (float) $coin['current_price'];
            }
        }
        return 0;
    }

    /**
     * Net USD flow into vs. out of exchanges across ALL qualifying Whale
     * Alert transactions in the window (not just the ones featured in the
     * post). Deliberately scoped to the Whale Alert table only for now --
     * the multichain side's DEX-heavy classifications don't map cleanly
     * onto "exchange flow" the same way, so mixing them in would blur what
     * this number actually means.
     */
    private function net_exchange_flow($since) {
        global $wpdb;
        $table = Coin680Whale_Fetcher::table_name();
        $inflow = (float) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(amount_usd),0) FROM $table WHERE classification='Exchange Inflow' AND tx_timestamp >= %s", $since
        ));
        $outflow = (float) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(amount_usd),0) FROM $table WHERE classification='Exchange Outflow' AND tx_timestamp >= %s", $since
        ));
        return array('inflow' => $inflow, 'outflow' => $outflow, 'net' => $inflow - $outflow);
    }

    /**
     * Finds a real, previously captured Whale Alert transaction of similar
     * size and the same classification, at least 6 hours old, and reports
     * the actual BTC price change from that capture moment to right now.
     * Returns '' if no honest match exists -- never fabricates one. Only
     * looks at the Whale Alert table (the one with a stored btc_price_usd
     * snapshot); multichain rows don't have this snapshot.
     */
    private function historical_comparison($item) {
        if ($item->source !== 'whale_alert') {
            return '';
        }
        global $wpdb;
        $table = Coin680Whale_Fetcher::table_name();
        $current_price = $this->current_btc_price();
        if (!$current_price || (float) $item->amount_usd <= 0) {
            return '';
        }
        $low = $item->amount_usd * 0.7;
        $high = $item->amount_usd * 1.4;
        $cutoff = gmdate('Y-m-d H:i:s', time() - 6 * HOUR_IN_SECONDS);

        $match = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE classification = %s AND amount_usd BETWEEN %f AND %f
             AND btc_price_usd > 0 AND tx_timestamp < %s AND id != %d
             ORDER BY tx_timestamp DESC LIMIT 1",
            $item->raw->classification, $low, $high, $cutoff, $item->id
        ));

        if (!$match) {
            return '';
        }

        $pct_change = (($current_price - $match->btc_price_usd) / $match->btc_price_usd) * 100;
        $direction = $pct_change >= 0 ? 'up' : 'down';
        $when = human_time_diff(strtotime($match->tx_timestamp), time()) . ' ago';
        return sprintf(
            // Plain "BTC" here, not a $BTC cashtag -- the digest's one
            // allowed cashtag is already spent on the featured transaction's
            // own symbol, and X rejects posts with more than one.
            'For context: the last similarly sized %s (%s) was captured with BTC at $%s -- BTC is %s %s%% since.',
            strtolower($item->raw->classification),
            $when,
            number_format($match->btc_price_usd),
            $direction,
            number_format(abs($pct_change), 1)
        );
    }

    private function build_closing_analysis($items, $since) {
        $flow = $this->net_exchange_flow($since);
        $net = $flow['net'];
        $direction_word = $net > 0 ? 'into' : 'out of';
        $abs_net_fmt = '$' . number_format(abs($net));

        if (abs($net) < 250000) {
            $flow_sentence = "Net exchange flow this window is close to flat ({$abs_net_fmt} {$direction_word} exchanges) -- no strong directional pressure either way.";
        } elseif ($net < 0) {
            $flow_sentence = "Net Exchange Flow: {$abs_net_fmt} moved off exchanges this window, more than came in. That kind of net outflow has historically leaned toward accumulation -- large holders parking coins in cold storage rather than positioning to sell.";
        } else {
            $flow_sentence = "Net Exchange Flow: {$abs_net_fmt} moved onto exchanges this window, more than left. Net inflows like this are worth watching, since coins sitting on an exchange are coins that can be sold quickly if sentiment turns.";
        }

        // Historical comparison, if a real one exists, from the single largest featured item.
        $biggest = $items[0];
        foreach ($items as $candidate) {
            if ($candidate->amount_usd > $biggest->amount_usd) {
                $biggest = $candidate;
            }
        }
        $history = $this->historical_comparison($biggest);

        $takeaways = array(
            'For traders, this is a data point to weigh alongside price action, not a signal to act on alone -- exchange flows shift the available supply, they don\'t guarantee direction.',
            'None of this guarantees where price goes next, but it does tell you where supply is moving -- and that\'s usually the part of the story raw price charts miss.',
            'Flows like this shift how much supply is sitting in easy reach of a sale, which matters most when combined with what price is already doing.',
        );

        $questions = array(
            'What\'s your read -- accumulation or distribution?',
            'Bullish or bearish signal to you? Drop your take below.',
            'Are whales buying the dip or setting up to sell? Curious what you think.',
            'Does this change how you\'re positioned right now?',
        );

        $parts = array($flow_sentence);
        if ($history) {
            $parts[] = $history;
        }
        $parts[] = $takeaways[array_rand($takeaways)];
        $parts[] = $questions[array_rand($questions)];

        return implode(' ', $parts);
    }

    public function build_and_get_text($items, $since, $window_label = '30 min') {
        $header = "🐋 #COIN680 WHALE SIGNAL (last {$window_label}):\n\n";
        $lines = array();
        $cashtag_used = false;
        foreach ($items as $item) {
            $amount_fmt = '$' . number_format($item->amount_usd);
            $blurb = self::classification_blurb($item->raw);
            $url = $item->url;
            // X rejects an entire post outright if it contains more than one
            // cashtag ($SYMBOL), and a multi-item digest routinely spans several
            // different coins -- so only the single largest entry gets the
            // cashtag treatment; the rest fall back to a plain symbol.
            $symbol_display = $cashtag_used ? strtoupper($item->symbol) : self::cashtag($item->symbol);
            $cashtag_used = true;
            $line = "• {$amount_fmt} {$symbol_display} ({$item->chain_label}) {$blurb}.";
            if ($url) {
                $line .= " {$url}";
            }
            $lines[] = $line;
        }
        $body = implode("\n\n", $lines);
        $closing = "\n\n" . $this->build_closing_analysis($items, $since);
        $hashtags = "\n\n#Crypto #WhaleAlert #OnChain #Multichain";
        return $header . $body . $closing . $hashtags;
    }

    /**
     * Pulls unused rows from both Coin680Whale_Fetcher (Whale Alert) and
     * Coin680MultiChain_Fetcher (Etherscan-based EVM chains, if that class
     * is loaded), normalizes them into a common shape, and returns them
     * sorted largest-first. This is the combined raw pool select_diverse_
     * items() then picks from -- doing the source-merging here keeps that
     * selection logic itself source-agnostic.
     */
    private function fetch_pool($since, $limit_each = 60) {
        global $wpdb;
        $pool = array();

        // Reversible switch (default ON): lets Whale Alert be pulled out of
        // POSTS specifically without touching its data collection, in case
        // you decide not to continue that subscription -- flip the setting
        // back on any time with no rebuild needed.
        $settings = get_option('coin680whale_settings', array());
        $include_whale_alert = !isset($settings['include_whale_alert_in_digest']) || !empty($settings['include_whale_alert_in_digest']);

        if ($include_whale_alert) {
            $whale_table = Coin680Whale_Fetcher::table_name();
            $whale_rows = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $whale_table WHERE used_in_digest = 0 AND tx_timestamp >= %s ORDER BY amount_usd DESC LIMIT %d",
                $since, $limit_each
            ));
            foreach ($whale_rows as $row) {
                $pool[] = (object) array(
                    'source'      => 'whale_alert',
                    'id'          => (int) $row->id,
                    'symbol'      => $row->symbol,
                    'classification' => $row->classification,
                    'amount_usd'  => (float) $row->amount_usd,
                    'chain_label' => ucfirst($row->blockchain),
                    'url'         => self::explorer_url($row->blockchain, $row->hash),
                    'raw'         => $row,
                );
            }
        }

        if (class_exists('Coin680MultiChain_Fetcher')) {
            $mc_table = Coin680MultiChain_Fetcher::table_name();
            $mc_rows = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $mc_table WHERE used_in_digest = 0 AND tx_timestamp >= %s ORDER BY amount_usd DESC LIMIT %d",
                $since, $limit_each
            ));
            foreach ($mc_rows as $row) {
                $chain_cfg = class_exists('Coin680MultiChain_Labels') ? Coin680MultiChain_Labels::chain_config($row->chain) : null;
                $pool[] = (object) array(
                    'source'      => 'multichain',
                    'id'          => (int) $row->id,
                    'symbol'      => $row->symbol,
                    'classification' => $row->classification,
                    'amount_usd'  => (float) $row->amount_usd,
                    'chain_label' => $chain_cfg['label'] ?? ucfirst($row->chain),
                    'url'         => $chain_cfg ? sprintf($chain_cfg['explorer'], $row->tx_hash) : '',
                    'raw'         => $row,
                );
            }
        }

        usort($pool, function ($a, $b) {
            return $b->amount_usd <=> $a->amount_usd;
        });

        return $pool;
    }

    /**
     * Picks up to $limit transactions, ONE PER DISTINCT COIN, never two
     * entries of the same symbol in one post -- if a coin has multiple
     * qualifying transactions (even across different chains/sources), only
     * its largest is used. If fewer than $limit distinct coins qualify this
     * window, the post simply has fewer lines; it never pads out by
     * repeating a coin already featured. Secondary priority (when there's
     * still room after covering distinct coins) leans toward classification
     * variety among the coins not yet picked, so exchange in/out signals
     * aren't crowded out by, say, several "wallet transfer, purpose
     * unclear" coins.
     */
    private function select_diverse_items($since, $limit = 3) {
        $pool = $this->fetch_pool($since);
        if (empty($pool)) {
            return array();
        }

        $picked = array();
        $picked_keys = array();
        $picked_symbols = array();

        $item_key = function ($item) {
            return $item->source . ':' . $item->id;
        };

        // Pass 1: one entry per distinct symbol -- the largest transaction
        // of that symbol, since $pool is already sorted by amount_usd DESC.
        foreach ($pool as $item) {
            if (count($picked) >= $limit) {
                break;
            }
            $sym = strtoupper($item->symbol);
            if (in_array($sym, $picked_symbols, true)) {
                continue;
            }
            $picked[] = $item;
            $picked_keys[] = $item_key($item);
            $picked_symbols[] = $sym;
        }

        // Pass 2: slots still open (fewer distinct coins this window than
        // $limit) -- nothing left to add that isn't a duplicate coin, so
        // this pass only exists to try covering a missing classification by
        // re-scanning for a DIFFERENT coin under that classification. It
        // still never picks a symbol already in $picked_symbols.
        if (count($picked) < $limit) {
            $priority = array('Exchange Outflow', 'Exchange Inflow', 'DEX Buy', 'DEX Sell', 'DEX Swap', 'Mint', 'Burn', 'Exchange to Exchange', 'Wallet Transfer');
            $have_classes = wp_list_pluck($picked, 'classification');
            foreach ($priority as $classification) {
                if (count($picked) >= $limit) {
                    break;
                }
                if (in_array($classification, $have_classes, true)) {
                    continue;
                }
                foreach ($pool as $item) {
                    $sym = strtoupper($item->symbol);
                    if ($item->classification === $classification
                        && !in_array($item_key($item), $picked_keys, true)
                        && !in_array($sym, $picked_symbols, true)) {
                        $picked[] = $item;
                        $picked_keys[] = $item_key($item);
                        $picked_symbols[] = $sym;
                        $have_classes[] = $classification;
                        break;
                    }
                }
            }
        }

        usort($picked, function ($a, $b) {
            return $b->amount_usd <=> $a->amount_usd;
        });

        return $picked;
    }

    /**
     * Checked every 5 min (via cron), but only actually posts once at least
     * MIN_COINS_TO_POST distinct coins are sitting unused across BOTH
     * sources -- no time-based trigger at all anymore. $since is the oldest
     * still-unused transaction across both tables (i.e. effectively "since
     * the last post"), used both to scope net_exchange_flow() and to build
     * an honest, dynamic window label ("last 42 min") instead of a
     * hardcoded one.
     */
    public function maybe_post_digest() {
        global $wpdb;
        $whale_table = Coin680Whale_Fetcher::table_name();
        $since_candidates = array();
        $whale_since = $wpdb->get_var("SELECT MIN(tx_timestamp) FROM $whale_table WHERE used_in_digest = 0");
        if ($whale_since) {
            $since_candidates[] = $whale_since;
        }
        if (class_exists('Coin680MultiChain_Fetcher')) {
            $mc_table = Coin680MultiChain_Fetcher::table_name();
            $mc_since = $wpdb->get_var("SELECT MIN(tx_timestamp) FROM $mc_table WHERE used_in_digest = 0");
            if ($mc_since) {
                $since_candidates[] = $mc_since;
            }
        }
        if (empty($since_candidates)) {
            return;
        }
        $since = min($since_candidates);

        $items = $this->select_diverse_items($since, self::MIN_COINS_TO_POST);
        if (count($items) < self::MIN_COINS_TO_POST) {
            return;
        }

        $minutes = max(1, (int) round((time() - strtotime($since)) / 60));
        if ($minutes > 60) {
            $hours = $minutes / 60;
            // Whole hours read as "4 hours", partial ones as "4.5 hours" --
            // never a decimal on an exact hour.
            $window_label = (floor($hours) == $hours)
                ? sprintf('%d hour%s', $hours, $hours == 1 ? '' : 's')
                : sprintf('%.1f hours', $hours);
        } else {
            $window_label = "{$minutes} min";
        }

        $text = $this->build_and_get_text($items, $since, $window_label);

        if (class_exists('Coin680X_Queue')) {
            // Single post only -- no first-comment/poll reply. A reply on
            // every post read as extra noise in the channel rather than
            // added value, so this was removed per direct feedback.
            Coin680X_Queue::add($text, '', '', current_time('mysql', true));
        }

        $this->mark_pool_used($items);
    }

    /**
     * Routes mark_used() calls to the correct table per item, since the
     * combined pool can contain rows from either Coin680Whale_Fetcher or
     * Coin680MultiChain_Fetcher.
     */
    private function mark_pool_used($items) {
        $whale_ids = array();
        $mc_ids = array();
        foreach ($items as $item) {
            if ($item->source === 'whale_alert') {
                $whale_ids[] = $item->id;
            } else {
                $mc_ids[] = $item->id;
            }
        }
        if ($whale_ids) {
            Coin680Whale_Fetcher::mark_used($whale_ids);
        }
        if ($mc_ids && class_exists('Coin680MultiChain_Fetcher')) {
            Coin680MultiChain_Fetcher::mark_used($mc_ids);
        }
    }

    /**
     * One-off manual test post, triggered from the admin page -- scoped to
     * ONLY the multichain (Etherscan) source, no Whale Alert, to verify the
     * new BSC full-token-scan data end to end. Selection rule (per direct
     * request): up to $limit distinct-symbol transactions by size --
     * duplicate symbols (same token appearing more than once, even across
     * chains) collapse to their single largest transaction; NO per-chain
     * cap, so two different tokens from the same chain can both appear in
     * one post if they both make the size cut. Fewer than $limit is fine
     * if that many distinct tokens simply aren't available right now.
     * Queues via Coin680X_Queue same as the regular digest -- actually
     * posts within ~5 min via that plugin's own cron, not instantly, so no
     * separate OAuth call (and no need to have live X credentials in this
     * chat) is required.
     */
    public function post_multichain_test_digest($limit = 7) {
        global $wpdb;
        if (!class_exists('Coin680MultiChain_Fetcher')) {
            return false;
        }
        $mc_table = Coin680MultiChain_Fetcher::table_name();
        $since = gmdate('Y-m-d H:i:s', time() - 48 * HOUR_IN_SECONDS);
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $mc_table WHERE used_in_digest = 0 AND tx_timestamp >= %s ORDER BY amount_usd DESC LIMIT 300",
            $since
        ));
        if (!$rows) {
            return false;
        }

        $picked = array();
        $picked_symbols = array();
        foreach ($rows as $row) {
            if (count($picked) >= $limit) {
                break;
            }
            $sym = strtoupper($row->symbol);
            if (in_array($sym, $picked_symbols, true)) {
                continue;
            }
            $chain_cfg = class_exists('Coin680MultiChain_Labels') ? Coin680MultiChain_Labels::chain_config($row->chain) : null;
            $picked[] = (object) array(
                'source'      => 'multichain',
                'id'          => (int) $row->id,
                'symbol'      => $row->symbol,
                'classification' => $row->classification,
                'amount_usd'  => (float) $row->amount_usd,
                'chain_label' => $chain_cfg['label'] ?? ucfirst($row->chain),
                'url'         => $chain_cfg ? sprintf($chain_cfg['explorer'], $row->tx_hash) : '',
                'raw'         => $row,
            );
            $picked_symbols[] = $sym;
        }

        if (empty($picked)) {
            return false;
        }

        $minutes = max(1, (int) round((time() - strtotime($since)) / 60));
        $hours = $minutes / 60;
        $window_label = ($minutes > 60)
            ? ((floor($hours) == $hours) ? sprintf('%d hour%s', $hours, $hours == 1 ? '' : 's') : sprintf('%.1f hours', $hours))
            : "{$minutes} min";

        $header = "🐋 #COIN680 MULTICHAIN TEST (last {$window_label}):\n\n";
        $lines = array();
        $cashtag_used = false;
        foreach ($picked as $item) {
            $amount_fmt = '$' . number_format($item->amount_usd);
            $blurb = self::classification_blurb($item->raw);
            $symbol_display = $cashtag_used ? strtoupper($item->symbol) : self::cashtag($item->symbol);
            $cashtag_used = true;
            $line = "• {$amount_fmt} {$symbol_display} ({$item->chain_label}) {$blurb}.";
            if ($item->url) {
                $line .= " {$item->url}";
            }
            $lines[] = $line;
        }
        $text = $header . implode("\n\n", $lines) . "\n\n#Crypto #Multichain #OnChain";

        if (class_exists('Coin680X_Queue')) {
            Coin680X_Queue::add($text, '', '', current_time('mysql', true));
        }
        Coin680MultiChain_Fetcher::mark_used(wp_list_pluck($picked, 'id'));
        return true;
    }

    /**
     * Standalone breaking post for a single mega transaction, fired
     * immediately from Coin680Whale_Fetcher::poll() the moment one is
     * captured -- doesn't wait for the next digest cycle. Whale Alert only
     * for now (the multichain side doesn't have its own mega-alert hook).
     */
    public function post_mega_alert($item) {
        $amount_fmt = '$' . number_format($item->amount_usd);
        $blurb = self::classification_blurb($item);
        $url = self::explorer_url($item->blockchain, $item->hash);
        $history = $this->historical_comparison((object) array('source' => 'whale_alert', 'id' => $item->id, 'amount_usd' => $item->amount_usd, 'raw' => $item));

        $text = "🚨 #COIN680 WHALE SIGNAL -- BREAKING:\n\n";
        $text .= "{$amount_fmt} " . self::cashtag($item->symbol) . " just {$blurb}.";
        if ($url) {
            $text .= " {$url}";
        }
        if ($history) {
            $text .= "\n\n{$history}";
        }
        $text .= "\n\n#Bitcoin #WhaleAlert #OnChain #Crypto";

        if (class_exists('Coin680X_Queue')) {
            Coin680X_Queue::add($text, '', '', current_time('mysql', true));
        }
        Coin680Whale_Fetcher::mark_used(array($item->id));
    }

    public function post_daily_recap() {
        global $wpdb;
        $table = Coin680Whale_Fetcher::table_name();
        $since = gmdate('Y-m-d H:i:s', time() - DAY_IN_SECONDS);

        $total_volume = (float) $wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(amount_usd),0) FROM $table WHERE tx_timestamp >= %s", $since));
        if ($total_volume <= 0) {
            return;
        }
        $flow = $this->net_exchange_flow($since);
        $biggest = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE tx_timestamp >= %s ORDER BY amount_usd DESC LIMIT 1", $since));
        $count = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE tx_timestamp >= %s", $since));

        $text = "📊 #COIN680 WHALE SIGNAL -- DAILY RECAP:\n\n";
        $text .= "• " . number_format($count) . " tracked transactions, totaling $" . number_format($total_volume) . " moved on-chain.\n\n";
        if ($biggest) {
            $url = self::explorer_url($biggest->blockchain, $biggest->hash);
            $text .= "• Biggest single move: $" . number_format($biggest->amount_usd) . " " . self::cashtag($biggest->symbol) . " (" . self::classification_blurb($biggest) . ").";
            if ($url) { $text .= " {$url}"; }
            $text .= "\n\n";
        }
        $net = $flow['net'];
        $direction = $net > 0 ? 'net inflow to exchanges' : 'net outflow from exchanges';
        $text .= "• 24h Net Exchange Flow: $" . number_format(abs($net)) . " ({$direction}).\n\n";
        $text .= ($net < 0)
            ? "More supply left exchanges than entered today -- a day that leaned toward accumulation."
            : "More supply moved onto exchanges than off today -- worth watching for follow-through selling.";
        $text .= "\n\n#Bitcoin #WhaleAlert #OnChain #Crypto";

        if (class_exists('Coin680X_Queue')) {
            Coin680X_Queue::add($text, '', '', current_time('mysql', true));
        }
    }
}
