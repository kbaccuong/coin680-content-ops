<?php
/**
 * Builds and posts the recurring "Whale Signal" digest to X, plus standalone
 * breaking alerts for mega transactions and a once-daily recap.
 *
 * Checked every 5 min, but only actually posts once at least MIN_COINS_
 * TO_POST distinct coins have accumulated since the last post -- UNLESS
 * MAX_WAIT_MINUTES has elapsed, in which case it posts anyway with
 * whatever's available (fallback cap re-added 2026-07-30 so a post still
 * goes out at least every ~30 min, matching Whale Alert's old cadence).
 * Pulls from TWO sources: Coin680Whale_Fetcher (Whale Alert -- Bitcoin
 * only as of 2026-07-30, see that class) and Coin680Bitquery_Fetcher
 * (Solana/BSC/Ethereum/TRON via Bitquery, DEX-swap detection -- replaced
 * the Etherscan-based Coin680MultiChain_Fetcher the same day) -- normalized
 * into one pool so coin diversity and "no duplicate coin" rules apply
 * across both together. Uses only real, already-classified transactions --
 * no fabricated correlations or invented price reactions. The per-
 * classification framing is standard, widely used analyst shorthand, not a
 * specific claim about what will happen next. Historical comparisons only
 * ever cite numbers actually stored in our own table at the time each
 * transaction was captured.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Coin680Whale_Digest {
    private static $instance = null;
    const MIN_COINS_TO_POST = 3;

    // Cap on how many coins a SINGLE post can show (added 2026-07-30, per
    // direct request: "nếu nhiều token quá thì tổng hợp thành 1 bài"). When
    // the backlog has more than MIN_COINS_TO_POST qualifying coins waiting
    // (common on busy Solana/BSC windows), one post consolidates up to this
    // many instead of only ever showing 3 and leaving the rest queued for
    // future posts -- this also naturally slows the POSTING RATE, since
    // each post now clears a bigger chunk of the backlog at once.
    const MAX_COINS_PER_POST = 7;

    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('coin680whale_digest_check', array($this, 'maybe_post_digest'));
        add_action('coin680whale_daily_recap', array($this, 'post_daily_recap'));
        add_action('coin680whale_teaser_check', array($this, 'maybe_post_onchain_teaser'));
        add_action('coin680whale_roundup_daily', array($this, 'post_daily_roundup'));
    }

    /**
     * Once-daily on-chain roundup (added 2026-07-31), the paid-link
     * counterpart to the free-form teaser posts above -- fires once/day via
     * WP's native 'daily' schedule (see plugin bootstrap), so no MIN/MAX_
     * WAIT_MINUTES accumulation logic is needed here, unlike the old 30-50
     * min digest this replaces. Reuses select_diverse_items()/fetch_pool()
     * (tested code, same coin-diversity picking as the old digest) but
     * ONLY ONE link total -- to /whale-signals/, appended once at the very
     * end -- instead of a separate explorer link on every line. That old
     * per-line-link format was the confirmed driver of the ~$26 X API bill
     * (developer.x.com/billing showed 127/209 requests as
     * "ContentCreateWithUrl", spiking exactly when the old digest was
     * live). Also drops the old "Net Exchange Flow" sentence -- that was
     * computed from Whale Alert (Bitcoin)-only data, and Whale Alert
     * stopped collecting new data on 2026-07-30, so reusing it here would
     * describe stale, frozen numbers rather than anything about the
     * current 24h window.
     */
    public function post_daily_roundup() {
        $since = gmdate('Y-m-d H:i:s', time() - DAY_IN_SECONDS);
        $items = $this->select_diverse_items($since, self::MAX_COINS_PER_POST);
        if (empty($items)) {
            return;
        }
        $text = $this->build_roundup_text($items);
        if (class_exists('Coin680X_Queue')) {
            Coin680X_Queue::add($text, '', '', current_time('mysql', true));
        }
        $this->mark_pool_used($items);
    }

    private function build_roundup_text($items) {
        $header = "🐋 #COIN680 DAILY ON-CHAIN RECAP:\n\n";
        $lines = array();
        $cashtag_used = false;
        foreach ($items as $item) {
            $amount_fmt = '$' . number_format($item->amount_usd);
            $blurb = self::classification_blurb($item->raw);
            // Same one-cashtag-per-post rule as the old digest (X rejects
            // the whole post if more than one $SYMBOL cashtag appears) --
            // only the first/largest item gets the real cashtag treatment.
            $symbol_display = $cashtag_used ? strtoupper($item->symbol) : self::cashtag($item->symbol);
            $cashtag_used = true;
            $lines[] = "• {$amount_fmt} {$symbol_display} ({$item->chain_label}) {$blurb}.";
        }
        $body = implode("\n\n", $lines);

        $takeaways = array(
            "None of this guarantees where price goes next, but it does tell you where supply is moving -- and that's usually the part of the story raw price charts miss.",
            "For traders, this is a data point to weigh alongside price action, not a signal to act on alone -- these flows shift available supply, they don't guarantee direction.",
            "Flows like this shift how much supply is sitting in easy reach of a sale, which matters most when combined with what price is already doing.",
        );
        $takeaway = $takeaways[array_rand($takeaways)];

        $cta = "\n\nFull breakdown + live feed: " . home_url('/whale-signals/');
        $hashtags = "\n\n#Crypto #OnChain #Whale #Multichain";

        return $header . $body . "\n\n" . $takeaway . $cta . $hashtags;
    }

    // 24h / 3 posts = 8h apart. Replaces the old multi-coin digest
    // (coin680whale_digest_check, permanently disabled 2026-07-31) as the
    // only remaining on-chain X posting path -- per direct feedback, the
    // old format's per-line explorer links were the confirmed driver of
    // real X API billing (checked developer.x.com/billing: 127/209
    // requests in 30 days were "ContentCreateWithUrl", spiking exactly
    // when the digest went live). This format is deliberately a SINGLE
    // notable transaction as a short hook, with NO url/domain text
    // anywhere in the body -- just the brand name "Coin680" (no ".com",
    // nothing X's link-detector would linkify) -- to test the hypothesis
    // that a link-free post falls into the cheap/free "Content Create"
    // category instead. The public /whale-signals/ page already carries
    // the full data feed, so this tweet's job is purely "hook -> brand
    // recall", not "deliver the data" -- consistent with why a raw link
    // isn't actually needed here even leaving cost aside.
    const TEASER_INTERVAL_MINUTES = 480;

    public function maybe_post_onchain_teaser() {
        $last_post_at = get_option('coin680whale_last_teaser_post_at');
        if ($last_post_at && (time() - (int) $last_post_at) < self::TEASER_INTERVAL_MINUTES * MINUTE_IN_SECONDS) {
            return;
        }

        // 12h lookback: wide enough to almost always find something even
        // through a quiet stretch, without reaching back so far that the
        // "biggest thing found" is no longer timely.
        $since = gmdate('Y-m-d H:i:s', time() - 12 * HOUR_IN_SECONDS);
        $pool = $this->fetch_pool($since, 20);
        if (empty($pool)) {
            return;
        }
        usort($pool, function ($a, $b) {
            return $b->amount_usd <=> $a->amount_usd;
        });
        $item = $pool[0];

        $text = $this->build_teaser_text($item);
        if (class_exists('Coin680X_Queue')) {
            Coin680X_Queue::add($text, '', '', current_time('mysql', true));
        }
        $this->mark_pool_used(array($item));
        update_option('coin680whale_last_teaser_post_at', time(), false);
    }

    private function build_teaser_text($item) {
        $amount_fmt = '$' . number_format($item->amount_usd);
        $blurb = self::classification_blurb($item->raw);
        $symbol_display = self::cashtag($item->symbol);

        $hooks = array(
            "🐋 Whale move just landed.",
            "🚨 On-chain signal worth a look.",
            "👀 Notable wallet activity just hit our radar.",
        );
        // Deliberately says "Coin680 website" (not "coin680.com" or any
        // other TLD-looking string) -- explicit enough that readers know
        // it's a real website to go visit, but nothing X's link-detector
        // would linkify/bill as ContentCreateWithUrl (per direct request
        // 2026-07-31: keep it plain English, but make sure "live" +
        // "website" + "Coin680" all show up in the closing line).
        $ctas = array(
            "Live now on the Coin680 website.",
            "More whale signals live on the Coin680 website.",
            "Full on-chain feed live on the Coin680 website.",
        );
        $hook = $hooks[array_rand($hooks)];
        $cta = $ctas[array_rand($ctas)];

        return "{$hook}\n\n{$amount_fmt} {$symbol_display} ({$item->chain_label}) {$blurb}.\n\n{$cta}\n\n#Crypto #OnChain #Whale";
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
     * generic phrasing only when there's no label to offer. Works on a
     * Whale Alert row (from_owner/from_owner_type/to_owner/to_owner_type
     * columns, real CEX labels) or a Coin680Bitquery_Fetcher row (dex_name/
     * counter_symbol columns, DEX Buy/Sell/Swap only -- no CEX label yet,
     * see that class's docblock).
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

        if (class_exists('Coin680Bitquery_Fetcher')) {
            $bq_table = Coin680Bitquery_Fetcher::table_name();
            $bq_rows = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $bq_table WHERE used_in_digest = 0 AND tx_timestamp >= %s ORDER BY amount_usd DESC LIMIT %d",
                $since, $limit_each
            ));
            foreach ($bq_rows as $row) {
                $chain_cfg = class_exists('Coin680Bitquery_Labels') ? Coin680Bitquery_Labels::chain_config($row->chain) : null;
                $pool[] = (object) array(
                    'source'      => 'bitquery',
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

    // Fallback cap (re-added 2026-07-30 per direct request): if it's been
    // this long since the last post and MIN_COINS_TO_POST still hasn't
    // been reached, post anyway with however many coins ARE ready (even
    // just 1) rather than waiting indefinitely -- guarantees a post at
    // least every ~50 min, while still preferring a fuller post when the
    // data allows it.
    const MAX_WAIT_MINUTES = 50;

    // Floor on the OTHER side (added 2026-07-30, per direct feedback: the
    // on-chain feed alone -- especially Solana/BSC via Bitquery -- can
    // accumulate MIN_COINS_TO_POST fast enough to post every few minutes,
    // which read as too dense/spammy. No post fires before this many
    // minutes have passed since the last one, REGARDLESS of how much data
    // is already sitting ready -- it just keeps accumulating in the pool
    // until this floor clears. Combined with MAX_WAIT_MINUTES above, a
    // post lands somewhere in the 30-50 min range depending on how fast
    // qualifying data actually arrives, never faster, never slower.
    const MIN_INTERVAL_MINUTES = 30;

    /**
     * Checked every 5 min (via cron). Never posts before MIN_INTERVAL_
     * MINUTES have passed since the LAST ACTUAL POST (tracked in the
     * 'coin680whale_last_digest_post_at' option -- fixed 2026-07-30: an
     * earlier version measured this from the OLDEST still-unused backlog
     * row instead, which broke the 30-min floor entirely once the backlog
     * grew past MAX_COINS_PER_POST per cycle -- rows left over from
     * previous cycles were already "old", so the very next 5-min check
     * always saw more than 30 minutes elapsed and posted again immediately.
     * A dedicated last-post-time option can't be fooled by backlog size).
     * Once that floor clears, posts as soon as MIN_COINS_TO_POST distinct
     * coins are sitting unused across BOTH sources (showing up to
     * MAX_COINS_PER_POST if more are ready, consolidating a big backlog
     * into one fuller post instead of several thin ones) -- OR, if fewer
     * than MIN_COINS_TO_POST are ready, once MAX_WAIT_MINUTES has elapsed,
     * in which case it posts with whatever coins ARE available (down to
     * just 1) rather than waiting longer. $since (oldest still-unused
     * transaction) is kept purely for the descriptive "last 42 min" window
     * label and to scope net_exchange_flow() -- it no longer drives the
     * posting-cadence decision itself.
     */
    public function maybe_post_digest() {
        global $wpdb;
        $whale_table = Coin680Whale_Fetcher::table_name();
        $since_candidates = array();
        $whale_since = $wpdb->get_var("SELECT MIN(tx_timestamp) FROM $whale_table WHERE used_in_digest = 0");
        if ($whale_since) {
            $since_candidates[] = $whale_since;
        }
        if (class_exists('Coin680Bitquery_Fetcher')) {
            $bq_table = Coin680Bitquery_Fetcher::table_name();
            $bq_since = $wpdb->get_var("SELECT MIN(tx_timestamp) FROM $bq_table WHERE used_in_digest = 0");
            if ($bq_since) {
                $since_candidates[] = $bq_since;
            }
        }
        if (empty($since_candidates)) {
            return;
        }
        $since = min($since_candidates);

        // Clamp $since so it can never drift arbitrarily far into the past
        // (added 2026-07-30). Without this, a smaller transaction of a
        // symbol that already has a bigger unused entry sits neglected
        // indefinitely -- select_diverse_items() always prefers the larger
        // one, so the smaller duplicate can go many cycles without ever
        // being picked or marked used. Since $since is the MIN across ALL
        // still-unused rows, that one neglected row alone keeps dragging
        // $since -- and therefore the "(last N hours)" label shown in the
        // actual tweet -- further into the past every single cycle, even
        // while posts keep firing on a perfectly healthy 30-50 min cadence.
        // Confirmed live 2026-07-30: the label climbed from ~25 hours to
        // ~28.6 hours across several consecutive, genuinely-posted digests.
        // Clamping means a permanently-starved old row simply stops being
        // treated as "recent" once older than this window -- it stays
        // harmless, inert history in the table, just no longer distorts the
        // label or (via fetch_pool()'s tx_timestamp >= $since filter) get
        // reconsidered for selection each cycle.
        $freshness_floor = time() - (self::MAX_WAIT_MINUTES * 2) * MINUTE_IN_SECONDS;
        if (strtotime($since) < $freshness_floor) {
            $since = gmdate('Y-m-d H:i:s', $freshness_floor);
        }

        // Stored as a raw Unix timestamp (time()), NOT a MySQL datetime
        // string -- deliberately avoids strtotime() here. A prior version
        // stored/read this via current_time('mysql', true) + strtotime(),
        // which depends on PHP's runtime default timezone (date.timezone)
        // to parse the naive "YYYY-MM-DD HH:MM:SS" string back correctly.
        // If that ini setting isn't UTC on this host (never confirmed
        // either way), strtotime() would silently misinterpret an
        // already-UTC string as being in the server's local zone, throwing
        // the computed gap off by whatever that offset is -- which, if
        // large enough, could make $minutes_waited look like it always
        // clears MIN_INTERVAL_MINUTES immediately, exactly matching the
        // reported symptom (posts still firing every ~5 min). Plain
        // time() vs (int) $last_post_at has no string-parsing step at all,
        // so no timezone can get involved.
        $last_post_at = get_option('coin680whale_last_digest_post_at');
        $minutes_waited = $last_post_at
            ? (time() - (int) $last_post_at) / 60
            : (time() - strtotime($since)) / 60; // first run ever, no prior post recorded yet
        if ($minutes_waited < self::MIN_INTERVAL_MINUTES) {
            return; // too soon since the last post, no matter how much data is ready
        }

        $items = $this->select_diverse_items($since, self::MAX_COINS_PER_POST);
        if (empty($items)) {
            return;
        }
        if (count($items) < self::MIN_COINS_TO_POST && $minutes_waited < self::MAX_WAIT_MINUTES) {
            return; // not enough coins yet, and still within the extended wait window
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
        update_option('coin680whale_last_digest_post_at', time(), false);
    }

    /**
     * Routes mark_used() calls to the correct table per item, since the
     * combined pool can contain rows from either Coin680Whale_Fetcher or
     * Coin680Bitquery_Fetcher.
     */
    private function mark_pool_used($items) {
        $whale_ids = array();
        $bq_ids = array();
        foreach ($items as $item) {
            if ($item->source === 'whale_alert') {
                $whale_ids[] = $item->id;
            } else {
                $bq_ids[] = $item->id;
            }
        }
        if ($whale_ids) {
            Coin680Whale_Fetcher::mark_used($whale_ids);
        }
        if ($bq_ids && class_exists('Coin680Bitquery_Fetcher')) {
            Coin680Bitquery_Fetcher::mark_used($bq_ids);
        }
    }

    /**
     * One-off manual test post, triggered from the admin page -- scoped to
     * ONLY the Bitquery (Solana/BSC/Ethereum/TRON) source, no Whale Alert.
     * Selection rule (per direct request): up to $limit distinct-symbol
     * transactions by size -- duplicate symbols (same token appearing more
     * than once, even across chains) collapse to their single largest
     * transaction; NO per-chain cap, so two different tokens from the same
     * chain can both appear in one post if they both make the size cut.
     * Fewer than $limit is fine if that many distinct tokens simply aren't
     * available right now. Queues via Coin680X_Queue same as the regular
     * digest -- actually posts within ~5 min via that plugin's own cron,
     * not instantly, so no separate OAuth call is required.
     */
    public function post_multichain_test_digest($limit = 7) {
        global $wpdb;
        if (!class_exists('Coin680Bitquery_Fetcher')) {
            return false;
        }
        $bq_table = Coin680Bitquery_Fetcher::table_name();
        // Was 48 hours -- per direct feedback, if posts go out every 30-50
        // min, the data behind them (even for a manual test post) shouldn't
        // be able to reach back nearly 2 days. Widened to 2x MAX_WAIT_MINUTES
        // rather than matching it exactly, purely so this button still
        // reliably finds something to test with right after a real digest
        // cycle just cleared the backlog -- not meant to let manual posts
        // look "old".
        $since = gmdate('Y-m-d H:i:s', time() - (self::MAX_WAIT_MINUTES * 2) * MINUTE_IN_SECONDS);
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $bq_table WHERE used_in_digest = 0 AND tx_timestamp >= %s ORDER BY amount_usd DESC LIMIT 300",
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
            $chain_cfg = class_exists('Coin680Bitquery_Labels') ? Coin680Bitquery_Labels::chain_config($row->chain) : null;
            $picked[] = (object) array(
                'source'      => 'bitquery',
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

        $header = "🐋 #COIN680 WHALE SIGNAL (last {$window_label}):\n\n";
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
        Coin680Bitquery_Fetcher::mark_used(wp_list_pluck($picked, 'id'));
        return true;
    }

    /**
     * Standalone alert for a watched-wallet ("smart money") move, fired
     * immediately from Coin680Watchlist_Fetcher::maybe_alert() -- doesn't
     * wait for the regular digest cycle, since these are address-driven
     * (rare, high-signal) events rather than the amount-driven regular
     * digest pool. Per-wallet cooldown is handled by the caller before this
     * is even invoked; this method just builds and queues the tweet.
     * Added 2026-07-30 alongside Coin680Watchlist_Fetcher, after upgrading
     * to a paid Bitquery plan.
     */
    public function post_watchlist_alert($data) {
        $action = $data['side'] === 'buy' ? 'bought' : 'sold';
        $amount_fmt = '$' . number_format($data['amount_usd']);
        $counter = $data['counter_symbol'] ? " for {$data['counter_symbol']}" : '';
        $via = $data['dex_name'] ? " via {$data['dex_name']}" : '';
        $chain_label = ucfirst($data['chain']);

        $text = "👁 #COIN680 SMART MONEY:\n\n";
        $text .= "{$data['wallet_label']} just {$action} " . self::cashtag($data['symbol']) . "{$counter}{$via} ({$chain_label}), {$amount_fmt}.";
        if (!empty($data['url'])) {
            $text .= " {$data['url']}";
        }
        $text .= "\n\nTracked because of WHO made this move, not size alone -- context for your own research, not a signal to copy blindly.";
        $text .= "\n\n#Crypto #SmartMoney #OnChain";

        if (class_exists('Coin680X_Queue')) {
            Coin680X_Queue::add($text, '', '', current_time('mysql', true));
        }
    }

    /**
     * Standalone breaking post for a single mega transaction, fired
     * immediately from Coin680Whale_Fetcher::poll() the moment one is
     * captured -- doesn't wait for the next digest cycle. Whale Alert only
     * for now (the multichain side doesn't have its own mega-alert hook).
     */
    // Bitcoin-only Whale Alert data produces enough $50M+ moves that
    // breaking alerts could otherwise fire back-to-back within minutes of
    // each other -- rate-limited to at most 1 every 30 min (per direct
    // feedback: "dữ liệu bitcoin nhiều quá, giảm cứ 30p bắn 1 tín hiệu").
    // A qualifying transaction that arrives inside the cooldown window is
    // simply not posted as a breaking alert (it can still show up in the
    // regular digest via the normal pool selection).
    const MEGA_ALERT_COOLDOWN_MINUTES = 30;

    public function post_mega_alert($item) {
        // Raw Unix timestamp, not a datetime string -- see the long comment
        // above maybe_post_digest()'s equivalent check for why strtotime()
        // on a stored date string is unsafe here (PHP runtime timezone
        // dependent). Dormant right now since Whale Alert polling is
        // stopped, but fixed for correctness in case it's ever reactivated.
        $last = get_option('coin680whale_last_mega_alert');
        if ($last && (time() - (int) $last) < self::MEGA_ALERT_COOLDOWN_MINUTES * 60) {
            return;
        }

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
        update_option('coin680whale_last_mega_alert', time(), false);
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

