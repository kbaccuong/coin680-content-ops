<?php
/**
 * Manages the "watched wallets" list -- admin-added addresses (per chain)
 * that Coin680Watchlist_Fetcher checks for NEW activity, separate from and
 * in addition to Coin680Bitquery_Fetcher's market-wide large-transaction
 * scan. Selection principle here is address-based (WHO made the trade),
 * not amount-based (HOW MUCH) -- a modest trade from a wallet worth
 * watching is still noteworthy because of who made it.
 *
 * Added 2026-07-30 after upgrading to a paid Bitquery plan gave enough
 * point budget headroom to add a second, address-driven polling pass
 * alongside the existing amount-driven one.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Coin680Watchlist {

    public static function table_name() {
        global $wpdb;
        return $wpdb->prefix . 'coin680_watched_wallets';
    }

    public static function create_table() {
        global $wpdb;
        $table = self::table_name();
        $charset_collate = $wpdb->get_charset_collate();
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta("CREATE TABLE $table (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            chain VARCHAR(20) NOT NULL DEFAULT '',
            address VARCHAR(100) NOT NULL DEFAULT '',
            label VARCHAR(100) NOT NULL DEFAULT '',
            active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY chain_address (chain, address(80)),
            KEY active (active)
        ) $charset_collate;");
    }

    public static function get_all($active_only = true) {
        global $wpdb;
        $table = self::table_name();
        if ($active_only) {
            return $wpdb->get_results("SELECT * FROM $table WHERE active = 1 ORDER BY created_at DESC");
        }
        return $wpdb->get_results("SELECT * FROM $table ORDER BY created_at DESC");
    }

    /**
     * Adds a wallet, or re-activates + relabels it if the same chain+address
     * pair was previously added and then removed (removal is a soft delete
     * -- see remove() below -- so history under that wallet_id in
     * wp_coin680_watchlist_txns isn't orphaned if it's watched again later).
     */
    public static function add($chain, $address, $label) {
        global $wpdb;
        $table = self::table_name();
        $chain = sanitize_key($chain);
        $address = sanitize_text_field($address);
        $label = sanitize_text_field($label);
        if (!$chain || !$address || !isset(Coin680Bitquery_Labels::CHAINS[$chain])) {
            return false;
        }
        $result = $wpdb->query($wpdb->prepare(
            "INSERT INTO $table (chain, address, label, active, created_at)
             VALUES (%s, %s, %s, 1, %s)
             ON DUPLICATE KEY UPDATE label = VALUES(label), active = 1",
            $chain, $address, $label, current_time('mysql', true)
        ));
        return $result !== false;
    }

    /**
     * Soft delete (active = 0) rather than a hard DELETE, so past matches
     * already recorded in wp_coin680_watchlist_txns keep a meaningful
     * wallet_id to join back against instead of pointing at a vanished row.
     */
    public static function remove($id) {
        global $wpdb;
        $table = self::table_name();
        return $wpdb->update($table, array('active' => 0), array('id' => (int) $id), array('%d'), array('%d'));
    }
}
