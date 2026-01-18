<?php
/**
 * Currency Handling - Formatting and display.
 *
 * @package CommunityAuctions
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles currency formatting and display.
 */
class Community_Auctions_Currency {

	/**
	 * Supported currencies with their configurations.
	 *
	 * @var array
	 */
	private static $currencies = array(
		'USD' => array(
			'name'              => 'US Dollar',
			'symbol'            => '$',
			'symbol_position'   => 'before',
			'decimal_separator' => '.',
			'thousand_separator' => ',',
			'decimals'          => 2,
		),
		'EUR' => array(
			'name'              => 'Euro',
			'symbol'            => '€',
			'symbol_position'   => 'before',
			'decimal_separator' => ',',
			'thousand_separator' => '.',
			'decimals'          => 2,
		),
		'GBP' => array(
			'name'              => 'British Pound',
			'symbol'            => '£',
			'symbol_position'   => 'before',
			'decimal_separator' => '.',
			'thousand_separator' => ',',
			'decimals'          => 2,
		),
		'CAD' => array(
			'name'              => 'Canadian Dollar',
			'symbol'            => 'CA$',
			'symbol_position'   => 'before',
			'decimal_separator' => '.',
			'thousand_separator' => ',',
			'decimals'          => 2,
		),
		'AUD' => array(
			'name'              => 'Australian Dollar',
			'symbol'            => 'A$',
			'symbol_position'   => 'before',
			'decimal_separator' => '.',
			'thousand_separator' => ',',
			'decimals'          => 2,
		),
		'JPY' => array(
			'name'              => 'Japanese Yen',
			'symbol'            => '¥',
			'symbol_position'   => 'before',
			'decimal_separator' => '',
			'thousand_separator' => ',',
			'decimals'          => 0,
		),
		'INR' => array(
			'name'              => 'Indian Rupee',
			'symbol'            => '₹',
			'symbol_position'   => 'before',
			'decimal_separator' => '.',
			'thousand_separator' => ',',
			'decimals'          => 2,
		),
		'CHF' => array(
			'name'              => 'Swiss Franc',
			'symbol'            => 'CHF',
			'symbol_position'   => 'before',
			'decimal_separator' => '.',
			'thousand_separator' => "'",
			'decimals'          => 2,
		),
		'CNY' => array(
			'name'              => 'Chinese Yuan',
			'symbol'            => '¥',
			'symbol_position'   => 'before',
			'decimal_separator' => '.',
			'thousand_separator' => ',',
			'decimals'          => 2,
		),
		'MXN' => array(
			'name'              => 'Mexican Peso',
			'symbol'            => 'MX$',
			'symbol_position'   => 'before',
			'decimal_separator' => '.',
			'thousand_separator' => ',',
			'decimals'          => 2,
		),
		'BRL' => array(
			'name'              => 'Brazilian Real',
			'symbol'            => 'R$',
			'symbol_position'   => 'before',
			'decimal_separator' => ',',
			'thousand_separator' => '.',
			'decimals'          => 2,
		),
	);

	/**
	 * Register hooks and settings.
	 */
	public static function register() {
		add_filter( 'community_auctions/settings_fields', array( __CLASS__, 'add_settings_fields' ) );
		add_filter( 'community_auctions/settings_defaults', array( __CLASS__, 'add_settings_defaults' ) );
	}

	/**
	 * Add currency settings fields.
	 *
	 * @param array $fields Settings fields.
	 * @return array Modified fields.
	 */
	public static function add_settings_fields( $fields ) {
		$fields['currency_section'] = array(
			'title'    => __( 'Currency Settings', 'community-auctions' ),
			'callback' => array( __CLASS__, 'render_section_description' ),
			'fields'   => array(
				'currency_code' => array(
					'title'    => __( 'Currency', 'community-auctions' ),
					'callback' => array( __CLASS__, 'render_currency_select' ),
				),
				'currency_symbol_position' => array(
					'title'    => __( 'Symbol Position', 'community-auctions' ),
					'callback' => array( __CLASS__, 'render_position_select' ),
				),
				'currency_decimals' => array(
					'title'    => __( 'Decimal Places', 'community-auctions' ),
					'callback' => array( __CLASS__, 'render_decimals_field' ),
				),
				'currency_thousand_sep' => array(
					'title'    => __( 'Thousand Separator', 'community-auctions' ),
					'callback' => array( __CLASS__, 'render_thousand_sep_field' ),
				),
				'currency_decimal_sep' => array(
					'title'    => __( 'Decimal Separator', 'community-auctions' ),
					'callback' => array( __CLASS__, 'render_decimal_sep_field' ),
				),
			),
		);

		return $fields;
	}

