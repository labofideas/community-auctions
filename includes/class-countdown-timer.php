<?php
/**
 * Countdown Timer - Handles auction countdown display.
 *
 * @package Community_Auctions
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Community_Auctions_Countdown_Timer
 *
 * Handles countdown timer display with urgency states.
 */
class Community_Auctions_Countdown_Timer {

	/**
	 * Register hooks.
	 */
	public static function register() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
		add_shortcode( 'community_auction_countdown', array( __CLASS__, 'render_shortcode' ) );
	}

	/**
	 * Register frontend assets.
	 */
	public static function register_assets() {
		$js_url = plugin_dir_url( __DIR__ ) . 'assets/js/countdown.js';

		wp_register_script(
			'community-auctions-countdown',
			$js_url,
			array(),
			Community_Auctions_Plugin::VERSION,
			true
		);

		wp_localize_script(
			'community-auctions-countdown',
			'CommunityAuctionsCountdown',
			array(
				'i18n' => array(
					'days'    => __( 'd', 'community-auctions' ),
					'hours'   => __( 'h', 'community-auctions' ),
					'minutes' => __( 'm', 'community-auctions' ),
					'seconds' => __( 's', 'community-auctions' ),
					'ended'   => __( 'Auction ended', 'community-auctions' ),
					'starts'  => __( 'Starts in', 'community-auctions' ),
				),
			)
		);
	}

	/**
	 * Enqueue countdown assets.
	 */
	public static function enqueue_assets() {
		wp_enqueue_script( 'community-auctions-countdown' );
	}

	/**
	 * Render countdown shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string HTML output.
	 */
	public static function render_shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'id'           => 0,
				'show_seconds' => '1',
				'show_labels'  => '1',
				'type'         => 'end', // 'end' or 'start'
			),
			$atts
		);

		$auction_id = absint( $atts['id'] );
		if ( ! $auction_id ) {
			$auction_id = get_the_ID();
		}

		if ( ! $auction_id ) {
			return '';
		}

		return self::render( $auction_id, $atts );
	}

	/**
	 * Render countdown timer.
	 *
	 * @param int   $auction_id Auction ID.
	 * @param array $args       Display arguments.
	 * @return string HTML output.
	 */
	public static function render( $auction_id, $args = array() ) {
		$defaults = array(
			'show_seconds' => '1',
			'show_labels'  => '1',
			'type'         => 'end',
		);

		$args = wp_parse_args( $args, $defaults );

		// Get the relevant timestamp.
		$meta_key = 'end' === $args['type'] ? 'ca_end_at' : 'ca_start_at';
		$datetime = get_post_meta( $auction_id, $meta_key, true );

		if ( empty( $datetime ) ) {
			return '';
		}

		$timestamp = strtotime( $datetime );
		if ( ! $timestamp ) {
			return '';
		}

		self::enqueue_assets();

		$now            = current_time( 'timestamp', true );
		$is_ended       = 'end' === $args['type'] && $timestamp <= $now;
		$is_started     = 'start' === $args['type'] && $timestamp <= $now;
		$diff           = max( 0, $timestamp - $now );
		$urgency_class  = self::get_urgency_class( $diff, $args['type'] );
		$initial_format = self::format_time( $diff, $args['show_seconds'] );

		ob_start();
		?>
		<div
			class="ca-countdown <?php echo esc_attr( $urgency_class ); ?>"
			data-ca-countdown
			data-end-time="<?php echo esc_attr( $timestamp ); ?>"
			data-type="<?php echo esc_attr( $args['type'] ); ?>"
			data-show-seconds="<?php echo esc_attr( $args['show_seconds'] ); ?>"
			data-show-labels="<?php echo esc_attr( $args['show_labels'] ); ?>"
			role="timer"
			aria-live="polite"
			aria-atomic="true"
		>
			<?php if ( $is_ended ) : ?>
				<span class="ca-countdown-ended"><?php esc_html_e( 'Auction ended', 'community-auctions' ); ?></span>
			<?php elseif ( $is_started ) : ?>
				<span class="ca-countdown-started"><?php esc_html_e( 'Auction is live', 'community-auctions' ); ?></span>
			<?php else : ?>
				<?php if ( 'start' === $args['type'] ) : ?>
					<span class="ca-countdown-label"><?php esc_html_e( 'Starts in', 'community-auctions' ); ?></span>
				<?php endif; ?>
				<span class="ca-countdown-time">
					<?php echo esc_html( $initial_format ); ?>
				</span>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Get urgency class based on remaining time.
	 *
	 * @param int    $seconds Seconds remaining.
	 * @param string $type    Timer type (end/start).
	 * @return string CSS class.
	 */
	private static function get_urgency_class( $seconds, $type = 'end' ) {
		if ( 'start' === $type ) {
			return 'ca-countdown--normal';
		}

		if ( $seconds <= 0 ) {
			return 'ca-countdown--ended';
		}

		if ( $seconds <= 300 ) { // 5 minutes.
			return 'ca-countdown--critical';
		}

		if ( $seconds <= 3600 ) { // 1 hour.
			return 'ca-countdown--urgent';
		}

		return 'ca-countdown--normal';
	}

	/**
	 * Format time for display.
	 *
	 * @param int    $seconds      Total seconds.
	 * @param string $show_seconds Whether to show seconds.
	 * @return string Formatted time string.
	 */
	public static function format_time( $seconds, $show_seconds = '1' ) {
		if ( $seconds <= 0 ) {
			return __( 'Auction ended', 'community-auctions' );
		}

		$days    = floor( $seconds / 86400 );
		$hours   = floor( ( $seconds % 86400 ) / 3600 );
		$minutes = floor( ( $seconds % 3600 ) / 60 );
		$secs    = $seconds % 60;

		$parts = array();

		if ( $days > 0 ) {
			$parts[] = sprintf( '%dd', $days );
		}

		if ( $hours > 0 || $days > 0 ) {
			$parts[] = sprintf( '%dh', $hours );
		}

		if ( $minutes > 0 || $hours > 0 || $days > 0 ) {
			$parts[] = sprintf( '%dm', $minutes );
		}

		if ( '1' === $show_seconds && $days === 0 ) {
			$parts[] = sprintf( '%ds', $secs );
		}

		return implode( ' ', $parts );
	}

	/**
	 * Render inline countdown for single auction.
	 *
	 * @param int    $auction_id Auction ID.
	 * @param string $type       Timer type (end/start).
	 * @return string HTML output.
	 */
	public static function render_inline( $auction_id, $type = 'end' ) {
		return self::render( $auction_id, array( 'type' => $type ) );
	}
}
