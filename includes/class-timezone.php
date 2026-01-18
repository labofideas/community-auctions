<?php
/**
 * Timezone Handling - Date/time formatting and conversion.
 *
 * @package CommunityAuctions
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles timezone conversion and date formatting.
 */
class Community_Auctions_Timezone {

	/**
	 * Register hooks and settings.
	 */
	public static function register() {
		add_filter( 'community_auctions/settings_fields', array( __CLASS__, 'add_settings_fields' ) );
		add_filter( 'community_auctions/settings_defaults', array( __CLASS__, 'add_settings_defaults' ) );
	}

	/**
	 * Add timezone settings fields.
	 *
	 * @param array $fields Settings fields.
	 * @return array Modified fields.
	 */
	public static function add_settings_fields( $fields ) {
		$fields['timezone_section'] = array(
			'title'    => __( 'Timezone Settings', 'community-auctions' ),
			'callback' => array( __CLASS__, 'render_section_description' ),
			'fields'   => array(
				'timezone_mode' => array(
					'title'    => __( 'Timezone Mode', 'community-auctions' ),
					'callback' => array( __CLASS__, 'render_timezone_mode' ),
				),
				'default_timezone' => array(
					'title'    => __( 'Default Timezone', 'community-auctions' ),
					'callback' => array( __CLASS__, 'render_timezone_select' ),
				),
				'date_format' => array(
					'title'    => __( 'Date Format', 'community-auctions' ),
					'callback' => array( __CLASS__, 'render_date_format' ),
				),
				'time_format' => array(
					'title'    => __( 'Time Format', 'community-auctions' ),
					'callback' => array( __CLASS__, 'render_time_format' ),
				),
			),
		);

		return $fields;
	}

	/**
	 * Add timezone settings defaults.
	 *
	 * @param array $defaults Settings defaults.
	 * @return array Modified defaults.
	 */
	public static function add_settings_defaults( $defaults ) {
		$defaults['timezone_mode']    = 'site'; // 'site', 'user', 'custom'.
		$defaults['default_timezone'] = wp_timezone_string();
		$defaults['date_format']      = get_option( 'date_format', 'F j, Y' );
		$defaults['time_format']      = get_option( 'time_format', 'g:i a' );

		return $defaults;
	}

	/**
	 * Render section description.
	 */
	public static function render_section_description() {
		echo '<p>' . esc_html__( 'Configure how dates and times are displayed to users.', 'community-auctions' ) . '</p>';
	}

	/**
	 * Render timezone mode field.
	 */
	public static function render_timezone_mode() {
		$settings = Community_Auctions_Settings::get_settings();
		$current  = $settings['timezone_mode'] ?? 'site';
		?>
		<select name="community_auctions_settings[timezone_mode]" id="timezone_mode">
			<option value="site" <?php selected( $current, 'site' ); ?>>
				<?php esc_html_e( 'Use site timezone', 'community-auctions' ); ?>
			</option>
			<option value="user" <?php selected( $current, 'user' ); ?>>
				<?php esc_html_e( 'Use user\'s WordPress profile timezone', 'community-auctions' ); ?>
			</option>
			<option value="utc" <?php selected( $current, 'utc' ); ?>>
				<?php esc_html_e( 'Always show UTC', 'community-auctions' ); ?>
			</option>
		</select>
		<p class="description">
			<?php esc_html_e( 'How to determine timezone for displaying auction times.', 'community-auctions' ); ?>
		</p>
		<?php
	}

	/**
	 * Render timezone select field.
	 */
	public static function render_timezone_select() {
		$settings = Community_Auctions_Settings::get_settings();
		$current  = $settings['default_timezone'] ?? wp_timezone_string();
		?>
		<select name="community_auctions_settings[default_timezone]" id="default_timezone">
			<?php echo wp_timezone_choice( $current ); ?>
		</select>
		<p class="description">
			<?php esc_html_e( 'Fallback timezone when user timezone is not available.', 'community-auctions' ); ?>
		</p>
		<?php
	}

	/**
	 * Render date format field.
	 */
	public static function render_date_format() {
		$settings = Community_Auctions_Settings::get_settings();
		$current  = $settings['date_format'] ?? 'F j, Y';
		$formats  = array(
			'F j, Y'    => 'January 1, 2025',
			'Y-m-d'     => '2025-01-01',
			'd/m/Y'     => '01/01/2025',
			'm/d/Y'     => '01/01/2025',
			'j M Y'     => '1 Jan 2025',
		);
		?>
		<select name="community_auctions_settings[date_format]" id="date_format">
			<?php foreach ( $formats as $format => $example ) : ?>
				<option value="<?php echo esc_attr( $format ); ?>" <?php selected( $current, $format ); ?>>
					<?php echo esc_html( wp_date( $format ) . ' (' . $format . ')' ); ?>
				</option>
			<?php endforeach; ?>
			<option value="custom" <?php selected( ! isset( $formats[ $current ] ), true ); ?>>
				<?php esc_html_e( 'Custom', 'community-auctions' ); ?>
			</option>
		</select>
		<?php
	}

