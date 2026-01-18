<?php
/**
 * Auction Cron Jobs
 *
 * Handles scheduled tasks for closing auctions and payment reminders.
 *
 * @package Community_Auctions
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Community_Auctions_Auction_Cron
 *
 * Manages cron jobs for auction lifecycle events.
 *
 * @since 1.0.0
 */
class Community_Auctions_Auction_Cron {
    const HOOK = 'community_auctions/close_auctions';
    const REMINDER_HOOK = 'community_auctions/payment_reminder';

    public static function register() {
        add_filter( 'cron_schedules', array( __CLASS__, 'register_schedule' ) );
        add_action( 'init', array( __CLASS__, 'schedule' ) );
        add_action( self::HOOK, array( __CLASS__, 'process' ) );
        add_action( self::REMINDER_HOOK, array( __CLASS__, 'handle_payment_reminder' ), 10, 2 );
    }

    public static function register_schedule( $schedules ) {
        if ( ! isset( $schedules['minute'] ) ) {
            $schedules['minute'] = array(
                'interval' => 60,
                'display'  => __( 'Every Minute', 'community-auctions' ),
            );
        }

        return $schedules;
    }

    public static function schedule() {
        if ( ! wp_next_scheduled( self::HOOK ) ) {
            wp_schedule_event( time() + 60, 'minute', self::HOOK );
        }
    }

    public static function process() {
        $now = gmdate( 'Y-m-d H:i:s' );

        $query = new WP_Query( array(
            'post_type'      => 'auction',
            'post_status'    => array( 'publish', 'ca_live' ),
            'posts_per_page' => 25,
            'fields'         => 'ids',
            'meta_query'     => array(
                array(
                    'key'     => 'ca_end_at',
                    'value'   => $now,
                    'compare' => '<=',
                    'type'    => 'DATETIME',
                ),
            ),
        ) );

        if ( ! $query->have_posts() ) {
            return;
        }

        foreach ( $query->posts as $auction_id ) {
            self::close_auction( $auction_id );
        }
    }

    private static function close_auction( $auction_id ) {
        $highest = Community_Auctions_Bid_Repository::get_highest_bid( $auction_id );
        $reserve = floatval( get_post_meta( $auction_id, 'ca_reserve_price', true ) );

        if ( ! $highest || ( $reserve > 0 && floatval( $highest->amount ) < $reserve ) ) {
            wp_update_post( array(
                'ID'          => $auction_id,
                'post_status' => 'ca_ended',
            ) );

            do_action( 'community_auctions/auction_ended', $auction_id, 0 );
            return;
        }

        $winner_id = intval( $highest->user_id );
        $final_amount = floatval( $highest->amount );
        $fee_amount = self::calculate_success_fee( $final_amount );

        wp_update_post( array(
            'ID'          => $auction_id,
            'post_status' => 'ca_ended',
        ) );

        update_post_meta( $auction_id, 'ca_winner_id', $winner_id );
        update_post_meta( $auction_id, 'ca_final_amount', $final_amount );

        $order_id = self::create_payment_order( $auction_id, $winner_id, $final_amount, $fee_amount );
        if ( $order_id && ! is_wp_error( $order_id ) ) {
            update_post_meta( $auction_id, 'ca_order_id', $order_id );
            self::schedule_payment_reminder( $auction_id, $winner_id, $order_id );
        }

        do_action( 'community_auctions/auction_won', $auction_id, $winner_id );
        do_action( 'community_auctions/auction_ended', $auction_id, $winner_id );
    }

    private static function calculate_success_fee( $amount ) {
        $settings = Community_Auctions_Settings::get_settings();
        if ( empty( $settings['success_fee_enabled'] ) ) {
            return 0;
        }

        $fee_amount = floatval( $settings['success_fee_amount'] ?? 0 );
        if ( 'percent' === ( $settings['success_fee_mode'] ?? 'flat' ) ) {
            return round( ( $fee_amount / 100 ) * $amount, 2 );
        }

        return $fee_amount;
    }

    private static function create_payment_order( $auction_id, $winner_id, $amount, $fee_amount ) {
        $settings = Community_Auctions_Settings::get_settings();
        $provider = $settings['payment_provider'] ?? '';
        if ( empty( $provider ) ) {
            return new WP_Error( 'ca_no_provider', __( 'Payment provider not selected.', 'community-auctions' ) );
        }

        if ( 'woocommerce' === $provider ) {
            return Community_Auctions_Payment_WooCommerce::create_order_for_auction( $auction_id, $winner_id, $amount, $fee_amount );
        }

        if ( 'fluentcart' === $provider ) {
            return Community_Auctions_Payment_FluentCart::create_order_for_auction( $auction_id, $winner_id, $amount, $fee_amount );
        }

        return new WP_Error( 'ca_invalid_provider', __( 'Invalid payment provider.', 'community-auctions' ) );
    }

    private static function schedule_payment_reminder( $auction_id, $winner_id, $order_id ) {
        if ( get_post_meta( $auction_id, 'ca_payment_reminder_scheduled', true ) ) {
            return;
        }

        $settings = Community_Auctions_Settings::get_settings();
        $override = get_post_meta( $auction_id, 'ca_payment_reminder_hours', true );
        $hours = $override !== '' ? intval( $override ) : intval( $settings['payment_reminder_hours'] ?? 24 );
        if ( $hours < 1 ) {
            $hours = 24;
        }

        $timestamp = time() + ( $hours * HOUR_IN_SECONDS );
        wp_schedule_single_event( $timestamp, self::REMINDER_HOOK, array( $auction_id, $winner_id ) );
        update_post_meta( $auction_id, 'ca_payment_reminder_scheduled', $timestamp );
        update_post_meta( $auction_id, 'ca_order_id', $order_id );
    }

    public static function handle_payment_reminder( $auction_id, $winner_id ) {
        $order_id = absint( get_post_meta( $auction_id, 'ca_order_id', true ) );
        if ( ! $order_id ) {
            return;
        }

        $provider = ( Community_Auctions_Settings::get_settings()['payment_provider'] ?? '' );
        if ( Community_Auctions_Payment_Status::is_order_paid( $order_id, $provider ) ) {
            return;
        }

        do_action( 'community_auctions/auction_payment_reminder', $auction_id, $winner_id );
    }

    // Payment status helpers live in Community_Auctions_Payment_Status.
}
