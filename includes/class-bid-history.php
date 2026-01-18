<?php
/**
 * Bid History - Provides bid history display and REST API.
 *
 * @package Community_Auctions
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Community_Auctions_Bid_History
 *
 * Handles bid history display and REST endpoint.
 */
class Community_Auctions_Bid_History {

	/**
	 * Register hooks.
	 */
	public static function register() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
		add_shortcode( 'community_auction_bid_history', array( __CLASS__, 'render_shortcode' ) );
	}

	/**
	 * Register REST API routes.
	 */
	public static function register_routes() {
		register_rest_route(
			'community-auctions/v1',
			'/auctions/(?P<id>\d+)/bids',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_auction_bids' ),
				'permission_callback' => array( __CLASS__, 'can_view_bids' ),
				'args'                => array(
					'id'       => array(
						'type'              => 'integer',
						'required'          => true,
						'sanitize_callback' => 'absint',
					),
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
	}

	/**
	 * Check if user can view bids.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool
	 */
	public static function can_view_bids( WP_REST_Request $request ) {
		$auction_id = absint( $request->get_param( 'id' ) );
		$auction    = get_post( $auction_id );

		if ( ! $auction || 'auction' !== $auction->post_type ) {
			return false;
		}

		// Check visibility for group-only auctions.
		$visibility = get_post_meta( $auction_id, 'ca_visibility', true );
		if ( 'group_only' === $visibility ) {
			if ( ! is_user_logged_in() ) {
				return false;
			}

			$group_id = absint( get_post_meta( $auction_id, 'ca_group_id', true ) );
			if ( $group_id && function_exists( 'groups_is_user_member' ) ) {
				return groups_is_user_member( get_current_user_id(), $group_id );
			}

			return false;
		}

		return true;
	}

	/**
	 * Get auction bids via REST API.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public static function get_auction_bids( WP_REST_Request $request ) {
		$auction_id = absint( $request->get_param( 'id' ) );
		$per_page   = min( 100, max( 1, absint( $request->get_param( 'per_page' ) ) ) );
		$page       = max( 1, absint( $request->get_param( 'page' ) ) );
		$offset     = ( $page - 1 ) * $per_page;

		$bids        = Community_Auctions_Bid_Repository::get_auction_bids( $auction_id, $per_page, $offset );
		$total       = Community_Auctions_Bid_Repository::count_auction_bids( $auction_id );
		$bidders     = Community_Auctions_Bid_Repository::count_unique_bidders( $auction_id );
		$total_pages = ceil( $total / $per_page );

		$formatted_bids = array();
		foreach ( $bids as $bid ) {
			$formatted_bids[] = self::format_bid( $bid );
		}

		return new WP_REST_Response(
			array(
				'bids'           => $formatted_bids,
				'total'          => $total,
				'total_pages'    => $total_pages,
				'current_page'   => $page,
				'unique_bidders' => $bidders,
			),
			200
		);
	}

	/**
	 * Format a bid for output.
	 *
	 * @param object $bid Bid object.
	 * @return array Formatted bid data.
	 */
	private static function format_bid( $bid ) {
		$user_id      = absint( $bid->user_id );
		$display_name = ! empty( $bid->display_name ) ? $bid->display_name : __( 'Anonymous', 'community-auctions' );
		$avatar_url   = get_avatar_url( $user_id, array( 'size' => 48 ) );

		return array(
			'id'           => absint( $bid->id ),
			'user_id'      => $user_id,
			'display_name' => $display_name,
			'avatar_url'   => $avatar_url,
			'amount'       => floatval( $bid->amount ),
			'is_proxy'     => (bool) $bid->is_proxy,
			'created_at'   => $bid->created_at,
			'time_ago'     => human_time_diff( strtotime( $bid->created_at ), current_time( 'timestamp', true ) ),
		);
	}

	/**
	 * Render bid history shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string HTML output.
	 */
	public static function render_shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'id'       => 0,
				'per_page' => 10,
				'show_avatars' => '1',
			),
			$atts
		);

		$auction_id = absint( $atts['id'] );
		if ( ! $auction_id ) {
			$auction_id = get_the_ID();
		}

		if ( ! $auction_id ) {
			return '';
		}

		// Check visibility.
		if ( ! self::can_user_view_auction( $auction_id ) ) {
			return '<p>' . esc_html__( 'Bid history is only visible to group members.', 'community-auctions' ) . '</p>';
		}

		$per_page = max( 1, absint( $atts['per_page'] ) );
		$bids     = Community_Auctions_Bid_Repository::get_auction_bids( $auction_id, $per_page, 0 );
		$total    = Community_Auctions_Bid_Repository::count_auction_bids( $auction_id );
		$bidders  = Community_Auctions_Bid_Repository::count_unique_bidders( $auction_id );

		if ( empty( $bids ) ) {
			return '<p class="ca-bid-history-empty">' . esc_html__( 'No bids yet.', 'community-auctions' ) . '</p>';
		}

		ob_start();
		?>
		<div class="ca-bid-history" data-auction-id="<?php echo esc_attr( $auction_id ); ?>">
			<div class="ca-bid-history-summary">
				<span class="ca-bid-count">
					<?php
					printf(
						/* translators: %d: number of bids */
						esc_html( _n( '%d bid', '%d bids', $total, 'community-auctions' ) ),
						esc_html( $total )
					);
					?>
				</span>
				<span class="ca-bidder-count">
					<?php
					printf(
						/* translators: %d: number of bidders */
						esc_html( _n( '%d bidder', '%d bidders', $bidders, 'community-auctions' ) ),
						esc_html( $bidders )
					);
					?>
				</span>
			</div>

			<ul class="ca-bid-history-list" role="list">
				<?php foreach ( $bids as $bid ) : ?>
					<?php
					$display_name = ! empty( $bid->display_name ) ? $bid->display_name : __( 'Anonymous', 'community-auctions' );
					$avatar_url   = get_avatar_url( $bid->user_id, array( 'size' => 48 ) );
					$time_ago     = human_time_diff( strtotime( $bid->created_at ), current_time( 'timestamp', true ) );
					?>
					<li class="ca-bid-item <?php echo esc_attr( $bid->is_proxy ? 'ca-bid-item--proxy' : '' ); ?>">
						<?php if ( '1' === $atts['show_avatars'] ) : ?>
							<img
								src="<?php echo esc_url( $avatar_url ); ?>"
								alt="<?php echo esc_attr( $display_name ); ?>"
								class="ca-bid-avatar"
								width="48"
								height="48"
							/>
						<?php endif; ?>
						<div class="ca-bid-details">
							<span class="ca-bid-bidder"><?php echo esc_html( $display_name ); ?></span>
							<span class="ca-bid-amount"><?php echo esc_html( number_format( $bid->amount, 2 ) ); ?></span>
							<?php if ( $bid->is_proxy ) : ?>
								<span class="ca-bid-proxy-badge"><?php esc_html_e( 'Proxy', 'community-auctions' ); ?></span>
							<?php endif; ?>
						</div>
						<time class="ca-bid-time" datetime="<?php echo esc_attr( $bid->created_at ); ?>">
							<?php
							printf(
								/* translators: %s: time ago */
								esc_html__( '%s ago', 'community-auctions' ),
								esc_html( $time_ago )
							);
							?>
						</time>
					</li>
				<?php endforeach; ?>
			</ul>

			<?php if ( $total > $per_page ) : ?>
				<button type="button" class="ca-bid-history-load-more" data-page="2" data-per-page="<?php echo esc_attr( $per_page ); ?>">
					<?php esc_html_e( 'Load more bids', 'community-auctions' ); ?>
				</button>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Check if current user can view auction.
	 *
	 * @param int $auction_id Auction ID.
	 * @return bool
	 */
	private static function can_user_view_auction( $auction_id ) {
		$visibility = get_post_meta( $auction_id, 'ca_visibility', true );
		if ( 'group_only' !== $visibility ) {
			return true;
		}

		if ( ! is_user_logged_in() ) {
			return false;
		}

		$group_id = absint( get_post_meta( $auction_id, 'ca_group_id', true ) );
		if ( ! $group_id || ! function_exists( 'groups_is_user_member' ) ) {
			return false;
		}

		return groups_is_user_member( get_current_user_id(), $group_id );
	}

	/**
	 * Render inline bid history for single auction page.
	 *
	 * @param int $auction_id Auction ID.
	 * @return string HTML output.
	 */
	public static function render_inline( $auction_id ) {
		return self::render_shortcode( array( 'id' => $auction_id ) );
	}
}
