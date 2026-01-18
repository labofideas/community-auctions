<?php
/**
 * REST API - Expanded endpoints for Community Auctions.
 *
 * @package CommunityAuctions
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles REST API endpoints.
 */
class Community_Auctions_REST_API {

	/**
	 * API namespace.
	 */
	const NAMESPACE = 'community-auctions/v1';

	/**
	 * Register hooks.
	 */
	public static function register() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	/**
	 * Register REST routes.
	 */
	public static function register_routes() {
		// Auctions list.
		register_rest_route(
			self::NAMESPACE,
			'/auctions',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'get_auctions' ),
				'permission_callback' => '__return_true',
				'args'                => self::get_auctions_args(),
			)
		);

		// Single auction.
		register_rest_route(
			self::NAMESPACE,
			'/auctions/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'get_auction' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'id' => array(
						'required'          => true,
						'validate_callback' => function ( $param ) {
							return is_numeric( $param );
						},
					),
				),
			)
		);

		// Upcoming auctions.
		register_rest_route(
			self::NAMESPACE,
			'/auctions/upcoming',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'get_upcoming' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'per_page'    => array(
						'default'           => 10,
						'sanitize_callback' => 'absint',
					),
					'hours_ahead' => array(
						'default'           => 168,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		// Categories.
		register_rest_route(
			self::NAMESPACE,
			'/categories',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'get_categories' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'hide_empty' => array(
						'default'           => false,
						'sanitize_callback' => 'rest_sanitize_boolean',
					),
				),
			)
		);

		// Seller endpoints (requires authentication).
		register_rest_route(
			self::NAMESPACE,
			'/seller/auctions',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'get_seller_auctions' ),
				'permission_callback' => array( __CLASS__, 'check_logged_in' ),
				'args'                => array(
					'status'   => array(
						'default'           => 'all',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'per_page' => array(
						'default'           => 10,
						'sanitize_callback' => 'absint',
					),
					'page'     => array(
						'default'           => 1,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/seller/stats',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'get_seller_stats' ),
				'permission_callback' => array( __CLASS__, 'check_logged_in' ),
			)
		);

		// Buyer endpoints (requires authentication).
		register_rest_route(
			self::NAMESPACE,
			'/buyer/won',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'get_buyer_won' ),
				'permission_callback' => array( __CLASS__, 'check_logged_in' ),
				'args'                => array(
					'status'   => array(
						'default'           => 'all',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'per_page' => array(
						'default'           => 10,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/buyer/stats',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'get_buyer_stats' ),
				'permission_callback' => array( __CLASS__, 'check_logged_in' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/buyer/bids',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'get_buyer_bids' ),
				'permission_callback' => array( __CLASS__, 'check_logged_in' ),
				'args'                => array(
					'per_page' => array(
						'default'           => 20,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);
	}

	/**
	 * Get auctions arguments schema.
	 *
	 * @return array Arguments schema.
	 */
	private static function get_auctions_args() {
		return array(
			'status'    => array(
				'default'           => 'live',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'category'  => array(
				'sanitize_callback' => 'sanitize_text_field',
			),
			'search'    => array(
				'sanitize_callback' => 'sanitize_text_field',
			),
			'per_page'  => array(
				'default'           => 10,
				'sanitize_callback' => 'absint',
			),
			'page'      => array(
				'default'           => 1,
				'sanitize_callback' => 'absint',
			),
			'orderby'   => array(
				'default'           => 'date',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'order'     => array(
				'default'           => 'DESC',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'min_price' => array(
				'sanitize_callback' => 'floatval',
			),
			'max_price' => array(
				'sanitize_callback' => 'floatval',
			),
			'ending'    => array(
				'sanitize_callback' => 'sanitize_text_field',
			),
		);
	}

	/**
	 * Check if user is logged in.
	 *
	 * @return bool Whether user is logged in.
	 */
	public static function check_logged_in() {
		return is_user_logged_in();
	}

	/**
	 * Get auctions list.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public static function get_auctions( $request ) {
		$status   = $request->get_param( 'status' );
		$category = $request->get_param( 'category' );
		$search   = $request->get_param( 'search' );
		$per_page = min( 100, max( 1, $request->get_param( 'per_page' ) ) );
		$page     = max( 1, $request->get_param( 'page' ) );
		$orderby  = $request->get_param( 'orderby' );
		$order    = strtoupper( $request->get_param( 'order' ) ) === 'ASC' ? 'ASC' : 'DESC';

		// Map status to post_status.
		$post_status = array( 'publish', 'ca_live' );
		if ( 'ended' === $status ) {
			$post_status = array( 'ca_ended', 'ca_closed' );
		} elseif ( 'pending' === $status ) {
			$post_status = array( 'ca_pending' );
		} elseif ( 'all' === $status ) {
			$post_status = array( 'publish', 'ca_live', 'ca_pending', 'ca_ended', 'ca_closed' );
		}

		$query_args = array(
			'post_type'      => 'auction',
			'post_status'    => $post_status,
			'posts_per_page' => $per_page,
			'paged'          => $page,
			'orderby'        => $orderby,
			'order'          => $order,
		);

		// Search.
		if ( $search ) {
			$query_args['s'] = $search;
		}

		// Category filter.
		if ( $category ) {
			$query_args['tax_query'] = array(
				array(
					'taxonomy' => Community_Auctions_Taxonomy::TAXONOMY,
					'field'    => is_numeric( $category ) ? 'term_id' : 'slug',
					'terms'    => $category,
				),
			);
		}

		// Price filters.
		$meta_query = array();
		$min_price  = $request->get_param( 'min_price' );
		$max_price  = $request->get_param( 'max_price' );

		if ( $min_price ) {
			$meta_query[] = array(
				'key'     => 'ca_current_bid',
				'value'   => $min_price,
				'type'    => 'NUMERIC',
				'compare' => '>=',
			);
		}

		if ( $max_price ) {
			$meta_query[] = array(
				'key'     => 'ca_current_bid',
				'value'   => $max_price,
				'type'    => 'NUMERIC',
				'compare' => '<=',
			);
		}

		// Ending filter.
		$ending = $request->get_param( 'ending' );
		if ( 'soon' === $ending ) {
			$query_args['orderby']  = 'meta_value';
			$query_args['order']    = 'ASC';
			$query_args['meta_key'] = 'ca_end_at';
		}

		if ( ! empty( $meta_query ) ) {
			$query_args['meta_query'] = $meta_query;
		}

		$query    = new WP_Query( $query_args );
		$auctions = array();

		foreach ( $query->posts as $post ) {
			$auctions[] = self::format_auction( $post->ID );
		}

		return rest_ensure_response(
			array(
				'success'     => true,
				'auctions'    => $auctions,
				'total'       => $query->found_posts,
				'total_pages' => $query->max_num_pages,
				'page'        => $page,
				'per_page'    => $per_page,
			)
		);
	}

	/**
	 * Get single auction.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public static function get_auction( $request ) {
		$auction_id = absint( $request->get_param( 'id' ) );
		$auction    = get_post( $auction_id );

		if ( ! $auction || 'auction' !== $auction->post_type ) {
			return new WP_Error( 'not_found', __( 'Auction not found.', 'community-auctions' ), array( 'status' => 404 ) );
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'auction' => self::format_auction( $auction_id, true ),
			)
		);
	}

	/**
	 * Get upcoming auctions.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public static function get_upcoming( $request ) {
		$per_page    = min( 100, max( 1, $request->get_param( 'per_page' ) ) );
		$hours_ahead = max( 1, $request->get_param( 'hours_ahead' ) );

		$auctions = Community_Auctions_Upcoming::get_upcoming_auctions(
			array(
				'per_page'    => $per_page,
				'hours_ahead' => $hours_ahead,
			)
		);

		$formatted = array();
		foreach ( $auctions as $auction ) {
			$formatted[] = self::format_auction( $auction->ID );
		}

		return rest_ensure_response(
			array(
				'success'  => true,
				'auctions' => $formatted,
			)
		);
	}

	/**
	 * Get categories.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public static function get_categories( $request ) {
		$hide_empty = $request->get_param( 'hide_empty' );

		$terms = get_terms(
			array(
				'taxonomy'   => Community_Auctions_Taxonomy::TAXONOMY,
				'hide_empty' => $hide_empty,
			)
		);

		if ( is_wp_error( $terms ) ) {
			return rest_ensure_response(
				array(
					'success'    => true,
					'categories' => array(),
				)
			);
		}

		$categories = array();
		foreach ( $terms as $term ) {
			$categories[] = array(
				'id'          => $term->term_id,
				'name'        => $term->name,
				'slug'        => $term->slug,
				'description' => $term->description,
				'count'       => $term->count,
				'parent'      => $term->parent,
			);
		}

		return rest_ensure_response(
			array(
				'success'    => true,
				'categories' => $categories,
			)
		);
	}

	/**
	 * Get seller's auctions.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public static function get_seller_auctions( $request ) {
		$user_id  = get_current_user_id();
		$status   = $request->get_param( 'status' );
		$per_page = min( 100, max( 1, $request->get_param( 'per_page' ) ) );
		$page     = max( 1, $request->get_param( 'page' ) );

		$post_status = array( 'publish', 'ca_live', 'ca_pending', 'ca_ended', 'ca_closed' );
		if ( 'active' === $status ) {
			$post_status = array( 'publish', 'ca_live' );
		} elseif ( 'pending' === $status ) {
			$post_status = array( 'ca_pending' );
		} elseif ( 'ended' === $status ) {
			$post_status = array( 'ca_ended', 'ca_closed' );
		}

		$query = new WP_Query(
			array(
				'post_type'      => 'auction',
				'post_status'    => $post_status,
				'author'         => $user_id,
				'posts_per_page' => $per_page,
				'paged'          => $page,
			)
		);

		$auctions = array();
		foreach ( $query->posts as $post ) {
			$auctions[] = self::format_auction( $post->ID );
		}

		return rest_ensure_response(
			array(
				'success'     => true,
				'auctions'    => $auctions,
				'total'       => $query->found_posts,
				'total_pages' => $query->max_num_pages,
			)
		);
	}

	/**
	 * Get seller stats.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public static function get_seller_stats( $request ) {
		$user_id = get_current_user_id();
		$stats   = Community_Auctions_Seller_Dashboard::get_seller_stats( $user_id );

		return rest_ensure_response(
			array(
				'success' => true,
				'stats'   => $stats,
			)
		);
	}

	/**
	 * Get buyer's won auctions.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public static function get_buyer_won( $request ) {
		$user_id  = get_current_user_id();
		$status   = $request->get_param( 'status' );
		$per_page = min( 100, max( 1, $request->get_param( 'per_page' ) ) );

		$query = new WP_Query(
			array(
				'post_type'      => 'auction',
				'post_status'    => array( 'ca_ended', 'ca_closed' ),
				'posts_per_page' => $per_page,
				'meta_query'     => array(
					array(
						'key'     => 'ca_winner_id',
						'value'   => $user_id,
						'compare' => '=',
						'type'    => 'NUMERIC',
					),
				),
			)
		);

		$settings = Community_Auctions_Settings::get_settings();
		$provider = $settings['payment_provider'] ?? '';

		$auctions = array();
		foreach ( $query->posts as $post ) {
			$auction            = self::format_auction( $post->ID );
			$order_id           = absint( get_post_meta( $post->ID, 'ca_order_id', true ) );
			$auction['paid']    = $order_id ? Community_Auctions_Payment_Status::is_order_paid( $order_id, $provider ) : false;
			$auction['pay_url'] = '';

			if ( $order_id && ! $auction['paid'] ) {
				$auction['pay_url'] = Community_Auctions_Payment_Status::get_payment_link( $order_id, $provider );
			}

			// Filter by payment status.
			if ( 'pending' === $status && $auction['paid'] ) {
				continue;
			}
			if ( 'paid' === $status && ! $auction['paid'] ) {
				continue;
			}

			$auctions[] = $auction;
		}

		return rest_ensure_response(
			array(
				'success'  => true,
				'auctions' => $auctions,
				'total'    => count( $auctions ),
			)
		);
	}

	/**
	 * Get buyer stats.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public static function get_buyer_stats( $request ) {
		$user_id = get_current_user_id();
		$stats   = Community_Auctions_Buyer_Dashboard::get_buyer_stats( $user_id );

		return rest_ensure_response(
			array(
				'success' => true,
				'stats'   => $stats,
			)
		);
	}

	/**
	 * Get buyer's bid history.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public static function get_buyer_bids( $request ) {
		global $wpdb;

		$user_id  = get_current_user_id();
		$per_page = min( 100, max( 1, $request->get_param( 'per_page' ) ) );
		$table    = $wpdb->prefix . 'ca_bids';

		$bids = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE user_id = %d ORDER BY created_at DESC LIMIT %d",
				$user_id,
				$per_page
			)
		);

		$formatted = array();
		foreach ( $bids as $bid ) {
			$auction_id = absint( $bid->auction_id );
			$formatted[] = array(
				'id'            => absint( $bid->id ),
				'auction_id'    => $auction_id,
				'auction_title' => get_the_title( $auction_id ),
				'auction_url'   => get_permalink( $auction_id ),
				'amount'        => floatval( $bid->amount ),
				'amount_formatted' => Community_Auctions_Currency::format( $bid->amount, $auction_id ),
				'is_proxy'      => ! empty( $bid->is_proxy ),
				'created_at'    => $bid->created_at,
			);
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'bids'    => $formatted,
			)
		);
	}

	/**
	 * Format auction data for API response.
	 *
	 * @param int  $auction_id Auction post ID.
	 * @param bool $full       Whether to include full details.
	 * @return array Formatted auction data.
	 */
	private static function format_auction( $auction_id, $full = false ) {
		$auction = get_post( $auction_id );
		if ( ! $auction ) {
			return null;
		}

		$current_bid  = get_post_meta( $auction_id, 'ca_current_bid', true );
		$start_price  = get_post_meta( $auction_id, 'ca_start_price', true );
		$end_at       = get_post_meta( $auction_id, 'ca_end_at', true );
		$start_at     = get_post_meta( $auction_id, 'ca_start_at', true );
		$bid_count    = Community_Auctions_Bid_Repository::count_auction_bids( $auction_id );
		$end_timestamp = strtotime( $end_at );
		$seconds_left = $end_timestamp ? max( 0, $end_timestamp - time() ) : 0;

		$data = array(
			'id'               => $auction_id,
			'title'            => $auction->post_title,
			'url'              => get_permalink( $auction_id ),
			'status'           => $auction->post_status,
			'current_bid'      => floatval( $current_bid ) ?: floatval( $start_price ),
			'current_bid_formatted' => Community_Auctions_Currency::format( $current_bid ?: $start_price, $auction_id ),
			'start_price'      => floatval( $start_price ),
			'bid_count'        => $bid_count,
			'start_at'         => $start_at,
			'end_at'           => $end_at,
			'seconds_left'     => $seconds_left,
			'has_ended'        => in_array( $auction->post_status, array( 'ca_ended', 'ca_closed' ), true ),
			'featured_image'   => get_the_post_thumbnail_url( $auction_id, 'medium' ),
		);

		// Categories.
		$categories = Community_Auctions_Taxonomy::get_auction_categories( $auction_id );
		$data['categories'] = array();
		foreach ( $categories as $cat ) {
			$data['categories'][] = array(
				'id'   => $cat->term_id,
				'name' => $cat->name,
				'slug' => $cat->slug,
			);
		}

		// Full details.
		if ( $full ) {
			$data['content']        = $auction->post_content;
			$data['excerpt']        = $auction->post_excerpt;
			$data['author_id']      = $auction->post_author;
			$data['author_name']    = get_the_author_meta( 'display_name', $auction->post_author );
			$data['reserve_price']  = floatval( get_post_meta( $auction_id, 'ca_reserve_price', true ) );
			$data['min_increment']  = floatval( get_post_meta( $auction_id, 'ca_min_increment', true ) );
			$data['proxy_enabled']  = ! empty( get_post_meta( $auction_id, 'ca_proxy_enabled', true ) );
			$data['buy_now_enabled'] = ! empty( get_post_meta( $auction_id, 'ca_buy_now_enabled', true ) );
			$data['buy_now_price']  = floatval( get_post_meta( $auction_id, 'ca_buy_now_price', true ) );
			$data['winner_id']      = absint( get_post_meta( $auction_id, 'ca_winner_id', true ) );

			// Gallery.
			$gallery_ids = Community_Auctions_Image_Gallery::get_gallery_ids( $auction_id );
			$data['gallery'] = array();
			foreach ( $gallery_ids as $image_id ) {
				$data['gallery'][] = array(
					'id'        => $image_id,
					'thumbnail' => wp_get_attachment_image_url( $image_id, 'thumbnail' ),
					'medium'    => wp_get_attachment_image_url( $image_id, 'medium' ),
					'large'     => wp_get_attachment_image_url( $image_id, 'large' ),
					'full'      => wp_get_attachment_image_url( $image_id, 'full' ),
				);
			}
		}

		return $data;
	}
}
