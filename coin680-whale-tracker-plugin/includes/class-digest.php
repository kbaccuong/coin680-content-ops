<?php
/**
 * Builds and posts the recurring "Whale Signal" digest to X.
 *
 * Guarantees a fresh post at least once per hour (checked every 30 min),
 * using only real, already-classified transactions from Coin680Whale_Fetcher
 * -- no fabricated correlations or invented price reactions. The per-
 * classification framing below is standard, widely used analyst shorthand
 * (exchange outflow = often read as accumulation, inflow = often read as
 * potential sell pressure), not a specific claim about what will happen next.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Coin680Whale_Digest {
    private static $instance = null;

    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('coin680whale_digest_check', array($this, 'maybe_post_digest'));
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

    private static function classification_blurb($classification) {
        $blurbs = array(
            'Exchange Outflow'     => 'left an exchange for an unlabeled wallet -- often read as accumulation, not selling',
            'Exchange Inflow'      => 'moved onto an exchange -- often read as potential sell-side pressure',
            'Exchange to Exchange' => 'moved directly between two exchanges',
            'Mint'                 => 'was freshly minted -- new supply entering circulation',
            'Burn'                 => 'was burned, reducing circulating supply',
            'Wallet Transfer'      => 'moved between two unlabeled wallets -- purpose unclear',
        );
        return $blurbs[$classification] ?? 'moved on-chain';
    }

    private static function closing_line($items) {
        $outflow = 0;
        $inflow = 0;
        foreach ($items as $item) {
            if ($item->classification === 'Exchange Outflow') { $outflow++; }
            if ($item->classification === 'Exchange Inflow') { $inflow++; }
        }
        if ($outflow > $inflow) {
            $reads = array(
                'More coins left exchanges than entered this window -- a quiet accumulation signal, historically.',
                'Outflows outweigh inflows here -- often a sign of long-term holders taking supply off exchanges.',
            );
        } elseif ($inflow > $outflow) {
            $reads = array(
                'More coins moved onto exchanges than off this window -- worth watching for near-term selling pressure.',
                'Inflows outweigh outflows here -- one to watch if you\'re already positioned.',
            );
        } else {
            $reads = array(
                'Inflows and outflows are roughly balanced this window -- no clean directional read.',
                'A mixed bag this hour -- no dominant flow either direction.',
            );
        }
        $questions = array(
            'What\'s your read -- accumulation or distribution?',
            'Bullish or bearish signal to you? Drop your take below.',
            'Are whales buying the dip or setting up to sell? Curious what you think.',
            'Does this change how you\'re positioned right now?',
        );
        return $reads[array_rand($reads)] . ' ' . $questions[array_rand($questions)];
    }

    public function build_and_get_text($items) {
        $header = "🐋 #Coin680 Whale Signal (last hour):\n\n";
        $lines = array();
        foreach ($items as $item) {
            $amount_fmt = '$' . number_format($item->amount_usd);
            $blurb = self::classification_blurb($item->classification);
            $url = self::explorer_url($item->blockchain, $item->hash);
            $line = "• {$amount_fmt} " . strtoupper($item->symbol) . " {$blurb}.";
            if ($url) {
                $line .= " {$url}";
            }
            $lines[] = $line;
        }
        $body = implode("\n\n", $lines);
        $closing = "\n\n" . self::closing_line($items);
        $hashtags = "\n\n#Bitcoin #WhaleAlert #OnChain #Crypto";
        return $header . $body . $closing . $hashtags;
    }

    public function maybe_post_digest() {
        $last_digest = (int) get_option('coin680whale_last_digest', 0);
        if ((time() - $last_digest) < HOUR_IN_SECONDS) {
            return;
        }

        global $wpdb;
        $table = Coin680Whale_Fetcher::table_name();
        $since = gmdate('Y-m-d H:i:s', time() - HOUR_IN_SECONDS - 15 * MINUTE_IN_SECONDS);
        $items = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE used_in_digest = 0 AND tx_timestamp >= %s ORDER BY amount_usd DESC LIMIT 5",
            $since
        ));

        if (empty($items)) {
            update_option('coin680whale_last_digest', time());
            return;
        }

        $text = $this->build_and_get_text($items);

        if (class_exists('Coin680X_Queue')) {
            Coin680X_Queue::add($text, '', '', current_time('mysql', true));
        }

        Coin680Whale_Fetcher::mark_used(wp_list_pluck($items, 'id'));
        update_option('coin680whale_last_digest', time());
    }
}
