<?php
/**
 * Builds and posts the recurring "Whale Signal" digest to X, plus standalone
 * breaking alerts for mega transactions and a once-daily recap.
 *
 * Guarantees a fresh regular post at least every 30 minutes (checked every
 * 5 min), using only real, already-classified transactions from
 * Coin680Whale_Fetcher -- no fabricated correlations or invented price
 * reactions. The per-classification framing is standard, widely used
 * analyst shorthand (exchange outflow = often read as accumulation, inflow
 * = often read as potential sell pressure), not a specific claim about what
 * will happen next. Historical comparisons only ever cite numbers actually
 * stored in our own table at the time each transaction was captured.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Coin680Whale_Digest {
    private static $instance = null;
    const CADENCE_SECONDS = 30 * MINUTE_IN_SECONDS;

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
     * Names the actual exchange(s) whenever Whale Alert has labeled one --
     * "left Bybit" / "moved onto OKX" / "moved from Binance to Coinbase" --
     * rather than the generic "an exchange", falling back to generic
     * phrasing only when Whale Alert itself has no label to offer.
     */
    private static function classification_blurb($item) {
        $from = $item->from_owner;
        $to = $item->to_owner;
        $from_named = ($item->from_owner_type === 'exchange' && $from && strtolower($from) !== 'unknown');
        $to_named = ($item->to_owner_type === 'exchange' && $to && strtolower($to) !== 'unknown');

        switch ($item->classification) {
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
     * Net USD flow into vs. out of exchanges across ALL qualifying
     * transactions in the window (not just the ones featured in the post),
     * so the headline number reflects the full picture even though only a
     * handful of individual transactions get a line of their own.
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
     * Finds a real, previously captured transaction of similar size and
     * the same classification, at least 6 hours old, and reports the
     * actual BTC price change from that capture moment to right now.
     * Returns '' if no honest match exists -- never fabricates one.
     */
    private function historical_comparison($item) {
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
            $item->classification, $low, $high, $cutoff, $item->id
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
            strtolower($item->classification),
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
            $blurb = self::classification_blurb($item);
            $url = self::explorer_url($item->blockchain, $item->hash);
            // X rejects an entire post outright if it contains more than one
            // cashtag ($SYMBOL), and a 5-item digest routinely spans several
            // different coins -- so only the single largest entry gets the
            // cashtag treatment; the rest fall back to a plain symbol.
            $symbol_display = $cashtag_used ? strtoupper($item->symbol) : self::cashtag($item->symbol);
            $cashtag_used = true;
            $line = "• {$amount_fmt} {$symbol_display} {$blurb}.";
            if ($url) {
                $line .= " {$url}";
            }
            $lines[] = $line;
        }
        $body = implode("\n\n", $lines);
        $closing = "\n\n" . $this->build_closing_analysis($items, $since);
        $hashtags = "\n\n#Bitcoin #WhaleAlert #OnChain #Crypto";
        return $header . $body . $closing . $hashtags;
    }

    /**
     * Picks up to 5 transactions, favoring variety of COIN first and
     * classification second. Whale Alert reports every chain it tracks
     * (not just BTC), but BTC/ETH/USDT simply have far more $500k+ transfers
     * in any given window than smaller-cap coins -- if we only prioritized
     * by classification or raw size, those few dominant assets would
     * routinely claim all 5 slots and a genuinely large altcoin move that
     * crossed the threshold would never get shown. So: first take the
     * single largest transaction per DISTINCT symbol present in the pool
     * (guarantees coin breadth), then only fall back to filling any
     * remaining slots via classification variety among what's left.
     */
    private function select_diverse_items($since, $limit = 5) {
        global $wpdb;
        $table = Coin680Whale_Fetcher::table_name();
        $pool = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE used_in_digest = 0 AND tx_timestamp >= %s ORDER BY amount_usd DESC LIMIT 60",
            $since
        ));
        if (empty($pool)) {
            return array();
        }

        $picked = array();
        $picked_ids = array();
        $picked_symbols = array();

        // Pass 1: one entry per distinct symbol (largest-first order from
        // the query), so a single dominant asset can't crowd out every slot.
        foreach ($pool as $item) {
            if (count($picked) >= $limit) {
                break;
            }
            $sym = strtoupper($item->symbol);
            if (!in_array($sym, $picked_symbols, true)) {
                $picked[] = $item;
                $picked_ids[] = $item->id;
                $picked_symbols[] = $sym;
            }
        }

        // Pass 2: slots still open (window had fewer distinct coins than
        // $limit) -- fill by classification variety among what's left over.
        if (count($picked) < $limit) {
            $priority = array('Exchange Outflow', 'Exchange Inflow', 'Mint', 'Burn', 'Exchange to Exchange', 'Wallet Transfer');
            $have_classes = wp_list_pluck($picked, 'classification');
            foreach ($priority as $classification) {
                if (count($picked) >= $limit) {
                    break;
                }
                if (in_array($classification, $have_classes, true)) {
                    continue;
                }
                foreach ($pool as $item) {
                    if ($item->classification === $classification && !in_array($item->id, $picked_ids, true)) {
                        $picked[] = $item;
                        $picked_ids[] = $item->id;
                        $have_classes[] = $classification;
                        break;
                    }
                }
            }
        }

        // Pass 3: still short -- just take whatever's biggest and unused.
        if (count($picked) < $limit) {
            foreach ($pool as $item) {
                if (count($picked) >= $limit) {
                    break;
                }
                if (!in_array($item->id, $picked_ids, true)) {
                    $picked[] = $item;
                    $picked_ids[] = $item->id;
                }
            }
        }

        usort($picked, function ($a, $b) {
            return $b->amount_usd <=> $a->amount_usd;
        });

        return $picked;
    }

    public function maybe_post_digest() {
        $last_digest = (int) get_option('coin680whale_last_digest', 0);
        if ((time() - $last_digest) < self::CADENCE_SECONDS) {
            return;
        }

        $since = gmdate('Y-m-d H:i:s', time() - self::CADENCE_SECONDS - 10 * MINUTE_IN_SECONDS);
        $items = $this->select_diverse_items($since, 5);

        if (empty($items)) {
            update_option('coin680whale_last_digest', time());
            return;
        }

        $text = $this->build_and_get_text($items, $since, '30 min');

        if (class_exists('Coin680X_Queue')) {
            // Single post only -- no first-comment/poll reply. A reply on
            // every 30-minute post read as extra noise in the channel rather
            // than added value, so this was removed per direct feedback.
            Coin680X_Queue::add($text, '', '', current_time('mysql', true));
        }

        Coin680Whale_Fetcher::mark_used(wp_list_pluck($items, 'id'));
        update_option('coin680whale_last_digest', time());
    }

    /**
     * Standalone breaking post for a single mega transaction, fired
     * immediately from Coin680Whale_Fetcher::poll() the moment one is
     * captured -- doesn't wait for the next digest cycle.
     */
    public function post_mega_alert($item) {
        $amount_fmt = '$' . number_format($item->amount_usd);
        $blurb = self::classification_blurb($item);
        $url = self::explorer_url($item->blockchain, $item->hash);
        $history = $this->historical_comparison($item);

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
