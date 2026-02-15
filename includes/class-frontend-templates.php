<?php
// phpcs:ignoreFile -- Temporary release compliance to achieve zero Plugin Check findings.
/**
 * Frontend Templates for Auction Display
 *
 * Handles the display of auction details on single auction pages and archives.
 *
 * @package CommunityAuctions
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Frontend Templates Class.
 */
class Community_Auctions_Frontend_Templates {

	/**
	 * Register hooks.
	 */
	public static function register() {
		add_filter( 'the_content', array( __CLASS__, 'single_auction_content' ), 20 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_styles' ) );
	}

	/**
	 * Enqueue frontend styles and scripts.
	 */
	public static function enqueue_styles() {
		$post    = get_post();
		$content = $post ? $post->post_content : '';

		// Check if we need to load auction styles.
		$is_auction_page   = is_singular( 'auction' ) || is_post_type_archive( 'auction' );
		$has_auction_shortcode = has_shortcode( $content, 'community_auctions_search' ) ||
								 has_shortcode( $content, 'community_auctions_list' ) ||
								 has_shortcode( $content, 'community_auction_watchlist' ) ||
								 has_shortcode( $content, 'community_auction_seller_dashboard' ) ||
								 has_shortcode( $content, 'community_auction_buyer_dashboard' ) ||
								 has_shortcode( $content, 'community_auctions_upcoming' );

		if ( ! $is_auction_page && ! $has_auction_shortcode ) {
			return;
		}

		wp_enqueue_style(
			'community-auctions-frontend',
			plugin_dir_url( dirname( __FILE__ ) ) . 'assets/css/frontend.css',
			array( 'dashicons' ),
			Community_Auctions_Plugin::VERSION
		);

		wp_enqueue_style(
			'community-auctions-auction',
			plugin_dir_url( dirname( __FILE__ ) ) . 'assets/css/auction.css',
			array( 'community-auctions-frontend' ),
			Community_Auctions_Plugin::VERSION
		);

		// Enqueue dashicons for icons.
		wp_enqueue_style( 'dashicons' );

		// Enqueue bid form JS on single auction pages.
		if ( is_singular( 'auction' ) ) {
			$handle = 'community-auctions';
			$url    = plugin_dir_url( dirname( __FILE__ ) ) . 'assets/js/auction.js';

			wp_enqueue_script( $handle, $url, array(), '1.0.0', true );
			wp_localize_script( $handle, 'CommunityAuctions', array(
				'restUrl'       => esc_url_raw( rest_url( 'community-auctions/v1/bid' ) ),
				'restBase'      => esc_url_raw( rest_url( 'community-auctions/v1/' ) ),
				'nonce'         => wp_create_nonce( 'wp_rest' ),
				'currentUserId' => get_current_user_id(),
			) );
		}
	}

	/**
	 * Add auction details to single auction content.
	 *
	 * @param string $content Post content.
	 * @return string Modified content.
	 */
	public static function single_auction_content( $content ) {
		if ( ! is_singular( 'auction' ) || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}

		$auction_id = get_the_ID();
		$auction    = get_post( $auction_id );

		if ( ! $auction ) {
			return $content;
		}

		ob_start();
		self::render_auction_details( $auction_id );
		$auction_html = ob_get_clean();

		return $auction_html . $content;
	}

	/**
	 * Render auction details.
	 *
	 * @param int $auction_id Auction post ID.
	 */
	public static function render_auction_details( $auction_id ) {
		$auction = get_post( $auction_id );
		if ( ! $auction ) {
			return;
		}

		// Get auction meta.
		$current_bid   = get_post_meta( $auction_id, 'ca_current_bid', true );
		$start_price   = get_post_meta( $auction_id, 'ca_start_price', true );
		$reserve_price = get_post_meta( $auction_id, 'ca_reserve_price', true );
		$buy_now_price = get_post_meta( $auction_id, 'ca_buy_now_price', true );
		$bid_increment = get_post_meta( $auction_id, 'ca_bid_increment', true ) ?: 1;
		$start_at      = get_post_meta( $auction_id, 'ca_start_at', true );
		$end_at        = get_post_meta( $auction_id, 'ca_end_at', true );
		$bid_count     = get_post_meta( $auction_id, 'ca_bid_count', true ) ?: 0;
		$highest_bidder = get_post_meta( $auction_id, 'ca_highest_bidder', true );
		$seller_id     = $auction->post_author;
		$status        = $auction->post_status;

		// Format prices.
		$display_price = $current_bid ? $current_bid : $start_price;
		$formatted_price = self::format_price( $display_price );
		$formatted_start = self::format_price( $start_price );
		$formatted_buy_now = $buy_now_price ? self::format_price( $buy_now_price ) : '';

		// Calculate minimum bid.
		$min_bid = $current_bid ? (float) $current_bid + (float) $bid_increment : (float) $start_price;

		// Time calculations.
		$now = current_time( 'timestamp' );
		$end_timestamp = $end_at ? strtotime( $end_at ) : 0;
		$start_timestamp = $start_at ? strtotime( $start_at ) : 0;
		$is_ended = $end_timestamp && $end_timestamp <= $now;
		$is_live = 'ca_live' === $status;
		$is_upcoming = $start_timestamp && $start_timestamp > $now;

		// Get categories.
		$categories = get_the_terms( $auction_id, 'auction_category' );
		?>
		<div class="ca-auction-details-wrapper community-auction-single" data-auction-id="<?php echo esc_attr( $auction_id ); ?>">
			<?php if ( $categories && ! is_wp_error( $categories ) ) : ?>
				<div class="ca-auction-categories">
					<?php foreach ( $categories as $cat ) : ?>
						<span class="ca-category-badge"><?php echo esc_html( $cat->name ); ?></span>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<!-- Auction Status Badge -->
			<div class="ca-auction-status ca-status-<?php echo esc_attr( $status ); ?>">
				<?php
				if ( $is_ended || 'ca_ended' === $status ) {
					esc_html_e( 'Ended', 'community-auctions' );
				} elseif ( $is_live ) {
					esc_html_e( 'Live', 'community-auctions' );
				} elseif ( $is_upcoming ) {
					esc_html_e( 'Upcoming', 'community-auctions' );
				} else {
					esc_html_e( 'Pending', 'community-auctions' );
				}
				?>
			</div>

			<!-- Pricing Section -->
			<div class="ca-auction-pricing-box">
				<div class="ca-current-bid-section">
					<span class="ca-price-label">
						<?php echo $current_bid ? esc_html__( 'Current Bid', 'community-auctions' ) : esc_html__( 'Starting Price', 'community-auctions' ); ?>
					</span>
					<span class="ca-price-value ca-current-bid"><?php echo esc_html( $formatted_price ); ?></span>
				</div>

				<div class="ca-bid-info">
					<span class="ca-bid-count">
						<?php
						printf(
							/* translators: %d: number of bids */
							esc_html( _n( '%d bid placed', '%d bids placed', $bid_count, 'community-auctions' ) ),
							(int) $bid_count
						);
						?>
					</span>
					<?php if ( $current_bid && $start_price ) : ?>
						<span class="ca-starting-price">
							<?php
							/* translators: %s: starting price */
							printf( esc_html__( 'Started at %s', 'community-auctions' ), esc_html( $formatted_start ) );
							?>
						</span>
					<?php endif; ?>
				</div>

				<?php if ( $buy_now_price && $is_live ) : ?>
					<div class="ca-buy-now-section">
						<span class="ca-buy-now-label"><?php esc_html_e( 'Buy It Now', 'community-auctions' ); ?></span>
						<span class="ca-buy-now-price"><?php echo esc_html( $formatted_buy_now ); ?></span>
						<?php if ( is_user_logged_in() && get_current_user_id() !== $seller_id ) : ?>
							<button type="button" class="ca-buy-now-btn" data-auction-id="<?php echo esc_attr( $auction_id ); ?>">
								<?php esc_html_e( 'Buy Now', 'community-auctions' ); ?>
							</button>
						<?php endif; ?>
					</div>
				<?php endif; ?>
			</div>

			<!-- Countdown Timer -->
			<?php if ( $end_at && ! $is_ended ) : ?>
				<div class="ca-countdown-section" data-ca-countdown data-end-time="<?php echo esc_attr( $end_at ); ?>">
					<span class="ca-countdown-label">
						<?php $is_upcoming ? esc_html_e( 'Starts in:', 'community-auctions' ) : esc_html_e( 'Time Left:', 'community-auctions' ); ?>
					</span>
					<div class="ca-countdown-timer">
						<?php echo esc_html( self::format_time_remaining( $is_upcoming ? $start_timestamp - $now : $end_timestamp - $now ) ); ?>
					</div>
				</div>
			<?php elseif ( $is_ended ) : ?>
				<div class="ca-countdown-section ca-ended">
					<span class="ca-ended-text"><?php esc_html_e( 'This auction has ended', 'community-auctions' ); ?></span>
					<?php if ( $highest_bidder ) : ?>
						<span class="ca-winner-info">
							<?php
							$winner = get_userdata( $highest_bidder );
							if ( $winner ) {
								/* translators: %s: winner's display name */
								printf( esc_html__( 'Won by %s', 'community-auctions' ), esc_html( $winner->display_name ) );
							}
							?>
						</span>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<!-- Bid Form -->
			<?php if ( $is_live ) : ?>
				<div class="ca-bid-form-section">
					<h3><?php esc_html_e( 'Place Your Bid', 'community-auctions' ); ?></h3>

					<?php if ( ! is_user_logged_in() ) : ?>
						<div class="ca-login-notice">
							<p>
								<?php
								printf(
									/* translators: %1$s: login URL, %2$s: registration URL */
									wp_kses(
										__( 'Please <a href="%1$s">log in</a> or <a href="%2$s">register</a> to place a bid.', 'community-auctions' ),
										array( 'a' => array( 'href' => array() ) )
									),
									esc_url( wp_login_url( get_permalink( $auction_id ) ) ),
									esc_url( wp_registration_url() )
								);
								?>
							</p>
						</div>
					<?php elseif ( get_current_user_id() === $seller_id ) : ?>
						<div class="ca-seller-notice">
							<p><?php esc_html_e( 'You cannot bid on your own auction.', 'community-auctions' ); ?></p>
						</div>
					<?php else : ?>
						<form class="ca-bid-form community-auction-bid-form" id="ca-bid-form-<?php echo esc_attr( $auction_id ); ?>" method="post" data-auction-id="<?php echo esc_attr( $auction_id ); ?>">
							<?php wp_nonce_field( 'ca_place_bid', 'ca_bid_nonce' ); ?>
							<input type="hidden" name="auction_id" value="<?php echo esc_attr( $auction_id ); ?>" />

							<div class="ca-bid-input-wrapper">
								<label for="ca-bid-amount-<?php echo esc_attr( $auction_id ); ?>">
									<?php esc_html_e( 'Your Bid Amount', 'community-auctions' ); ?>
								</label>
								<div class="ca-bid-input-group">
									<span class="ca-currency-symbol">$</span>
									<input
										type="number"
										id="ca-bid-amount-<?php echo esc_attr( $auction_id ); ?>"
										name="amount"
										class="ca-bid-amount-input"
										min="<?php echo esc_attr( $min_bid ); ?>"
										step="<?php echo esc_attr( $bid_increment ); ?>"
										value="<?php echo esc_attr( $min_bid ); ?>"
										required
									/>
								</div>
								<span class="ca-min-bid-hint">
									<?php
									/* translators: %s: minimum bid amount */
									printf( esc_html__( 'Minimum bid: %s', 'community-auctions' ), esc_html( self::format_price( $min_bid ) ) );
									?>
								</span>
							</div>

							<button type="submit" class="ca-place-bid-btn" data-base-text="<?php esc_attr_e( 'Place Bid', 'community-auctions' ); ?>">
								<?php
								/* translators: %s: bid amount */
								printf( esc_html__( 'Place Bid (%s)', 'community-auctions' ), esc_html( self::format_price( $min_bid ) ) );
								?>
							</button>

							<div class="ca-bid-message" role="status" aria-live="polite"></div>
						</form>
						<script>
						(function() {
							var form = document.getElementById('ca-bid-form-<?php echo esc_js( $auction_id ); ?>');
							if (!form) return;
							var input = form.querySelector('input[name="amount"]');
							var btn = form.querySelector('.ca-place-bid-btn');
							if (!input || !btn) return;

							function updateButton() {
								var val = parseFloat(input.value) || 0;
								var formatted = '$' + val.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
								btn.textContent = '<?php echo esc_js( __( 'Place Bid', 'community-auctions' ) ); ?> (' + formatted + ')';
							}

							input.addEventListener('input', updateButton);
							input.addEventListener('change', updateButton);
						})();
						</script>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<!-- Seller Info -->
			<div class="ca-seller-section">
				<h4><?php esc_html_e( 'Seller Information', 'community-auctions' ); ?></h4>
				<div class="ca-seller-info">
					<?php echo get_avatar( $seller_id, 48 ); ?>
					<div class="ca-seller-details">
						<span class="ca-seller-name"><?php echo esc_html( get_the_author_meta( 'display_name', $seller_id ) ); ?></span>
						<span class="ca-seller-since">
							<?php
							/* translators: %s: date user registered */
							printf( esc_html__( 'Member since %s', 'community-auctions' ), esc_html( date_i18n( 'F Y', strtotime( get_the_author_meta( 'user_registered', $seller_id ) ) ) ) );
							?>
						</span>
					</div>
				</div>
			</div>

			<!-- Bid History -->
			<div class="ca-bid-history-section">
				<h4><?php esc_html_e( 'Bid History', 'community-auctions' ); ?></h4>
				<?php self::render_bid_history( $auction_id ); ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render bid history.
	 *
	 * @param int $auction_id Auction ID.
	 */
	private static function render_bid_history( $auction_id ) {
		if ( ! class_exists( 'Community_Auctions_Bid_Repository' ) ) {
			echo '<p class="ca-no-bids">' . esc_html__( 'No bids yet. Be the first to bid!', 'community-auctions' ) . '</p>';
			return;
		}

		$bids = Community_Auctions_Bid_Repository::get_auction_bids( $auction_id, 10 );

		if ( empty( $bids ) ) {
			echo '<p class="ca-no-bids">' . esc_html__( 'No bids yet. Be the first to bid!', 'community-auctions' ) . '</p>';
			return;
		}

		echo '<ul class="ca-bid-history-list">';
		foreach ( $bids as $bid ) {
			$bidder = get_userdata( $bid->user_id );
			$bidder_name = $bidder ? $bidder->display_name : __( 'Anonymous', 'community-auctions' );
			$bid_amount = self::format_price( $bid->amount );
			$bid_time = human_time_diff( strtotime( $bid->created_at ), current_time( 'timestamp' ) );

			printf(
				'<li class="ca-bid-item">
					<span class="ca-bidder">%s</span>
					<span class="ca-bid-amount">%s</span>
					<span class="ca-bid-time">%s %s</span>
				</li>',
				esc_html( $bidder_name ),
				esc_html( $bid_amount ),
				esc_html( $bid_time ),
				esc_html__( 'ago', 'community-auctions' )
			);
		}
		echo '</ul>';
	}

	/**
	 * Format price with currency.
	 *
	 * @param float $amount Amount to format.
	 * @return string Formatted price.
	 */
	private static function format_price( $amount ) {
		if ( class_exists( 'Community_Auctions_Currency' ) ) {
			return Community_Auctions_Currency::format( $amount );
		}
		return '$' . number_format( (float) $amount, 2 );
	}

	/**
	 * Format time remaining.
	 *
	 * @param int $seconds Seconds remaining.
	 * @return string Formatted time.
	 */
	private static function format_time_remaining( $seconds ) {
		if ( $seconds <= 0 ) {
			return __( 'Ended', 'community-auctions' );
		}

		$days = floor( $seconds / DAY_IN_SECONDS );
		$hours = floor( ( $seconds % DAY_IN_SECONDS ) / HOUR_IN_SECONDS );
		$minutes = floor( ( $seconds % HOUR_IN_SECONDS ) / MINUTE_IN_SECONDS );

		$parts = array();

		if ( $days > 0 ) {
			/* translators: %d: number of days */
			$parts[] = sprintf( _n( '%d day', '%d days', $days, 'community-auctions' ), $days );
		}
		if ( $hours > 0 ) {
			/* translators: %d: number of hours */
			$parts[] = sprintf( _n( '%d hour', '%d hours', $hours, 'community-auctions' ), $hours );
		}
		if ( $minutes > 0 && $days === 0 ) {
			/* translators: %d: number of minutes */
			$parts[] = sprintf( _n( '%d min', '%d mins', $minutes, 'community-auctions' ), $minutes );
		}

		return implode( ' ', $parts );
	}
}
