<?php
/**
 * Static config for the multi-chain (EVM) fetcher: which chains are active,
 * which DEX router/pool addresses count as a "DEX trade", which token
 * contracts are known stablecoins (used to read a swap's direction as a
 * buy or a sell), and which token contracts we have a CoinGecko price
 * mapping for. A token NOT in TOKEN_PRICE_IDS is skipped entirely by the
 * fetcher -- we never estimate a price we can't verify, so an unmapped
 * token just doesn't get a $ figure and can't clear the threshold.
 *
 * Only Ethereum/Polygon/Arbitrum are enabled -- that's what the free
 * Etherscan API tier allows (confirmed live: BSC/Base/Optimism/Avalanche
 * all return "Free API access is not supported for this chain" on this
 * key). BSC/Base/Optimism/Avalanche are listed commented-out below --
 * uncomment once the Etherscan key is upgraded to a paid tier, no other
 * code changes needed to bring them online.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Coin680MultiChain_Labels {

    const CHAINS = array(
        'ethereum' => array(
            'chainid'         => 1,
            'label'           => 'Ethereum',
            'explorer'        => 'https://etherscan.io/tx/%s',
        ),
        'polygon' => array(
            'chainid'         => 137,
            'label'           => 'Polygon',
            'explorer'        => 'https://polygonscan.com/tx/%s',
        ),
        'arbitrum' => array(
            'chainid'         => 42161,
            'label'           => 'Arbitrum',
            'explorer'        => 'https://arbiscan.io/tx/%s',
        ),
        // Uncomment once the Etherscan API key is upgraded (LITE $49/mo or
        // higher -- confirmed these return "Free API access is not
        // supported for this chain" on the free tier):
        // 'bsc'       => array('chainid' => 56,    'label' => 'BSC',       'explorer' => 'https://bscscan.com/tx/%s'),
        // 'base'      => array('chainid' => 8453,  'label' => 'Base',      'explorer' => 'https://basescan.org/tx/%s'),
        // 'optimism'  => array('chainid' => 10,     'label' => 'Optimism',  'explorer' => 'https://optimistic.etherscan.io/tx/%s'),
        // 'avalanche' => array('chainid' => 43114, 'label' => 'Avalanche', 'explorer' => 'https://snowtrace.io/tx/%s'),
    );

    /**
     * Known DEX router/pool addresses per chain (lowercase). A Transfer
     * event whose from/to matches one of these is treated as one leg of a
     * DEX swap rather than a plain wallet-to-wallet transfer. Best-effort
     * list of the most widely used routers -- not exhaustive, expandable.
     */
    const DEX_ROUTERS = array(
        'ethereum' => array(
            '0x7a250d5630b4cf539739df2c5dacb4c659f2488d' => 'Uniswap',
            '0xe592427a0aece92de3edee1f18e0157c05861564' => 'Uniswap',
            '0x68b3465833fb72a70ecdf485e0e4c7bd8665fc45' => 'Uniswap',
            '0xd9e1ce17f2641f24ae83637ab66a2cca9c378b9f' => 'SushiSwap',
        ),
        'polygon' => array(
            '0xa5e0829caced8ffdd4de3c43696c57f7d7a678ff' => 'QuickSwap',
            '0x1b02da8cb0d097eb8d57a175b88c7d8b47997506' => 'SushiSwap',
            '0x68b3465833fb72a70ecdf485e0e4c7bd8665fc45' => 'Uniswap',
        ),
        'arbitrum' => array(
            '0xc873fecbd354f5a56e00e710b90ef4201db2448d' => 'Camelot',
            '0x1b02da8cb0d097eb8d57a175b88c7d8b47997506' => 'SushiSwap',
            '0x68b3465833fb72a70ecdf485e0e4c7bd8665fc45' => 'Uniswap',
        ),
    );

    /**
     * Known stablecoin contract addresses per chain (lowercase) -- used
     * only to read a decoded swap's DIRECTION (stablecoin leaving = a buy
     * of the other token, stablecoin arriving = a sell of the other
     * token). Not a price source by itself.
     */
    const STABLECOINS = array(
        'ethereum' => array(
            '0xdac17f958d2ee523a2206206994597c13d831ec7', // USDT
            '0xa0b86991c6218b36c1d19d4a2e9eb0ce3606eb48', // USDC
            '0x6b175474e89094c44da98b954eedeac495271d0f', // DAI
        ),
        'polygon' => array(
            '0xc2132d05d31c914a87c6611c10748aeb04b58e8f', // USDT
            '0x3c499c542cef5e3811e1192ce70d8cc03d5c3359', // USDC (native)
            '0x2791bca1f2de4661ed88a30c99a7a9449aa84174', // USDC.e (bridged)
            '0x8f3cf7ad23cd3cadbd9735aff958023239c6a063', // DAI
        ),
        'arbitrum' => array(
            '0xfd086bc7cd5c481dcc9c85ebe478a1c0b69fcbb9', // USDT
            '0xaf88d065e77c8cc2239327c5edb3a432268e5831', // USDC (native)
            '0xff970a61a04b1ca14834a43f5de4533ebddb5cc8', // USDC.e (bridged)
            '0xda10009cbd5d07dd0cecc66161fc93d7c9000da1', // DAI
        ),
    );

    /**
     * Token contract -> CoinGecko id, so amounts can be converted to USD via
     * the same coin680_get_crypto_prices() feed the rest of the site
     * already uses. Deliberately small at launch -- expand this list over
     * time as you notice tokens you want covered showing up unpriced.
     */
    const TOKEN_PRICE_IDS = array(
        'ethereum' => array(
            '0xdac17f958d2ee523a2206206994597c13d831ec7' => 'tether',
            '0xa0b86991c6218b36c1d19d4a2e9eb0ce3606eb48' => 'usd-coin',
            '0x6b175474e89094c44da98b954eedeac495271d0f' => 'dai',
            '0xc02aaa39b223fe8d0a0e5c4f27ead9083c756cc2' => 'weth',
            '0x2260fac5e5542a773aa44fbcfedf7c193bc2c599' => 'wrapped-bitcoin',
            '0x1f9840a85d5af5bf1d1762f925bdaddc4201f984' => 'uniswap',
            '0x514910771af9ca656af840dff83e8264ecf986ca' => 'chainlink',
        ),
        'polygon' => array(
            '0xc2132d05d31c914a87c6611c10748aeb04b58e8f' => 'tether',
            '0x3c499c542cef5e3811e1192ce70d8cc03d5c3359' => 'usd-coin',
            '0x2791bca1f2de4661ed88a30c99a7a9449aa84174' => 'usd-coin',
            '0x8f3cf7ad23cd3cadbd9735aff958023239c6a063' => 'dai',
            '0x0d500b1d8e8ef31e21c99d1db9a6444d3adf1270' => 'wmatic',
            '0x7ceb23fd6bc0add59e62ac25578270cff1b9f619' => 'weth',
        ),
        'arbitrum' => array(
            '0xfd086bc7cd5c481dcc9c85ebe478a1c0b69fcbb9' => 'tether',
            '0xaf88d065e77c8cc2239327c5edb3a432268e5831' => 'usd-coin',
            '0xff970a61a04b1ca14834a43f5de4533ebddb5cc8' => 'usd-coin',
            '0xda10009cbd5d07dd0cecc66161fc93d7c9000da1' => 'dai',
            '0x82af49447d8a07e3bd95bd0d56f35241523fbab1' => 'weth',
            '0x912ce59144191c1204e64559fe8253a0e49e6548' => 'arbitrum',
        ),
    );

    // keccak256("Transfer(address,address,uint256)") -- the ERC-20 Transfer event signature.
    const TRANSFER_TOPIC = '0xddf252ad1be2c89b69c2b068fc378daa952ba7f163c4a11628f55a4df523b3ef';

    /**
     * CoinGecko ids treated as "major" for threshold purposes -- stablecoins
     * and wrapped BTC/ETH/native-gas tokens use the higher multichain_min_value
     * threshold, same as BTC/ETH/USDT/USDC do on the Whale Alert side.
     * Everything else (UNI, LINK, ARB, and anything added later) uses the
     * lower token_min_value threshold instead, so a genuinely large move in
     * a smaller token isn't held to the same bar as a routine USDT transfer.
     */
    const MAJOR_PRICE_IDS = array('tether', 'usd-coin', 'dai', 'weth', 'wrapped-bitcoin', 'wmatic', 'ethereum', 'bitcoin', 'matic-network');

    public static function is_major_price_id($price_id) {
        return in_array($price_id, self::MAJOR_PRICE_IDS, true);
    }

    public static function chain_config($chain) {
        return self::CHAINS[$chain] ?? null;
    }

    public static function is_dex_router($chain, $address) {
        $address = strtolower($address);
        return self::DEX_ROUTERS[$chain][$address] ?? false;
    }

    public static function is_stablecoin($chain, $address) {
        return in_array(strtolower($address), self::STABLECOINS[$chain] ?? array(), true);
    }

    public static function price_id($chain, $address) {
        $address = strtolower($address);
        return self::TOKEN_PRICE_IDS[$chain][$address] ?? null;
    }
}
