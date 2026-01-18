<?php
/**
 * Payment reminder email template.
 *
 * @package CommunityAuctions
 *
 * Available variables:
 * @var string $user_name     User's display name.
 * @var string $auction_title Auction title.
 * @var string $auction_url   Auction URL.
 * @var string $final_bid     Final bid formatted.
 * @var string $payment_url   Payment URL.
 * @var string $days_overdue  Days since auction ended.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<h2><?php esc_html_e( 'Payment Reminder', 'community-auctions' ); ?></h2>

<p><?php printf( esc_html__( 'Hi %s,', 'community-auctions' ), esc_html( $user_name ) ); ?></p>

<p><?php esc_html_e( 'This is a friendly reminder that your payment is still pending for the following auction:', 'community-auctions' ); ?></p>

<div class="highlight-box">
	<div class="label"><?php esc_html_e( 'Amount Due', 'community-auctions' ); ?></div>
	<div class="value"><?php echo esc_html( $final_bid ); ?></div>
</div>

<table class="info-table">
	<tr>
		<td><?php esc_html_e( 'Auction', 'community-auctions' ); ?></td>
		<td><?php echo esc_html( $auction_title ); ?></td>
	</tr>
</table>

<?php if ( ! empty( $payment_url ) ) : ?>
	<p style="text-align: center;">
		<a href="<?php echo esc_url( $payment_url ); ?>" class="btn"><?php esc_html_e( 'Complete Payment', 'community-auctions' ); ?></a>
	</p>
<?php else : ?>
	<p style="text-align: center;">
		<a href="<?php echo esc_url( $auction_url ); ?>" class="btn btn-secondary"><?php esc_html_e( 'View Auction', 'community-auctions' ); ?></a>
	</p>
<?php endif; ?>

<p style="font-size: 14px; color: #666666;">
	<?php esc_html_e( 'Please complete your payment as soon as possible to secure your item. If you have any questions, please contact the seller.', 'community-auctions' ); ?>
</p>
