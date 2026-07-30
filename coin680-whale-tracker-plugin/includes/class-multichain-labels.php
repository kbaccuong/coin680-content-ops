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
 * Only Ethereum + BSC enabled for scanning/posting (2026-07-30, narrowed
 * down per direct request -- ERC20 and BEP20 are what's actually needed;
 * the other EVM chains Etherscan can reach (Polygon/Arbitrum/Base/
 * Optimism/Avalanche) are commented out below rather than deleted, so
 * turning them back on later is just uncommenting -- no rebuild needed.
 * Router/stablecoin/token/price-id config for the disabled chains is left
 * in place further down this file (harmless while unused).
 *
 * BSC also has `full_token_scan` turned on (see CHAINS['bsc']) -- BSC has
 * far too many low-cap/meme tokens to hand-maintain in TOKEN_PRICE_IDS one
 * at a time, so for that chain the fetcher additionally discovers ANY
 * token (mapped or not) that touches a known DEX router and prices it via
 * whichever side of the swap IS mapped (a stablecoin or WBNB/WETH), rather
 * than requiring the token itself to be pre-configured. See
 * Coin680MultiChain_Fetcher::discover_router_logs().
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
        // 'polygon' => array(
        //     'chainid'         => 137,
        //     'label'           => 'Polygon',
        //     'explorer'        => 'https://polygonscan.com/tx/%s',
        // ),
        // 'arbitrum' => array(
        //     'chainid'         => 42161,
        //     'label'           => 'Arbitrum',
        //     'explorer'        => 'https://arbiscan.io/tx/%s',
        // ),
        'bsc' => array(
            'chainid'  => 56,
            'label'    => 'BSC',
            'explorer' => 'https://bscscan.com/tx/%s',
            // BSC has enormous numbers of low-cap/meme tokens that will
            // never all get hand-added to TOKEN_PRICE_IDS -- this flag
            // turns on discover_router_logs() in the fetcher, which finds
            // ANY token (mapped or not) touching a known router and prices
            // it via whichever side of the swap IS mapped (stablecoin or
            // WBNB/WETH), instead of requiring the token itself to be
            // pre-configured. Off by default elsewhere since it roughly
            // doubles the API calls for a chain -- turn on per-chain if
            // meme-coin coverage is wanted there too.
            'full_token_scan' => true,
        ),
        // 'base' => array(
        //     'chainid'  => 8453,
        //     'label'    => 'Base',
        //     'explorer' => 'https://basescan.org/tx/%s',
        // ),
        // 'optimism' => array(
        //     'chainid'  => 10,
        //     'label'    => 'Optimism',
        //     'explorer' => 'https://optimistic.etherscan.io/tx/%s',
        // ),
        // 'avalanche' => array(
        //     'chainid'  => 43114,
        //     'label'    => 'Avalanche',
        //     'explorer' => 'https://snowtrace.io/tx/%s',
        // ),
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
        'bsc' => array(
            '0x10ed43c718714eb63d5aa57b78b54704e256024e' => 'PancakeSwap',
            '0x13f4ea83d0bd40e75c8222255bc855a974568dd4' => 'PancakeSwap',
        ),
        'base' => array(
            '0x2626664c2603336e57b271c5c0b26f421741e481' => 'Uniswap',
            '0x327df1e6de05895d2ab08513aadd9313fe505d86' => 'BaseSwap',
        ),
        'optimism' => array(
            '0x2626664c2603336e57b271c5c0b26f421741e481' => 'Uniswap',
            '0xa062ae8a9c5e11aaa026fc2670b0d65ccc8b2858' => 'Velodrome',
        ),
        'avalanche' => array(
            '0x60ae616a2155ee3d9a68541ba4544862310933d4' => 'Trader Joe',
            '0xe54ca86531e17ef3616d22ca28b0d458b6c89106' => 'Pangolin',
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
        'bsc' => array(
            '0x55d398326f99059ff775485246999027b3197955', // USDT
            '0x8ac76a51cc950d9822d68b83fe1ad97b32cd580d', // USDC
            '0xe9e7cea3dedca5984780bafc599bd69add087d56', // BUSD
            '0x1af3f329e8be154074d8769d1ffa4ee058b1dbc3', // DAI
        ),
        'base' => array(
            '0x833589fcd6edb6e08f4c7c32d4f71b54bda02913', // USDC (native)
            '0xd9aaec86b65d86f6a7b5b1b0c42ffa531710b6ca', // USDbC (bridged)
            '0x50c5725949a6f0c72e6c4a641f24049a917db0cb', // DAI
        ),
        'optimism' => array(
            '0x0b2c639c533813f4aa9d7837caf62653d097ff85', // USDC (native)
            '0x94b008aa00579c1307b0ef2c499ad98a8ce58e58', // USDT
            '0xda10009cbd5d07dd0cecc66161fc93d7c9000da1', // DAI
        ),
        'avalanche' => array(
            '0x9702230a8ea53601f5cd2dc00fdbc13d4df4a8c7', // USDT
            '0xb97ef9ef8734c71904d8002f8b6bc66dd9c48a6e', // USDC
            '0xd586e7f844cea2f87f50152665bcbc2c279d8d70', // DAI.e
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
            // WETH/WBTC mapped to the NATIVE coin's id (not a separate
            // "weth"/"wrapped-bitcoin" listing) -- CoinGecko tracks the
            // wrapped token's own market cap separately from the native
            // coin's, and that wrapped-specific listing sometimes falls
            // outside the top 250 fetched by coin680_get_crypto_prices(),
            // causing a silent "NOT FOUND" even though ETH/BTC themselves
            // are obviously always in range. Same fix applied to every
            // wrapped-native mapping below.
            '0xc02aaa39b223fe8d0a0e5c4f27ead9083c756cc2' => 'ethereum',
            '0x2260fac5e5542a773aa44fbcfedf7c193bc2c599' => 'bitcoin',
            '0x1f9840a85d5af5bf1d1762f925bdaddc4201f984' => 'uniswap',
            '0x514910771af9ca656af840dff83e8264ecf986ca' => 'chainlink',
        ),
        'polygon' => array(
            '0xc2132d05d31c914a87c6611c10748aeb04b58e8f' => 'tether',
            '0x3c499c542cef5e3811e1192ce70d8cc03d5c3359' => 'usd-coin',
            '0x2791bca1f2de4661ed88a30c99a7a9449aa84174' => 'usd-coin',
            '0x8f3cf7ad23cd3cadbd9735aff958023239c6a063' => 'dai',
            '0x0d500b1d8e8ef31e21c99d1db9a6444d3adf1270' => 'matic-network',
            '0x7ceb23fd6bc0add59e62ac25578270cff1b9f619' => 'ethereum',
        ),
        'arbitrum' => array(
            '0xfd086bc7cd5c481dcc9c85ebe478a1c0b69fcbb9' => 'tether',
            '0xaf88d065e77c8cc2239327c5edb3a432268e5831' => 'usd-coin',
            '0xff970a61a04b1ca14834a43f5de4533ebddb5cc8' => 'usd-coin',
            '0xda10009cbd5d07dd0cecc66161fc93d7c9000da1' => 'dai',
            '0x82af49447d8a07e3bd95bd0d56f35241523fbab1' => 'ethereum',
            '0x912ce59144191c1204e64559fe8253a0e49e6548' => 'arbitrum',
        ),
        'bsc' => array(
            '0x55d398326f99059ff775485246999027b3197955' => 'tether',
            '0x8ac76a51cc950d9822d68b83fe1ad97b32cd580d' => 'usd-coin',
            '0xe9e7cea3dedca5984780bafc599bd69add087d56' => 'binance-usd',
            '0x1af3f329e8be154074d8769d1ffa4ee058b1dbc3' => 'dai',
            '0xbb4cdb9cbd36b01bd1cbaebf2de08d9173bc095c' => 'binancecoin',
            '0x7130d2a12b9bcbfae4f2634d864a1ee1ce3ead9c' => 'bitcoin',
            '0x2170ed0880ac9a755fd29b2688956bd959f933f8' => 'ethereum',
            '0x0e09fabb73bd3ade0a17ecc321fd13a19e81ce82' => 'pancakeswap-token',
        ),
        'base' => array(
            '0x833589fcd6edb6e08f4c7c32d4f71b54bda02913' => 'usd-coin',
            '0xd9aaec86b65d86f6a7b5b1b0c42ffa531710b6ca' => 'usd-coin',
            '0x50c5725949a6f0c72e6c4a641f24049a917db0cb' => 'dai',
            '0x4200000000000000000000000000000000000006' => 'ethereum',
            '0xcbb7c0000ab88b473b1f5afd9ef808440eed33bf' => 'bitcoin',
        ),
        'optimism' => array(
            '0x0b2c639c533813f4aa9d7837caf62653d097ff85' => 'usd-coin',
            '0x94b008aa00579c1307b0ef2c499ad98a8ce58e58' => 'tether',
            '0xda10009cbd5d07dd0cecc66161fc93d7c9000da1' => 'dai',
            '0x4200000000000000000000000000000000000006' => 'ethereum',
            '0x4200000000000000000000000000000000000042' => 'optimism',
            '0x68f180fcce6836688e9084f035309e29bf0a2095' => 'bitcoin',
        ),
        'avalanche' => array(
            '0x9702230a8ea53601f5cd2dc00fdbc13d4df4a8c7' => 'tether',
            '0xb97ef9ef8734c71904d8002f8b6bc66dd9c48a6e' => 'usd-coin',
            '0xd586e7f844cea2f87f50152665bcbc2c279d8d70' => 'dai',
            '0xb31f66aa3c1e785363f0875a1b74e27b85fd66c7' => 'avalanche-2',
            '0x49d5c2bdffac6ce2bfdb6640f4f80f226bc10bab' => 'ethereum',
            '0x50b7545627a5162f82a992c33b87adc75187b218' => 'bitcoin',
        ),
    );

    // keccak256("Transfer(address,address,uint256)") -- the ERC-20 Transfer event signature.
    const TRANSFER_TOPIC = '0xddf252ad1be2c89b69c2b068fc378daa952ba7f163c4a11628f55a4df523b3ef';

    /**
     * CoinGecko ids treated as "major"/numeraire for threshold AND swap-
     * direction purposes -- stablecoins and wrapped BTC/ETH/native-gas
     * tokens (now mapped directly to their native id, see TOKEN_PRICE_IDS)
     * use the higher multichain_min_value threshold, same as BTC/ETH/USDT/
     * USDC do on the Whale Alert side. Everything else (UNI, LINK, ARB,
     * CAKE, OP, and any newly-discovered meme/low-cap token) uses the lower
     * token_min_value threshold instead, so a genuinely large move in a
     * smaller token isn't held to the same bar as a routine USDT transfer.
     * Also used to decide DEX Buy/Sell vs generic DEX Swap: a volatile
     * token traded against one of these (stablecoin OR WBNB/WETH-class
     * asset) gets clear buy/sell direction; volatile-vs-volatile is always
     * just "DEX Swap" (ambiguous which side is "the trade").
     */
    const MAJOR_PRICE_IDS = array('tether', 'usd-coin', 'dai', 'binance-usd', 'ethereum', 'bitcoin', 'matic-network', 'binancecoin', 'avalanche-2');

    public static function is_major_price_id($price_id) {
        return in_array($price_id, self::MAJOR_PRICE_IDS, true);
    }

    public static function full_token_scan_enabled($chain) {
        return !empty(self::CHAINS[$chain]['full_token_scan']);
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