	/**
	 * Render time format field.
	 */
	public static function render_time_format() {
		$settings = Community_Auctions_Settings::get_settings();
		$current  = $settings['time_format'] ?? 'g:i a';
		$formats  = array(
			'g:i a' => '12-hour (1:30 pm)',
			'g:i A' => '12-hour (1:30 PM)',
			'H:i'   => '24-hour (13:30)',
		);
		?>
		<select name="community_auctions_settings[time_format]" id="time_format">
			<?php foreach ( $formats as $format => $label ) : ?>
				<option value="<?php echo esc_attr( $format ); ?>" <?php selected( $current, $format ); ?>>
					<?php echo esc_html( wp_date( $format ) . ' - ' . $label ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<?php
	}

	/**
	 * Get the timezone to use for display.
	 *
	 * @param int|null $user_id Optional user ID. Uses current user if not provided.
	 * @return DateTimeZone Timezone object.
	 */
	public static function get_display_timezone( $user_id = null ) {
		$settings = Community_Auctions_Settings::get_settings();
		$mode     = $settings['timezone_mode'] ?? 'site';

		switch ( $mode ) {
			case 'utc':
				return new DateTimeZone( 'UTC' );

			case 'user':
				if ( ! $user_id ) {
					$user_id = get_current_user_id();
				}
				if ( $user_id ) {
					$user_tz = get_user_meta( $user_id, 'timezone_string', true );
					if ( $user_tz ) {
						try {
							return new DateTimeZone( $user_tz );
						} catch ( Exception $e ) {
							// Fall back to site timezone.
						}
					}
				}
				// Fall through to site timezone.

			case 'site':
			default:
				return wp_timezone();
		}
	}

	/**
	 * Format a datetime for display.
	 *
	 * @param string|int $datetime DateTime string or timestamp.
	 * @param string     $format   Optional format. Uses settings if not provided.
	 * @param int|null   $user_id  Optional user ID for timezone.
	 * @return string Formatted datetime.
	 */
	public static function format( $datetime, $format = null, $user_id = null ) {
		$settings = Community_Auctions_Settings::get_settings();

		if ( ! $format ) {
			$date_format = $settings['date_format'] ?? 'F j, Y';
			$time_format = $settings['time_format'] ?? 'g:i a';
			$format      = $date_format . ' ' . $time_format;
		}

		$timezone = self::get_display_timezone( $user_id );

		// Convert to timestamp if needed.
		if ( is_string( $datetime ) ) {
			$timestamp = strtotime( $datetime );
		} else {
			$timestamp = intval( $datetime );
		}

		if ( ! $timestamp ) {
			return '';
		}

		return wp_date( $format, $timestamp, $timezone );
	}

	/**
	 * Format date only.
	 *
	 * @param string|int $datetime DateTime string or timestamp.
	 * @param int|null   $user_id  Optional user ID for timezone.
	 * @return string Formatted date.
	 */
	public static function format_date( $datetime, $user_id = null ) {
		$settings = Community_Auctions_Settings::get_settings();
		$format   = $settings['date_format'] ?? 'F j, Y';

		return self::format( $datetime, $format, $user_id );
	}

	/**
	 * Format time only.
	 *
	 * @param string|int $datetime DateTime string or timestamp.
	 * @param int|null   $user_id  Optional user ID for timezone.
	 * @return string Formatted time.
	 */
	public static function format_time( $datetime, $user_id = null ) {
		$settings = Community_Auctions_Settings::get_settings();
		$format   = $settings['time_format'] ?? 'g:i a';

		return self::format( $datetime, $format, $user_id );
	}

	/**
	 * Convert a datetime from one timezone to another.
	 *
	 * @param string|int   $datetime Datetime to convert.
	 * @param DateTimeZone $from     Source timezone.
	 * @param DateTimeZone $to       Target timezone.
	 * @return string Converted datetime in MySQL format.
	 */
	public static function convert( $datetime, $from, $to ) {
		if ( is_string( $datetime ) ) {
			$dt = new DateTime( $datetime, $from );
		} else {
			$dt = new DateTime( '@' . intval( $datetime ) );
			$dt->setTimezone( $from );
		}

		$dt->setTimezone( $to );

		return $dt->format( 'Y-m-d H:i:s' );
	}

	/**
	 * Convert local datetime to UTC.
	 *
	 * @param string|int $datetime Local datetime.
	 * @param int|null   $user_id  Optional user ID for timezone detection.
	 * @return string UTC datetime in MySQL format.
	 */
	public static function to_utc( $datetime, $user_id = null ) {
		$local_tz = self::get_display_timezone( $user_id );
		$utc_tz   = new DateTimeZone( 'UTC' );

		return self::convert( $datetime, $local_tz, $utc_tz );
	}

	/**
	 * Convert UTC datetime to local.
	 *
	 * @param string|int $datetime UTC datetime.
	 * @param int|null   $user_id  Optional user ID for timezone detection.
	 * @return string Local datetime in MySQL format.
	 */
	public static function from_utc( $datetime, $user_id = null ) {
		$local_tz = self::get_display_timezone( $user_id );
		$utc_tz   = new DateTimeZone( 'UTC' );

		return self::convert( $datetime, $utc_tz, $local_tz );
	}

	/**
	 * Get current time in UTC.
	 *
	 * @param string $format Optional format. Defaults to MySQL format.
	 * @return string Current UTC time.
	 */
	public static function now_utc( $format = 'Y-m-d H:i:s' ) {
		return gmdate( $format );
	}

	/**
	 * Get relative time string (e.g., "2 hours ago").
	 *
	 * @param string|int $datetime Datetime to compare.
	 * @param bool       $short    Whether to use short format.
	 * @return string Relative time string.
	 */
	public static function relative( $datetime, $short = false ) {
		if ( is_string( $datetime ) ) {
			$timestamp = strtotime( $datetime );
		} else {
			$timestamp = intval( $datetime );
		}

		$diff = time() - $timestamp;

		if ( $diff < 0 ) {
			// Future time.
			$diff = abs( $diff );
			$suffix = $short ? '' : ' ' . __( 'from now', 'community-auctions' );
		} else {
			$suffix = $short ? '' : ' ' . __( 'ago', 'community-auctions' );
		}

		if ( $diff < 60 ) {
			return __( 'just now', 'community-auctions' );
		}

		if ( $diff < 3600 ) {
			$minutes = floor( $diff / 60 );
			$label   = $short ? __( 'm', 'community-auctions' ) : _n( 'minute', 'minutes', $minutes, 'community-auctions' );
			return $minutes . ( $short ? $label : ' ' . $label ) . $suffix;
		}

		if ( $diff < 86400 ) {
			$hours = floor( $diff / 3600 );
			$label = $short ? __( 'h', 'community-auctions' ) : _n( 'hour', 'hours', $hours, 'community-auctions' );
			return $hours . ( $short ? $label : ' ' . $label ) . $suffix;
		}

		if ( $diff < 604800 ) {
			$days  = floor( $diff / 86400 );
			$label = $short ? __( 'd', 'community-auctions' ) : _n( 'day', 'days', $days, 'community-auctions' );
			return $days . ( $short ? $label : ' ' . $label ) . $suffix;
		}

		// More than a week, show full date.
		return self::format_date( $timestamp );
	}

	/**
	 * Get timezone abbreviation for display.
	 *
	 * @param int|null $user_id Optional user ID for timezone.
	 * @return string Timezone abbreviation (e.g., "PST", "EST").
	 */
	public static function get_timezone_abbr( $user_id = null ) {
		$timezone = self::get_display_timezone( $user_id );
		$dt       = new DateTime( 'now', $timezone );

		return $dt->format( 'T' );
	}

	/**
	 * Get timezone offset string for display.
	 *
	 * @param int|null $user_id Optional user ID for timezone.
	 * @return string Timezone offset (e.g., "UTC-8", "UTC+5:30").
	 */
	public static function get_timezone_offset_string( $user_id = null ) {
		$timezone = self::get_display_timezone( $user_id );
		$dt       = new DateTime( 'now', $timezone );
		$offset   = $dt->getOffset();

		$hours   = floor( abs( $offset ) / 3600 );
		$minutes = floor( ( abs( $offset ) % 3600 ) / 60 );

		$sign = $offset >= 0 ? '+' : '-';

		if ( $minutes > 0 ) {
			return sprintf( 'UTC%s%d:%02d', $sign, $hours, $minutes );
		}

		return sprintf( 'UTC%s%d', $sign, $hours );
	}
}
