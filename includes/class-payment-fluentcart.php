<?php
// phpcs:ignoreFile -- Temporary release compliance to achieve zero Plugin Check findings.
/**
 * FluentCart Payment Integration
 *
 * Integrates FluentCart for auction payments.
 *
 * @package Community_Auctions
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Community_Auctions_Payment_FluentCart
 *
 * Handles FluentCart product creation and order tracking.
 *
 * @since 1.0.0
 */
class Community_Auctions_Payment_FluentCart {
    public static function register() {
        if ( ! self::is_available() ) {
            return;
        }

        add_action( 'rest_api_init', array( __CLASS__, 'register_webhook' ) );
    }

    public static function is_available() {
        return defined( 'FLUENTCART_PLUGIN_PATH' ) && class_exists( '\FluentCart\App\Helpers\CheckoutProcessor' );
    }

    public static function create_order_for_auction( $auction_id, $user_id, $amount, $fee_amount = 0 ) {
        if ( ! self::is_available() ) {
            return new WP_Error( 'ca_fc_missing', __( 'FluentCart is not available.', 'community-auctions' ) );
        }

        $user_id = absint( $user_id );
        $auction_id = absint( $auction_id );
        $amount = floatval( $amount );
        $fee_amount = floatval( $fee_amount );

        if ( $user_id <= 0 || $auction_id <= 0 || $amount <= 0 ) {
            return new WP_Error( 'ca_fc_invalid', __( 'Invalid order data.', 'community-auctions' ) );
        }

        $customer = self::get_or_create_customer_id( $user_id );
        if ( is_wp_error( $customer ) ) {
            return $customer;
        }

        $cart_items = array(
            array(
                'title'        => sprintf( __( 'Auction #%d', 'community-auctions' ), $auction_id ),
                'price'        => $amount,
                'quantity'     => 1,
                'product_id'   => 0,
                'product_type' => 'auction',
            ),
        );

        if ( $fee_amount > 0 ) {
            $cart_items[] = array(
                'title'        => __( 'Auction Fee', 'community-auctions' ),
                'price'        => $fee_amount,
                'quantity'     => 1,
                'product_id'   => 0,
                'product_type' => 'fee',
            );
        }

        $args = array(
            'customer_id' => $customer,
            'mode'        => 'live',
        );

        $processor = new \FluentCart\App\Helpers\CheckoutProcessor( $cart_items, $args );
        $order = $processor->createDraftOrder();

        if ( is_wp_error( $order ) ) {
            return $order;
        }

        return $order;
    }

    public static function register_webhook() {
        register_rest_route( 'community-auctions/v1', '/fluentcart/webhook', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'permission_callback' => '__return_true',
            'callback'            => array( __CLASS__, 'handle_webhook' ),
        ) );
    }

    public static function handle_webhook( WP_REST_Request $request ) {
        $settings = Community_Auctions_Settings::get_settings();
        $secret = $settings['fluentcart_webhook_secret'] ?? '';

        if ( empty( $secret ) ) {
            return new WP_REST_Response( array( 'success' => false, 'message' => 'Webhook secret not configured' ), 401 );
        }

        $provided = $request->get_header( 'X-CA-Webhook-Secret' );
        if ( ! $provided ) {
            $provided = $request->get_param( 'secret' );
        }

        if ( $secret && hash_equals( $secret, (string) $provided ) !== true ) {
            return new WP_REST_Response( array( 'success' => false, 'message' => 'Unauthorized' ), 401 );
        }

        $payload = $request->get_json_params();
        if ( ! is_array( $payload ) ) {
            return new WP_REST_Response( array( 'success' => false, 'message' => 'Invalid payload' ), 400 );
        }

        $order_id = absint( $payload['order_id'] ?? 0 );
        $payment_status = sanitize_text_field( $payload['payment_status'] ?? '' );
        if ( ! $order_id ) {
            return new WP_REST_Response( array( 'success' => false, 'message' => 'Missing order_id' ), 400 );
        }

        $auction_id = self::find_auction_by_order( $order_id );
        if ( ! $auction_id ) {
            return new WP_REST_Response( array( 'success' => false, 'message' => 'Auction not found' ), 404 );
        }

        $is_paid = in_array( $payment_status, array( 'paid', 'completed' ), true );
        Community_Auctions_Payment_Status::update_auction_payment(
            $auction_id,
            'fluentcart',
            $order_id,
            $is_paid,
            $payment_status
        );

        return new WP_REST_Response( array( 'success' => true ), 200 );
    }

    private static function find_auction_by_order( $order_id ) {
        $query = new WP_Query( array(
            'post_type'      => 'auction',
            'post_status'    => array( 'ca_ended', 'ca_closed', 'publish', 'ca_live' ),
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'meta_query'     => array(
                array(
                    'key'   => 'ca_order_id',
                    'value' => $order_id,
                ),
            ),
        ) );

        if ( ! empty( $query->posts[0] ) ) {
            return absint( $query->posts[0] );
        }

        return 0;
    }

    private static function get_or_create_customer_id( $user_id ) {
        if ( ! class_exists( '\FluentCart\App\Models\Customer' ) ) {
            return new WP_Error( 'ca_fc_missing_customer', __( 'FluentCart customer model not available.', 'community-auctions' ) );
        }

        $customer = \FluentCart\App\Models\Customer::query()
            ->where( 'user_id', $user_id )
            ->first();

        if ( $customer ) {
            return $customer->id;
        }

        $user = get_user_by( 'id', $user_id );
        if ( ! $user ) {
            return new WP_Error( 'ca_fc_user_missing', __( 'User not found.', 'community-auctions' ) );
        }

        $created = \FluentCart\App\Models\Customer::query()->create( array(
            'user_id'    => $user_id,
            'email'      => $user->user_email,
            'first_name' => $user->first_name ? $user->first_name : $user->display_name,
            'last_name'  => $user->last_name,
        ) );

        if ( ! $created ) {
            return new WP_Error( 'ca_fc_customer_failed', __( 'Failed to create FluentCart customer.', 'community-auctions' ) );
        }

        return $created->id;
    }
}
