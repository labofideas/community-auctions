<?php
/**
 * Bid Confirmation - Handles bid confirmation modal.
 *
 * @package Community_Auctions
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Community_Auctions_Bid_Confirmation
 *
 * Handles accessible bid confirmation modal.
 */
class Community_Auctions_Bid_Confirmation {

	/**
	 * Register hooks.
	 */
	public static function register() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
		add_action( 'wp_footer', array( __CLASS__, 'render_modal_template' ) );
	}

	/**
	 * Register frontend assets.
	 */
	public static function register_assets() {
		$js_url  = plugin_dir_url( __DIR__ ) . 'assets/js/modal.js';
		$css_url = plugin_dir_url( __DIR__ ) . 'assets/css/modal.css';

		wp_register_script(
			'community-auctions-modal',
			$js_url,
			array(),
			Community_Auctions_Plugin::VERSION,
			true
		);

		wp_register_style(
			'community-auctions-modal',
			$css_url,
			array(),
			Community_Auctions_Plugin::VERSION
		);

		wp_localize_script(
			'community-auctions-modal',
			'CommunityAuctionsModal',
			array(
				'i18n' => array(
					'confirm'       => __( 'Confirm Bid', 'community-auctions' ),
					'cancel'        => __( 'Cancel', 'community-auctions' ),
					'bidAmount'     => __( 'Bid Amount:', 'community-auctions' ),
					'auctionTitle'  => __( 'Auction:', 'community-auctions' ),
					'currentHighest'=> __( 'Current Highest:', 'community-auctions' ),
					'proxyMax'      => __( 'Proxy Max:', 'community-auctions' ),
					'confirmPrompt' => __( 'Please confirm your bid', 'community-auctions' ),
					'processing'    => __( 'Processing...', 'community-auctions' ),
				),
			)
		);
	}

	/**
	 * Enqueue modal assets.
	 */
	public static function enqueue_assets() {
		wp_enqueue_script( 'community-auctions-modal' );
		wp_enqueue_style( 'community-auctions-modal' );
	}

	/**
	 * Render modal template in footer.
	 */
	public static function render_modal_template() {
		// Only render if we have auction content.
		if ( ! is_singular( 'auction' ) && ! has_shortcode( get_post_field( 'post_content', get_the_ID() ), 'community_auction_single' ) ) {
			// Check if we're on a page with auction shortcodes.
			global $post;
			if ( ! $post || ( strpos( $post->post_content, '[community_auction' ) === false ) ) {
				return;
			}
		}

		self::enqueue_assets();
		?>
		<div
			id="ca-bid-modal"
			class="ca-modal"
			role="dialog"
			aria-modal="true"
			aria-labelledby="ca-modal-title"
			aria-describedby="ca-modal-description"
			aria-hidden="true"
		>
			<div class="ca-modal-overlay" data-ca-modal-close></div>
			<div class="ca-modal-content">
				<div class="ca-modal-header">
					<h3 id="ca-modal-title"><?php esc_html_e( 'Confirm Your Bid', 'community-auctions' ); ?></h3>
					<button
						type="button"
						class="ca-modal-close"
						data-ca-modal-close
						aria-label="<?php esc_attr_e( 'Close', 'community-auctions' ); ?>"
					>
						&times;
					</button>
				</div>

				<div class="ca-modal-body" id="ca-modal-description">
					<p class="ca-modal-prompt"><?php esc_html_e( 'Please review your bid details before confirming.', 'community-auctions' ); ?></p>

					<dl class="ca-modal-details">
						<div class="ca-modal-detail">
							<dt><?php esc_html_e( 'Auction', 'community-auctions' ); ?></dt>
							<dd id="ca-modal-auction-title">-</dd>
						</div>
						<div class="ca-modal-detail">
							<dt><?php esc_html_e( 'Current Highest Bid', 'community-auctions' ); ?></dt>
							<dd id="ca-modal-current-bid">-</dd>
						</div>
						<div class="ca-modal-detail ca-modal-detail--highlight">
							<dt><?php esc_html_e( 'Your Bid', 'community-auctions' ); ?></dt>
							<dd id="ca-modal-bid-amount">-</dd>
						</div>
						<div class="ca-modal-detail ca-modal-detail--proxy" style="display: none;">
							<dt><?php esc_html_e( 'Proxy Max', 'community-auctions' ); ?></dt>
							<dd id="ca-modal-proxy-max">-</dd>
						</div>
					</dl>

					<p class="ca-modal-notice">
						<?php esc_html_e( 'By confirming, you agree to pay the winning amount if your bid is successful.', 'community-auctions' ); ?>
					</p>
				</div>

				<div class="ca-modal-footer">
					<button
						type="button"
						class="ca-modal-btn ca-modal-btn--cancel"
						data-ca-modal-close
					>
						<?php esc_html_e( 'Cancel', 'community-auctions' ); ?>
					</button>
					<button
						type="button"
						class="ca-modal-btn ca-modal-btn--confirm"
						id="ca-modal-confirm"
					>
						<?php esc_html_e( 'Confirm Bid', 'community-auctions' ); ?>
					</button>
				</div>

				<div class="ca-modal-loading" style="display: none;">
					<span class="ca-modal-spinner"></span>
					<span><?php esc_html_e( 'Processing...', 'community-auctions' ); ?></span>
				</div>
			</div>
		</div>
		<?php
	}
}
