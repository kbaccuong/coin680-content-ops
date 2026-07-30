<?php
/**
 * Static config for the Bitquery-based fetcher: which chains are active
 * and, per chain, which SYMBOLS count as "numeraire" (stablecoins + the
 * chain's wrapped/native gas token) -- used both for the two-tier USD
 * threshold and to decide DEX Buy/Sell direction vs generic DEX Swap.
 *
 * Unlike the Etherscan-based Coin680MultiChain_Fetcher, this is SYMBOL-
 * based, not contract-address-based -- Bitquery's GraphQL API already
 * returns a human-readable Currency.Symbol and a ready-computed
 * Trade.Buy/Sell.AmountInUSD for every trade, so there's no need to
 * hand-maintain a token-contract -> CoinGecko-id map or a stablecoin-
 * address list per chain the way the Etherscan build required.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Coin680Bitquery_Labels {

    const CHAINS = array(
        'solana' => array(
            'label'    => 'Solana',
            'query_kind' => 'solana', // uses the `Solana { DEXTrades }` root
            'explorer' => 'https://solscan.io/tx/%s',
        ),
        'bsc' => array(
            'label'    => 'BSC',
            'query_kind' => 'evm',
            'network'  => 'bsc', // used as EVM(network: bsc)
            'explorer' => 'https://bscscan.com/tx/%s',
        ),
        'ethereum' => array(
            'label'    => 'Ethereum',
            'query_kind' => 'evm',
            'network'  => 'eth',
            'explorer' => 'https://etherscan.io/tx/%s',
        ),
        'tron' => array(
            'label'    => 'TRON',
            'query_kind' => 'tron', // uses the `Tron { DEXTrades }` root
            'explorer' => 'https://tronscan.org/#/transaction/%s',
        ),
    );

    /**
     * Symbols treated as "numeraire" per chain -- stablecoins and the
     * chain's own wrapped/native gas token. A trade where one side is a
     * numeraire and the other isn't gets clean DEX Buy/Sell direction
     * (arriving = buy, leaving = sell) with the non-numeraire side as the
     * reported symbol; a numeraire-vs-numeraire or non-numeraire-vs-non-
     * numeraire pair is just "DEX Swap" (ambiguous which side is "the
     * trade"). This is deliberately loose (symbol match, not verified
     * contract address) -- a scam token that fakes a stablecoin's SYMBOL
     * (not its real contract) could slip through as a false numeraire;
     * acceptable tradeoff for the simplicity this gives, but worth knowing.
     */
    const NUMERAIRE_SYMBOLS = array(
        'solana'   => array('USDT', 'USDC', 'WSOL', 'SOL'),
        'bsc'      => array('USDT', 'USDC', 'BUSD', 'DAI', 'WBNB', 'BNB'),
        'ethereum' => array('USDT', 'USDC', 'DAI', 'WETH', 'ETH'),
        'tron'     => array('USDT', 'USDC', 'WTRX', 'TRX'),
    );

    public static function chain_config($chain) {
        return self::CHAINS[$chain] ?? null;
    }

    public static function is_numeraire($chain, $symbol) {
        return in_array(strtoupper($symbol), self::NUMERAIRE_SYMBOLS[$chain] ?? array(), true);
    }
}
