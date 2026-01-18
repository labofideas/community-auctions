<?php
/**
 * Email Templates - HTML email system with template support.
 *
 * @package CommunityAuctions
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles email template rendering and sending.
 */
class Community_Auctions_Email_Templates {

	/**
	 * Email types and their configurations.
	 *
	 * @var array
	 */
	private static $email_types = array(
		'outbid'          => array(
			'label'    => 'Outbid Notification',
			'template' => 'outbid.php',
			'subject'  => 'You\'ve been outbid on {auction_title}',
		),
		'won'             => array(
			'label'    => 'Auction Won',
			'template' => 'won.php',
			'subject'  => 'Congratulations! You won {auction_title}',
		),
		'payment_reminder' => array(
			'label'    => 'Payment Reminder',
			'template' => 'payment-reminder.php',
			'subject'  => 'Payment reminder for {auction_title}',
		),
		'watched_ending'  => array(
			'label'    => 'Watched Auction Ending',
			'template' => 'watched-ending.php',
			'subject'  => '{auction_title} is ending soon!',
		),
		'auction_starting' => array(
			'label'    => 'Auction Starting',
			'template' => 'auction-starting.php',
			'subject'  => '{auction_title} is starting soon!',
		),
		'seller_sold'     => array(
			'label'    => 'Auction Sold (Seller)',
			'template' => 'seller-sold.php',
			'subject'  => 'Your auction {auction_title} has sold!',
		),
	);

	/**
	 * Register hooks and settings.
	 */
	public static function register() {
		add_filter( 'community_auctions/settings_fields', array( __CLASS__, 'add_settings_fields' ) );
		add_filter( 'community_auctions/settings_defaults', array( __CLASS__, 'add_settings_defaults' ) );

		// Hook into auction events.
		add_action( 'community_auctions/bid_placed', array( __CLASS__, 'handle_outbid_notification' ), 10, 3 );
		add_action( 'community_auctions/auction_ended', array( __CLASS__, 'handle_auction_ended' ), 10, 2 );
	}

	/**
	 * Add email settings fields.
	 *
	 * @param array $fields Settings fields.
	 * @return array Modified fields.
	 */
	public static function add_settings_fields( $fields ) {
		$fields['email_section'] = array(
			'title'    => __( 'Email Settings', 'community-auctions' ),
			'callback' => array( __CLASS__, 'render_section_description' ),
			'fields'   => array(
				'email_from_name' => array(
					'title'    => __( 'From Name', 'community-auctions' ),
					'callback' => array( __CLASS__, 'render_from_name_field' ),
				),
				'email_from_address' => array(
					'title'    => __( 'From Email', 'community-auctions' ),
					'callback' => array( __CLASS__, 'render_from_address_field' ),
				),
				'email_header_image' => array(
					'title'    => __( 'Header Image', 'community-auctions' ),
					'callback' => array( __CLASS__, 'render_header_image_field' ),
				),
				'email_footer_text' => array(
					'title'    => __( 'Footer Text', 'community-auctions' ),
					'callback' => array( __CLASS__, 'render_footer_text_field' ),
				),
			),
		);

		return $fields;
	}

	/**
	 * Add email settings defaults.
	 *
	 * @param array $defaults Settings defaults.
	 * @return array Modified defaults.
	 */
	public static function add_settings_defaults( $defaults ) {
		$defaults['email_from_name']    = get_bloginfo( 'name' );
		$defaults['email_from_address'] = get_option( 'admin_email' );
		$defaults['email_header_image'] = '';
		$defaults['email_footer_text']  = sprintf(
			/* translators: %s: site name */
			__( 'This email was sent by %s.', 'community-auctions' ),
			get_bloginfo( 'name' )
		);

		return $defaults;
	}

	/**
	 * Render section description.
	 */
	public static function render_section_description() {
		echo '<p>' . esc_html__( 'Configure email notification settings and appearance.', 'community-auctions' ) . '</p>';
	}

	/**
	 * Render from name field.
	 */
	public static function render_from_name_field() {
		$settings = Community_Auctions_Settings::get_settings();
		$value    = $settings['email_from_name'] ?? get_bloginfo( 'name' );
		?>
		<input type="text" name="community_auctions_settings[email_from_name]" id="email_from_name" value="<?php echo esc_attr( $value ); ?>" class="regular-text" />
		<?php
	}

