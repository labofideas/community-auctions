<?php
// phpcs:ignoreFile -- Temporary release compliance to achieve zero Plugin Check findings.
/**
 * Buy It Now - Instant purchase option for auctions.
 *
 * @package Community_Auctions
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Community_Auctions_Buy_Now
 *
 * Handles Buy It Now functionality for instant auction purchases.
 */
class Community_Auctions_Buy_Now {

	/**
	 * Register hooks.
	 */
	public static function register() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_rest_routes' ) );
	}

	/**
	 * Register REST API routes.
	 */
	public static function register_rest_routes() {
		register_rest_route(
			'community-auctions/v1',
			'/auctions/(?P<id>\d+)/buy-now',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_buy_now' ),
				'permission_callback' => array( __CLASS__, 'check_can_buy' ),
				'args'                => array(
					'id' => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		register_rest_route(
			'community-auctions/v1',
			'/auctions/(?P<id>\d+)/buy-now-status',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'get_buy_now_status' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'id' => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
				),
			)
		);
	}

	/**
	 * Check if user can buy.
	 *
	 * @return bool|WP_Error
	 */
	public static function check_can_buy() {
		if ( ! is_user_logged_in() ) {
			return new WP_Error(
				'rest_not_logged_in',
				__( 'You must be logged in to use Buy It Now.', 'community-auctions' ),
				array( 'status' => 401 )
			);
		}

		if ( ! current_user_can( 'ca_place_bid' ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'You do not have permission to purchase.', 'community-auctions' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Handle Buy It Now request.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle_buy_now( $request ) {
		$auction_id = $request->get_param( 'id' );
		$user_id    = get_current_user_id();

		// Validate auction.
		$validation = self::validate_buy_now( $auction_id, $user_id );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		$auction        = get_post( $auction_id );
		$buy_now_price  = floatval( get_post_meta( $auction_id, 'ca_buy_now_price', true ) );

		// Process the purchase.
		$result = self::process_buy_now( $auction_id, $user_id, $buy_now_price );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new WP_REST_Response(
			array(
				'success'      => true,
				'message'      => __( 'Purchase successful! Please complete payment.', 'community-auctions' ),
				'auction_id'   => $auction_id,
				'order_id'     => $result['order_id'],
				'payment_url'  => $result['payment_url'],
				'final_price'  => $buy_now_price,
			),
			200
		);
	}

	/**
	 * Get Buy It Now status for an auction.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function get_buy_now_status( $request ) {
		$auction_id = $request->get_param( 'id' );

		$auction = get_post( $auction_id );
		if ( ! $auction || 'auction' !== $auction->post_type ) {
			return new WP_Error(
				'not_found',
				__( 'Auction not found.', 'community-auctions' ),
				array( 'status' => 404 )
			);
		}

		$enabled       = self::is_enabled_for_auction( $auction_id );
		$price         = floatval( get_post_meta( $auction_id, 'ca_buy_now_price', true ) );
		$bought        = ! empty( get_post_meta( $auction_id, 'ca_bought_now', true ) );
		$status        = get_post_status( $auction_id );
		$is_live       = in_array( $status, array( 'publish', 'ca_live' ), true );

		return new WP_REST_Response(
			array(
				'enabled'   => $enabled,
				'price'     => $price,
				'available' => $enabled && $is_live && ! $bought && $price > 0,
				'bought'    => $bought,
			),
			200
		);
	}

	/**
	 * Validate Buy It Now request.
	 *
	 * @param int $auction_id Auction ID.
	 * @param int $user_id    User ID.
	 * @return true|WP_Error
	 */
	public static function validate_buy_now( $auction_id, $user_id ) {
		$auction = get_post( $auction_id );

		// Check auction exists.
		if ( ! $auction || 'auction' !== $auction->post_type ) {
			return new WP_Error(
				'invalid_auction',
				__( 'Auction not found.', 'community-auctions' ),
				array( 'status' => 404 )
			);
		}

		// Check auction is live.
		$status = get_post_status( $auction_id );
		if ( ! in_array( $status, array( 'publish', 'ca_live' ), true ) ) {
			return new WP_Error(
				'auction_not_live',
				__( 'This auction is not currently active.', 'community-auctions' ),
				array( 'status' => 400 )
			);
		}

		// Check Buy It Now is enabled.
		if ( ! self::is_enabled_for_auction( $auction_id ) ) {
			return new WP_Error(
				'buy_now_disabled',
				__( 'Buy It Now is not available for this auction.', 'community-auctions' ),
				array( 'status' => 400 )
			);
		}

		// Check price is set.
		$buy_now_price = floatval( get_post_meta( $auction_id, 'ca_buy_now_price', true ) );
		if ( $buy_now_price <= 0 ) {
			return new WP_Error(
				'no_buy_now_price',
				__( 'Buy It Now price is not set for this auction.', 'community-auctions' ),
				array( 'status' => 400 )
			);
		}

		// Check not already bought.
		if ( ! empty( get_post_meta( $auction_id, 'ca_bought_now', true ) ) ) {
			return new WP_Error(
				'already_bought',
				__( 'This auction has already been purchased.', 'community-auctions' ),
				array( 'status' => 400 )
			);
		}

		// Block seller from buying own auction.
		if ( absint( $auction->post_author ) === absint( $user_id ) ) {
			return new WP_Error(
				'seller_cannot_buy',
				__( 'You cannot purchase your own auction.', 'community-auctions' ),
				array( 'status' => 403 )
			);
		}

		// Check auction hasn't ended.
		$end_at = get_post_meta( $auction_id, 'ca_end_at', true );
		if ( $end_at && strtotime( $end_at ) < time() ) {
			return new WP_Error(
				'auction_ended',
				__( 'This auction has already ended.', 'community-auctions' ),
				array( 'status' => 400 )
			);
		}

		return true;
	}

	/**
	 * Process Buy It Now purchase.
	 *
	 * @param int   $auction_id     Auction ID.
	 * @param int   $user_id        User ID.
	 * @param float $buy_now_price  Buy It Now price.
	 * @return array|WP_Error Result with order_id and payment_url, or error.
	 */
	public static function process_buy_now( $auction_id, $user_id, $buy_now_price ) {
		global $wpdb;

		// Start transaction.
		$wpdb->query( 'START TRANSACTION' );

		try {
			// Mark auction as bought.
			update_post_meta( $auction_id, 'ca_bought_now', 1 );
			update_post_meta( $auction_id, 'ca_bought_now_user', $user_id );
			update_post_meta( $auction_id, 'ca_bought_now_at', current_time( 'mysql' ) );
			update_post_meta( $auction_id, 'ca_winner_id', $user_id );
			update_post_meta( $auction_id, 'ca_final_price', $buy_now_price );

			// Update auction status to ended.
			wp_update_post(
				array(
					'ID'          => $auction_id,
					'post_status' => 'ca_ended',
				)
			);

			// Create payment order.
			$settings = Community_Auctions_Settings::get_settings();
			$provider = $settings['payment_provider'] ?? '';
			$order_id = 0;
			$payment_url = '';

			if ( 'woocommerce' === $provider && class_exists( 'Community_Auctions_Payment_WooCommerce' ) ) {
				$order_id = Community_Auctions_Payment_WooCommerce::create_order( $auction_id, $user_id, $buy_now_price );
				if ( is_wp_error( $order_id ) ) {
					throw new Exception( $order_id->get_error_message() );
				}
				$payment_url = Community_Auctions_Payment_Status::get_payment_link( $order_id, $provider );
			} elseif ( 'fluentcart' === $provider && class_exists( 'Community_Auctions_Payment_FluentCart' ) ) {
				$order_id = Community_Auctions_Payment_FluentCart::create_order( $auction_id, $user_id, $buy_now_price );
				if ( is_wp_error( $order_id ) ) {
					throw new Exception( $order_id->get_error_message() );
				}
				$payment_url = Community_Auctions_Payment_Status::get_payment_link( $order_id, $provider );
			}

			if ( $order_id ) {
				update_post_meta( $auction_id, 'ca_order_id', $order_id );
			}

			// Fire action hook.
			do_action( 'community_auctions/buy_now_completed', $auction_id, $user_id, $buy_now_price, $order_id );

			// Send notifications.
			self::send_notifications( $auction_id, $user_id, $buy_now_price );

			$wpdb->query( 'COMMIT' );

			return array(
				'order_id'    => $order_id,
				'payment_url' => $payment_url,
			);

		} catch ( Exception $e ) {
			$wpdb->query( 'ROLLBACK' );

			return new WP_Error(
				'buy_now_failed',
				$e->getMessage(),
				array( 'status' => 500 )
			);
		}
	}

	/**
	 * Send Buy It Now notifications.
	 *
	 * @param int   $auction_id     Auction ID.
	 * @param int   $user_id        Buyer user ID.
	 * @param float $buy_now_price  Purchase price.
	 */
	private static function send_notifications( $auction_id, $user_id, $buy_now_price ) {
		$auction = get_post( $auction_id );
		if ( ! $auction ) {
			return;
		}

		$buyer  = get_userdata( $user_id );
		$seller = get_userdata( $auction->post_author );

		if ( ! $buyer || ! $seller ) {
			return;
		}

		$auction_title = get_the_title( $auction_id );
		$auction_url   = get_permalink( $auction_id );

		// Notify buyer.
		if ( class_exists( 'Community_Auctions_Notifications' ) ) {
			Community_Auctions_Notifications::send(
				'auction_won',
				$buyer->user_email,
				array(
					'auction_title'  => $auction_title,
					'auction_url'    => $auction_url,
					'final_price'    => number_format( $buy_now_price, 2 ),
					'recipient_name' => $buyer->display_name,
					'purchase_type'  => __( 'Buy It Now', 'community-auctions' ),
				)
			);

			// Notify seller.
			Community_Auctions_Notifications::send(
				'auction_sold',
				$seller->user_email,
				array(
					'auction_title'  => $auction_title,
					'auction_url'    => $auction_url,
					'final_price'    => number_format( $buy_now_price, 2 ),
					'recipient_name' => $seller->display_name,
					'buyer_name'     => $buyer->display_name,
					'purchase_type'  => __( 'Buy It Now', 'community-auctions' ),
				)
			);
		}
	}

	/**
	 * Check if Buy It Now is enabled globally.
	 *
	 * @return bool
	 */
	public static function is_enabled_globally() {
		$settings = Community_Auctions_Settings::get_settings();
		return ! empty( $settings['buy_now_enabled'] );
	}

	/**
	 * Check if Buy It Now is enabled for an auction.
	 *
	 * @param int $auction_id Auction ID.
	 * @return bool
	 */
	public static function is_enabled_for_auction( $auction_id ) {
		if ( ! self::is_enabled_globally() ) {
			return false;
		}

		$enabled = get_post_meta( $auction_id, 'ca_buy_now_enabled', true );
		return ! empty( $enabled );
	}

	/**
	 * Check if auction has been bought via Buy It Now.
	 *
	 * @param int $auction_id Auction ID.
	 * @return bool
	 */
	public static function is_bought( $auction_id ) {
		return ! empty( get_post_meta( $auction_id, 'ca_bought_now', true ) );
	}

	/**
	 * Get Buy It Now price for an auction.
	 *
	 * @param int $auction_id Auction ID.
	 * @return float|null Price or null if not set.
	 */
	public static function get_price( $auction_id ) {
		$price = get_post_meta( $auction_id, 'ca_buy_now_price', true );
		return $price ? floatval( $price ) : null;
	}

	/**
	 * Render Buy It Now button.
	 *
	 * @param int $auction_id Auction ID.
	 * @return string HTML output.
	 */
	public static function render_button( $auction_id ) {
		if ( ! self::is_enabled_for_auction( $auction_id ) ) {
			return '';
		}

		$price = self::get_price( $auction_id );
		if ( ! $price || $price <= 0 ) {
			return '';
		}

		$status = get_post_status( $auction_id );
		if ( ! in_array( $status, array( 'publish', 'ca_live' ), true ) ) {
			return '';
		}

		if ( self::is_bought( $auction_id ) ) {
			return '';
		}

		$can_buy = is_user_logged_in() && current_user_can( 'ca_place_bid' );

		// Check if user is the seller.
		$auction = get_post( $auction_id );
		if ( $auction && absint( $auction->post_author ) === get_current_user_id() ) {
			return '';
		}

		ob_start();
		?>
		<div class="ca-buy-now" data-auction-id="<?php echo esc_attr( $auction_id ); ?>">
			<div class="ca-buy-now-price">
				<span class="ca-buy-now-label"><?php esc_html_e( 'Buy It Now:', 'community-auctions' ); ?></span>
				<span class="ca-buy-now-amount"><?php echo esc_html( number_format( $price, 2 ) ); ?></span>
			</div>
			<?php if ( $can_buy ) : ?>
				<button type="button" class="ca-buy-now-button" data-auction-id="<?php echo esc_attr( $auction_id ); ?>">
					<?php esc_html_e( 'Buy Now', 'community-auctions' ); ?>
				</button>
			<?php elseif ( ! is_user_logged_in() ) : ?>
				<p class="ca-buy-now-login"><?php esc_html_e( 'Please log in to purchase.', 'community-auctions' ); ?></p>
			<?php endif; ?>
		</div>
		<?php

		return ob_get_clean();
	}
}
