<?php
/**
 * Performance optimizations.
 *
 * @package CommunityAuctions
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Community_Auctions_Performance
 *
 * Handles caching, denormalized counters, and background processing.
 */
final class Community_Auctions_Performance {

	/**
	 * Cache group for auction data.
	 */
	const CACHE_GROUP = 'community_auctions';

	/**
	 * Cache expiration in seconds (5 minutes).
	 */
	const CACHE_EXPIRATION = 300;

	/**
	 * Register hooks.
	 */
	public static function register() {
		// Update denormalized counters when bids are placed.
		add_action( 'community_auctions/bid_placed', array( __CLASS__, 'update_counters_on_bid' ), 10, 3 );

		// Update counters when auction status changes.
		add_action( 'transition_post_status', array( __CLASS__, 'update_counters_on_status_change' ), 10, 3 );

		// Cache invalidation hooks.
		add_action( 'community_auctions/bid_placed', array( __CLASS__, 'invalidate_auction_cache' ), 10, 3 );
		add_action( 'save_post_auction', array( __CLASS__, 'invalidate_auction_cache_on_save' ), 10, 1 );

		// Schedule async counter recalculation if Action Scheduler is available.
		if ( self::has_action_scheduler() ) {
			add_action( 'community_auctions/recalculate_counters', array( __CLASS__, 'recalculate_all_counters' ) );
		}

		// Admin notice for recalculating counters.
		add_action( 'admin_init', array( __CLASS__, 'handle_recalculate_request' ) );
	}

	/**
	 * Check if Action Scheduler is available.
	 *
	 * @return bool True if available.
	 */
	public static function has_action_scheduler() {
		return function_exists( 'as_schedule_single_action' );
	}

