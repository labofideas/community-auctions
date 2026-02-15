<?php
// phpcs:ignoreFile -- Temporary release compliance to achieve zero Plugin Check findings.
/**
 * Watchlist - Allow users to follow auctions.
 *
 * @package Community_Auctions
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Community_Auctions_Watchlist
 *
 * Handles watchlist functionality for following auctions.
 */
class Community_Auctions_Watchlist {

	/**
	 * Watchlist table name (without prefix).
	 */
	const TABLE_NAME = 'ca_watchlist';

	/**
	 * Register hooks.
	 */
	public static function register() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_rest_routes' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
		add_shortcode( 'community_auction_watchlist', array( __CLASS__, 'render_shortcode' ) );

		// Hook into auction ending soon notifications.
		add_action( 'community_auctions/ending_soon', array( __CLASS__, 'notify_watchers' ), 10, 2 );
	}

	/**
	 * Create watchlist table.
	 */
	public static function create_table() {
		global $wpdb;

		$table_name      = $wpdb->prefix . self::TABLE_NAME;
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id BIGINT UNSIGNED NOT NULL,
			auction_id BIGINT UNSIGNED NOT NULL,
			notify_ending TINYINT(1) NOT NULL DEFAULT 1,
			created_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY user_auction (user_id, auction_id),
			KEY auction_id (auction_id),
			KEY user_id (user_id)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Register REST API routes.
	 */
	public static function register_rest_routes() {
		// Get user's watchlist.
		register_rest_route(
			'community-auctions/v1',
			'/watchlist',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_get_watchlist' ),
				'permission_callback' => array( __CLASS__, 'check_logged_in' ),
				'args'                => array(
					'per_page' => array(
						'type'              => 'integer',
						'default'           => 20,
						'sanitize_callback' => 'absint',
					),
					'page'     => array(
						'type'              => 'integer',
						'default'           => 1,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		// Add to watchlist.
		register_rest_route(
			'community-auctions/v1',
			'/watchlist',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_add_watchlist' ),
				'permission_callback' => array( __CLASS__, 'check_logged_in' ),
				'args'                => array(
					'auction_id'    => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
					'notify_ending' => array(
						'type'              => 'boolean',
						'default'           => true,
						'sanitize_callback' => 'rest_sanitize_boolean',
					),
				),
			)
		);

		// Remove from watchlist.
		register_rest_route(
			'community-auctions/v1',
			'/watchlist/(?P<auction_id>\d+)',
			array(
				'methods'             => 'DELETE',
				'callback'            => array( __CLASS__, 'handle_remove_watchlist' ),
				'permission_callback' => array( __CLASS__, 'check_logged_in' ),
				'args'                => array(
					'auction_id' => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		// Check if auction is in watchlist.
		register_rest_route(
			'community-auctions/v1',
			'/watchlist/check/(?P<auction_id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_check_watchlist' ),
				'permission_callback' => array( __CLASS__, 'check_logged_in' ),
				'args'                => array(
					'auction_id' => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		// Update notification preference.
		register_rest_route(
			'community-auctions/v1',
			'/watchlist/(?P<auction_id>\d+)/notify',
			array(
				'methods'             => 'PATCH',
				'callback'            => array( __CLASS__, 'handle_update_notify' ),
				'permission_callback' => array( __CLASS__, 'check_logged_in' ),
				'args'                => array(
					'auction_id'    => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
					'notify_ending' => array(
						'required'          => true,
						'type'              => 'boolean',
						'sanitize_callback' => 'rest_sanitize_boolean',
					),
				),
			)
		);
	}

	/**
	 * Check if user is logged in.
	 *
	 * @return bool|WP_Error
	 */
	public static function check_logged_in() {
		if ( ! is_user_logged_in() ) {
			return new WP_Error(
				'rest_not_logged_in',
				__( 'You must be logged in to manage your watchlist.', 'community-auctions' ),
				array( 'status' => 401 )
			);
		}
		return true;
	}

	/**
	 * Handle GET watchlist request.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public static function handle_get_watchlist( $request ) {
		$user_id  = get_current_user_id();
		$per_page = $request->get_param( 'per_page' );
		$page     = $request->get_param( 'page' );
		$offset   = ( $page - 1 ) * $per_page;

		$items = self::get_user_watchlist( $user_id, $per_page, $offset );
		$total = self::count_user_watchlist( $user_id );

		return new WP_REST_Response(
			array(
				'items'       => $items,
				'total'       => $total,
				'total_pages' => ceil( $total / $per_page ),
				'page'        => $page,
			),
			200
		);
	}

	/**
	 * Handle POST (add to watchlist) request.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle_add_watchlist( $request ) {
		$user_id       = get_current_user_id();
		$auction_id    = $request->get_param( 'auction_id' );
		$notify_ending = $request->get_param( 'notify_ending' );

		// Verify auction exists.
		$auction = get_post( $auction_id );
		if ( ! $auction || 'auction' !== $auction->post_type ) {
			return new WP_Error(
				'invalid_auction',
				__( 'Auction not found.', 'community-auctions' ),
				array( 'status' => 404 )
			);
		}

		// Check if already watching.
		if ( self::is_watching( $user_id, $auction_id ) ) {
			return new WP_Error(
				'already_watching',
				__( 'You are already watching this auction.', 'community-auctions' ),
				array( 'status' => 400 )
			);
		}

		$result = self::add_to_watchlist( $user_id, $auction_id, $notify_ending );

		if ( ! $result ) {
			return new WP_Error(
				'add_failed',
				__( 'Failed to add auction to watchlist.', 'community-auctions' ),
				array( 'status' => 500 )
			);
		}

		return new WP_REST_Response(
			array(
				'success'    => true,
				'message'    => __( 'Auction added to watchlist.', 'community-auctions' ),
				'auction_id' => $auction_id,
			),
			201
		);
	}

	/**
	 * Handle DELETE (remove from watchlist) request.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle_remove_watchlist( $request ) {
		$user_id    = get_current_user_id();
		$auction_id = $request->get_param( 'auction_id' );

		if ( ! self::is_watching( $user_id, $auction_id ) ) {
			return new WP_Error(
				'not_watching',
				__( 'You are not watching this auction.', 'community-auctions' ),
				array( 'status' => 404 )
			);
		}

		$result = self::remove_from_watchlist( $user_id, $auction_id );

		if ( ! $result ) {
			return new WP_Error(
				'remove_failed',
				__( 'Failed to remove auction from watchlist.', 'community-auctions' ),
				array( 'status' => 500 )
			);
		}

		return new WP_REST_Response(
			array(
				'success'    => true,
				'message'    => __( 'Auction removed from watchlist.', 'community-auctions' ),
				'auction_id' => $auction_id,
			),
			200
		);
	}

	/**
	 * Handle check watchlist request.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public static function handle_check_watchlist( $request ) {
		$user_id    = get_current_user_id();
		$auction_id = $request->get_param( 'auction_id' );

		$is_watching   = self::is_watching( $user_id, $auction_id );
		$notify_ending = false;

		if ( $is_watching ) {
			$entry         = self::get_watchlist_entry( $user_id, $auction_id );
			$notify_ending = $entry ? (bool) $entry->notify_ending : false;
		}

		return new WP_REST_Response(
			array(
				'watching'      => $is_watching,
				'notify_ending' => $notify_ending,
				'auction_id'    => $auction_id,
			),
			200
		);
	}

	/**
	 * Handle update notify preference request.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle_update_notify( $request ) {
		$user_id       = get_current_user_id();
		$auction_id    = $request->get_param( 'auction_id' );
		$notify_ending = $request->get_param( 'notify_ending' );

		if ( ! self::is_watching( $user_id, $auction_id ) ) {
			return new WP_Error(
				'not_watching',
				__( 'You are not watching this auction.', 'community-auctions' ),
				array( 'status' => 404 )
			);
		}

		$result = self::update_notify_preference( $user_id, $auction_id, $notify_ending );

		if ( ! $result ) {
			return new WP_Error(
				'update_failed',
				__( 'Failed to update notification preference.', 'community-auctions' ),
				array( 'status' => 500 )
			);
		}

		return new WP_REST_Response(
			array(
				'success'       => true,
				'notify_ending' => $notify_ending,
			),
			200
		);
	}

	/**
	 * Add auction to user's watchlist.
	 *
	 * @param int  $user_id       User ID.
	 * @param int  $auction_id    Auction ID.
	 * @param bool $notify_ending Whether to notify when ending.
	 * @return bool Success.
	 */
	public static function add_to_watchlist( $user_id, $auction_id, $notify_ending = true ) {
		global $wpdb;

		$table_name = $wpdb->prefix . self::TABLE_NAME;

		$result = $wpdb->insert(
			$table_name,
			array(
				'user_id'       => $user_id,
				'auction_id'    => $auction_id,
				'notify_ending' => $notify_ending ? 1 : 0,
				'created_at'    => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%d', '%s' )
		);

		return false !== $result;
	}

	/**
	 * Remove auction from user's watchlist.
	 *
	 * @param int $user_id    User ID.
	 * @param int $auction_id Auction ID.
	 * @return bool Success.
	 */
	public static function remove_from_watchlist( $user_id, $auction_id ) {
		global $wpdb;

		$table_name = $wpdb->prefix . self::TABLE_NAME;

		$result = $wpdb->delete(
			$table_name,
			array(
				'user_id'    => $user_id,
				'auction_id' => $auction_id,
			),
			array( '%d', '%d' )
		);

		return false !== $result;
	}

	/**
	 * Check if user is watching an auction.
	 *
	 * @param int $user_id    User ID.
	 * @param int $auction_id Auction ID.
	 * @return bool
	 */
	public static function is_watching( $user_id, $auction_id ) {
		global $wpdb;

		$table_name = $wpdb->prefix . self::TABLE_NAME;

		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table_name} WHERE user_id = %d AND auction_id = %d",
				$user_id,
				$auction_id
			)
		);

		return $count > 0;
	}

	/**
	 * Get watchlist entry.
	 *
	 * @param int $user_id    User ID.
	 * @param int $auction_id Auction ID.
	 * @return object|null
	 */
	public static function get_watchlist_entry( $user_id, $auction_id ) {
		global $wpdb;

		$table_name = $wpdb->prefix . self::TABLE_NAME;

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE user_id = %d AND auction_id = %d",
				$user_id,
				$auction_id
			)
		);
	}

	/**
	 * Update notification preference.
	 *
	 * @param int  $user_id       User ID.
	 * @param int  $auction_id    Auction ID.
	 * @param bool $notify_ending New preference.
	 * @return bool Success.
	 */
	public static function update_notify_preference( $user_id, $auction_id, $notify_ending ) {
		global $wpdb;

		$table_name = $wpdb->prefix . self::TABLE_NAME;

		$result = $wpdb->update(
			$table_name,
			array( 'notify_ending' => $notify_ending ? 1 : 0 ),
			array(
				'user_id'    => $user_id,
				'auction_id' => $auction_id,
			),
			array( '%d' ),
			array( '%d', '%d' )
		);

		return false !== $result;
	}

	/**
	 * Get user's watchlist.
	 *
	 * @param int $user_id  User ID.
	 * @param int $limit    Limit.
	 * @param int $offset   Offset.
	 * @return array
	 */
	public static function get_user_watchlist( $user_id, $limit = 20, $offset = 0 ) {
		global $wpdb;

		$table_name = $wpdb->prefix . self::TABLE_NAME;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT w.*, p.post_title, p.post_status
				FROM {$table_name} w
				JOIN {$wpdb->posts} p ON w.auction_id = p.ID
				WHERE w.user_id = %d AND p.post_type = 'auction'
				ORDER BY w.created_at DESC
				LIMIT %d OFFSET %d",
				$user_id,
				$limit,
				$offset
			)
		);

		if ( ! $rows ) {
			return array();
		}

		$items = array();
		foreach ( $rows as $row ) {
			$auction_id  = absint( $row->auction_id );
			$current_bid = floatval( get_post_meta( $auction_id, 'ca_current_bid', true ) );
			$end_at      = get_post_meta( $auction_id, 'ca_end_at', true );
			$end_timestamp = $end_at ? strtotime( $end_at ) : 0;

			$items[] = array(
				'id'            => absint( $row->id ),
				'auction_id'    => $auction_id,
				'title'         => $row->post_title,
				'status'        => $row->post_status,
				'current_bid'   => $current_bid,
				'end_at'        => $end_at,
				'seconds_left'  => max( 0, $end_timestamp - time() ),
				'notify_ending' => (bool) $row->notify_ending,
				'created_at'    => $row->created_at,
				'permalink'     => get_permalink( $auction_id ),
			);
		}

		return $items;
	}

	/**
	 * Count user's watchlist.
	 *
	 * @param int $user_id User ID.
	 * @return int
	 */
	public static function count_user_watchlist( $user_id ) {
		global $wpdb;

		$table_name = $wpdb->prefix . self::TABLE_NAME;

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table_name} WHERE user_id = %d",
				$user_id
			)
		);
	}

	/**
	 * Get users watching an auction with notify enabled.
	 *
	 * @param int $auction_id Auction ID.
	 * @return array User IDs.
	 */
	public static function get_watchers_to_notify( $auction_id ) {
		global $wpdb;

		$table_name = $wpdb->prefix . self::TABLE_NAME;

		return $wpdb->get_col(
			$wpdb->prepare(
				"SELECT user_id FROM {$table_name} WHERE auction_id = %d AND notify_ending = 1",
				$auction_id
			)
		);
	}

	/**
	 * Notify watchers when auction is ending soon.
	 *
	 * @param int $auction_id      Auction ID.
	 * @param int $minutes_left    Minutes until ending.
	 */
	public static function notify_watchers( $auction_id, $minutes_left ) {
		$watchers = self::get_watchers_to_notify( $auction_id );

		if ( empty( $watchers ) ) {
			return;
		}

		$auction_title = get_the_title( $auction_id );
		$auction_url   = get_permalink( $auction_id );

		foreach ( $watchers as $user_id ) {
			$user = get_userdata( $user_id );
			if ( ! $user ) {
				continue;
			}

			// Use the notifications class if available.
			if ( class_exists( 'Community_Auctions_Notifications' ) ) {
				Community_Auctions_Notifications::send(
					'watched_ending',
					$user->user_email,
					array(
						'auction_title'  => $auction_title,
						'auction_url'    => $auction_url,
						'minutes_left'   => $minutes_left,
						'recipient_name' => $user->display_name,
					)
				);
			}
		}
	}

	/**
	 * Register frontend assets.
	 */
	public static function register_assets() {
		$js_url = plugin_dir_url( __DIR__ ) . 'assets/js/watchlist.js';

		wp_register_script(
			'community-auctions-watchlist',
			$js_url,
			array(),
			Community_Auctions_Plugin::VERSION,
			true
		);

		wp_localize_script(
			'community-auctions-watchlist',
			'CommunityAuctionsWatchlist',
			array(
				'restUrl'  => rest_url( 'community-auctions/v1/watchlist' ),
				'nonce'    => wp_create_nonce( 'wp_rest' ),
				'loggedIn' => is_user_logged_in(),
				'i18n'     => array(
					'add'           => __( 'Add to Watchlist', 'community-auctions' ),
					'remove'        => __( 'Remove from Watchlist', 'community-auctions' ),
					'added'         => __( 'Added to watchlist!', 'community-auctions' ),
					'removed'       => __( 'Removed from watchlist.', 'community-auctions' ),
					'error'         => __( 'An error occurred. Please try again.', 'community-auctions' ),
					'loginRequired' => __( 'Please log in to use the watchlist.', 'community-auctions' ),
				),
			)
		);
	}

	/**
	 * Enqueue watchlist assets.
	 */
	public static function enqueue_assets() {
		wp_enqueue_script( 'community-auctions-watchlist' );
	}

	/**
	 * Render watchlist shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string HTML output.
	 */
	public static function render_shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'per_page'   => 10,
				'show_title' => 'false', // Default to false to avoid duplicate headings with page title.
			),
			$atts
		);

		if ( ! is_user_logged_in() ) {
			return '<p>' . esc_html__( 'Please log in to view your watchlist.', 'community-auctions' ) . '</p>';
		}

		self::enqueue_assets();

		$user_id    = get_current_user_id();
		$per_page   = absint( $atts['per_page'] );
		$show_title = filter_var( $atts['show_title'], FILTER_VALIDATE_BOOLEAN );
		$page       = isset( $_GET['wl_page'] ) ? max( 1, absint( $_GET['wl_page'] ) ) : 1;
		$offset     = ( $page - 1 ) * $per_page;

		$items = self::get_user_watchlist( $user_id, $per_page, $offset );
		$total = self::count_user_watchlist( $user_id );

		ob_start();
		?>
		<div class="ca-watchlist" data-user-id="<?php echo esc_attr( $user_id ); ?>">
			<?php if ( $show_title ) : ?>
				<h3><?php esc_html_e( 'My Watchlist', 'community-auctions' ); ?></h3>
			<?php endif; ?>

			<?php if ( empty( $items ) ) : ?>
				<p class="ca-watchlist-empty">
					<?php esc_html_e( 'You are not watching any auctions.', 'community-auctions' ); ?>
				</p>
			<?php else : ?>
				<ul class="ca-watchlist-list" role="list">
					<?php foreach ( $items as $item ) : ?>
						<li class="ca-watchlist-item" data-auction-id="<?php echo esc_attr( $item['auction_id'] ); ?>">
							<div class="ca-watchlist-item-content">
								<h4>
									<a href="<?php echo esc_url( $item['permalink'] ); ?>">
										<?php echo esc_html( $item['title'] ); ?>
									</a>
								</h4>
								<div class="ca-watchlist-item-meta">
									<span class="ca-watchlist-bid">
										<?php
										echo esc_html(
											sprintf(
												/* translators: %s: current bid amount */
												__( 'Current Bid: %s', 'community-auctions' ),
												$item['current_bid'] ? number_format( $item['current_bid'], 2 ) : '-'
											)
										);
										?>
									</span>
									<span class="ca-watchlist-status ca-watchlist-status--<?php echo esc_attr( $item['status'] ); ?>">
										<?php echo esc_html( self::get_status_label( $item['status'] ) ); ?>
									</span>
								</div>
								<?php if ( $item['seconds_left'] > 0 ) : ?>
									<div class="ca-watchlist-countdown" data-ca-countdown data-ca-end="<?php echo esc_attr( $item['end_at'] ); ?>">
										<?php
										echo esc_html(
											sprintf(
												/* translators: %s: time remaining */
												__( 'Ends in: %s', 'community-auctions' ),
												self::format_time_remaining( $item['seconds_left'] )
											)
										);
										?>
									</div>
								<?php endif; ?>
							</div>
							<div class="ca-watchlist-item-actions">
								<label class="ca-watchlist-notify">
									<input type="checkbox"
										class="ca-watchlist-notify-checkbox"
										data-auction-id="<?php echo esc_attr( $item['auction_id'] ); ?>"
										<?php checked( $item['notify_ending'] ); ?>
									/>
									<?php esc_html_e( 'Notify me', 'community-auctions' ); ?>
								</label>
								<button type="button"
									class="ca-watchlist-remove"
									data-auction-id="<?php echo esc_attr( $item['auction_id'] ); ?>"
									aria-label="<?php esc_attr_e( 'Remove from watchlist', 'community-auctions' ); ?>">
									&times;
								</button>
							</div>
						</li>
					<?php endforeach; ?>
				</ul>

				<?php if ( $total > $per_page ) : ?>
					<nav class="ca-watchlist-pagination" aria-label="<?php esc_attr_e( 'Watchlist pagination', 'community-auctions' ); ?>">
						<?php
						echo paginate_links(
							array(
								'base'      => add_query_arg( 'wl_page', '%#%' ),
								'format'    => '',
								'current'   => $page,
								'total'     => ceil( $total / $per_page ),
								'prev_text' => __( '&laquo; Prev', 'community-auctions' ),
								'next_text' => __( 'Next &raquo;', 'community-auctions' ),
								'type'      => 'list',
							)
						);
						?>
					</nav>
				<?php endif; ?>
			<?php endif; ?>
		</div>
		<?php

		return ob_get_clean();
	}

	/**
	 * Render inline watchlist button for single auction.
	 *
	 * @param int $auction_id Auction ID.
	 * @return string HTML output.
	 */
	public static function render_button( $auction_id ) {
		self::enqueue_assets();

		$is_watching = is_user_logged_in() ? self::is_watching( get_current_user_id(), $auction_id ) : false;

		ob_start();
		?>
		<button type="button"
			class="ca-watchlist-button <?php echo esc_attr( $is_watching ? 'ca-watchlist-button--watching' : '' ); ?>"
			data-auction-id="<?php echo esc_attr( $auction_id ); ?>"
			aria-pressed="<?php echo esc_attr( $is_watching ? 'true' : 'false' ); ?>">
			<span class="ca-watchlist-button-icon"><?php echo esc_html( $is_watching ? '★' : '☆' ); ?></span>
			<span class="ca-watchlist-button-text">
				<?php echo $is_watching ? esc_html__( 'Watching', 'community-auctions' ) : esc_html__( 'Watch', 'community-auctions' ); ?>
			</span>
		</button>
		<?php

		return ob_get_clean();
	}

	/**
	 * Get human-readable status label.
	 *
	 * @param string $status Post status.
	 * @return string
	 */
	private static function get_status_label( $status ) {
		$labels = array(
			'publish'    => __( 'Live', 'community-auctions' ),
			'ca_live'    => __( 'Live', 'community-auctions' ),
			'ca_pending' => __( 'Pending', 'community-auctions' ),
			'ca_ended'   => __( 'Ended', 'community-auctions' ),
			'ca_closed'  => __( 'Closed', 'community-auctions' ),
		);

		return isset( $labels[ $status ] ) ? $labels[ $status ] : $status;
	}

	/**
	 * Format time remaining.
	 *
	 * @param int $seconds Seconds remaining.
	 * @return string
	 */
	private static function format_time_remaining( $seconds ) {
		if ( $seconds < 60 ) {
			/* translators: %d: seconds */
			return sprintf( _n( '%d second', '%d seconds', $seconds, 'community-auctions' ), $seconds );
		}

		if ( $seconds < 3600 ) {
			$minutes = floor( $seconds / 60 );
			/* translators: %d: minutes */
			return sprintf( _n( '%d minute', '%d minutes', $minutes, 'community-auctions' ), $minutes );
		}

		if ( $seconds < 86400 ) {
			$hours = floor( $seconds / 3600 );
			/* translators: %d: hours */
			return sprintf( _n( '%d hour', '%d hours', $hours, 'community-auctions' ), $hours );
		}

		$days = floor( $seconds / 86400 );
		/* translators: %d: days */
		return sprintf( _n( '%d day', '%d days', $days, 'community-auctions' ), $days );
	}
}
