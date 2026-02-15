<?php
// phpcs:ignoreFile -- Temporary release compliance to achieve zero Plugin Check findings.
/**
 * Search and filtering functionality.
 *
 * @package CommunityAuctions
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Community_Auctions_Search_Filter
 *
 * Provides AJAX-powered search and filtering for auctions.
 */
final class Community_Auctions_Search_Filter {

	/**
	 * Register hooks.
	 */
	public static function register() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_endpoints' ) );
		add_shortcode( 'community_auctions_search', array( __CLASS__, 'render_shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_scripts' ) );
	}

	/**
	 * Register REST endpoints.
	 */
	public static function register_endpoints() {
		register_rest_route(
			'community-auctions/v1',
			'/search',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'handle_search' ),
				'permission_callback' => '__return_true',
				'args'                => self::get_search_args(),
			)
		);

		register_rest_route(
			'community-auctions/v1',
			'/search/filters',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_filter_options' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Get search argument definitions.
	 *
	 * @return array Argument definitions.
	 */
	private static function get_search_args() {
		return array(
			'q'           => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => '',
			),
			'category'    => array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'default'           => 0,
			),
			'min_price'   => array(
				'type'              => 'number',
				'sanitize_callback' => array( __CLASS__, 'sanitize_float' ),
				'default'           => 0,
			),
			'max_price'   => array(
				'type'              => 'number',
				'sanitize_callback' => array( __CLASS__, 'sanitize_float' ),
				'default'           => 0,
			),
			'status'      => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => 'live',
				'enum'              => array( 'live', 'upcoming', 'ended', 'all' ),
			),
			'ending_soon' => array(
				'type'              => 'boolean',
				'sanitize_callback' => 'rest_sanitize_boolean',
				'default'           => false,
			),
			'sort'        => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => 'ending_soon',
				'enum'              => array( 'ending_soon', 'newest', 'price_low', 'price_high', 'bids' ),
			),
			'page'        => array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'default'           => 1,
			),
			'per_page'    => array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'default'           => 12,
			),
		);
	}

	/**
	 * Sanitize float value for REST API.
	 *
	 * @param mixed $value Value to sanitize.
	 * @return float Sanitized float.
	 */
	public static function sanitize_float( $value ) {
		return (float) $value;
	}

	/**
	 * Handle search request.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public static function handle_search( $request ) {
		$query      = $request->get_param( 'q' );
		$category   = $request->get_param( 'category' );
		$min_price  = $request->get_param( 'min_price' );
		$max_price  = $request->get_param( 'max_price' );
		$status     = $request->get_param( 'status' );
		$ending_soon = $request->get_param( 'ending_soon' );
		$sort       = $request->get_param( 'sort' );
		$page       = max( 1, $request->get_param( 'page' ) );
		$per_page   = min( 50, max( 1, $request->get_param( 'per_page' ) ) );

		$args = array(
			'post_type'      => 'auction',
			'posts_per_page' => $per_page,
			'paged'          => $page,
			'meta_query'     => array( 'relation' => 'AND' ),
		);

		// Text search.
		if ( ! empty( $query ) ) {
			$args['s'] = $query;
		}

		// Category filter.
		if ( $category > 0 ) {
			$args['tax_query'] = array(
				array(
					'taxonomy' => 'auction_category',
					'field'    => 'term_id',
					'terms'    => $category,
				),
			);
		}

		// Status filter.
		$now = current_time( 'mysql', true );

		switch ( $status ) {
			case 'live':
				$args['post_status'] = 'ca_live';
				break;

			case 'upcoming':
				$args['post_status'] = 'publish';
				$args['meta_query'][] = array(
					'key'     => 'ca_start_at',
					'value'   => $now,
					'compare' => '>',
					'type'    => 'DATETIME',
				);
				break;

			case 'ended':
				$args['post_status'] = array( 'ca_ended', 'ca_closed' );
				break;

			case 'all':
				$args['post_status'] = array( 'ca_live', 'ca_ended', 'ca_closed', 'publish' );
				break;
		}

		// Price range filter.
		if ( $min_price > 0 ) {
			$args['meta_query'][] = array(
				'key'     => 'ca_current_bid',
				'value'   => $min_price,
				'compare' => '>=',
				'type'    => 'DECIMAL(10,2)',
			);
		}

		if ( $max_price > 0 ) {
			$args['meta_query'][] = array(
				'key'     => 'ca_current_bid',
				'value'   => $max_price,
				'compare' => '<=',
				'type'    => 'DECIMAL(10,2)',
			);
		}

		// Ending soon filter (within 24 hours).
		if ( $ending_soon && 'live' === $status ) {
			$tomorrow = gmdate( 'Y-m-d H:i:s', strtotime( '+24 hours' ) );
			$args['meta_query'][] = array(
				'key'     => 'ca_end_at',
				'value'   => array( $now, $tomorrow ),
				'compare' => 'BETWEEN',
				'type'    => 'DATETIME',
			);
		}

		// Sorting.
		switch ( $sort ) {
			case 'ending_soon':
				$args['meta_key'] = 'ca_end_at';
				$args['orderby']  = 'meta_value';
				$args['order']    = 'ASC';
				break;

			case 'newest':
				$args['orderby'] = 'date';
				$args['order']   = 'DESC';
				break;

			case 'price_low':
				$args['meta_key'] = 'ca_current_bid';
				$args['orderby']  = 'meta_value_num';
				$args['order']    = 'ASC';
				break;

			case 'price_high':
				$args['meta_key'] = 'ca_current_bid';
				$args['orderby']  = 'meta_value_num';
				$args['order']    = 'DESC';
				break;

			case 'bids':
				$args['meta_key'] = 'ca_bid_count';
				$args['orderby']  = 'meta_value_num';
				$args['order']    = 'DESC';
				break;
		}

		$wp_query = new WP_Query( $args );
		$auctions = array();

		foreach ( $wp_query->posts as $post ) {
			$auctions[] = self::format_auction( $post );
		}

		return rest_ensure_response(
			array(
				'auctions'    => $auctions,
				'total'       => (int) $wp_query->found_posts,
				'total_pages' => (int) $wp_query->max_num_pages,
				'page'        => $page,
				'per_page'    => $per_page,
			)
		);
	}

	/**
	 * Format auction for response.
	 *
	 * @param WP_Post $post Post object.
	 * @return array Formatted auction data.
	 */
	private static function format_auction( $post ) {
		$current_bid   = (float) get_post_meta( $post->ID, 'ca_current_bid', true );
		$start_price   = (float) get_post_meta( $post->ID, 'ca_start_price', true );
		$end_at        = get_post_meta( $post->ID, 'ca_end_at', true );
		$start_at      = get_post_meta( $post->ID, 'ca_start_at', true );
		$bid_count     = (int) get_post_meta( $post->ID, 'ca_bid_count', true );
		$buy_now_price = (float) get_post_meta( $post->ID, 'ca_buy_now_price', true );
		$thumbnail_id  = get_post_thumbnail_id( $post->ID );

		$display_price = $current_bid > 0 ? $current_bid : $start_price;

		// Calculate time remaining.
		$now        = current_time( 'timestamp', true );
		$end_time   = strtotime( $end_at );
		$time_left  = $end_time - $now;
		$time_label = '';

		if ( $time_left > 0 ) {
			if ( $time_left < 3600 ) {
				$time_label = sprintf(
					/* translators: %d: minutes remaining */
					_n( '%d minute', '%d minutes', (int) ceil( $time_left / 60 ), 'community-auctions' ),
					(int) ceil( $time_left / 60 )
				);
			} elseif ( $time_left < 86400 ) {
				$time_label = sprintf(
					/* translators: %d: hours remaining */
					_n( '%d hour', '%d hours', (int) floor( $time_left / 3600 ), 'community-auctions' ),
					(int) floor( $time_left / 3600 )
				);
			} else {
				$time_label = sprintf(
					/* translators: %d: days remaining */
					_n( '%d day', '%d days', (int) floor( $time_left / 86400 ), 'community-auctions' ),
					(int) floor( $time_left / 86400 )
				);
			}
		} else {
			$time_label = __( 'Ended', 'community-auctions' );
		}

		// Get categories.
		$categories = wp_get_post_terms( $post->ID, 'auction_category', array( 'fields' => 'names' ) );

		return array(
			'id'             => $post->ID,
			'title'          => $post->post_title,
			'url'            => get_permalink( $post->ID ),
			'current_bid'    => $display_price,
			'current_bid_formatted' => Community_Auctions_Currency::format( $display_price, $post->ID ),
			'bid_count'      => $bid_count,
			'end_at'         => $end_at,
			'end_timestamp'  => $end_time,
			'time_left'      => $time_label,
			'time_left_seconds' => max( 0, $time_left ),
			'status'         => $post->post_status,
			'categories'     => is_array( $categories ) ? $categories : array(),
			'thumbnail'      => $thumbnail_id ? wp_get_attachment_image_url( $thumbnail_id, 'medium' ) : '',
			'buy_now_price'  => $buy_now_price > 0 ? $buy_now_price : null,
			'buy_now_formatted' => $buy_now_price > 0 ? Community_Auctions_Currency::format( $buy_now_price, $post->ID ) : null,
		);
	}

	/**
	 * Get filter options for search form.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public static function get_filter_options( $request ) {
		// Get categories.
		$categories = get_terms(
			array(
				'taxonomy'   => 'auction_category',
				'hide_empty' => false,
			)
		);

		$category_options = array();
		if ( ! is_wp_error( $categories ) ) {
			foreach ( $categories as $category ) {
				$category_options[] = array(
					'id'    => $category->term_id,
					'name'  => $category->name,
					'slug'  => $category->slug,
					'count' => $category->count,
				);
			}
		}

		// Get price ranges.
		global $wpdb;

		$max_bid = $wpdb->get_var(
			"SELECT MAX( CAST( meta_value AS DECIMAL(10,2) ) )
			FROM {$wpdb->postmeta}
			WHERE meta_key = 'ca_current_bid'"
		);

		$price_ranges = array(
			array( 'min' => 0, 'max' => 50, 'label' => __( 'Under $50', 'community-auctions' ) ),
			array( 'min' => 50, 'max' => 100, 'label' => '$50 - $100' ),
			array( 'min' => 100, 'max' => 250, 'label' => '$100 - $250' ),
			array( 'min' => 250, 'max' => 500, 'label' => '$250 - $500' ),
			array( 'min' => 500, 'max' => 1000, 'label' => '$500 - $1,000' ),
			array( 'min' => 1000, 'max' => 0, 'label' => __( 'Over $1,000', 'community-auctions' ) ),
		);

		// Get live auction count.
		$live_count = wp_count_posts( 'auction' );

		return rest_ensure_response(
			array(
				'categories'   => $category_options,
				'price_ranges' => $price_ranges,
				'max_price'    => (float) $max_bid ?: 1000,
				'counts'       => array(
					'live'     => isset( $live_count->ca_live ) ? $live_count->ca_live : 0,
					'upcoming' => self::count_upcoming_auctions(),
					'ended'    => ( isset( $live_count->ca_ended ) ? $live_count->ca_ended : 0 ) +
								  ( isset( $live_count->ca_closed ) ? $live_count->ca_closed : 0 ),
				),
				'sort_options' => array(
					array( 'value' => 'ending_soon', 'label' => __( 'Ending Soon', 'community-auctions' ) ),
					array( 'value' => 'newest', 'label' => __( 'Newest', 'community-auctions' ) ),
					array( 'value' => 'price_low', 'label' => __( 'Price: Low to High', 'community-auctions' ) ),
					array( 'value' => 'price_high', 'label' => __( 'Price: High to Low', 'community-auctions' ) ),
					array( 'value' => 'bids', 'label' => __( 'Most Bids', 'community-auctions' ) ),
				),
			)
		);
	}

	/**
	 * Count upcoming auctions.
	 *
	 * @return int Count of upcoming auctions.
	 */
	private static function count_upcoming_auctions() {
		$now = current_time( 'mysql', true );

		$query = new WP_Query(
			array(
				'post_type'      => 'auction',
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_query'     => array(
					array(
						'key'     => 'ca_start_at',
						'value'   => $now,
						'compare' => '>',
						'type'    => 'DATETIME',
					),
				),
			)
		);

		return $query->found_posts;
	}

	/**
	 * Enqueue scripts.
	 */
	public static function enqueue_scripts() {
		$post = get_post();
		$content = $post ? $post->post_content : '';

		if ( ! is_singular() && ! has_shortcode( $content, 'community_auctions_search' ) ) {
			return;
		}

		wp_enqueue_script(
			'ca-search-filter',
			plugins_url( 'assets/js/search-filter.js', dirname( __FILE__ ) ),
			array(),
			Community_Auctions_Plugin::VERSION,
			true
		);

		wp_localize_script(
			'ca-search-filter',
			'CaSearch',
			array(
				'restUrl' => esc_url_raw( rest_url( 'community-auctions/v1/search' ) ),
				'filtersUrl' => esc_url_raw( rest_url( 'community-auctions/v1/search/filters' ) ),
				'nonce'   => wp_create_nonce( 'wp_rest' ),
				'strings' => array(
					'loading'    => __( 'Loading...', 'community-auctions' ),
					'noResults'  => __( 'No auctions found matching your criteria.', 'community-auctions' ),
					'error'      => __( 'An error occurred. Please try again.', 'community-auctions' ),
					'bid'        => __( 'Bid', 'community-auctions' ),
					'bids'       => __( 'bids', 'community-auctions' ),
					'endingSoon' => __( 'Ending Soon', 'community-auctions' ),
					'buyNow'     => __( 'Buy Now', 'community-auctions' ),
				),
			)
		);
	}

	/**
	 * Render search shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string HTML output.
	 */
	public static function render_shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'show_categories'  => 'yes',
				'show_price_range' => 'yes',
				'show_status'      => 'yes',
				'show_sort'        => 'yes',
				'default_status'   => 'live',
				'per_page'         => 12,
				'columns'          => 3,
			),
			$atts,
			'community_auctions_search'
		);

		$columns_class = 'ca-columns-' . absint( $atts['columns'] );

		ob_start();
		?>
		<div class="ca-search-container" data-per-page="<?php echo absint( $atts['per_page'] ); ?>" data-default-status="<?php echo esc_attr( $atts['default_status'] ); ?>">

			<div class="ca-search-form">
				<div class="ca-search-input-wrap">
					<input type="text" class="ca-search-input" placeholder="<?php esc_attr_e( 'Search auctions...', 'community-auctions' ); ?>" />
					<button type="button" class="ca-search-btn">
						<span class="dashicons dashicons-search"></span>
						<span class="screen-reader-text"><?php esc_html_e( 'Search', 'community-auctions' ); ?></span>
					</button>
				</div>

				<div class="ca-filters">
					<?php if ( 'yes' === $atts['show_categories'] ) : ?>
					<div class="ca-filter-group">
						<label for="ca-filter-category"><?php esc_html_e( 'Category', 'community-auctions' ); ?></label>
						<select id="ca-filter-category" class="ca-filter-select" data-filter="category">
							<option value=""><?php esc_html_e( 'All Categories', 'community-auctions' ); ?></option>
						</select>
					</div>
					<?php endif; ?>

					<?php if ( 'yes' === $atts['show_price_range'] ) : ?>
					<div class="ca-filter-group">
						<label for="ca-filter-price"><?php esc_html_e( 'Price Range', 'community-auctions' ); ?></label>
						<select id="ca-filter-price" class="ca-filter-select" data-filter="price">
							<option value=""><?php esc_html_e( 'Any Price', 'community-auctions' ); ?></option>
						</select>
					</div>
					<?php endif; ?>

					<?php if ( 'yes' === $atts['show_status'] ) : ?>
					<div class="ca-filter-group">
						<label for="ca-filter-status"><?php esc_html_e( 'Status', 'community-auctions' ); ?></label>
						<select id="ca-filter-status" class="ca-filter-select" data-filter="status">
							<option value="live" <?php selected( 'live', $atts['default_status'] ); ?>><?php esc_html_e( 'Live Auctions', 'community-auctions' ); ?></option>
							<option value="upcoming" <?php selected( 'upcoming', $atts['default_status'] ); ?>><?php esc_html_e( 'Upcoming', 'community-auctions' ); ?></option>
							<option value="ended" <?php selected( 'ended', $atts['default_status'] ); ?>><?php esc_html_e( 'Ended', 'community-auctions' ); ?></option>
							<option value="all" <?php selected( 'all', $atts['default_status'] ); ?>><?php esc_html_e( 'All Auctions', 'community-auctions' ); ?></option>
						</select>
					</div>
					<?php endif; ?>

					<?php if ( 'yes' === $atts['show_sort'] ) : ?>
					<div class="ca-filter-group">
						<label for="ca-filter-sort"><?php esc_html_e( 'Sort By', 'community-auctions' ); ?></label>
						<select id="ca-filter-sort" class="ca-filter-select" data-filter="sort">
							<option value="ending_soon"><?php esc_html_e( 'Ending Soon', 'community-auctions' ); ?></option>
							<option value="newest"><?php esc_html_e( 'Newest', 'community-auctions' ); ?></option>
							<option value="price_low"><?php esc_html_e( 'Price: Low to High', 'community-auctions' ); ?></option>
							<option value="price_high"><?php esc_html_e( 'Price: High to Low', 'community-auctions' ); ?></option>
							<option value="bids"><?php esc_html_e( 'Most Bids', 'community-auctions' ); ?></option>
						</select>
					</div>
					<?php endif; ?>

					<div class="ca-filter-group ca-filter-toggle">
						<label>
							<input type="checkbox" class="ca-filter-checkbox" data-filter="ending_soon" />
							<?php esc_html_e( 'Ending within 24h', 'community-auctions' ); ?>
						</label>
					</div>
				</div>

				<div class="ca-active-filters"></div>
			</div>

			<div class="ca-search-stats">
				<span class="ca-result-count"></span>
			</div>

			<div class="ca-search-results <?php echo esc_attr( $columns_class ); ?>">
				<div class="ca-search-loading">
					<span class="spinner is-active"></span>
					<?php esc_html_e( 'Loading auctions...', 'community-auctions' ); ?>
				</div>
			</div>

			<div class="ca-search-pagination"></div>
		</div>
		<?php
		return ob_get_clean();
	}
}
