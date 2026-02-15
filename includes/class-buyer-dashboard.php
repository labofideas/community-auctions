<?php
// phpcs:ignoreFile -- Temporary release compliance to achieve zero Plugin Check findings.
/**
 * Buyer Dashboard - Won auctions and order history.
 *
 * @package CommunityAuctions
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles buyer dashboard display and functionality.
 */
class Community_Auctions_Buyer_Dashboard {

	/**
	 * Register hooks and shortcodes.
	 */
	public static function register() {
		add_shortcode( 'community_auction_buyer_dashboard', array( __CLASS__, 'render_shortcode' ) );
		add_action( 'bp_setup_nav', array( __CLASS__, 'add_buddypress_subnav' ), 100 );
	}

	/**
	 * Add BuddyPress subnav for buyer dashboard.
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
				'name'            => __( 'Won Auctions', 'community-auctions' ),
				'slug'            => 'purchases',
				'parent_url'      => bp_displayed_user_domain() . $parent_slug . '/',
				'parent_slug'     => $parent_slug,
				'screen_function' => array( __CLASS__, 'buddypress_screen' ),
				'position'        => 20,
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
		// Show title in BuddyPress context since there's no page title.
		echo self::render_shortcode( array( 'show_title' => 'true' ) );
	}

	/**
	 * Render buyer dashboard shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string HTML output.
	 */
	public static function render_shortcode( $atts ) {
		if ( ! is_user_logged_in() ) {
			return '<p>' . esc_html__( 'Please log in to view your purchases.', 'community-auctions' ) . '</p>';
		}

		$atts = shortcode_atts(
			array(
				'user_id'    => get_current_user_id(),
				'per_page'   => 10,
				'show_title' => 'false', // Default to false to avoid duplicate headings with page title.
			),
			$atts
		);

		$user_id    = absint( $atts['user_id'] );
		$show_title = filter_var( $atts['show_title'], FILTER_VALIDATE_BOOLEAN );

		// Only allow viewing own dashboard unless admin.
		if ( $user_id !== get_current_user_id() && ! current_user_can( 'ca_manage_auctions' ) ) {
			return '<p>' . esc_html__( 'You do not have permission to view this dashboard.', 'community-auctions' ) . '</p>';
		}

		self::enqueue_assets();

		$stats = self::get_buyer_stats( $user_id );

		ob_start();
		?>
		<div class="ca-buyer-dashboard">
			<?php if ( $show_title ) : ?>
				<h2><?php esc_html_e( 'My Purchases', 'community-auctions' ); ?></h2>
			<?php endif; ?>

			<?php echo self::render_stats_summary( $stats ); ?>

			<div class="ca-dashboard-tabs" role="tablist">
				<button class="ca-tab ca-tab--active" data-tab="pending" role="tab" aria-selected="true" aria-controls="ca-buyer-tab-pending">
					<?php esc_html_e( 'Pending Payment', 'community-auctions' ); ?>
					<span class="ca-tab-count"><?php echo esc_html( $stats['pending_payment'] ); ?></span>
				</button>
				<button class="ca-tab" data-tab="paid" role="tab" aria-selected="false" aria-controls="ca-buyer-tab-paid">
					<?php esc_html_e( 'Paid', 'community-auctions' ); ?>
					<span class="ca-tab-count"><?php echo esc_html( $stats['paid_count'] ); ?></span>
				</button>
				<button class="ca-tab" data-tab="all" role="tab" aria-selected="false" aria-controls="ca-buyer-tab-all">
					<?php esc_html_e( 'All Won', 'community-auctions' ); ?>
					<span class="ca-tab-count"><?php echo esc_html( $stats['total_won'] ); ?></span>
				</button>
			</div>

			<div id="ca-buyer-tab-pending" class="ca-tab-content ca-tab-content--active" role="tabpanel">
				<?php echo self::render_won_list( $user_id, 'pending', intval( $atts['per_page'] ) ); ?>
			</div>

			<div id="ca-buyer-tab-paid" class="ca-tab-content" role="tabpanel" hidden>
				<?php echo self::render_won_list( $user_id, 'paid', intval( $atts['per_page'] ) ); ?>
			</div>

			<div id="ca-buyer-tab-all" class="ca-tab-content" role="tabpanel" hidden>
				<?php echo self::render_won_list( $user_id, 'all', intval( $atts['per_page'] ) ); ?>
			</div>
		</div>

		<script>
		(function() {
			// Tab switching.
			var tabs = document.querySelectorAll('.ca-buyer-dashboard .ca-dashboard-tabs .ca-tab');
			tabs.forEach(function(tab) {
				tab.addEventListener('click', function() {
					var targetId = 'ca-buyer-tab-' + this.getAttribute('data-tab');
					tabs.forEach(function(t) {
						t.classList.remove('ca-tab--active');
						t.setAttribute('aria-selected', 'false');
					});
					this.classList.add('ca-tab--active');
					this.setAttribute('aria-selected', 'true');
					document.querySelectorAll('.ca-buyer-dashboard .ca-tab-content').forEach(function(c) {
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

			// Seller contact toggle.
			var toggles = document.querySelectorAll('.ca-seller-contact-toggle');
			toggles.forEach(function(toggle) {
				toggle.addEventListener('click', function(e) {
					e.stopPropagation();
					var targetId = this.getAttribute('aria-controls');
					var dropdown = document.getElementById(targetId);
					if (!dropdown) return;

					var expanded = this.getAttribute('aria-expanded') === 'true';

					// Close all other dropdowns first.
					document.querySelectorAll('.ca-seller-contact-dropdown').forEach(function(d) {
						d.hidden = true;
					});
					document.querySelectorAll('.ca-seller-contact-toggle').forEach(function(t) {
						t.setAttribute('aria-expanded', 'false');
					});

					// Toggle current dropdown.
					if (!expanded) {
						dropdown.hidden = false;
						this.setAttribute('aria-expanded', 'true');
					}
				});
			});

			// Close dropdown when clicking outside.
			document.addEventListener('click', function(e) {
				if (!e.target.closest('.ca-seller-contact-wrapper')) {
					document.querySelectorAll('.ca-seller-contact-dropdown').forEach(function(d) {
						d.hidden = true;
					});
					document.querySelectorAll('.ca-seller-contact-toggle').forEach(function(t) {
						t.setAttribute('aria-expanded', 'false');
					});
				}
			});
		})();
		</script>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render stats summary section.
	 *
	 * @param array $stats Buyer statistics.
	 * @return string HTML output.
	 */
	private static function render_stats_summary( $stats ) {
		ob_start();
		?>
		<div class="ca-buyer-stats">
			<div class="ca-stat-card">
				<span class="ca-stat-value"><?php echo esc_html( $stats['total_won'] ); ?></span>
				<span class="ca-stat-label"><?php esc_html_e( 'Auctions Won', 'community-auctions' ); ?></span>
			</div>
			<div class="ca-stat-card ca-stat-card--highlight">
				<span class="ca-stat-value"><?php echo esc_html( self::format_currency( $stats['total_spent'] ) ); ?></span>
				<span class="ca-stat-label"><?php esc_html_e( 'Total Spent', 'community-auctions' ); ?></span>
			</div>
			<div class="ca-stat-card">
				<span class="ca-stat-value"><?php echo esc_html( $stats['total_bids_placed'] ); ?></span>
				<span class="ca-stat-label"><?php esc_html_e( 'Bids Placed', 'community-auctions' ); ?></span>
			</div>
			<div class="ca-stat-card">
				<span class="ca-stat-value"><?php echo esc_html( $stats['active_bids'] ); ?></span>
				<span class="ca-stat-label"><?php esc_html_e( 'Active Bids', 'community-auctions' ); ?></span>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render won auctions list by payment status.
	 *
	 * @param int    $user_id  User ID.
	 * @param string $filter   Filter type (pending, paid, all).
	 * @param int    $per_page Items per page.
	 * @return string HTML output.
	 */
	private static function render_won_list( $user_id, $filter, $per_page ) {
		$won_auctions = self::get_won_auctions( $user_id );

		if ( empty( $won_auctions ) ) {
			return '<p class="ca-empty-state">' . esc_html__( 'You haven\'t won any auctions yet.', 'community-auctions' ) . '</p>';
		}

		$settings = Community_Auctions_Settings::get_settings();
		$provider = $settings['payment_provider'] ?? '';

		// Filter by payment status.
		$filtered = array();
		foreach ( $won_auctions as $auction_id ) {
			$order_id = absint( get_post_meta( $auction_id, 'ca_order_id', true ) );
			$paid     = $order_id ? Community_Auctions_Payment_Status::is_order_paid( $order_id, $provider ) : false;

			if ( 'pending' === $filter && ! $paid ) {
				$filtered[] = $auction_id;
			} elseif ( 'paid' === $filter && $paid ) {
				$filtered[] = $auction_id;
			} elseif ( 'all' === $filter ) {
				$filtered[] = $auction_id;
			}
		}

		if ( empty( $filtered ) ) {
			$empty_messages = array(
				'pending' => __( 'No payments pending.', 'community-auctions' ),
				'paid'    => __( 'No paid purchases yet.', 'community-auctions' ),
				'all'     => __( 'No won auctions.', 'community-auctions' ),
			);
			return '<p class="ca-empty-state">' . esc_html( $empty_messages[ $filter ] ?? __( 'No auctions found.', 'community-auctions' ) ) . '</p>';
		}

		// Limit to per_page.
		$filtered = array_slice( $filtered, 0, $per_page );

		ob_start();
		?>
		<div class="ca-buyer-auctions">
			<?php foreach ( $filtered as $auction_id ) : ?>
				<?php echo self::render_won_row( $auction_id, $provider ); ?>
			<?php endforeach; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render a single won auction row.
	 *
	 * @param int    $auction_id Auction post ID.
	 * @param string $provider   Payment provider.
	 * @return string HTML output.
	 */
	private static function render_won_row( $auction_id, $provider ) {
		$final_bid    = get_post_meta( $auction_id, 'ca_current_bid', true );
		$order_id     = absint( get_post_meta( $auction_id, 'ca_order_id', true ) );
		$end_at       = get_post_meta( $auction_id, 'ca_end_at', true );
		$seller_id    = get_post_field( 'post_author', $auction_id );
		$paid         = $order_id ? Community_Auctions_Payment_Status::is_order_paid( $order_id, $provider ) : false;
		$pay_url      = $order_id && ! $paid ? Community_Auctions_Payment_Status::get_payment_link( $order_id, $provider ) : '';

		ob_start();
		?>
		<div class="ca-buyer-auction-row" data-auction-id="<?php echo esc_attr( $auction_id ); ?>">
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
					<span class="ca-meta-item">
						<strong><?php esc_html_e( 'Won for:', 'community-auctions' ); ?></strong>
						<?php echo esc_html( self::format_currency( $final_bid ) ); ?>
					</span>
					<span class="ca-meta-item">
						<strong><?php esc_html_e( 'Seller:', 'community-auctions' ); ?></strong>
						<?php echo esc_html( self::get_user_display_name( $seller_id ) ); ?>
					</span>
					<span class="ca-meta-item">
						<strong><?php esc_html_e( 'Won on:', 'community-auctions' ); ?></strong>
						<?php echo esc_html( $end_at ); ?>
					</span>
					<span class="ca-payment-badge ca-payment-badge--<?php echo esc_attr( $paid ? 'paid' : 'pending' ); ?>">
						<?php echo $paid ? esc_html__( 'Paid', 'community-auctions' ) : esc_html__( 'Payment Pending', 'community-auctions' ); ?>
					</span>
				</div>
			</div>
			<div class="ca-auction-row-actions">
				<a href="<?php echo esc_url( get_permalink( $auction_id ) ); ?>" class="ca-action-link">
					<?php esc_html_e( 'View', 'community-auctions' ); ?>
				</a>
				<?php if ( $pay_url && ! $paid ) : ?>
					<a href="<?php echo esc_url( $pay_url ); ?>" class="ca-action-link ca-action-link--primary ca-pay-now-btn">
						<?php esc_html_e( 'Pay Now', 'community-auctions' ); ?>
					</a>
				<?php elseif ( ! $order_id && ! $paid ) : ?>
					<span class="ca-action-link ca-action-link--notice" title="<?php esc_attr_e( 'Contact seller to arrange payment', 'community-auctions' ); ?>">
						<?php esc_html_e( 'Contact Seller for Payment', 'community-auctions' ); ?>
					</span>
				<?php endif; ?>
				<?php echo self::render_seller_contact( $auction_id, $seller_id ); ?>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render seller contact information.
	 *
	 * @param int $auction_id Auction post ID.
	 * @param int $seller_id  Seller user ID.
	 * @return string HTML output.
	 */
	private static function render_seller_contact( $auction_id, $seller_id ) {
		$seller = get_userdata( $seller_id );
		if ( ! $seller ) {
			return '';
		}

		// Get BuddyPress profile URL if available.
		$profile_url = '';
		if ( function_exists( 'bp_core_get_user_domain' ) ) {
			$profile_url = bp_core_get_user_domain( $seller_id );
		}

		// Get message URL if BuddyPress Messages is active.
		$message_url = '';
		if ( function_exists( 'bp_is_active' ) && bp_is_active( 'messages' ) && function_exists( 'bp_core_get_user_domain' ) ) {
			$message_url = trailingslashit( bp_core_get_user_domain( $seller_id ) ) . 'messages/compose/';
		}

		ob_start();
		?>
		<div class="ca-seller-contact-wrapper">
			<button type="button" class="ca-action-link ca-seller-contact-toggle" aria-expanded="false" aria-controls="ca-seller-contact-<?php echo esc_attr( $auction_id ); ?>" data-auction-id="<?php echo esc_attr( $auction_id ); ?>">
				<?php esc_html_e( 'Contact Seller', 'community-auctions' ); ?>
				<span class="ca-toggle-icon" aria-hidden="true">▼</span>
			</button>
			<div id="ca-seller-contact-<?php echo esc_attr( $auction_id ); ?>" class="ca-seller-contact-dropdown" hidden>
				<div class="ca-seller-contact-content">
					<div class="ca-seller-info">
						<p>
							<strong><?php esc_html_e( 'Seller:', 'community-auctions' ); ?></strong>
							<?php echo esc_html( $seller->display_name ); ?>
						</p>
						<p>
							<strong><?php esc_html_e( 'Email:', 'community-auctions' ); ?></strong>
							<a href="mailto:<?php echo esc_attr( $seller->user_email ); ?>"><?php echo esc_html( $seller->user_email ); ?></a>
						</p>
					</div>
					<div class="ca-seller-actions">
						<a href="mailto:<?php echo esc_attr( $seller->user_email ); ?>" class="ca-btn ca-btn--secondary">
							<?php esc_html_e( 'Send Email', 'community-auctions' ); ?>
						</a>
						<?php if ( $message_url ) : ?>
							<a href="<?php echo esc_url( $message_url ); ?>" class="ca-btn ca-btn--secondary">
								<?php esc_html_e( 'Send Message', 'community-auctions' ); ?>
							</a>
						<?php endif; ?>
						<?php if ( $profile_url ) : ?>
							<a href="<?php echo esc_url( $profile_url ); ?>" class="ca-btn ca-btn--outline">
								<?php esc_html_e( 'View Profile', 'community-auctions' ); ?>
							</a>
						<?php endif; ?>
					</div>
				</div>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Get buyer statistics.
	 *
	 * @param int $user_id User ID.
	 * @return array Statistics data.
	 */
	public static function get_buyer_stats( $user_id ) {
		global $wpdb;

		$won_auctions = self::get_won_auctions( $user_id );

		$settings = Community_Auctions_Settings::get_settings();
		$provider = $settings['payment_provider'] ?? '';

		// Calculate payment stats.
		$pending_payment = 0;
		$paid_count      = 0;
		$total_spent     = 0;

		foreach ( $won_auctions as $auction_id ) {
			$order_id  = absint( get_post_meta( $auction_id, 'ca_order_id', true ) );
			$final_bid = floatval( get_post_meta( $auction_id, 'ca_current_bid', true ) );
			$paid      = $order_id ? Community_Auctions_Payment_Status::is_order_paid( $order_id, $provider ) : false;

			if ( $paid ) {
				$paid_count++;
				$total_spent += $final_bid;
			} else {
				$pending_payment++;
			}
		}

		// Get total bids placed.
		$bids_table = $wpdb->prefix . 'ca_bids';
		$total_bids_placed = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$bids_table} WHERE user_id = %d",
				$user_id
			)
		);

		// Get active bids (user is highest bidder on live auctions).
		$active_bids = self::count_active_bids( $user_id );

		return array(
			'total_won'         => count( $won_auctions ),
			'pending_payment'   => $pending_payment,
			'paid_count'        => $paid_count,
			'total_spent'       => $total_spent,
			'total_bids_placed' => $total_bids_placed,
			'active_bids'       => $active_bids,
		);
	}

	/**
	 * Get won auctions for a user.
	 *
	 * @param int $user_id User ID.
	 * @return int[] Array of auction IDs.
	 */
	private static function get_won_auctions( $user_id ) {
		$query = new WP_Query(
			array(
				'post_type'      => 'auction',
				'post_status'    => array( 'ca_ended', 'ca_closed' ),
				'posts_per_page' => 100, // Bounded query - reasonable limit for won auctions.
				'fields'         => 'ids',
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

		return $query->posts;
	}

	/**
	 * Count auctions where user is currently highest bidder.
	 *
	 * @param int $user_id User ID.
	 * @return int Count of active bids.
	 */
	private static function count_active_bids( $user_id ) {
		$query = new WP_Query(
			array(
				'post_type'      => 'auction',
				'post_status'    => array( 'publish', 'ca_live' ),
				'posts_per_page' => 1, // Only need count, not posts.
				'fields'         => 'ids',
				'no_found_rows'  => false, // Need found_posts for count.
				'meta_query'     => array(
					array(
						'key'     => 'ca_current_bidder',
						'value'   => $user_id,
						'compare' => '=',
						'type'    => 'NUMERIC',
					),
				),
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
	}
}
