<?php
/**
 * Tests for the Settings class.
 *
 * @package Community_Auctions
 */

class Community_Auctions_Settings_Test extends WP_UnitTestCase {

	/**
	 * Clean up after each test.
	 */
	public function tear_down() {
		delete_option( Community_Auctions_Settings::OPTION_KEY );
		parent::tear_down();
	}

	/**
	 * Test default settings.
	 */
	public function test_get_settings_returns_defaults() {
		$settings = Community_Auctions_Settings::get_settings();

		$this->assertIsArray( $settings );
		$this->assertEquals( '', $settings['payment_provider'] );
		$this->assertEquals( array( 'administrator' ), $settings['allowed_roles_create'] );
		$this->assertEquals( array( 'administrator' ), $settings['allowed_roles_bid'] );
		$this->assertEquals( 0, $settings['admin_approval'] );
		$this->assertEquals( 'public', $settings['group_visibility_default'] );
		$this->assertEquals( 15, $settings['realtime_poll_interval'] );
	}

	/**
	 * Test saved settings override defaults.
	 */
	public function test_get_settings_merges_with_saved() {
		update_option( Community_Auctions_Settings::OPTION_KEY, array(
			'payment_provider' => 'woocommerce',
			'admin_approval'   => 1,
		) );

		$settings = Community_Auctions_Settings::get_settings();

		$this->assertEquals( 'woocommerce', $settings['payment_provider'] );
		$this->assertEquals( 1, $settings['admin_approval'] );
		// Defaults still applied.
		$this->assertEquals( array( 'administrator' ), $settings['allowed_roles_create'] );
	}

	/**
	 * Test payment provider sanitization.
	 */
	public function test_sanitize_payment_provider() {
		$input = array( 'payment_provider' => 'invalid_provider' );
		$output = Community_Auctions_Settings::sanitize_settings( $input );

		$this->assertEquals( '', $output['payment_provider'] );

		$input = array( 'payment_provider' => 'woocommerce' );
		$output = Community_Auctions_Settings::sanitize_settings( $input );

		$this->assertEquals( 'woocommerce', $output['payment_provider'] );
	}

	/**
	 * Test checkbox fields default to 0 when unchecked.
	 */
	public function test_sanitize_checkbox_handles_unchecked() {
		$input = array(); // No checkboxes checked.
		$output = Community_Auctions_Settings::sanitize_settings( $input );

		$this->assertEquals( 0, $output['admin_approval'] );
		$this->assertEquals( 0, $output['proxy_default'] );
		$this->assertEquals( 0, $output['block_seller_bidding'] );
		$this->assertEquals( 0, $output['realtime_enabled'] );
		$this->assertEquals( 0, $output['buy_now_enabled'] );
	}

	/**
	 * Test checkbox fields set to 1 when checked.
	 */
	public function test_sanitize_checkbox_handles_checked() {
		$input = array(
			'admin_approval'      => '1',
			'block_seller_bidding' => 'yes',
			'realtime_enabled'    => 'on',
		);
		$output = Community_Auctions_Settings::sanitize_settings( $input );

		$this->assertEquals( 1, $output['admin_approval'] );
		$this->assertEquals( 1, $output['block_seller_bidding'] );
		$this->assertEquals( 1, $output['realtime_enabled'] );
	}

	/**
	 * Test visibility field sanitization.
	 */
	public function test_sanitize_visibility() {
		$input = array( 'group_visibility_default' => 'invalid' );
		$output = Community_Auctions_Settings::sanitize_settings( $input );

		$this->assertEquals( 'public', $output['group_visibility_default'] );

		$input = array( 'group_visibility_default' => 'group_only' );
		$output = Community_Auctions_Settings::sanitize_settings( $input );

		$this->assertEquals( 'group_only', $output['group_visibility_default'] );
	}

	/**
	 * Test numeric field sanitization.
	 */
	public function test_sanitize_numeric_fields() {
		$input = array(
			'anti_sniping_minutes'    => '-5',
			'payment_reminder_hours'  => '0',
			'realtime_poll_interval'  => '3',
			'max_bid_limit'           => '-100',
		);
		$output = Community_Auctions_Settings::sanitize_settings( $input );

		$this->assertEquals( 0, $output['anti_sniping_minutes'] );
		$this->assertEquals( 1, $output['payment_reminder_hours'] ); // Min 1.
		$this->assertEquals( 5, $output['realtime_poll_interval'] ); // Min 5.
		$this->assertEquals( 0, $output['max_bid_limit'] ); // Min 0.
	}

	/**
	 * Test role sanitization filters invalid roles.
	 */
	public function test_sanitize_roles_filters_invalid() {
		$input = array(
			'allowed_roles_create' => array( 'administrator', 'fake_role', 'subscriber' ),
		);
		$output = Community_Auctions_Settings::sanitize_settings( $input );

		$this->assertContains( 'administrator', $output['allowed_roles_create'] );
		$this->assertContains( 'subscriber', $output['allowed_roles_create'] );
		$this->assertNotContains( 'fake_role', $output['allowed_roles_create'] );
	}

	/**
	 * Test fee mode sanitization.
	 */
	public function test_sanitize_fee_mode() {
		$input = array(
			'listing_fee_mode' => 'invalid',
			'success_fee_mode' => 'percent',
		);
		$output = Community_Auctions_Settings::sanitize_settings( $input );

		$this->assertEquals( 'flat', $output['listing_fee_mode'] );
		$this->assertEquals( 'percent', $output['success_fee_mode'] );
	}

	/**
	 * Test fee amount sanitization.
	 */
	public function test_sanitize_fee_amounts() {
		$input = array(
			'listing_fee_amount' => '-10.50',
			'success_fee_amount' => '5.25',
		);
		$output = Community_Auctions_Settings::sanitize_settings( $input );

		$this->assertEquals( 0, $output['listing_fee_amount'] );
		$this->assertEquals( 5.25, $output['success_fee_amount'] );
	}

	/**
	 * Test text field sanitization.
	 */
	public function test_sanitize_text_fields() {
		$input = array(
			'email_subject_prefix'      => '<script>alert("xss")</script>[Auctions]',
			'fluentcart_webhook_secret' => 'secret123<tag>',
		);
		$output = Community_Auctions_Settings::sanitize_settings( $input );

		$this->assertStringNotContainsString( '<script>', $output['email_subject_prefix'] );
		$this->assertStringNotContainsString( '<tag>', $output['fluentcart_webhook_secret'] );
	}

	/**
	 * Test textarea field sanitization.
	 */
	public function test_sanitize_textarea_fields() {
		$input = array(
			'email_footer_text' => "Line 1\nLine 2\n<script>bad</script>",
		);
		$output = Community_Auctions_Settings::sanitize_settings( $input );

		$this->assertStringNotContainsString( '<script>', $output['email_footer_text'] );
		$this->assertStringContainsString( 'Line 1', $output['email_footer_text'] );
	}
}