	/**
	 * Add currency settings defaults.
	 *
	 * @param array $defaults Settings defaults.
	 * @return array Modified defaults.
	 */
	public static function add_settings_defaults( $defaults ) {
		$defaults['currency_code']            = 'USD';
		$defaults['currency_symbol_position'] = 'before';
		$defaults['currency_decimals']        = 2;
		$defaults['currency_thousand_sep']    = ',';
		$defaults['currency_decimal_sep']     = '.';

		return $defaults;
	}

	/**
	 * Render section description.
	 */
	public static function render_section_description() {
		echo '<p>' . esc_html__( 'Configure how currency is displayed throughout the auction system.', 'community-auctions' ) . '</p>';
	}

	/**
	 * Render currency select field.
	 */
	public static function render_currency_select() {
		$settings = Community_Auctions_Settings::get_settings();
		$current  = $settings['currency_code'] ?? 'USD';
		?>
		<select name="community_auctions_settings[currency_code]" id="currency_code">
			<?php foreach ( self::$currencies as $code => $config ) : ?>
				<option value="<?php echo esc_attr( $code ); ?>" <?php selected( $current, $code ); ?>>
					<?php echo esc_html( $code . ' - ' . $config['name'] . ' (' . $config['symbol'] . ')' ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<p class="description">
			<?php esc_html_e( 'Select the default currency for auctions.', 'community-auctions' ); ?>
		</p>
		<?php
	}

	/**
	 * Render symbol position select field.
	 */
	public static function render_position_select() {
		$settings = Community_Auctions_Settings::get_settings();
		$current  = $settings['currency_symbol_position'] ?? 'before';
		?>
		<select name="community_auctions_settings[currency_symbol_position]" id="currency_symbol_position">
			<option value="before" <?php selected( $current, 'before' ); ?>>
				<?php esc_html_e( 'Before amount ($99.99)', 'community-auctions' ); ?>
			</option>
			<option value="after" <?php selected( $current, 'after' ); ?>>
				<?php esc_html_e( 'After amount (99.99$)', 'community-auctions' ); ?>
			</option>
			<option value="before_space" <?php selected( $current, 'before_space' ); ?>>
				<?php esc_html_e( 'Before with space ($ 99.99)', 'community-auctions' ); ?>
			</option>
			<option value="after_space" <?php selected( $current, 'after_space' ); ?>>
				<?php esc_html_e( 'After with space (99.99 $)', 'community-auctions' ); ?>
			</option>
		</select>
		<?php
	}

	/**
	 * Render decimals field.
	 */
	public static function render_decimals_field() {
		$settings = Community_Auctions_Settings::get_settings();
		$current  = $settings['currency_decimals'] ?? 2;
		?>
		<select name="community_auctions_settings[currency_decimals]" id="currency_decimals">
			<option value="0" <?php selected( $current, 0 ); ?>><?php esc_html_e( '0 (whole numbers)', 'community-auctions' ); ?></option>
			<option value="1" <?php selected( $current, 1 ); ?>>1</option>
			<option value="2" <?php selected( $current, 2 ); ?>>2</option>
			<option value="3" <?php selected( $current, 3 ); ?>>3</option>
		</select>
		<?php
	}

	/**
	 * Render thousand separator field.
	 */
	public static function render_thousand_sep_field() {
		$settings = Community_Auctions_Settings::get_settings();
		$current  = $settings['currency_thousand_sep'] ?? ',';
		?>
		<select name="community_auctions_settings[currency_thousand_sep]" id="currency_thousand_sep">
			<option value="," <?php selected( $current, ',' ); ?>><?php esc_html_e( 'Comma (1,000)', 'community-auctions' ); ?></option>
			<option value="." <?php selected( $current, '.' ); ?>><?php esc_html_e( 'Period (1.000)', 'community-auctions' ); ?></option>
			<option value=" " <?php selected( $current, ' ' ); ?>><?php esc_html_e( 'Space (1 000)', 'community-auctions' ); ?></option>
			<option value="" <?php selected( $current, '' ); ?>><?php esc_html_e( 'None (1000)', 'community-auctions' ); ?></option>
		</select>
		<?php
	}

	/**
	 * Render decimal separator field.
	 */
	public static function render_decimal_sep_field() {
		$settings = Community_Auctions_Settings::get_settings();
		$current  = $settings['currency_decimal_sep'] ?? '.';
		?>
		<select name="community_auctions_settings[currency_decimal_sep]" id="currency_decimal_sep">
			<option value="." <?php selected( $current, '.' ); ?>><?php esc_html_e( 'Period (99.99)', 'community-auctions' ); ?></option>
			<option value="," <?php selected( $current, ',' ); ?>><?php esc_html_e( 'Comma (99,99)', 'community-auctions' ); ?></option>
		</select>
		<?php
	}

	/**
	 * Format currency amount.
	 *
	 * @param float    $amount     Amount to format.
	 * @param int|null $auction_id Optional auction ID for per-auction currency.
	 * @return string Formatted amount.
	 */
	public static function format( $amount, $auction_id = null ) {
		$settings = Community_Auctions_Settings::get_settings();

		// Check for per-auction currency override.
		$currency_code = $settings['currency_code'] ?? 'USD';
		if ( $auction_id ) {
			$auction_currency = get_post_meta( $auction_id, 'ca_currency', true );
			if ( $auction_currency && isset( self::$currencies[ $auction_currency ] ) ) {
				$currency_code = $auction_currency;
			}
		}

		$currency_config = self::$currencies[ $currency_code ] ?? self::$currencies['USD'];

		// Get formatting settings.
		$symbol       = $currency_config['symbol'];
		$position     = $settings['currency_symbol_position'] ?? $currency_config['symbol_position'];
		$decimals     = isset( $settings['currency_decimals'] ) ? intval( $settings['currency_decimals'] ) : $currency_config['decimals'];
		$thousand_sep = $settings['currency_thousand_sep'] ?? $currency_config['thousand_separator'];
		$decimal_sep  = $settings['currency_decimal_sep'] ?? $currency_config['decimal_separator'];

		// Format the number.
		$formatted_number = number_format( floatval( $amount ), $decimals, $decimal_sep, $thousand_sep );

		// Apply symbol position.
		switch ( $position ) {
			case 'after':
				return $formatted_number . $symbol;
			case 'before_space':
				return $symbol . ' ' . $formatted_number;
			case 'after_space':
				return $formatted_number . ' ' . $symbol;
			case 'before':
			default:
				return $symbol . $formatted_number;
		}
	}

	/**
	 * Get raw formatted number without currency symbol.
	 *
	 * @param float $amount Amount to format.
	 * @return string Formatted number.
	 */
	public static function format_number( $amount ) {
		$settings     = Community_Auctions_Settings::get_settings();
		$decimals     = isset( $settings['currency_decimals'] ) ? intval( $settings['currency_decimals'] ) : 2;
		$thousand_sep = $settings['currency_thousand_sep'] ?? ',';
		$decimal_sep  = $settings['currency_decimal_sep'] ?? '.';

		return number_format( floatval( $amount ), $decimals, $decimal_sep, $thousand_sep );
	}

	/**
	 * Get currency symbol.
	 *
	 * @param string|null $currency_code Optional currency code. Uses default if not provided.
	 * @return string Currency symbol.
	 */
	public static function get_symbol( $currency_code = null ) {
		if ( ! $currency_code ) {
			$settings      = Community_Auctions_Settings::get_settings();
			$currency_code = $settings['currency_code'] ?? 'USD';
		}

		return self::$currencies[ $currency_code ]['symbol'] ?? '$';
	}

	/**
	 * Get currency code.
	 *
	 * @param int|null $auction_id Optional auction ID for per-auction currency.
	 * @return string Currency code.
	 */
	public static function get_code( $auction_id = null ) {
		$settings      = Community_Auctions_Settings::get_settings();
		$currency_code = $settings['currency_code'] ?? 'USD';

		if ( $auction_id ) {
			$auction_currency = get_post_meta( $auction_id, 'ca_currency', true );
			if ( $auction_currency && isset( self::$currencies[ $auction_currency ] ) ) {
				$currency_code = $auction_currency;
			}
		}

		return $currency_code;
	}

	/**
	 * Get list of available currencies.
	 *
	 * @return array Array of currency configurations.
	 */
	public static function get_currencies() {
		return self::$currencies;
	}

	/**
	 * Check if a currency code is valid.
	 *
	 * @param string $code Currency code.
	 * @return bool Whether the currency is valid.
	 */
	public static function is_valid_currency( $code ) {
		return isset( self::$currencies[ $code ] );
	}

	/**
	 * Parse currency string to float.
	 *
	 * @param string $value Currency string to parse.
	 * @return float Parsed amount.
	 */
	public static function parse( $value ) {
		$settings     = Community_Auctions_Settings::get_settings();
		$decimal_sep  = $settings['currency_decimal_sep'] ?? '.';
		$thousand_sep = $settings['currency_thousand_sep'] ?? ',';

		// Remove currency symbol and trim.
		$value = preg_replace( '/[^\d' . preg_quote( $decimal_sep, '/' ) . preg_quote( $thousand_sep, '/' ) . '-]/', '', $value );

		// Remove thousand separators.
		$value = str_replace( $thousand_sep, '', $value );

		// Normalize decimal separator.
		$value = str_replace( $decimal_sep, '.', $value );

		return floatval( $value );
	}
}
