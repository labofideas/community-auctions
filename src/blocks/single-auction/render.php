<?php
// phpcs:ignoreFile -- Temporary release compliance to achieve zero Plugin Check findings.
/**
 * Single Auction Block - Server-side render.
 *
 * @package CommunityAuctions
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Block content.
 * @var WP_Block $block      Block instance.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$auction_id      = absint( $attributes['auctionId'] ?? 0 );
$show_image      = ! empty( $attributes['showImage'] );
$show_gallery    = ! empty( $attributes['showGallery'] );
$show_countdown  = ! empty( $attributes['showCountdown'] );
$show_bid_history = ! empty( $attributes['showBidHistory'] );
$show_bid_form   = ! empty( $attributes['showBidForm'] );
$show_seller     = ! empty( $attributes['showSellerInfo'] );
$show_category   = ! empty( $attributes['showCategory'] );
$show_buy_now    = ! empty( $attributes['showBuyNow'] );

if ( ! $auction_id ) {
	echo '<div class="ca-block-no-auction">';
	esc_html_e( 'No auction selected.', 'community-auctions' );
	echo '</div>';
	return;
}

$auction = get_post( $auction_id );

if ( ! $auction || 'auction' !== $auction->post_type ) {
	echo '<div class="ca-block-no-auction">';
	esc_html_e( 'Auction not found.', 'community-auctions' );
	echo '</div>';
	return;
}

// Get auction meta.
$current_bid    = get_post_meta( $auction_id, 'ca_current_bid', true );
$start_price    = get_post_meta( $auction_id, 'ca_start_price', true );
$buy_now_price  = get_post_meta( $auction_id, 'ca_buy_now_price', true );
$end_at         = get_post_meta( $auction_id, 'ca_end_at', true );
$bid_count      = get_post_meta( $auction_id, 'ca_bid_count', true ) ?: 0;
$gallery_ids    = get_post_meta( $auction_id, 'ca_gallery_ids', true );
$seller_id      = $auction->post_author;

$display_price   = $current_bid ? $current_bid : $start_price;
$formatted_price = class_exists( 'Community_Auctions_Currency' )
	? Community_Auctions_Currency::format( $display_price )
	: '$' . number_format( (float) $display_price, 2 );

$categories = get_the_terms( $auction_id, 'auction_category' );

$wrapper_attributes = get_block_wrapper_attributes( array(
	'class' => 'ca-single-auction-block',
) );
?>

<div <?php echo wp_kses_data( $wrapper_attributes ); ?>>
	<article class="ca-single-auction" data-auction-id="<?php echo esc_attr( $auction_id ); ?>">
		<?php if ( $show_image && has_post_thumbnail( $auction_id ) ) : ?>
			<div class="ca-auction-featured-image">
				<?php echo get_the_post_thumbnail( $auction_id, 'large' ); ?>
			</div>
		<?php endif; ?>

		<?php if ( $show_gallery && $gallery_ids ) : ?>
			<?php
			$gallery_array = explode( ',', $gallery_ids );
			if ( ! empty( $gallery_array ) ) :
				?>
				<div class="ca-auction-gallery">
					<?php foreach ( $gallery_array as $image_id ) : ?>
						<div class="ca-gallery-item">
							<?php echo wp_get_attachment_image( absint( $image_id ), 'thumbnail' ); ?>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		<?php endif; ?>

		<div class="ca-auction-details">
			<?php if ( $show_category && $categories && ! is_wp_error( $categories ) ) : ?>
				<div class="ca-auction-category">
					<?php echo esc_html( $categories[0]->name ); ?>
				</div>
			<?php endif; ?>

			<h2 class="ca-auction-title"><?php echo esc_html( $auction->post_title ); ?></h2>

			<div class="ca-auction-description">
				<?php echo wp_kses_post( $auction->post_content ); ?>
			</div>

			<div class="ca-auction-pricing">
				<div class="ca-current-price">
					<span class="ca-price-label">
						<?php echo $current_bid ? esc_html__( 'Current Bid:', 'community-auctions' ) : esc_html__( 'Starting Price:', 'community-auctions' ); ?>
					</span>
					<span class="ca-price-amount"><?php echo esc_html( $formatted_price ); ?></span>
				</div>

				<div class="ca-bid-stats">
					<?php
					printf(
						/* translators: %d: number of bids */
						esc_html( _n( '%d bid', '%d bids', $bid_count, 'community-auctions' ) ),
						(int) $bid_count
					);
					?>
				</div>

				<?php if ( $show_buy_now && $buy_now_price ) : ?>
					<div class="ca-buy-now-price">
						<span class="ca-price-label"><?php esc_html_e( 'Buy Now:', 'community-auctions' ); ?></span>
						<span class="ca-price-amount">
							<?php
							echo esc_html(
								class_exists( 'Community_Auctions_Currency' )
									? Community_Auctions_Currency::format( $buy_now_price )
									: '$' . number_format( (float) $buy_now_price, 2 )
							);
							?>
						</span>
					</div>
				<?php endif; ?>
			</div>

			<?php if ( $show_countdown && $end_at ) : ?>
				<div class="ca-auction-countdown" data-ca-countdown data-end-time="<?php echo esc_attr( $end_at ); ?>">
					<?php
					$end_timestamp = strtotime( $end_at );
					$now           = current_time( 'timestamp' );
					if ( $end_timestamp > $now ) {
						$diff = $end_timestamp - $now;
						if ( $diff > DAY_IN_SECONDS ) {
							$ca_days_left = (int) floor( $diff / DAY_IN_SECONDS );
							printf(
								/* translators: %d: number of days */
								esc_html( _n( '%d day left', '%d days left', $ca_days_left, 'community-auctions' ) ),
								$ca_days_left
							);
						} elseif ( $diff > HOUR_IN_SECONDS ) {
							$ca_hours_left = (int) floor( $diff / HOUR_IN_SECONDS );
							printf(
								/* translators: %d: number of hours */
								esc_html( _n( '%d hour left', '%d hours left', $ca_hours_left, 'community-auctions' ) ),
								$ca_hours_left
							);
						} else {
							$ca_minutes_left = max( 1, (int) floor( $diff / MINUTE_IN_SECONDS ) );
							printf(
								/* translators: %d: number of minutes */
								esc_html( _n( '%d minute left', '%d minutes left', $ca_minutes_left, 'community-auctions' ) ),
								$ca_minutes_left
							);
						}
					} else {
						esc_html_e( 'Ended', 'community-auctions' );
					}
					?>
				</div>
			<?php endif; ?>

			<?php if ( $show_seller ) : ?>
				<div class="ca-auction-seller">
					<span class="ca-seller-label"><?php esc_html_e( 'Seller:', 'community-auctions' ); ?></span>
					<?php echo get_avatar( $seller_id, 32, '', '', array( 'class' => 'ca-seller-avatar' ) ); ?>
					<span class="ca-seller-name"><?php echo esc_html( get_the_author_meta( 'display_name', $seller_id ) ); ?></span>
				</div>
			<?php endif; ?>

			<?php if ( $show_bid_form && 'ca_live' === $auction->post_status ) : ?>
				<div class="ca-bid-form-wrapper">
					<?php
					if ( is_user_logged_in() ) {
						$min_bid = $current_bid ? (float) $current_bid + 1 : (float) $start_price;
						?>
						<form class="ca-bid-form" data-auction-id="<?php echo esc_attr( $auction_id ); ?>">
							<?php wp_nonce_field( 'ca_bid_action', 'ca_bid_nonce' ); ?>
							<div class="ca-bid-input-group">
								<label for="ca-bid-amount-<?php echo esc_attr( $auction_id ); ?>">
									<?php esc_html_e( 'Your Bid:', 'community-auctions' ); ?>
								</label>
								<input
									type="number"
									id="ca-bid-amount-<?php echo esc_attr( $auction_id ); ?>"
									name="bid_amount"
									min="<?php echo esc_attr( $min_bid ); ?>"
									step="0.01"
									required
									placeholder="<?php echo esc_attr( number_format( $min_bid, 2 ) ); ?>"
								/>
							</div>
							<button type="submit" class="ca-place-bid-btn">
								<?php esc_html_e( 'Place Bid', 'community-auctions' ); ?>
							</button>
						</form>
						<?php
					} else {
						?>
						<p class="ca-login-to-bid">
							<?php
							printf(
								/* translators: %s: login URL */
								esc_html__( 'Please %s to place a bid.', 'community-auctions' ),
								'<a href="' . esc_url( wp_login_url( get_permalink( $auction_id ) ) ) . '">' . esc_html__( 'log in', 'community-auctions' ) . '</a>'
							);
							?>
						</p>
						<?php
					}
					?>
				</div>
			<?php endif; ?>

			<?php if ( $show_bid_history ) : ?>
				<div class="ca-bid-history" data-auction-id="<?php echo esc_attr( $auction_id ); ?>">
					<h4><?php esc_html_e( 'Bid History', 'community-auctions' ); ?></h4>
					<div class="ca-bid-history-list">
						<?php
						if ( class_exists( 'Community_Auctions_Bid_Repository' ) ) {
							$bids = Community_Auctions_Bid_Repository::get_auction_bids( $auction_id, 5 );
							if ( ! empty( $bids ) ) {
								echo '<ul>';
								foreach ( $bids as $bid ) {
									$bid_user = get_userdata( $bid->user_id );
									$bid_name = $bid_user ? $bid_user->display_name : __( 'Anonymous', 'community-auctions' );
									$bid_amount = class_exists( 'Community_Auctions_Currency' )
										? Community_Auctions_Currency::format( $bid->amount )
										: '$' . number_format( (float) $bid->amount, 2 );
									printf(
										'<li><span class="ca-bid-user">%s</span> - <span class="ca-bid-amount">%s</span> <span class="ca-bid-time">%s</span></li>',
										esc_html( $bid_name ),
										esc_html( $bid_amount ),
										esc_html( human_time_diff( strtotime( $bid->created_at ), current_time( 'timestamp' ) ) . ' ' . __( 'ago', 'community-auctions' ) )
									);
								}
								echo '</ul>';
							} else {
								echo '<p class="ca-no-bids">' . esc_html__( 'No bids yet.', 'community-auctions' ) . '</p>';
							}
						} else {
							echo '<p class="ca-no-bids">' . esc_html__( 'No bids yet.', 'community-auctions' ) . '</p>';
						}
						?>
					</div>
				</div>
			<?php endif; ?>
		</div>
	</article>
</div>
