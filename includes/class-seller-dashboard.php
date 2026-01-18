<?php
/**
 * Seller Dashboard - Auction management for sellers.
 *
 * @package CommunityAuctions
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles seller dashboard display and functionality.
 */
class Community_Auctions_Seller_Dashboard {

	/**
	 * Register hooks and shortcodes.
	 */
	public static function register() {
		add_shortcode( 'community_auction_seller_dashboard', array( __CLASS__, 'render_shortcode' ) );
		add_action( 'bp_setup_nav', array( __CLASS__, 'add_buddypress_subnav' ), 100 );
	}

	/**
	 * Add BuddyPress subnav for seller dashboard.
	 */
	public static function add_buddypress_subnav() {
		if ( ! function_exists( 'bp_core_new_subnav_item' ) ) {
			return;
		}

		$parent_slug = 'auctions';

		// Check if parent nav exists.
		if ( ! buddypress()->members->nav->get_primary( array( 'slug' => $parent_slug ) ) ) {
			return;
		}

		bp_core_new_subnav_item(
			array(
				'name'            => __( 'My Auctions', 'community-auctions' ),
				'slug'            => 'dashboard',
				'parent_url'      => bp_displayed_user_domain() . $parent_slug . '/',
				'parent_slug'     => $parent_slug,
				'screen_function' => array( __CLASS__, 'buddypress_screen' ),
				'position'        => 10,
				'user_has_access' => bp_is_my_profile(),
			)
		);
	}

	/**
	 * BuddyPress screen callback.
	 */
	public static function buddypress_screen() {
		add_action( 'bp_template_content', array( __CLASS__, 'buddypress_content' ) );
		bp_core_load_template( 'members/single/plugins' );
	}

	/**
	 * BuddyPress content callback.
	 */
	public static function buddypress_content() {
		echo self::render_shortcode( array() );
	}

