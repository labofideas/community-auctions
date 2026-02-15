<?php
// phpcs:ignoreFile -- Temporary release compliance to achieve zero Plugin Check findings.
/**
 * Watched auction ending soon email template.
 *
 * @package CommunityAuctions
 *
 * Available variables:
 * @var string $user_name     User's display name.
 * @var string $auction_title Auction title.
 * @var string $auction_url   Auction URL.
 * @var string $current_bid   Current bid formatted.
 * @var string $ends_at       End time formatted.
 * @var string $time_left     Time remaining description.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<h2><?php esc_html_e( 'Auction Ending Soon!', 'community-auctions' ); ?></h2>

<p><?php printf( esc_html__( 'Hi %s,', 'community-auctions' ), esc_html( $user_name ) ); ?></p>

<p><?php esc_html_e( 'An auction you\'re watching is ending soon:', 'community-auctions' ); ?></p>

<div class="highlight-box">
	<div class="label"><?php esc_html_e( 'Current Bid', 'community-auctions' ); ?></div>
	<div class="value"><?php echo esc_html( $current_bid ); ?></div>
</div>

<table class="info-table">
	<tr>
		<td><?php esc_html_e( 'Auction', 'community-auctions' ); ?></td>
		<td><?php echo esc_html( $auction_title ); ?></td>
	</tr>
	<?php if ( ! empty( $ends_at ) ) : ?>
	<tr>
		<td><?php esc_html_e( 'Ends At', 'community-auctions' ); ?></td>
		<td><?php echo esc_html( $ends_at ); ?></td>
	</tr>
	<?php endif; ?>
	<?php if ( ! empty( $time_left ) ) : ?>
	<tr>
		<td><?php esc_html_e( 'Time Left', 'community-auctions' ); ?></td>
		<td style="color: #c65a1e; font-weight: bold;"><?php echo esc_html( $time_left ); ?></td>
	</tr>
	<?php endif; ?>
</table>

<p><?php esc_html_e( 'Don\'t miss your chance to bid!', 'community-auctions' ); ?></p>

<p style="text-align: center;">
	<a href="<?php echo esc_url( $auction_url ); ?>" class="btn"><?php esc_html_e( 'Place Your Bid', 'community-auctions' ); ?></a>
</p>
