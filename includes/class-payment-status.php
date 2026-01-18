<?php
/**
 * Payment Status
 *
 * Unified payment status checking across providers.
 *
 * @package Community_Auctions
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Community_Auctions_Payment_Status
 *
 * Checks order payment status for WooCommerce and FluentCart.
 *
 * @since 1.0.0
 */
class Community_Auctions_Payment_Status {
    public static function is_order_paid( $order_id, $provider ) {
        $order_id = absint( $order_id );
        if ( ! $order_id ) {
            return false;
        }

        if ( 'woocommerce' === $provider && function_exists( 'wc_get_order' ) ) {
            $order = wc_get_order( $order_id );
            return $order && $order->is_paid();
        }

        if ( 'fluentcart' === $provider && class_exists( '\FluentCart\App\Models\Order' ) ) {
            $order = \FluentCart\App\Models\Order::find( $order_id );
            if ( ! $order ) {
                return false;
            }
            $status = isset( $order->payment_status ) ? $order->payment_status : '';
            return 'paid' === $status || 'completed' === $status;
        }

        return false;
    }

    public static function get_payment_link( $order_id, $provider ) {
        $order_id = absint( $order_id );
        if ( ! $order_id ) {
            return '';
        }

        if ( 'woocommerce' === $provider && function_exists( 'wc_get_order' ) ) {
            $order = wc_get_order( $order_id );
            if ( ! $order ) {
                return '';
            }
            return $order->get_checkout_payment_url();
        }

        if ( 'fluentcart' === $provider && class_exists( '\FluentCart\App\Models\Order' ) ) {
            $order = \FluentCart\App\Models\Order::find( $order_id );
            if ( ! $order ) {
                return '';
            }
            return apply_filters( 'community_auctions/fluentcart_payment_url', '', $order );
        }

        return '';
    }

    public static function update_auction_payment( $auction_id, $provider, $order_id, $is_paid, $status = '' ) {
        $auction_id = absint( $auction_id );
        if ( ! $auction_id ) {
            return;
        }

        update_post_meta( $auction_id, 'ca_payment_status', $status ? $status : ( $is_paid ? 'paid' : 'pending' ) );
        update_post_meta( $auction_id, 'ca_order_id', absint( $order_id ) );
        if ( $is_paid ) {
            update_post_meta( $auction_id, 'ca_paid_at', gmdate( 'Y-m-d H:i:s' ) );
            do_action( 'community_auctions/auction_payment_completed', $auction_id, $provider, $order_id );
        }
    }
}
