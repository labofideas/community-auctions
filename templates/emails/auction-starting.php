<?php
/**
 * Auction starting soon email template.
 *
 * @package CommunityAuctions
 *
 * Available variables:
 * @var string $user_name     User's display name.
 * @var string $auction_title Auction title.
 * @var string $auction_url   Auction URL.
 * @var string $start_price   Starting price formatted.
 * @var string $starts_at     Start time formatted.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<h2><?php esc_html_e( 'Auction Starting Soon!', 'community-auctions' ); ?></h2>

<p><?php printf( esc_html__( 'Hi %s,', 'community-auctions' ), esc_html( $user_name ) ); ?></p>

<p><?php esc_html_e( 'An auction you\'re watching is about to start:', 'community-auctions' ); ?></p>

<table class="info-table">
	<tr>
		<td><?php esc_html_e( 'Auction', 'community-auctions' ); ?></td>
		<td><?php echo esc_html( $auction_title ); ?></td>
	</tr>
	<?php if ( ! empty( $starts_at ) ) : ?>
	<tr>
		<td><?php esc_html_e( 'Starts At', 'community-auctions' ); ?></td>
		<td style="color: #2f6f44; font-weight: bold;"><?php echo esc_html( $starts_at ); ?></td>
	</tr>
	<?php endif; ?>
	<?php if ( ! empty( $start_price ) ) : ?>
	<tr>
		<td><?php esc_html_e( 'Starting Price', 'community-auctions' ); ?></td>
		<td><?php echo esc_html( $start_price ); ?></td>
	</tr>
	<?php endif; ?>
</table>

<p><?php esc_html_e( 'Be ready to place your bid when the auction goes live!', 'community-auctions' ); ?></p>

<p style="text-align: center;">
	<a href="<?php echo esc_url( $auction_url ); ?>" class="btn btn-secondary"><?php esc_html_e( 'View Auction', 'community-auctions' ); ?></a>
</p>
