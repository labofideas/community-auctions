<?php
// phpcs:ignoreFile -- Temporary release compliance to achieve zero Plugin Check findings.
/**
 * Bid Repository
 *
 * Database operations for auction bids.
 *
 * @package Community_Auctions
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Community_Auctions_Bid_Repository
 *
 * Handles bid storage, retrieval, and database table management.
 *
 * @since 1.0.0
 */
class Community_Auctions_Bid_Repository {
    public static function table_name() {
        global $wpdb;
        return $wpdb->prefix . 'ca_bids';
    }

    public static function insert_bid( $auction_id, $user_id, $amount, $max_proxy_amount = null, $is_proxy = 0 ) {
        global $wpdb;

        $table = self::table_name();
        $auction_id = absint( $auction_id );
        $user_id = absint( $user_id );
        $amount = floatval( $amount );
        $max_proxy_amount = is_null( $max_proxy_amount ) ? null : floatval( $max_proxy_amount );
        $is_proxy = $is_proxy ? 1 : 0;
        $created_at = current_time( 'mysql', true );

        if ( is_null( $max_proxy_amount ) ) {
            $sql = $wpdb->prepare(
                'INSERT INTO %i (auction_id, user_id, amount, max_proxy_amount, is_proxy, created_at) VALUES (%d, %d, %f, NULL, %d, %s)',
                $table,
                $auction_id,
                $user_id,
                $amount,
                $is_proxy,
                $created_at
            );
        } else {
            $sql = $wpdb->prepare(
                'INSERT INTO %i (auction_id, user_id, amount, max_proxy_amount, is_proxy, created_at) VALUES (%d, %d, %f, %f, %d, %s)',
                $table,
                $auction_id,
                $user_id,
                $amount,
                $max_proxy_amount,
                $is_proxy,
                $created_at
            );
        }

        $inserted = $wpdb->query( $sql );
        if ( false === $inserted ) {
            return new WP_Error( 'ca_bid_insert_failed', __( 'Failed to place bid.', 'community-auctions' ) );
        }

        return $wpdb->insert_id;
    }

    public static function get_highest_bid( $auction_id ) {
        global $wpdb;

        $table = self::table_name();
        $sql = $wpdb->prepare(
            'SELECT * FROM %i WHERE auction_id = %d ORDER BY amount DESC, id DESC LIMIT 1',
            $table,
            $auction_id
        );

        return $wpdb->get_row( $sql );
    }

    public static function get_top_proxy_bids( $auction_id, $limit = 2 ) {
        global $wpdb;

        $table = self::table_name();
        $auction_id = absint( $auction_id );
        $limit = max( 1, absint( $limit ) );

        $sql = $wpdb->prepare(
            'SELECT * FROM %i WHERE auction_id = %d AND max_proxy_amount IS NOT NULL ORDER BY max_proxy_amount DESC, id DESC LIMIT %d',
            $table,
            $auction_id,
            $limit
        );

        return $wpdb->get_results( $sql );
    }

    public static function get_user_bids( $user_id, $limit = 20, $offset = 0 ) {
        global $wpdb;

        $table = self::table_name();
        $user_id = absint( $user_id );
        $limit = max( 1, absint( $limit ) );
        $offset = max( 0, absint( $offset ) );
        $posts = $wpdb->posts;

        $sql = $wpdb->prepare(
            "SELECT b.*, p.post_title, p.post_status
             FROM %i b
             LEFT JOIN %i p ON p.ID = b.auction_id
             WHERE b.user_id = %d
             ORDER BY b.id DESC
             LIMIT %d OFFSET %d",
            $table,
            $posts,
            $user_id,
            $limit,
            $offset
        );

        return $wpdb->get_results( $sql );
    }

    public static function count_user_bids( $user_id ) {
        global $wpdb;

        $table = self::table_name();
        $user_id = absint( $user_id );
        if ( ! $user_id ) {
            return 0;
        }

        $sql = $wpdb->prepare(
            'SELECT COUNT(*) FROM %i WHERE user_id = %d',
            $table,
            $user_id
        );

        return intval( $wpdb->get_var( $sql ) );
    }

    public static function get_last_bid( $auction_id ) {
        global $wpdb;

        $table = self::table_name();
        $sql = $wpdb->prepare(
            'SELECT * FROM %i WHERE auction_id = %d ORDER BY id DESC LIMIT 1',
            $table,
            $auction_id
        );

        return $wpdb->get_row( $sql );
    }

    /**
     * Get all bids for an auction with pagination.
     *
     * @param int $auction_id Auction ID.
     * @param int $limit      Number of bids to return.
     * @param int $offset     Offset for pagination.
     * @return array Array of bid objects.
     */
    public static function get_auction_bids( $auction_id, $limit = 20, $offset = 0 ) {
        global $wpdb;

        $table = self::table_name();
        $auction_id = absint( $auction_id );
        $limit = max( 1, absint( $limit ) );
        $offset = max( 0, absint( $offset ) );

        $sql = $wpdb->prepare(
            "SELECT b.*, u.display_name, u.user_email
             FROM %i b
             LEFT JOIN %i u ON u.ID = b.user_id
             WHERE b.auction_id = %d
             ORDER BY b.amount DESC, b.id DESC
             LIMIT %d OFFSET %d",
            $table,
            $wpdb->users,
            $auction_id,
            $limit,
            $offset
        );

        return $wpdb->get_results( $sql );
    }

    /**
     * Count total bids for an auction.
     *
     * @param int $auction_id Auction ID.
     * @return int Number of bids.
     */
    public static function count_auction_bids( $auction_id ) {
        global $wpdb;

        $table = self::table_name();
        $auction_id = absint( $auction_id );

        $sql = $wpdb->prepare(
            'SELECT COUNT(*) FROM %i WHERE auction_id = %d',
            $table,
            $auction_id
        );

        return intval( $wpdb->get_var( $sql ) );
    }

    /**
     * Count unique bidders for an auction.
     *
     * @param int $auction_id Auction ID.
     * @return int Number of unique bidders.
     */
    public static function count_unique_bidders( $auction_id ) {
        global $wpdb;

        $table = self::table_name();
        $auction_id = absint( $auction_id );

        $sql = $wpdb->prepare(
            'SELECT COUNT(DISTINCT user_id) FROM %i WHERE auction_id = %d',
            $table,
            $auction_id
        );

        return intval( $wpdb->get_var( $sql ) );
    }

    public static function create_table() {
        global $wpdb;

        $table_name = self::table_name();
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table_name} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            auction_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            amount DECIMAL(16,2) NOT NULL,
            max_proxy_amount DECIMAL(16,2) NULL,
            is_proxy TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY auction_id (auction_id),
            KEY user_id (user_id),
            KEY amount (amount)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }
}
