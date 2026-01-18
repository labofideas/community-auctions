<?php
/**
 * Outbid notification email template.
 *
 * @package CommunityAuctions
 *
 * Available variables:
 * @var string $user_name     User's display name.
 * @var string $auction_title Auction title.
 * @var string $auction_url   Auction URL.
 * @var string $current_bid   Current bid formatted.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<h2><?php esc_html_e( 'You\'ve Been Outbid!', 'community-auctions' ); ?></h2>

<p><?php printf( esc_html__( 'Hi %s,', 'community-auctions' ), esc_html( $user_name ) ); ?></p>

<p><?php printf( esc_html__( 'Someone has placed a higher bid on the auction you were winning:', 'community-auctions' ) ); ?></p>

<div class="highlight-box">
	<div class="label"><?php esc_html_e( 'Current Highest Bid', 'community-auctions' ); ?></div>
	<div class="value"><?php echo esc_html( $current_bid ); ?></div>
</div>

<table class="info-table">
	<tr>
		<td><?php esc_html_e( 'Auction', 'community-auctions' ); ?></td>
		<td><?php echo esc_html( $auction_title ); ?></td>
	</tr>
</table>

<p><?php esc_html_e( 'Don\'t lose out! Place a higher bid now to stay in the lead.', 'community-auctions' ); ?></p>

<p style="text-align: center;">
	<a href="<?php echo esc_url( $auction_url ); ?>" class="btn"><?php esc_html_e( 'Place a New Bid', 'community-auctions' ); ?></a>
</p>

<p style="font-size: 14px; color: #666666;">
	<?php esc_html_e( 'Tip: Consider using proxy bidding to automatically bid up to your maximum amount.', 'community-auctions' ); ?>
</p>