	/**
	 * Update counters when a bid is placed.
	 *
	 * @param int   $auction_id Auction ID.
	 * @param int   $user_id    Bidder user ID.
	 * @param float $amount     Bid amount.
	 */
	public static function update_counters_on_bid( $auction_id, $user_id, $amount ) {
		global $wpdb;

		$table = $wpdb->prefix . 'ca_bids';

		// Get bid count.
		$bid_count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE auction_id = %d",
				$auction_id
			)
		);

		// Get unique bidder count.
		$unique_bidders = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT user_id) FROM {$table} WHERE auction_id = %d",
				$auction_id
			)
		);

		// Update meta.
		update_post_meta( $auction_id, 'ca_bid_count', absint( $bid_count ) );
		update_post_meta( $auction_id, 'ca_unique_bidders', absint( $unique_bidders ) );

		// Cache the highest bid.
		self::cache_highest_bid( $auction_id );
	}

	/**
	 * Update counters when auction status changes.
	 *
	 * @param string  $new_status New post status.
	 * @param string  $old_status Old post status.
	 * @param WP_Post $post       Post object.
	 */
	public static function update_counters_on_status_change( $new_status, $old_status, $post ) {
		if ( 'auction' !== $post->post_type ) {
			return;
		}

		// Only act on specific transitions.
		$tracked_statuses = array( 'ca_live', 'ca_ended', 'ca_closed' );

		if ( in_array( $new_status, $tracked_statuses, true ) || in_array( $old_status, $tracked_statuses, true ) ) {
			self::recalculate_auction_counters( $post->ID );
		}
	}

	/**
	 * Recalculate counters for a single auction.
	 *
	 * @param int $auction_id Auction ID.
	 */
	public static function recalculate_auction_counters( $auction_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'ca_bids';

		// Get bid count.
		$bid_count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE auction_id = %d",
				$auction_id
			)
		);

		// Get unique bidder count.
		$unique_bidders = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT user_id) FROM {$table} WHERE auction_id = %d",
				$auction_id
			)
		);

		// Get highest bid.
		$highest_bid = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT MAX(amount) FROM {$table} WHERE auction_id = %d",
				$auction_id
			)
		);

		// Update meta.
		update_post_meta( $auction_id, 'ca_bid_count', absint( $bid_count ) );
		update_post_meta( $auction_id, 'ca_unique_bidders', absint( $unique_bidders ) );

		if ( $highest_bid ) {
			update_post_meta( $auction_id, 'ca_current_bid', floatval( $highest_bid ) );
		}
	}

	/**
	 * Recalculate counters for all auctions.
	 * Can be run via Action Scheduler or manually.
	 */
	public static function recalculate_all_counters() {
		$page     = 1;
		$per_page = 100; // Process in batches to avoid memory issues.
		$total    = 0;

		do {
			$auctions = get_posts(
				array(
					'post_type'      => 'auction',
					'post_status'    => array( 'publish', 'ca_pending', 'ca_live', 'ca_ended', 'ca_closed' ),
					'posts_per_page' => $per_page,
					'paged'          => $page,
					'fields'         => 'ids',
					'orderby'        => 'ID',
					'order'          => 'ASC',
				)
			);

			foreach ( $auctions as $auction_id ) {
				self::recalculate_auction_counters( $auction_id );
			}

			$total += count( $auctions );
			++$page;
		} while ( count( $auctions ) === $per_page );

		// Clear global caches.
		wp_cache_delete( 'live_count', self::CACHE_GROUP );
		wp_cache_delete( 'total_bids', self::CACHE_GROUP );

		return $total;
	}

	/**
	 * Schedule async counter recalculation.
	 */
	public static function schedule_counter_recalculation() {
		if ( self::has_action_scheduler() ) {
			as_schedule_single_action(
				time() + 60,
				'community_auctions/recalculate_counters'
			);
		} else {
			// Fallback to immediate calculation.
			self::recalculate_all_counters();
		}
	}

	/**
	 * Cache the highest bid for an auction.
	 *
	 * @param int $auction_id Auction ID.
	 */
	private static function cache_highest_bid( $auction_id ) {
		$highest_bid = get_post_meta( $auction_id, 'ca_current_bid', true );

		if ( ! $highest_bid ) {
			$highest_bid = get_post_meta( $auction_id, 'ca_start_price', true );
		}

		$cache_key = 'highest_bid_' . $auction_id;
		wp_cache_set( $cache_key, $highest_bid, self::CACHE_GROUP, self::CACHE_EXPIRATION );
	}

	/**
	 * Get highest bid with caching.
	 *
	 * @param int $auction_id Auction ID.
	 * @return float Highest bid amount.
	 */
	public static function get_highest_bid( $auction_id ) {
		$cache_key   = 'highest_bid_' . $auction_id;
		$highest_bid = wp_cache_get( $cache_key, self::CACHE_GROUP );

		if ( false === $highest_bid ) {
			$highest_bid = get_post_meta( $auction_id, 'ca_current_bid', true );

			if ( ! $highest_bid ) {
				$highest_bid = get_post_meta( $auction_id, 'ca_start_price', true );
			}

			wp_cache_set( $cache_key, $highest_bid, self::CACHE_GROUP, self::CACHE_EXPIRATION );
		}

		return (float) $highest_bid;
	}

	/**
	 * Get bid count with caching.
	 *
	 * @param int $auction_id Auction ID.
	 * @return int Bid count.
	 */
	public static function get_bid_count( $auction_id ) {
		$cache_key = 'bid_count_' . $auction_id;
		$bid_count = wp_cache_get( $cache_key, self::CACHE_GROUP );

		if ( false === $bid_count ) {
			$bid_count = get_post_meta( $auction_id, 'ca_bid_count', true );

			if ( '' === $bid_count ) {
				// Recalculate if not set.
				self::recalculate_auction_counters( $auction_id );
				$bid_count = get_post_meta( $auction_id, 'ca_bid_count', true );
			}

			wp_cache_set( $cache_key, $bid_count, self::CACHE_GROUP, self::CACHE_EXPIRATION );
		}

		return (int) $bid_count;
	}

	/**
	 * Get unique bidders count with caching.
	 *
	 * @param int $auction_id Auction ID.
	 * @return int Unique bidder count.
	 */
	public static function get_unique_bidders( $auction_id ) {
		$cache_key      = 'unique_bidders_' . $auction_id;
		$unique_bidders = wp_cache_get( $cache_key, self::CACHE_GROUP );

		if ( false === $unique_bidders ) {
			$unique_bidders = get_post_meta( $auction_id, 'ca_unique_bidders', true );

			if ( '' === $unique_bidders ) {
				// Recalculate if not set.
				self::recalculate_auction_counters( $auction_id );
				$unique_bidders = get_post_meta( $auction_id, 'ca_unique_bidders', true );
			}

			wp_cache_set( $cache_key, $unique_bidders, self::CACHE_GROUP, self::CACHE_EXPIRATION );
		}

		return (int) $unique_bidders;
	}

	/**
	 * Invalidate auction cache when bid placed.
	 *
	 * @param int   $auction_id Auction ID.
	 * @param int   $user_id    User ID.
	 * @param float $amount     Bid amount.
	 */
	public static function invalidate_auction_cache( $auction_id, $user_id = 0, $amount = 0 ) {
		wp_cache_delete( 'highest_bid_' . $auction_id, self::CACHE_GROUP );
		wp_cache_delete( 'bid_count_' . $auction_id, self::CACHE_GROUP );
		wp_cache_delete( 'unique_bidders_' . $auction_id, self::CACHE_GROUP );
		wp_cache_delete( 'auction_data_' . $auction_id, self::CACHE_GROUP );
	}

	/**
	 * Invalidate cache on auction save.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function invalidate_auction_cache_on_save( $post_id ) {
		self::invalidate_auction_cache( $post_id );
	}

	/**
	 * Get live auction count with caching.
	 *
	 * @return int Live auction count.
	 */
	public static function get_live_auction_count() {
		$count = wp_cache_get( 'live_count', self::CACHE_GROUP );

		if ( false === $count ) {
			$counts = wp_count_posts( 'auction' );
			$count  = isset( $counts->ca_live ) ? $counts->ca_live : 0;
			wp_cache_set( 'live_count', $count, self::CACHE_GROUP, self::CACHE_EXPIRATION );
		}

		return (int) $count;
	}

	/**
	 * Get auction data with caching.
	 * Returns frequently accessed data in a single cached object.
	 *
	 * @param int $auction_id Auction ID.
	 * @return array Auction data.
	 */
	public static function get_auction_data( $auction_id ) {
		$cache_key = 'auction_data_' . $auction_id;
		$data      = wp_cache_get( $cache_key, self::CACHE_GROUP );

		if ( false === $data ) {
			$post = get_post( $auction_id );

			if ( ! $post || 'auction' !== $post->post_type ) {
				return array();
			}

			$data = array(
				'id'             => $auction_id,
				'title'          => $post->post_title,
				'status'         => $post->post_status,
				'author'         => (int) $post->post_author,
				'start_price'    => (float) get_post_meta( $auction_id, 'ca_start_price', true ),
				'reserve_price'  => (float) get_post_meta( $auction_id, 'ca_reserve_price', true ),
				'current_bid'    => (float) get_post_meta( $auction_id, 'ca_current_bid', true ),
				'highest_bidder' => (int) get_post_meta( $auction_id, 'ca_highest_bidder', true ),
				'bid_count'      => (int) get_post_meta( $auction_id, 'ca_bid_count', true ),
				'unique_bidders' => (int) get_post_meta( $auction_id, 'ca_unique_bidders', true ),
				'start_at'       => get_post_meta( $auction_id, 'ca_start_at', true ),
				'end_at'         => get_post_meta( $auction_id, 'ca_end_at', true ),
				'buy_now_price'  => (float) get_post_meta( $auction_id, 'ca_buy_now_price', true ),
			);

			wp_cache_set( $cache_key, $data, self::CACHE_GROUP, self::CACHE_EXPIRATION );
		}

		return $data;
	}

	/**
	 * Batch get auction data for multiple auctions.
	 * More efficient than individual calls.
	 *
	 * @param array $auction_ids Array of auction IDs.
	 * @return array Array of auction data keyed by ID.
	 */
	public static function batch_get_auction_data( $auction_ids ) {
		$results   = array();
		$to_fetch  = array();

		// Check cache first.
		foreach ( $auction_ids as $auction_id ) {
			$cache_key = 'auction_data_' . $auction_id;
			$data      = wp_cache_get( $cache_key, self::CACHE_GROUP );

			if ( false !== $data ) {
				$results[ $auction_id ] = $data;
			} else {
				$to_fetch[] = $auction_id;
			}
		}

		// Fetch uncached data.
		if ( ! empty( $to_fetch ) ) {
			foreach ( $to_fetch as $auction_id ) {
				$data = self::get_auction_data( $auction_id );
				if ( ! empty( $data ) ) {
					$results[ $auction_id ] = $data;
				}
			}
		}

		return $results;
	}

	/**
	 * Pre-warm cache for auction listings.
	 * Call this before rendering auction lists.
	 *
	 * @param array $auction_ids Array of auction IDs.
	 */
	public static function prewarm_cache( $auction_ids ) {
		if ( empty( $auction_ids ) ) {
			return;
		}

		// Prime post cache.
		_prime_post_caches( $auction_ids );

		// Batch fetch post meta.
		update_meta_cache( 'post', $auction_ids );

		// Batch fetch our computed data.
		self::batch_get_auction_data( $auction_ids );
	}

	/**
	 * Handle admin request to recalculate counters.
	 */
	public static function handle_recalculate_request() {
		if ( ! isset( $_GET['ca_recalculate_counters'] ) || ! isset( $_GET['_wpnonce'] ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'ca_recalculate_counters' ) ) {
			return;
		}

		if ( self::has_action_scheduler() ) {
			self::schedule_counter_recalculation();
			$message = __( 'Counter recalculation has been scheduled.', 'community-auctions' );
		} else {
			$count   = self::recalculate_all_counters();
			/* translators: %d: number of auctions recalculated */
			$message = sprintf( __( 'Counters recalculated for %d auctions.', 'community-auctions' ), $count );
		}

		add_action(
			'admin_notices',
			function () use ( $message ) {
				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $message ) . '</p></div>';
			}
		);
	}

	/**
	 * Get URL for recalculating counters.
	 *
	 * @return string Admin URL with nonce.
	 */
	public static function get_recalculate_url() {
		return wp_nonce_url(
			add_query_arg( 'ca_recalculate_counters', '1', admin_url( 'admin.php?page=community-auctions' ) ),
			'ca_recalculate_counters'
		);
	}

	/**
	 * Clear all auction caches.
	 * Use after bulk updates.
	 */
	public static function clear_all_caches() {
		// Clear object cache group if supported.
		if ( function_exists( 'wp_cache_flush_group' ) ) {
			wp_cache_flush_group( self::CACHE_GROUP );
		} else {
			// Fall back to clearing known keys.
			wp_cache_delete( 'live_count', self::CACHE_GROUP );
			wp_cache_delete( 'total_bids', self::CACHE_GROUP );

			// Clear individual auction caches in batches to avoid memory issues.
			$page     = 1;
			$per_page = 100;

			do {
				$auction_ids = get_posts(
					array(
						'post_type'      => 'auction',
						'post_status'    => 'any',
						'posts_per_page' => $per_page,
						'paged'          => $page,
						'fields'         => 'ids',
						'orderby'        => 'ID',
						'order'          => 'ASC',
					)
				);

				foreach ( $auction_ids as $id ) {
					self::invalidate_auction_cache( $id );
				}

				++$page;
			} while ( count( $auction_ids ) === $per_page );
		}
	}

	/**
	 * Get performance stats for debugging.
	 *
	 * @return array Performance statistics.
	 */
	public static function get_stats() {
		global $wpdb;

		$table = $wpdb->prefix . 'ca_bids';

		return array(
			'total_auctions'     => wp_count_posts( 'auction' ),
			'live_auctions'      => self::get_live_auction_count(),
			'total_bids'         => $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ),
			'unique_bidders'     => $wpdb->get_var( "SELECT COUNT(DISTINCT user_id) FROM {$table}" ),
			'has_object_cache'   => wp_using_ext_object_cache(),
			'has_action_scheduler' => self::has_action_scheduler(),
			'cache_group'        => self::CACHE_GROUP,
		);
	}
}
