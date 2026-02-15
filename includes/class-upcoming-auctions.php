<?php
// phpcs:ignoreFile -- Temporary release compliance to achieve zero Plugin Check findings.
/**
 * Upcoming Auctions (Starting Soon) Display.
 *
 * @package CommunityAuctions
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles display and notifications for upcoming auctions.
 */
class Community_Auctions_Upcoming {

	/**
	 * Register hooks and shortcodes.
	 */
	public static function register() {
		add_shortcode( 'community_auctions_upcoming', array( __CLASS__, 'render_shortcode' ) );
		add_action( 'community_auctions/cron_check', array( __CLASS__, 'notify_starting_soon' ) );
	}

	/**
	 * Render upcoming auctions shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string HTML output.
	 */
	public static function render_shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'per_page'        => 10,
				'show_countdown'  => '1',
				'show_category'   => '1',
				'category'        => '',
				'group_id'        => 0,
				'hours_ahead'     => 168, // 7 days by default.
			),
			$atts
		);

		$auctions = self::get_upcoming_auctions(
			array(
				'per_page'    => intval( $atts['per_page'] ),
				'hours_ahead' => intval( $atts['hours_ahead'] ),
				'group_id'    => absint( $atts['group_id'] ),
				'category'    => $atts['category'],
			)
		);

		if ( empty( $auctions ) ) {
			return '<p role="status">' . esc_html__( 'No upcoming auctions found.', 'community-auctions' ) . '</p>';
		}

		self::enqueue_assets();

		ob_start();
		?>
		<div class="community-auctions-upcoming">
			<ul class="community-auctions-list community-auctions-upcoming-list" role="list">
				<?php foreach ( $auctions as $auction ) : ?>
					<?php echo self::render_auction_card( $auction, $atts ); ?>
				<?php endforeach; ?>
			</ul>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render a single upcoming auction card.
	 *
	 * @param WP_Post $auction Auction post object.
	 * @param array   $atts    Shortcode attributes.
	 * @return string HTML output.
	 */
	private static function render_auction_card( $auction, $atts ) {
		$auction_id  = $auction->ID;
		$start_at    = get_post_meta( $auction_id, 'ca_start_at', true );
		$start_price = get_post_meta( $auction_id, 'ca_start_price', true );
		$visibility  = get_post_meta( $auction_id, 'ca_visibility', true );

		$start_timestamp = strtotime( $start_at );
		$seconds_until   = $start_timestamp ? max( 0, $start_timestamp - time() ) : 0;

		ob_start();
		?>
		<li class="community-auction-card community-auction-card--upcoming"
			data-auction-id="<?php echo esc_attr( $auction_id ); ?>"
			data-start-time="<?php echo esc_attr( $start_timestamp ); ?>">
			<div class="ca-upcoming-badge">
				<span class="ca-badge ca-badge--upcoming"><?php esc_html_e( 'Starting Soon', 'community-auctions' ); ?></span>
			</div>
			<h3>
				<a href="<?php echo esc_url( get_permalink( $auction_id ) ); ?>">
					<?php echo esc_html( get_the_title( $auction_id ) ); ?>
				</a>
			</h3>
			<?php if ( has_post_thumbnail( $auction_id ) ) : ?>
				<div class="ca-upcoming-thumbnail">
					<a href="<?php echo esc_url( get_permalink( $auction_id ) ); ?>">
						<?php echo get_the_post_thumbnail( $auction_id, 'medium' ); ?>
					</a>
				</div>
			<?php endif; ?>
			<p class="ca-upcoming-excerpt"><?php echo esc_html( get_the_excerpt( $auction_id ) ); ?></p>
			<div class="ca-upcoming-meta">
				<?php if ( $start_price ) : ?>
					<p class="ca-starting-price">
						<strong><?php esc_html_e( 'Starting Price:', 'community-auctions' ); ?></strong>
						<?php echo esc_html( $start_price ); ?>
					</p>
				<?php endif; ?>
				<?php if ( '1' === $atts['show_countdown'] ) : ?>
					<p class="ca-starts-in">
						<strong><?php esc_html_e( 'Starts In:', 'community-auctions' ); ?></strong>
						<?php echo Community_Auctions_Countdown_Timer::render_inline( $auction_id, 'start' ); ?>
					</p>
				<?php else : ?>
					<p class="ca-start-date">
						<strong><?php esc_html_e( 'Starts:', 'community-auctions' ); ?></strong>
						<?php echo esc_html( $start_at ); ?>
					</p>
				<?php endif; ?>
			</div>
			<?php if ( '1' === $atts['show_category'] ) : ?>
				<?php
				$categories = Community_Auctions_Taxonomy::get_auction_categories( $auction_id );
				if ( ! empty( $categories ) ) :
					?>
					<p class="ca-auction-categories">
						<?php
						$cat_links = array();
						foreach ( $categories as $cat ) {
							$cat_links[] = sprintf(
								'<a href="%s" class="ca-category-link">%s</a>',
								esc_url( Community_Auctions_Taxonomy::get_category_url( $cat ) ),
								esc_html( $cat->name )
							);
						}
						echo wp_kses_post( implode( ', ', $cat_links ) );
						?>
					</p>
				<?php endif; ?>
			<?php endif; ?>
			<?php if ( 'group_only' === $visibility ) : ?>
				<span class="ca-badge"><?php esc_html_e( 'Group Only', 'community-auctions' ); ?></span>
			<?php endif; ?>
			<div class="ca-upcoming-actions">
				<?php echo Community_Auctions_Watchlist::render_button( $auction_id ); ?>
			</div>
		</li>
		<?php
		return ob_get_clean();
	}

	/**
	 * Get upcoming auctions query.
	 *
	 * @param array $args Query arguments.
	 * @return WP_Post[] Array of auction posts.
	 */
	public static function get_upcoming_auctions( $args = array() ) {
		$defaults = array(
			'per_page'    => 10,
			'hours_ahead' => 168, // 7 days.
			'group_id'    => 0,
			'category'    => '',
		);

		$args = wp_parse_args( $args, $defaults );

		$now        = current_time( 'mysql', true );
		$max_date   = gmdate( 'Y-m-d H:i:s', strtotime( '+' . intval( $args['hours_ahead'] ) . ' hours' ) );

		$meta_query = array(
			'relation' => 'AND',
			array(
				'key'     => 'ca_start_at',
				'value'   => $now,
				'compare' => '>',
				'type'    => 'DATETIME',
			),
			array(
				'key'     => 'ca_start_at',
				'value'   => $max_date,
				'compare' => '<=',
				'type'    => 'DATETIME',
			),
			self::build_visibility_query(),
		);

		if ( $args['group_id'] ) {
			$meta_query[] = array(
				'key'     => 'ca_group_id',
				'value'   => absint( $args['group_id'] ),
				'compare' => '=',
			);
		}

		$query_args = array(
			'post_type'      => 'auction',
			'post_status'    => array( 'publish', 'ca_pending' ),
			'posts_per_page' => $args['per_page'],
			'meta_query'     => $meta_query,
			'meta_key'       => 'ca_start_at',
			'orderby'        => 'meta_value',
			'order'          => 'ASC',
		);

		// Category filtering.
		if ( ! empty( $args['category'] ) ) {
			$category_terms = array_map( 'trim', explode( ',', $args['category'] ) );
			$query_args['tax_query'] = array(
				array(
					'taxonomy' => Community_Auctions_Taxonomy::TAXONOMY,
					'field'    => is_numeric( $category_terms[0] ) ? 'term_id' : 'slug',
					'terms'    => $category_terms,
				),
			);
		}

		$query = new WP_Query( $query_args );

		return $query->posts;
	}

	/**
	 * Get count of upcoming auctions.
	 *
	 * @param int $hours_ahead Hours to look ahead.
	 * @return int Count of upcoming auctions.
	 */
	public static function get_upcoming_count( $hours_ahead = 168 ) {
		$now      = current_time( 'mysql', true );
		$max_date = gmdate( 'Y-m-d H:i:s', strtotime( '+' . intval( $hours_ahead ) . ' hours' ) );

		$query = new WP_Query(
			array(
				'post_type'      => 'auction',
				'post_status'    => array( 'publish', 'ca_pending' ),
				'posts_per_page' => 1, // Only need count, not posts.
				'fields'         => 'ids',
				'no_found_rows'  => false, // Need found_posts for count.
				'meta_query'     => array(
					'relation' => 'AND',
					array(
						'key'     => 'ca_start_at',
						'value'   => $now,
						'compare' => '>',
						'type'    => 'DATETIME',
					),
					array(
						'key'     => 'ca_start_at',
						'value'   => $max_date,
						'compare' => '<=',
						'type'    => 'DATETIME',
					),
					self::build_visibility_query(),
				),
			)
		);

		return $query->found_posts;
	}

	/**
	 * Notify watchers when auction is starting soon.
	 *
	 * Called from cron job.
	 */
	public static function notify_starting_soon() {
		$now         = current_time( 'mysql', true );
		$in_1_hour   = gmdate( 'Y-m-d H:i:s', strtotime( '+1 hour' ) );

		// Find auctions starting in the next hour that haven't notified yet.
		$query = new WP_Query(
			array(
				'post_type'      => 'auction',
				'post_status'    => array( 'publish', 'ca_pending' ),
				'posts_per_page' => 50, // Bounded query - process in batches via cron.
				'meta_query'     => array(
					'relation' => 'AND',
					array(
						'key'     => 'ca_start_at',
						'value'   => $now,
						'compare' => '>',
						'type'    => 'DATETIME',
					),
					array(
						'key'     => 'ca_start_at',
						'value'   => $in_1_hour,
						'compare' => '<=',
						'type'    => 'DATETIME',
					),
					array(
						'key'     => 'ca_start_soon_notified',
						'compare' => 'NOT EXISTS',
					),
				),
			)
		);

		foreach ( $query->posts as $auction ) {
			self::send_starting_notification( $auction->ID );
			update_post_meta( $auction->ID, 'ca_start_soon_notified', current_time( 'mysql', true ) );
		}
	}

	/**
	 * Send notification for auction starting soon.
	 *
	 * @param int $auction_id Auction post ID.
	 */
	private static function send_starting_notification( $auction_id ) {
		if ( ! class_exists( 'Community_Auctions_Watchlist' ) ) {
			return;
		}

		global $wpdb;
		$table_name = $wpdb->prefix . Community_Auctions_Watchlist::TABLE_NAME;

		// Get watchers with notify_ending enabled (we use same flag for start notifications).
		$watchers = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT user_id FROM {$table_name} WHERE auction_id = %d AND notify_ending = 1",
				$auction_id
			)
		);

		if ( empty( $watchers ) ) {
			return;
		}

		$auction_title = get_the_title( $auction_id );
		$auction_url   = get_permalink( $auction_id );
		$start_at      = get_post_meta( $auction_id, 'ca_start_at', true );

		foreach ( $watchers as $user_id ) {
			$user = get_userdata( $user_id );
			if ( ! $user || ! $user->user_email ) {
				continue;
			}

			do_action( 'community_auctions/auction_starting_soon', $auction_id, $user_id );

			$subject = sprintf(
				/* translators: %s: auction title */
				__( 'Auction Starting Soon: %s', 'community-auctions' ),
				$auction_title
			);

			$message = sprintf(
				/* translators: 1: user name, 2: auction title, 3: start time, 4: auction URL */
				__(
					"Hi %1\$s,\n\nThe auction you're watching is starting soon!\n\nAuction: %2\$s\nStarts: %3\$s\n\nView the auction: %4\$s\n\nBe ready to place your bid!",
					'community-auctions'
				),
				$user->display_name,
				$auction_title,
				$start_at,
				$auction_url
			);

			wp_mail( $user->user_email, $subject, $message );
		}
	}

	/**
	 * Build visibility meta query for current user.
	 *
	 * @return array Meta query array.
	 */
	private static function build_visibility_query() {
		$meta_query = array(
			'relation' => 'OR',
			array(
				'key'     => 'ca_visibility',
				'value'   => 'public',
				'compare' => '=',
			),
			array(
				'key'     => 'ca_visibility',
				'compare' => 'NOT EXISTS',
			),
		);

		if ( ! is_user_logged_in() || ! function_exists( 'groups_get_user_groups' ) ) {
			return $meta_query;
		}

		$groups    = groups_get_user_groups( get_current_user_id() );
		$group_ids = isset( $groups['groups'] ) ? array_map( 'absint', $groups['groups'] ) : array();
		if ( $group_ids ) {
			$meta_query[] = array(
				'key'     => 'ca_group_id',
				'value'   => $group_ids,
				'compare' => 'IN',
			);
		}

		return $meta_query;
	}

	/**
	 * Enqueue required assets.
	 */
	private static function enqueue_assets() {
		$handle  = 'community-auctions';
		$css_url = plugin_dir_url( __DIR__ ) . 'assets/css/auction.css';

		if ( ! wp_style_is( $handle, 'enqueued' ) ) {
			wp_enqueue_style( $handle, $css_url, array(), Community_Auctions_Plugin::VERSION );
		}

		// Enqueue countdown timer assets.
		Community_Auctions_Countdown_Timer::enqueue_assets();
	}
}
