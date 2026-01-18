<?php
/**
 * Auction Engine
 *
 * Handles bidding logic, REST API endpoints, and auction state management.
 *
 * @package Community_Auctions
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Community_Auctions_Auction_Engine
 *
 * Core bidding engine with REST API endpoints.
 *
 * @since 1.0.0
 */
class Community_Auctions_Auction_Engine {
    public static function register() {
        add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
    }

    public static function register_routes() {
        register_rest_route( 'community-auctions/v1', '/bid', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'permission_callback' => array( __CLASS__, 'can_place_bid' ),
            'callback'            => array( __CLASS__, 'handle_place_bid' ),
            'args'                => array(
                'auction_id' => array(
                    'type'     => 'integer',
                    'required' => true,
                ),
                'amount' => array(
                    'type'     => 'number',
                    'required' => true,
                ),
                'proxy_max' => array(
                    'type'     => 'number',
                    'required' => false,
                ),
            ),
        ) );
    }

    public static function can_place_bid( WP_REST_Request $request ) {
        if ( ! is_user_logged_in() ) {
            return false;
        }

        if ( ! current_user_can( 'ca_place_bid' ) ) {
            return false;
        }

        $nonce = $request->get_header( 'X-WP-Nonce' );
        if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
            return false;
        }

        if ( ! self::is_payment_provider_selected() ) {
            return false;
        }

        return true;
    }

    public static function handle_place_bid( WP_REST_Request $request ) {
        $auction_id = absint( $request->get_param( 'auction_id' ) );
        $amount = floatval( $request->get_param( 'amount' ) );
        $proxy_max = $request->get_param( 'proxy_max' );
        $proxy_max = ( null === $proxy_max || '' === $proxy_max ) ? null : floatval( $proxy_max );

        if ( ! self::is_payment_provider_selected() ) {
            return new WP_REST_Response( array(
                'success' => false,
                'message' => __( 'Please select a payment provider before bidding.', 'community-auctions' ),
            ), 400 );
        }

        $result = self::place_bid( $auction_id, get_current_user_id(), $amount, $proxy_max );
        if ( is_wp_error( $result ) ) {
            return new WP_REST_Response( array(
                'success' => false,
                'message' => $result->get_error_message(),
            ), 400 );
        }

        return new WP_REST_Response( array(
            'success' => true,
            'data'    => $result,
        ), 200 );
    }

    public static function place_bid( $auction_id, $user_id, $amount, $proxy_max = null ) {
        $auction = get_post( $auction_id );
        if ( ! $auction || 'auction' !== $auction->post_type ) {
            return new WP_Error( 'ca_invalid_auction', __( 'Invalid auction.', 'community-auctions' ) );
        }

        $status = get_post_status( $auction_id );
        if ( ! in_array( $status, array( 'publish', 'ca_live' ), true ) ) {
            return new WP_Error( 'ca_auction_not_live', __( 'Auction is not live.', 'community-auctions' ) );
        }

        if ( ! self::can_user_bid_on_auction( $auction_id, $user_id ) ) {
            return new WP_Error( 'ca_auction_no_access', __( 'You do not have access to this auction.', 'community-auctions' ) );
        }

        // Block seller from bidding on own auction.
        $settings = Community_Auctions_Settings::get_settings();
        if ( ! empty( $settings['block_seller_bidding'] ) ) {
            if ( absint( $auction->post_author ) === absint( $user_id ) ) {
                return new WP_Error( 'ca_seller_cannot_bid', __( 'You cannot bid on your own auction.', 'community-auctions' ) );
            }
        }

        // Prevent duplicate bids when already highest bidder.
        if ( ! empty( $settings['prevent_duplicate_highest'] ) ) {
            $current_bidder = absint( get_post_meta( $auction_id, 'ca_current_bidder', true ) );
            if ( $current_bidder === absint( $user_id ) ) {
                return new WP_Error( 'ca_already_highest', __( 'You are already the highest bidder.', 'community-auctions' ) );
            }
        }

        // Maximum bid limit.
        $max_bid_limit = floatval( $settings['max_bid_limit'] ?? 0 );
        if ( $max_bid_limit > 0 && $amount > $max_bid_limit ) {
            return new WP_Error(
                'ca_bid_exceeds_limit',
                sprintf(
                    /* translators: %s: maximum bid limit */
                    __( 'Bid amount exceeds the maximum limit of %s.', 'community-auctions' ),
                    number_format( $max_bid_limit, 2 )
                )
            );
        }

        $end_at = get_post_meta( $auction_id, 'ca_end_at', true );
        if ( $end_at ) {
            $end_ts = strtotime( $end_at );
            if ( $end_ts && $end_ts <= time() ) {
                return new WP_Error( 'ca_auction_ended', __( 'Auction has ended.', 'community-auctions' ) );
            }
        }

        $highest = Community_Auctions_Bid_Repository::get_highest_bid( $auction_id );
        $current_highest = $highest ? floatval( $highest->amount ) : floatval( get_post_meta( $auction_id, 'ca_start_price', true ) );
        $min_increment = floatval( get_post_meta( $auction_id, 'ca_min_increment', true ) );
        if ( $min_increment <= 0 ) {
            $min_increment = 1;
        }

        $required_min = $current_highest + $min_increment;
        if ( $amount < $required_min ) {
            return new WP_Error( 'ca_bid_too_low', __( 'Bid amount is too low.', 'community-auctions' ) );
        }

        if ( ! self::is_proxy_enabled( $auction_id ) ) {
            $proxy_max = null;
        }

        $is_proxy = 0;
        if ( null !== $proxy_max && $proxy_max > $amount ) {
            $is_proxy = 1;
        } else {
            $proxy_max = null;
        }

        $inserted = Community_Auctions_Bid_Repository::insert_bid( $auction_id, $user_id, $amount, $proxy_max, $is_proxy );
        if ( is_wp_error( $inserted ) ) {
            return $inserted;
        }

        update_post_meta( $auction_id, 'ca_current_bid', $amount );
        update_post_meta( $auction_id, 'ca_current_bidder', $user_id );

        if ( $highest && intval( $highest->user_id ) !== $user_id ) {
            do_action( 'community_auctions/bid_outbid', $auction_id, intval( $highest->user_id ), $user_id );
        }

        do_action( 'community_auctions/bid_placed', $auction_id, $user_id, $amount );

        self::process_proxy_bids( $auction_id );
        self::maybe_extend_end_time( $auction_id );

        return array(
            'auction_id'     => $auction_id,
            'bid_id'         => $inserted,
            'amount'         => $amount,
            'current_highest'=> $amount,
            'current_highest_bidder' => get_post_meta( $auction_id, 'ca_current_bidder', true ),
        );
    }

    private static function is_payment_provider_selected() {
        $settings = Community_Auctions_Settings::get_settings();
        $provider = $settings['payment_provider'] ?? '';
        if ( empty( $provider ) ) {
            return false;
        }

        if ( 'woocommerce' === $provider ) {
            return Community_Auctions_Payment_WooCommerce::is_available();
        }

        if ( 'fluentcart' === $provider ) {
            return Community_Auctions_Payment_FluentCart::is_available();
        }

        return false;
    }

    private static function maybe_extend_end_time( $auction_id ) {
        $settings = Community_Auctions_Settings::get_settings();
        $minutes = intval( $settings['anti_sniping_minutes'] ?? 0 );
        if ( $minutes <= 0 ) {
            return;
        }

        $end_at = get_post_meta( $auction_id, 'ca_end_at', true );
        if ( ! $end_at ) {
            return;
        }

        $end_ts = strtotime( $end_at );
        if ( ! $end_ts ) {
            return;
        }

        $threshold = $end_ts - ( $minutes * 60 );
        if ( time() >= $threshold ) {
            $new_end = gmdate( 'Y-m-d H:i:s', $end_ts + ( $minutes * 60 ) );
            update_post_meta( $auction_id, 'ca_end_at', $new_end );
        }
    }

    private static function is_proxy_enabled( $auction_id ) {
        $enabled = get_post_meta( $auction_id, 'ca_proxy_enabled', true );
        return ! empty( $enabled );
    }

    private static function can_user_bid_on_auction( $auction_id, $user_id ) {
        $visibility = get_post_meta( $auction_id, 'ca_visibility', true );
        if ( 'group_only' !== $visibility ) {
            return true;
        }

        $group_id = absint( get_post_meta( $auction_id, 'ca_group_id', true ) );
        if ( ! $group_id || ! function_exists( 'groups_is_user_member' ) ) {
            return false;
        }

        return groups_is_user_member( $user_id, $group_id );
    }

    private static function process_proxy_bids( $auction_id ) {
        if ( ! self::is_proxy_enabled( $auction_id ) ) {
            return;
        }

        $min_increment = floatval( get_post_meta( $auction_id, 'ca_min_increment', true ) );
        if ( $min_increment <= 0 ) {
            $min_increment = 1;
        }

        for ( $i = 0; $i < 5; $i++ ) {
            $highest = Community_Auctions_Bid_Repository::get_highest_bid( $auction_id );
            if ( ! $highest ) {
                return;
            }

            $current_amount = floatval( $highest->amount );
            $current_user = intval( $highest->user_id );

            $proxies = Community_Auctions_Bid_Repository::get_top_proxy_bids( $auction_id, 2 );
            if ( empty( $proxies ) ) {
                return;
            }

            $top = $proxies[0];
            $top_user = intval( $top->user_id );
            $top_max = floatval( $top->max_proxy_amount );
            $second = $proxies[1] ?? null;
            $second_max = $second ? floatval( $second->max_proxy_amount ) : 0;

            if ( $top_max <= $current_amount ) {
                return;
            }

            if ( $current_user === $top_user ) {
                return;
            }

            $target_min = $current_amount + $min_increment;
            if ( $second ) {
                $target_min = max( $target_min, $second_max + $min_increment );
            }

            $target = min( $top_max, $target_min );
            if ( $target <= $current_amount ) {
                return;
            }

            $inserted = Community_Auctions_Bid_Repository::insert_bid(
                $auction_id,
                $top_user,
                $target,
                $top_max,
                1
            );

            if ( is_wp_error( $inserted ) ) {
                return;
            }

            update_post_meta( $auction_id, 'ca_current_bid', $target );
            update_post_meta( $auction_id, 'ca_current_bidder', $top_user );

            if ( $current_user && $current_user !== $top_user ) {
                do_action( 'community_auctions/bid_outbid', $auction_id, $current_user, $top_user );
            }

            do_action( 'community_auctions/bid_placed', $auction_id, $top_user, $target );
        }
    }
}
