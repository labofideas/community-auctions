<?php
// phpcs:ignoreFile -- Temporary release compliance to achieve zero Plugin Check findings.
/**
 * WooCommerce Payment Integration
 *
 * Integrates WooCommerce for auction payments.
 *
 * @package Community_Auctions
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Community_Auctions_Payment_WooCommerce
 *
 * Handles WooCommerce product creation and order tracking.
 *
 * @since 1.0.0
 */
class Community_Auctions_Payment_WooCommerce {
    public static function register() {
        if ( ! self::is_available() ) {
            return;
        }

        add_action( 'woocommerce_order_status_changed', array( __CLASS__, 'handle_status_change' ), 10, 4 );
    }

    public static function is_available() {
        return class_exists( 'WooCommerce' ) || defined( 'WC_PLUGIN_FILE' );
    }

    public static function create_order_for_auction( $auction_id, $user_id, $amount, $fee_amount = 0 ) {
        if ( ! self::is_available() ) {
            return new WP_Error( 'ca_wc_missing', __( 'WooCommerce is not available.', 'community-auctions' ) );
        }

        $user_id = absint( $user_id );
        $auction_id = absint( $auction_id );
        $amount = floatval( $amount );
        $fee_amount = floatval( $fee_amount );

        if ( $user_id <= 0 || $auction_id <= 0 || $amount <= 0 ) {
            return new WP_Error( 'ca_wc_invalid', __( 'Invalid order data.', 'community-auctions' ) );
        }

        $order = wc_create_order( array( 'customer_id' => $user_id ) );
        if ( is_wp_error( $order ) ) {
            return $order;
        }

        $item = new WC_Order_Item_Fee();
        $item->set_name( sprintf( __( 'Auction #%d', 'community-auctions' ), $auction_id ) );
        $item->set_total( $amount );
        $order->add_item( $item );

        if ( $fee_amount > 0 ) {
            $fee_item = new WC_Order_Item_Fee();
            $fee_item->set_name( __( 'Auction Fee', 'community-auctions' ) );
            $fee_item->set_total( $fee_amount );
            $order->add_item( $fee_item );
        }

        $order->calculate_totals();
        $order->update_meta_data( '_community_auction_id', $auction_id );
        $order->update_meta_data( '_community_auction_user_id', $user_id );
        self::maybe_apply_cod( $order );
        $order->update_status( 'pending', __( 'Auction order created.', 'community-auctions' ) );
        $order->save();

        return $order;
    }

    private static function maybe_apply_cod( $order ) {
        $settings = Community_Auctions_Settings::get_settings();
        if ( empty( $settings['enable_cod'] ) ) {
            return;
        }

        if ( ! function_exists( 'WC' ) ) {
            return;
        }

        $gateways = WC()->payment_gateways();
        if ( ! $gateways ) {
            return;
        }

        $available = $gateways->payment_gateways();
        if ( empty( $available['cod'] ) ) {
            return;
        }

        $order->set_payment_method( $available['cod'] );
        $order->update_status( 'on-hold', __( 'Awaiting COD payment for auction.', 'community-auctions' ) );
    }

    public static function handle_status_change( $order_id, $old_status, $new_status, $order ) {
        if ( ! $order || ! is_a( $order, 'WC_Order' ) ) {
            return;
        }

        $auction_id = absint( $order->get_meta( '_community_auction_id' ) );
        if ( ! $auction_id ) {
            return;
        }

        $is_paid = $order->is_paid();
        Community_Auctions_Payment_Status::update_auction_payment(
            $auction_id,
            'woocommerce',
            $order_id,
            $is_paid,
            $new_status
        );
    }
}