	/**
	 * Render from address field.
	 */
	public static function render_from_address_field() {
		$settings = Community_Auctions_Settings::get_settings();
		$value    = $settings['email_from_address'] ?? get_option( 'admin_email' );
		?>
		<input type="email" name="community_auctions_settings[email_from_address]" id="email_from_address" value="<?php echo esc_attr( $value ); ?>" class="regular-text" />
		<?php
	}

	/**
	 * Render header image field.
	 */
	public static function render_header_image_field() {
		$settings = Community_Auctions_Settings::get_settings();
		$value    = $settings['email_header_image'] ?? '';
		?>
		<input type="url" name="community_auctions_settings[email_header_image]" id="email_header_image" value="<?php echo esc_url( $value ); ?>" class="regular-text" />
		<p class="description"><?php esc_html_e( 'URL to an image to display in email headers.', 'community-auctions' ); ?></p>
		<?php
	}

	/**
	 * Render footer text field.
	 */
	public static function render_footer_text_field() {
		$settings = Community_Auctions_Settings::get_settings();
		$value    = $settings['email_footer_text'] ?? '';
		?>
		<textarea name="community_auctions_settings[email_footer_text]" id="email_footer_text" rows="3" class="large-text"><?php echo esc_textarea( $value ); ?></textarea>
		<?php
	}

	/**
	 * Send an email using a template.
	 *
	 * @param string $to         Recipient email.
	 * @param string $email_type Email type key.
	 * @param array  $data       Data for template placeholders.
	 * @return bool Whether email was sent.
	 */
	public static function send( $to, $email_type, $data = array() ) {
		if ( ! isset( self::$email_types[ $email_type ] ) ) {
			return false;
		}

		$config  = self::$email_types[ $email_type ];
		$subject = self::replace_placeholders( $config['subject'], $data );
		$content = self::render_template( $config['template'], $data );

		if ( empty( $content ) ) {
			return false;
		}

		$settings   = Community_Auctions_Settings::get_settings();
		$from_name  = $settings['email_from_name'] ?? get_bloginfo( 'name' );
		$from_email = $settings['email_from_address'] ?? get_option( 'admin_email' );

		$headers = array(
			'Content-Type: text/html; charset=UTF-8',
			sprintf( 'From: %s <%s>', $from_name, $from_email ),
		);

		return wp_mail( $to, $subject, $content, $headers );
	}

	/**
	 * Render an email template.
	 *
	 * @param string $template Template filename.
	 * @param array  $data     Data for template.
	 * @return string Rendered HTML.
	 */
	public static function render_template( $template, $data = array() ) {
		$settings = Community_Auctions_Settings::get_settings();

		// Add global template data.
		$data['site_name']     = get_bloginfo( 'name' );
		$data['site_url']      = home_url();
		$data['header_image']  = $settings['email_header_image'] ?? '';
		$data['footer_text']   = $settings['email_footer_text'] ?? '';
		$data['current_year']  = gmdate( 'Y' );

		// Look for template in theme first, then plugin.
		$template_path = self::locate_template( $template );

		if ( ! $template_path ) {
			return '';
		}

		// Get the base template.
		$base_path = self::locate_template( 'base.php' );

		// Render inner template.
		// Make template variables available in local scope.
		// phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- Required for email template variable scope.
		extract( $data, EXTR_SKIP );
		ob_start();
		include $template_path;
		$inner_content = ob_get_clean();

		// Wrap in base template.
		if ( $base_path && 'base.php' !== $template ) {
			$content = $inner_content;
			ob_start();
			include $base_path;
			return ob_get_clean();
		}

		return $inner_content;
	}

	/**
	 * Locate a template file.
	 *
	 * @param string $template Template filename.
	 * @return string|false Template path or false if not found.
	 */
	public static function locate_template( $template ) {
		// Check theme directory first.
		$theme_path = get_stylesheet_directory() . '/community-auctions/emails/' . $template;
		if ( file_exists( $theme_path ) ) {
			return $theme_path;
		}

		// Check parent theme.
		$parent_path = get_template_directory() . '/community-auctions/emails/' . $template;
		if ( file_exists( $parent_path ) ) {
			return $parent_path;
		}

		// Fall back to plugin templates.
		$plugin_path = plugin_dir_path( __DIR__ ) . 'templates/emails/' . $template;
		if ( file_exists( $plugin_path ) ) {
			return $plugin_path;
		}

		return false;
	}

