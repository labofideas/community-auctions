<?php
/**
 * Real-time Bid Updates - Polling mechanism for live auction updates.
 *
 * @package Community_Auctions
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Community_Auctions_Realtime_Updates
 *
 * Provides real-time bid updates via polling.
 */
class Community_Auctions_Realtime_Updates {

	/**
	 * Default polling interval in seconds.
	 */
	const DEFAULT_INTERVAL = 15;

	/**
	 * Register hooks.
	 */
	public static function register() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_rest_routes' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
	}

	/**
	 * Register REST API routes.
	 */
	public static function register_rest_routes() {
		register_rest_route(
			'community-auctions/v1',
			'/status/batch',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_batch_status' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'auction_ids' => array(
						'required'          => true,
						'type'              => 'array',
						'items'             => array( 'type' => 'integer' ),
						'sanitize_callback' => function( $ids ) {
							return array_map( 'absint', (array) $ids );
						},
					),
				),
			)
		);

		register_rest_route(
			'community-auctions/v1',
			'/status/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_single_status' ),
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
	 * Handle batch status request.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public static function handle_batch_status( $request ) {
		$auction_ids = $request->get_param( 'auction_ids' );

		if ( empty( $auction_ids ) ) {
			return new WP_REST_Response( array( 'auctions' => array() ), 200 );
		}

		$auctions = array();

		foreach ( $auction_ids as $auction_id ) {
			$status = self::get_auction_status( $auction_id );
			if ( $status ) {
				$auctions[ $auction_id ] = $status;
			}
		}

		return new WP_REST_Response(
			array(
				'auctions'  => $auctions,
				'timestamp' => time(),
			),
			200
		);
	}

	/**
	 * Handle single auction status request.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle_single_status( $request ) {
		$auction_id = $request->get_param( 'id' );

		$status = self::get_auction_status( $auction_id );

		if ( ! $status ) {
			return new WP_Error(
				'not_found',
				__( 'Auction not found.', 'community-auctions' ),
				array( 'status' => 404 )
			);
		}

		return new WP_REST_Response(
			array(
				'auction'   => $status,
				'timestamp' => time(),
			),
			200
		);
	}

	/**
	 * Get auction status data.
	 *
	 * @param int $auction_id Auction ID.
	 * @return array|null Status data or null if not found.
	 */
	public static function get_auction_status( $auction_id ) {
		$auction = get_post( $auction_id );

		if ( ! $auction || 'auction' !== $auction->post_type ) {
			return null;
		}

		$current_bid    = floatval( get_post_meta( $auction_id, 'ca_current_bid', true ) );
		$start_price    = floatval( get_post_meta( $auction_id, 'ca_start_price', true ) );
		$current_bidder = absint( get_post_meta( $auction_id, 'ca_current_bidder', true ) );
		$end_time       = get_post_meta( $auction_id, 'ca_end_at', true );
		$start_time     = get_post_meta( $auction_id, 'ca_start_at', true );
		$bid_count      = absint( get_post_meta( $auction_id, 'ca_bid_count', true ) );

		// Get unique bidder count if not cached.
		if ( ! $bid_count && class_exists( 'Community_Auctions_Bid_Repository' ) ) {
			$bid_count = Community_Auctions_Bid_Repository::count_auction_bids( $auction_id );
		}

		// Get unique bidders count.
		$unique_bidders = 0;
		if ( class_exists( 'Community_Auctions_Bid_Repository' ) ) {
			$unique_bidders = Community_Auctions_Bid_Repository::count_unique_bidders( $auction_id );
		}

		// Calculate time remaining.
		$now            = time();
		$end_timestamp  = strtotime( $end_time );
		$seconds_left   = max( 0, $end_timestamp - $now );

		// Determine auction state.
		$status = $auction->post_status;
		if ( 'ca_live' === $status && $seconds_left <= 0 ) {
			$status = 'ended';
		}

		// Get bidder display name if exists.
		$bidder_name = '';
		if ( $current_bidder ) {
			$bidder      = get_userdata( $current_bidder );
			$bidder_name = $bidder ? $bidder->display_name : __( 'Anonymous', 'community-auctions' );
		}

		// Format current bid for display.
		$display_bid = $current_bid > 0 ? $current_bid : $start_price;

		return array(
			'id'             => $auction_id,
			'status'         => $status,
			'current_bid'    => $display_bid,
			'formatted_bid'  => self::format_currency( $display_bid ),
			'bid_count'      => $bid_count,
			'unique_bidders' => $unique_bidders,
			'current_bidder' => array(
				'id'   => $current_bidder,
				'name' => $bidder_name,
			),
			'end_time'       => $end_time,
			'end_timestamp'  => $end_timestamp,
			'seconds_left'   => $seconds_left,
			'has_ended'      => $seconds_left <= 0,
		);
	}

	/**
	 * Format currency amount.
	 *
	 * @param float $amount Amount to format.
	 * @return string Formatted amount.
	 */
	private static function format_currency( $amount ) {
		$settings = Community_Auctions_Settings::get_settings();
		$symbol   = isset( $settings['currency_symbol'] ) ? $settings['currency_symbol'] : '$';
		$position = isset( $settings['currency_position'] ) ? $settings['currency_position'] : 'before';

		$formatted = number_format( $amount, 2 );

		if ( 'after' === $position ) {
			return $formatted . $symbol;
		}

		return $symbol . $formatted;
	}

	/**
	 * Register frontend assets.
	 */
	public static function register_assets() {
		$js_url = plugin_dir_url( __DIR__ ) . 'assets/js/realtime.js';

		wp_register_script(
			'community-auctions-realtime',
			$js_url,
			array(),
			Community_Auctions_Plugin::VERSION,
			true
		);

		$settings = Community_Auctions_Settings::get_settings();
		$interval = isset( $settings['realtime_poll_interval'] ) ? absint( $settings['realtime_poll_interval'] ) : self::DEFAULT_INTERVAL;

		// Ensure minimum interval of 5 seconds.
		$interval = max( 5, $interval );

		wp_localize_script(
			'community-auctions-realtime',
			'CommunityAuctionsRealtime',
			array(
				'restUrl'  => rest_url( 'community-auctions/v1/status' ),
				'nonce'    => wp_create_nonce( 'wp_rest' ),
				'interval' => $interval * 1000, // Convert to milliseconds.
				'enabled'  => ! empty( $settings['realtime_enabled'] ),
				'i18n'     => array(
					'bidPlaced' => __( 'New bid placed!', 'community-auctions' ),
					'ended'     => __( 'Auction ended', 'community-auctions' ),
					'outbid'    => __( 'You have been outbid!', 'community-auctions' ),
				),
			)
		);
	}

	/**
	 * Enqueue realtime assets.
	 */
	public static function enqueue_assets() {
		$settings = Community_Auctions_Settings::get_settings();

		// Only enqueue if realtime is enabled.
		if ( empty( $settings['realtime_enabled'] ) ) {
			return;
		}

		wp_enqueue_script( 'community-auctions-realtime' );
	}
}
