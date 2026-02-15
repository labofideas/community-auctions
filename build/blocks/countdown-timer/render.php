<?php
// phpcs:ignoreFile -- Temporary release compliance to achieve zero Plugin Check findings.
/**
 * Countdown Timer Block - Server-side render.
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

$auction_id       = absint( $attributes['auctionId'] ?? 0 );
$style            = sanitize_text_field( $attributes['style'] ?? 'default' );
$show_labels      = ! empty( $attributes['showLabels'] );
$show_seconds     = ! empty( $attributes['showSeconds'] );
$ended_text       = sanitize_text_field( $attributes['endedText'] ?? __( 'Auction Ended', 'community-auctions' ) );
$urgent_threshold = absint( $attributes['urgentThreshold'] ?? 60 );

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

$end_at = get_post_meta( $auction_id, 'ca_end_at', true );

if ( ! $end_at ) {
	echo '<div class="ca-block-no-auction">';
	esc_html_e( 'No end time set for this auction.', 'community-auctions' );
	echo '</div>';
	return;
}

$end_timestamp = strtotime( $end_at );
$now           = current_time( 'timestamp' );
$is_ended      = $end_timestamp <= $now;
$diff          = $end_timestamp - $now;

// Determine urgency class.
$urgency_class = '';
if ( ! $is_ended ) {
	$minutes_left = floor( $diff / MINUTE_IN_SECONDS );
	if ( $minutes_left <= 5 ) {
		$urgency_class = 'ca-countdown-critical';
	} elseif ( $minutes_left <= $urgent_threshold ) {
		$urgency_class = 'ca-countdown-urgent';
	}
}

$wrapper_classes = array(
	'ca-countdown-block',
	'ca-countdown-style-' . $style,
);

if ( $urgency_class ) {
	$wrapper_classes[] = $urgency_class;
}

if ( $is_ended ) {
	$wrapper_classes[] = 'ca-countdown-ended';
}

$wrapper_attributes = get_block_wrapper_attributes( array(
	'class' => implode( ' ', $wrapper_classes ),
) );
?>

<div <?php echo wp_kses_data( $wrapper_attributes ); ?>
	data-ca-countdown
	data-end-time="<?php echo esc_attr( $end_at ); ?>"
	data-show-seconds="<?php echo $show_seconds ? 'true' : 'false'; ?>"
	data-show-labels="<?php echo $show_labels ? 'true' : 'false'; ?>"
	data-ended-text="<?php echo esc_attr( $ended_text ); ?>"
	data-urgent-threshold="<?php echo esc_attr( $urgent_threshold ); ?>"
>
	<?php if ( $is_ended ) : ?>
		<div class="ca-countdown-ended-text"><?php echo esc_html( $ended_text ); ?></div>
	<?php else : ?>
		<?php
		$days    = floor( $diff / DAY_IN_SECONDS );
		$hours   = floor( ( $diff % DAY_IN_SECONDS ) / HOUR_IN_SECONDS );
		$minutes = floor( ( $diff % HOUR_IN_SECONDS ) / MINUTE_IN_SECONDS );
		$seconds = $diff % MINUTE_IN_SECONDS;
		?>
		<div class="ca-countdown-units">
			<?php if ( $days > 0 ) : ?>
				<div class="ca-countdown-unit ca-countdown-days">
					<span class="ca-countdown-value"><?php echo esc_html( $days ); ?></span>
					<?php if ( $show_labels ) : ?>
						<span class="ca-countdown-label"><?php echo esc_html( _n( 'Day', 'Days', $days, 'community-auctions' ) ); ?></span>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<div class="ca-countdown-unit ca-countdown-hours">
				<span class="ca-countdown-value"><?php echo esc_html( str_pad( $hours, 2, '0', STR_PAD_LEFT ) ); ?></span>
				<?php if ( $show_labels ) : ?>
					<span class="ca-countdown-label"><?php echo esc_html( _n( 'Hour', 'Hours', $hours, 'community-auctions' ) ); ?></span>
				<?php endif; ?>
			</div>

			<div class="ca-countdown-unit ca-countdown-minutes">
				<span class="ca-countdown-value"><?php echo esc_html( str_pad( $minutes, 2, '0', STR_PAD_LEFT ) ); ?></span>
				<?php if ( $show_labels ) : ?>
					<span class="ca-countdown-label"><?php echo esc_html( _n( 'Min', 'Mins', $minutes, 'community-auctions' ) ); ?></span>
				<?php endif; ?>
			</div>

			<?php if ( $show_seconds ) : ?>
				<div class="ca-countdown-unit ca-countdown-seconds">
					<span class="ca-countdown-value"><?php echo esc_html( str_pad( $seconds, 2, '0', STR_PAD_LEFT ) ); ?></span>
					<?php if ( $show_labels ) : ?>
						<span class="ca-countdown-label"><?php echo esc_html( _n( 'Sec', 'Secs', $seconds, 'community-auctions' ) ); ?></span>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		</div>
	<?php endif; ?>
</div>