	/**
	 * Replace placeholders in text.
	 *
	 * @param string $text Text with placeholders.
	 * @param array  $data Data for replacements.
	 * @return string Text with placeholders replaced.
	 */
	public static function replace_placeholders( $text, $data ) {
		foreach ( $data as $key => $value ) {
			if ( is_string( $value ) || is_numeric( $value ) ) {
				$text = str_replace( '{' . $key . '}', $value, $text );
			}
		}

		return $text;
	}

	/**
	 * Handle outbid notification.
	 *
	 * @param int   $auction_id Auction ID.
	 * @param int   $user_id    New bidder user ID.
	 * @param array $bid_data   Bid data.
	 */
	public static function handle_outbid_notification( $auction_id, $user_id, $bid_data ) {
		// Get previous highest bidder.
		$previous_bidder_id = isset( $bid_data['previous_bidder'] ) ? absint( $bid_data['previous_bidder'] ) : 0;

		if ( ! $previous_bidder_id || $previous_bidder_id === $user_id ) {
			return;
		}

		$previous_bidder = get_userdata( $previous_bidder_id );
		if ( ! $previous_bidder || ! $previous_bidder->user_email ) {
			return;
		}

		$auction_title = get_the_title( $auction_id );
		$auction_url   = get_permalink( $auction_id );
		$current_bid   = get_post_meta( $auction_id, 'ca_current_bid', true );

		self::send(
			$previous_bidder->user_email,
			'outbid',
			array(
				'user_name'      => $previous_bidder->display_name,
				'auction_title'  => $auction_title,
				'auction_url'    => $auction_url,
				'current_bid'    => Community_Auctions_Currency::format( $current_bid, $auction_id ),
				'bid_amount_raw' => $current_bid,
			)
		);
	}

	/**
	 * Handle auction ended notifications.
	 *
	 * @param int $auction_id Auction ID.
	 * @param int $winner_id  Winner user ID.
	 */
	public static function handle_auction_ended( $auction_id, $winner_id ) {
		$auction_title = get_the_title( $auction_id );
		$auction_url   = get_permalink( $auction_id );
		$final_bid     = get_post_meta( $auction_id, 'ca_current_bid', true );
		$seller_id     = get_post_field( 'post_author', $auction_id );

		// Notify winner.
		if ( $winner_id ) {
			$winner = get_userdata( $winner_id );
			if ( $winner && $winner->user_email ) {
				$order_id = absint( get_post_meta( $auction_id, 'ca_order_id', true ) );
				$pay_url  = '';

				if ( $order_id ) {
					$settings = Community_Auctions_Settings::get_settings();
					$provider = $settings['payment_provider'] ?? '';
					$pay_url  = Community_Auctions_Payment_Status::get_payment_link( $order_id, $provider );
				}

				self::send(
					$winner->user_email,
					'won',
					array(
						'user_name'      => $winner->display_name,
						'auction_title'  => $auction_title,
						'auction_url'    => $auction_url,
						'final_bid'      => Community_Auctions_Currency::format( $final_bid, $auction_id ),
						'payment_url'    => $pay_url,
					)
				);
			}

			// Notify seller.
			$seller = get_userdata( $seller_id );
			if ( $seller && $seller->user_email && $seller_id !== $winner_id ) {
				$winner_user = get_userdata( $winner_id );

				self::send(
					$seller->user_email,
					'seller_sold',
					array(
						'user_name'     => $seller->display_name,
						'auction_title' => $auction_title,
						'auction_url'   => $auction_url,
						'final_bid'     => Community_Auctions_Currency::format( $final_bid, $auction_id ),
						'winner_name'   => $winner_user ? $winner_user->display_name : __( 'Unknown', 'community-auctions' ),
						'winner_email'  => $winner_user ? $winner_user->user_email : '',
					)
				);
			}
		}
	}

	/**
	 * Get available email types.
	 *
	 * @return array Email types.
	 */
	public static function get_email_types() {
		return self::$email_types;
	}
}
