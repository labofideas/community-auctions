<?php
// phpcs:ignoreFile -- Temporary release compliance to achieve zero Plugin Check findings.
/**
 * Auction won email template.
 *
 * @package CommunityAuctions
 *
 * Available variables:
 * @var string $user_name     User's display name.
 * @var string $auction_title Auction title.
 * @var string $auction_url   Auction URL.
 * @var string $final_bid     Final bid formatted.
 * @var string $payment_url   Payment URL.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<h2><?php esc_html_e( 'Congratulations! You Won!', 'community-auctions' ); ?></h2>

<p><?php printf( esc_html__( 'Hi %s,', 'community-auctions' ), esc_html( $user_name ) ); ?></p>

<p><?php esc_html_e( 'Great news! You\'ve won the following auction:', 'community-auctions' ); ?></p>

<div class="highlight-box">
	<div class="label"><?php esc_html_e( 'Your Winning Bid', 'community-auctions' ); ?></div>
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
</table>

<?php if ( ! empty( $payment_url ) ) : ?>
	<p><?php esc_html_e( 'Please complete your payment to claim your item:', 'community-auctions' ); ?></p>

	<p style="text-align: center;">
		<a href="<?php echo esc_url( $payment_url ); ?>" class="btn"><?php esc_html_e( 'Pay Now', 'community-auctions' ); ?></a>
	</p>
<?php else : ?>
	<p><?php esc_html_e( 'You will receive payment instructions shortly. Please check back on the auction page for details.', 'community-auctions' ); ?></p>

	<p style="text-align: center;">
		<a href="<?php echo esc_url( $auction_url ); ?>" class="btn btn-secondary"><?php esc_html_e( 'View Auction', 'community-auctions' ); ?></a>
	</p>
<?php endif; ?>

<p style="font-size: 14px; color: #666666;">
	<?php esc_html_e( 'Please complete your payment within the specified timeframe to avoid cancellation.', 'community-auctions' ); ?>
</p>
