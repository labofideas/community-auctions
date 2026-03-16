<?php
/**
 * Seller notification for sold auction email template.
 *
 * @package CommunityAuctions
 *
 * Available variables:
 * @var string $user_name     Seller's display name.
 * @var string $auction_title Auction title.
 * @var string $auction_url   Auction URL.
 * @var string $final_bid     Final bid formatted.
 * @var string $winner_name   Winner's display name.
 * @var string $winner_email  Winner's email address.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<h2><?php esc_html_e( 'Your Auction Has Sold!', 'community-auctions' ); ?></h2>

<p><?php printf( esc_html__( 'Hi %s,', 'community-auctions' ), esc_html( $user_name ) ); ?></p>

<p><?php esc_html_e( 'Great news! Your auction has ended and you have a winner:', 'community-auctions' ); ?></p>

<div class="highlight-box">
	<div class="label"><?php esc_html_e( 'Sold For', 'community-auctions' ); ?></div>
	<div class="value"><?php echo esc_html( $final_bid ); ?></div>
</div>

<table class="info-table">
	<tr>
		<td><?php esc_html_e( 'Auction', 'community-auctions' ); ?></td>
		<td><?php echo esc_html( $auction_title ); ?></td>
	</tr>
	<tr>
		<td><?php esc_html_e( 'Final Price', 'community-auctions' ); ?></td>
		<td><?php echo esc_html( $final_bid ); ?></td>
	</tr>
	<tr>
		<td><?php esc_html_e( 'Winner', 'community-auctions' ); ?></td>
		<td><?php echo esc_html( $winner_name ); ?></td>
	</tr>
	<?php if ( ! empty( $winner_email ) ) : ?>
	<tr>
		<td><?php esc_html_e( 'Contact', 'community-auctions' ); ?></td>
		<td><a href="mailto:<?php echo esc_attr( $winner_email ); ?>"><?php echo esc_html( $winner_email ); ?></a></td>
	</tr>
	<?php endif; ?>
</table>

<p><?php esc_html_e( 'The buyer has been notified and will be completing payment shortly. You can view the full buyer contact information on your seller dashboard.', 'community-auctions' ); ?></p>

<p style="text-align: center;">
	<a href="<?php echo esc_url( $auction_url ); ?>" class="btn btn-secondary"><?php esc_html_e( 'View Auction', 'community-auctions' ); ?></a>
</p>

<p style="font-size: 14px; color: #666666;">
	<?php esc_html_e( 'Please prepare the item for shipping once payment is confirmed.', 'community-auctions' ); ?>
</p>
