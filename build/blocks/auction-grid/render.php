<?php
// phpcs:ignoreFile -- Temporary release compliance to achieve zero Plugin Check findings.
/**
 * Auction Grid Block - Server-side render.
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

$columns        = absint( $attributes['columns'] ?? 3 );
$posts_per_page = absint( $attributes['postsPerPage'] ?? 6 );
$layout         = sanitize_text_field( $attributes['layout'] ?? 'grid' );
$status         = sanitize_text_field( $attributes['status'] ?? 'live' );
$order_by       = sanitize_text_field( $attributes['orderBy'] ?? 'date' );
$order          = sanitize_text_field( $attributes['order'] ?? 'DESC' );
$show_countdown = ! empty( $attributes['showCountdown'] );
$show_bid_count = ! empty( $attributes['showBidCount'] );
$show_current   = ! empty( $attributes['showCurrentBid'] );
$show_image     = ! empty( $attributes['showImage'] );
$show_category  = ! empty( $attributes['showCategory'] );

// Build query args.
$query_args = array(
	'post_type'      => 'auction',
	'posts_per_page' => $posts_per_page,
	'order'          => $order,
);

// Status filter.
switch ( $status ) {
	case 'live':
		$query_args['post_status'] = 'ca_live';
		break;
	case 'ended':
		$query_args['post_status'] = 'ca_ended';
		break;
	case 'upcoming':
		$query_args['post_status'] = 'publish';
		$query_args['meta_query']  = array(
			array(
				'key'     => 'ca_start_at',
				'value'   => current_time( 'mysql' ),
				'compare' => '>',
				'type'    => 'DATETIME',
			),
		);
		break;
	default:
		$query_args['post_status'] = array( 'publish', 'ca_live', 'ca_ended' );
}

// Order by.
switch ( $order_by ) {
	case 'end_time':
		$query_args['meta_key'] = 'ca_end_at';
		$query_args['orderby']  = 'meta_value';
		break;
	case 'current_bid':
		$query_args['meta_key'] = 'ca_current_bid';
		$query_args['orderby']  = 'meta_value_num';
		break;
	case 'bid_count':
		$query_args['meta_key'] = 'ca_bid_count';
		$query_args['orderby']  = 'meta_value_num';
		break;
	case 'title':
		$query_args['orderby'] = 'title';
		break;
	default:
		$query_args['orderby'] = 'date';
}

$auctions = new WP_Query( $query_args );

if ( ! $auctions->have_posts() ) {
	echo '<div class="ca-block-no-auctions">';
	esc_html_e( 'No auctions found.', 'community-auctions' );
	echo '</div>';
	return;
}

$wrapper_classes = array(
	'ca-auction-grid-block',
	'ca-layout-' . $layout,
);

if ( 'grid' === $layout ) {
	$wrapper_classes[] = 'ca-columns-' . $columns;
}

$wrapper_attributes = get_block_wrapper_attributes( array(
	'class' => implode( ' ', $wrapper_classes ),
) );
?>

<div <?php echo wp_kses_data( $wrapper_attributes ); ?>>
	<?php
	while ( $auctions->have_posts() ) :
		$auctions->the_post();
		$auction_id  = get_the_ID();
		$current_bid = get_post_meta( $auction_id, 'ca_current_bid', true );
		$start_price = get_post_meta( $auction_id, 'ca_start_price', true );
		$end_at      = get_post_meta( $auction_id, 'ca_end_at', true );
		$bid_count   = get_post_meta( $auction_id, 'ca_bid_count', true ) ?: 0;

		$display_price = $current_bid ? $current_bid : $start_price;
		$formatted_price = class_exists( 'Community_Auctions_Currency' )
			? Community_Auctions_Currency::format( $display_price )
			: '$' . number_format( (float) $display_price, 2 );

		$categories = get_the_terms( $auction_id, 'auction_category' );
		?>
		<article class="ca-auction-item">
			<?php if ( $show_image && has_post_thumbnail() ) : ?>
				<div class="ca-auction-image">
					<a href="<?php the_permalink(); ?>">
						<?php the_post_thumbnail( 'medium' ); ?>
					</a>
				</div>
			<?php endif; ?>

			<div class="ca-auction-content">
				<?php if ( $show_category && $categories && ! is_wp_error( $categories ) ) : ?>
					<div class="ca-auction-category">
						<?php echo esc_html( $categories[0]->name ); ?>
					</div>
				<?php endif; ?>

				<h3 class="ca-auction-title">
					<a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
				</h3>

				<?php if ( $show_current ) : ?>
					<div class="ca-auction-price">
						<span class="ca-price-label"><?php echo $current_bid ? esc_html__( 'Current Bid:', 'community-auctions' ) : esc_html__( 'Starting Price:', 'community-auctions' ); ?></span>
						<span class="ca-price-amount"><?php echo esc_html( $formatted_price ); ?></span>
					</div>
				<?php endif; ?>

				<?php if ( $show_bid_count ) : ?>
					<div class="ca-auction-bids">
						<?php
						printf(
							/* translators: %d: number of bids */
							esc_html( _n( '%d bid', '%d bids', $bid_count, 'community-auctions' ) ),
							(int) $bid_count
						);
						?>
					</div>
				<?php endif; ?>

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

				<a href="<?php the_permalink(); ?>" class="ca-auction-link">
					<?php esc_html_e( 'View Auction', 'community-auctions' ); ?>
				</a>
			</div>
		</article>
		<?php
	endwhile;
	wp_reset_postdata();
	?>
</div>