	/**
	 * Render seller dashboard shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string HTML output.
	 */
	public static function render_shortcode( $atts ) {
		if ( ! is_user_logged_in() ) {
			return '<p>' . esc_html__( 'Please log in to view your seller dashboard.', 'community-auctions' ) . '</p>';
		}

		$atts = shortcode_atts(
			array(
				'user_id'  => get_current_user_id(),
				'per_page' => 10,
			),
			$atts
		);

		$user_id = absint( $atts['user_id'] );

		// Only allow viewing own dashboard unless admin.
		if ( $user_id !== get_current_user_id() && ! current_user_can( 'ca_manage_auctions' ) ) {
			return '<p>' . esc_html__( 'You do not have permission to view this dashboard.', 'community-auctions' ) . '</p>';
		}

		self::enqueue_assets();

		$stats = self::get_seller_stats( $user_id );

		ob_start();
		?>
		<div class="ca-seller-dashboard">
			<h2><?php esc_html_e( 'Seller Dashboard', 'community-auctions' ); ?></h2>

			<?php echo self::render_stats_summary( $stats ); ?>

			<div class="ca-dashboard-tabs" role="tablist">
				<button class="ca-tab ca-tab--active" data-tab="active" role="tab" aria-selected="true" aria-controls="ca-tab-active">
					<?php esc_html_e( 'Active', 'community-auctions' ); ?>
					<span class="ca-tab-count"><?php echo esc_html( $stats['active_count'] ); ?></span>
				</button>
				<button class="ca-tab" data-tab="pending" role="tab" aria-selected="false" aria-controls="ca-tab-pending">
					<?php esc_html_e( 'Pending', 'community-auctions' ); ?>
					<span class="ca-tab-count"><?php echo esc_html( $stats['pending_count'] ); ?></span>
				</button>
				<button class="ca-tab" data-tab="ended" role="tab" aria-selected="false" aria-controls="ca-tab-ended">
					<?php esc_html_e( 'Ended', 'community-auctions' ); ?>
					<span class="ca-tab-count"><?php echo esc_html( $stats['ended_count'] ); ?></span>
				</button>
			</div>

			<div id="ca-tab-active" class="ca-tab-content ca-tab-content--active" role="tabpanel">
				<?php echo self::render_auctions_list( $user_id, 'active', intval( $atts['per_page'] ) ); ?>
			</div>

			<div id="ca-tab-pending" class="ca-tab-content" role="tabpanel" hidden>
				<?php echo self::render_auctions_list( $user_id, 'pending', intval( $atts['per_page'] ) ); ?>
			</div>

			<div id="ca-tab-ended" class="ca-tab-content" role="tabpanel" hidden>
				<?php echo self::render_auctions_list( $user_id, 'ended', intval( $atts['per_page'] ) ); ?>
			</div>
		</div>

		<script>
		(function() {
			var tabs = document.querySelectorAll('.ca-dashboard-tabs .ca-tab');
			tabs.forEach(function(tab) {
				tab.addEventListener('click', function() {
					var targetId = 'ca-tab-' + this.getAttribute('data-tab');
					tabs.forEach(function(t) {
						t.classList.remove('ca-tab--active');
						t.setAttribute('aria-selected', 'false');
					});
					this.classList.add('ca-tab--active');
					this.setAttribute('aria-selected', 'true');
					document.querySelectorAll('.ca-tab-content').forEach(function(c) {
						c.classList.remove('ca-tab-content--active');
						c.hidden = true;
					});
					var target = document.getElementById(targetId);
					if (target) {
						target.classList.add('ca-tab-content--active');
						target.hidden = false;
					}
				});
			});
		})();
		</script>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render stats summary section.
	 *
	 * @param array $stats Seller statistics.
	 * @return string HTML output.
	 */
	private static function render_stats_summary( $stats ) {
		ob_start();
		?>
		<div class="ca-seller-stats">
			<div class="ca-stat-card">
				<span class="ca-stat-value"><?php echo esc_html( $stats['total_auctions'] ); ?></span>
				<span class="ca-stat-label"><?php esc_html_e( 'Total Auctions', 'community-auctions' ); ?></span>
			</div>
			<div class="ca-stat-card">
				<span class="ca-stat-value"><?php echo esc_html( $stats['sold_count'] ); ?></span>
				<span class="ca-stat-label"><?php esc_html_e( 'Sold', 'community-auctions' ); ?></span>
			</div>
			<div class="ca-stat-card ca-stat-card--highlight">
				<span class="ca-stat-value"><?php echo esc_html( self::format_currency( $stats['total_earned'] ) ); ?></span>
				<span class="ca-stat-label"><?php esc_html_e( 'Total Earned', 'community-auctions' ); ?></span>
			</div>
			<div class="ca-stat-card">
				<span class="ca-stat-value"><?php echo esc_html( $stats['total_bids'] ); ?></span>
				<span class="ca-stat-label"><?php esc_html_e( 'Total Bids Received', 'community-auctions' ); ?></span>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render auctions list by status.
	 *
	 * @param int    $user_id  User ID.
	 * @param string $status   Status filter (active, pending, ended).
	 * @param int    $per_page Items per page.
	 * @return string HTML output.
	 */
	private static function render_auctions_list( $user_id, $status, $per_page ) {
		$post_status = array( 'publish', 'ca_live' );
		if ( 'pending' === $status ) {
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
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);

		if ( ! $query->have_posts() ) {
			$empty_messages = array(
				'active'  => __( 'No active auctions.', 'community-auctions' ),
				'pending' => __( 'No auctions pending approval.', 'community-auctions' ),
				'ended'   => __( 'No ended auctions.', 'community-auctions' ),
			);
			return '<p class="ca-empty-state">' . esc_html( $empty_messages[ $status ] ?? __( 'No auctions found.', 'community-auctions' ) ) . '</p>';
		}

		ob_start();
		?>
		<div class="ca-seller-auctions">
			<?php
			while ( $query->have_posts() ) {
				$query->the_post();
				echo self::render_auction_row( get_the_ID(), $status );
			}
			wp_reset_postdata();
			?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render a single auction row.
	 *
	 * @param int    $auction_id Auction post ID.
	 * @param string $status     Status context.
	 * @return string HTML output.
	 */
	private static function render_auction_row( $auction_id, $status ) {
		$current_bid   = get_post_meta( $auction_id, 'ca_current_bid', true );
		$start_price   = get_post_meta( $auction_id, 'ca_start_price', true );
		$end_at        = get_post_meta( $auction_id, 'ca_end_at', true );
		$winner_id     = absint( get_post_meta( $auction_id, 'ca_winner_id', true ) );
		$order_id      = absint( get_post_meta( $auction_id, 'ca_order_id', true ) );
		$bid_count     = Community_Auctions_Bid_Repository::count_auction_bids( $auction_id );
		$post_status   = get_post_status( $auction_id );

		// Determine payment status.
		$payment_status = '';
		if ( $winner_id && $order_id ) {
			$settings = Community_Auctions_Settings::get_settings();
			$provider = $settings['payment_provider'] ?? '';
			$paid     = Community_Auctions_Payment_Status::is_order_paid( $order_id, $provider );
			$payment_status = $paid ? 'paid' : 'pending';
		}

		ob_start();
		?>
		<div class="ca-seller-auction-row" data-auction-id="<?php echo esc_attr( $auction_id ); ?>">
			<div class="ca-auction-row-thumbnail">
				<?php if ( has_post_thumbnail( $auction_id ) ) : ?>
					<a href="<?php echo esc_url( get_permalink( $auction_id ) ); ?>">
						<?php echo get_the_post_thumbnail( $auction_id, 'thumbnail' ); ?>
					</a>
				<?php else : ?>
					<div class="ca-no-image"><?php esc_html_e( 'No Image', 'community-auctions' ); ?></div>
				<?php endif; ?>
			</div>
			<div class="ca-auction-row-details">
				<h4>
					<a href="<?php echo esc_url( get_permalink( $auction_id ) ); ?>">
						<?php echo esc_html( get_the_title( $auction_id ) ); ?>
					</a>
				</h4>
				<div class="ca-auction-row-meta">
					<?php if ( 'ended' === $status && $winner_id ) : ?>
						<span class="ca-meta-item">
							<strong><?php esc_html_e( 'Won by:', 'community-auctions' ); ?></strong>
							<?php echo esc_html( self::get_user_display_name( $winner_id ) ); ?>
						</span>
						<span class="ca-meta-item">
							<strong><?php esc_html_e( 'Final:', 'community-auctions' ); ?></strong>
							<?php echo esc_html( self::format_currency( $current_bid ) ); ?>
						</span>
						<?php if ( $payment_status ) : ?>
							<span class="ca-payment-badge ca-payment-badge--<?php echo esc_attr( $payment_status ); ?>">
								<?php echo 'paid' === $payment_status ? esc_html__( 'Paid', 'community-auctions' ) : esc_html__( 'Payment Pending', 'community-auctions' ); ?>
							</span>
						<?php endif; ?>
					<?php elseif ( 'ended' === $status && ! $winner_id ) : ?>
						<span class="ca-meta-item ca-meta-item--unsold">
							<?php esc_html_e( 'No bids received', 'community-auctions' ); ?>
						</span>
					<?php else : ?>
						<span class="ca-meta-item">
							<strong><?php esc_html_e( 'Current:', 'community-auctions' ); ?></strong>
							<?php echo $current_bid ? esc_html( self::format_currency( $current_bid ) ) : esc_html( self::format_currency( $start_price ) ) . ' ' . esc_html__( '(starting)', 'community-auctions' ); ?>
						</span>
						<span class="ca-meta-item">
							<strong><?php esc_html_e( 'Bids:', 'community-auctions' ); ?></strong>
							<?php echo esc_html( $bid_count ); ?>
						</span>
						<?php if ( 'active' === $status && $end_at ) : ?>
							<span class="ca-meta-item ca-meta-item--countdown">
								<?php echo Community_Auctions_Countdown_Timer::render_inline( $auction_id, 'end' ); ?>
							</span>
						<?php endif; ?>
					<?php endif; ?>
				</div>
			</div>
			<div class="ca-auction-row-actions">
				<a href="<?php echo esc_url( get_permalink( $auction_id ) ); ?>" class="ca-action-link">
					<?php esc_html_e( 'View', 'community-auctions' ); ?>
				</a>
				<?php if ( in_array( $post_status, array( 'publish', 'ca_live', 'ca_pending' ), true ) ) : ?>
					<a href="<?php echo esc_url( get_edit_post_link( $auction_id ) ); ?>" class="ca-action-link">
						<?php esc_html_e( 'Edit', 'community-auctions' ); ?>
					</a>
				<?php endif; ?>
				<?php if ( 'ended' === $status && $winner_id && $payment_status ) : ?>
					<?php echo self::render_buyer_contact( $auction_id, $winner_id ); ?>
				<?php endif; ?>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render buyer contact information (for ended auctions).
	 *
	 * @param int $auction_id Auction post ID.
	 * @param int $winner_id  Winner user ID.
	 * @return string HTML output.
	 */
	private static function render_buyer_contact( $auction_id, $winner_id ) {
		$winner = get_userdata( $winner_id );
		if ( ! $winner ) {
			return '';
		}

		ob_start();
		?>
		<button type="button" class="ca-action-link ca-buyer-contact-toggle" aria-expanded="false" aria-controls="ca-buyer-contact-<?php echo esc_attr( $auction_id ); ?>">
			<?php esc_html_e( 'Buyer Contact', 'community-auctions' ); ?>
		</button>
		<div id="ca-buyer-contact-<?php echo esc_attr( $auction_id ); ?>" class="ca-buyer-contact" hidden>
			<p>
				<strong><?php esc_html_e( 'Name:', 'community-auctions' ); ?></strong>
				<?php echo esc_html( $winner->display_name ); ?>
			</p>
			<p>
				<strong><?php esc_html_e( 'Email:', 'community-auctions' ); ?></strong>
				<a href="mailto:<?php echo esc_attr( $winner->user_email ); ?>"><?php echo esc_html( $winner->user_email ); ?></a>
			</p>
		</div>
		<script>
		(function() {
			var toggle = document.querySelector('.ca-buyer-contact-toggle[aria-controls="ca-buyer-contact-<?php echo esc_js( $auction_id ); ?>"]');
			var contact = document.getElementById('ca-buyer-contact-<?php echo esc_js( $auction_id ); ?>');
			if (toggle && contact) {
				toggle.addEventListener('click', function() {
					var expanded = this.getAttribute('aria-expanded') === 'true';
					this.setAttribute('aria-expanded', !expanded);
					contact.hidden = expanded;
				});
			}
		})();
		</script>
		<?php
		return ob_get_clean();
	}

	/**
	 * Get seller statistics.
	 *
	 * @param int $user_id User ID.
	 * @return array Statistics data.
	 */
	public static function get_seller_stats( $user_id ) {
		global $wpdb;

		// Count auctions by status.
		$active_count = self::count_auctions_by_status( $user_id, array( 'publish', 'ca_live' ) );
		$pending_count = self::count_auctions_by_status( $user_id, array( 'ca_pending' ) );
		$ended_count = self::count_auctions_by_status( $user_id, array( 'ca_ended', 'ca_closed' ) );
		$total_auctions = $active_count + $pending_count + $ended_count;

		// Get sold count (ended with winner).
		$sold_query = new WP_Query(
			array(
				'post_type'      => 'auction',
				'post_status'    => array( 'ca_ended', 'ca_closed' ),
				'author'         => $user_id,
				'posts_per_page' => 100, // Bounded query - paginate if needed for large sellers.
				'fields'         => 'ids',
				'meta_query'     => array(
					array(
						'key'     => 'ca_winner_id',
						'compare' => 'EXISTS',
					),
					array(
						'key'     => 'ca_winner_id',
						'value'   => 0,
						'compare' => '>',
						'type'    => 'NUMERIC',
					),
				),
			)
		);
		$sold_count = $sold_query->found_posts;
		$sold_ids   = $sold_query->posts;

		// Calculate total earned.
		$total_earned = 0;
		foreach ( $sold_ids as $auction_id ) {
			$final_bid = get_post_meta( $auction_id, 'ca_current_bid', true );
			$total_earned += floatval( $final_bid );
		}

		// Get total bids received.
		$auction_ids = get_posts(
			array(
				'post_type'      => 'auction',
				'author'         => $user_id,
				'posts_per_page' => 500, // Bounded query for large sellers.
				'fields'         => 'ids',
				'post_status'    => 'any',
			)
		);

		$total_bids = 0;
		if ( ! empty( $auction_ids ) ) {
			$table_name = $wpdb->prefix . 'ca_bids';
			$ids_placeholder = implode( ',', array_map( 'intval', $auction_ids ) );
			$total_bids = (int) $wpdb->get_var(
				"SELECT COUNT(*) FROM {$table_name} WHERE auction_id IN ({$ids_placeholder})"
			);
		}

		return array(
			'total_auctions' => $total_auctions,
			'active_count'   => $active_count,
			'pending_count'  => $pending_count,
			'ended_count'    => $ended_count,
			'sold_count'     => $sold_count,
			'total_earned'   => $total_earned,
			'total_bids'     => $total_bids,
		);
	}

	/**
	 * Count auctions by status.
	 *
	 * @param int   $user_id     User ID.
	 * @param array $post_status Post status array.
	 * @return int Count.
	 */
	private static function count_auctions_by_status( $user_id, $post_status ) {
		$query = new WP_Query(
			array(
				'post_type'      => 'auction',
				'post_status'    => $post_status,
				'author'         => $user_id,
				'posts_per_page' => 1, // Only need count, not posts.
				'fields'         => 'ids',
				'no_found_rows'  => false, // Need found_posts for count.
			)
		);

		return $query->found_posts;
	}

	/**
	 * Get user display name.
	 *
	 * @param int $user_id User ID.
	 * @return string Display name.
	 */
	private static function get_user_display_name( $user_id ) {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return __( 'Unknown', 'community-auctions' );
		}
		return $user->display_name;
	}

	/**
	 * Format currency value.
	 *
	 * @param float $amount Amount to format.
	 * @return string Formatted amount.
	 */
	private static function format_currency( $amount ) {
		return Community_Auctions_Currency::format( $amount );
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
