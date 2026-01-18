<?php
/**
 * Tests for the Currency class.
 *
 * @package Community_Auctions
 */

class Community_Auctions_Currency_Test extends WP_UnitTestCase {

	/**
	 * Set up before each test.
	 */
	public function set_up() {
		parent::set_up();
		delete_option( Community_Auctions_Settings::OPTION_KEY );
	}

	/**
	 * Clean up after each test.
	 */
	public function tear_down() {
		delete_option( Community_Auctions_Settings::OPTION_KEY );
		parent::tear_down();
	}

	/**
	 * Test default USD formatting.
	 */
	public function test_format_default_usd() {
		$result = Community_Auctions_Currency::format( 1234.56 );

		$this->assertEquals( '$1,234.56', $result );
	}

	/**
	 * Test formatting with zero decimals.
	 */
	public function test_format_zero_decimals() {
		update_option( Community_Auctions_Settings::OPTION_KEY, array(
			'currency_code'     => 'JPY',
			'currency_decimals' => 0,
		) );

		$result = Community_Auctions_Currency::format( 1234 );

		$this->assertEquals( '¥1,234', $result );
	}

	/**
	 * Test symbol position after amount.
	 */
	public function test_format_symbol_after() {
		update_option( Community_Auctions_Settings::OPTION_KEY, array(
			'currency_code'            => 'USD',
			'currency_symbol_position' => 'after',
		) );

		$result = Community_Auctions_Currency::format( 99.99 );

		$this->assertEquals( '99.99$', $result );
	}

	/**
	 * Test symbol position before with space.
	 */
	public function test_format_symbol_before_space() {
		update_option( Community_Auctions_Settings::OPTION_KEY, array(
			'currency_code'            => 'EUR',
			'currency_symbol_position' => 'before_space',
		) );

		$result = Community_Auctions_Currency::format( 50.00 );

		$this->assertEquals( '€ 50,00', $result );
	}

	/**
	 * Test symbol position after with space.
	 */
	public function test_format_symbol_after_space() {
		update_option( Community_Auctions_Settings::OPTION_KEY, array(
			'currency_code'            => 'USD',
			'currency_symbol_position' => 'after_space',
		) );

		$result = Community_Auctions_Currency::format( 100 );

		$this->assertEquals( '100.00 $', $result );
	}

	/**
	 * Test European format (comma decimal, period thousand).
	 */
	public function test_format_european_style() {
		update_option( Community_Auctions_Settings::OPTION_KEY, array(
			'currency_code'         => 'EUR',
			'currency_decimal_sep'  => ',',
			'currency_thousand_sep' => '.',
		) );

		$result = Community_Auctions_Currency::format( 1234.56 );

		$this->assertEquals( '€1.234,56', $result );
	}

	/**
	 * Test formatting zero amount.
	 */
	public function test_format_zero() {
		$result = Community_Auctions_Currency::format( 0 );

		$this->assertEquals( '$0.00', $result );
	}

	/**
	 * Test formatting negative amount.
	 */
	public function test_format_negative() {
		$result = Community_Auctions_Currency::format( -50.00 );

		$this->assertEquals( '$-50.00', $result );
	}

	/**
	 * Test formatting large amount.
	 */
	public function test_format_large_amount() {
		$result = Community_Auctions_Currency::format( 1234567.89 );

		$this->assertEquals( '$1,234,567.89', $result );
	}

	/**
	 * Test per-auction currency override.
	 */
	public function test_format_with_auction_override() {
		$auction_id = self::factory()->post->create( array( 'post_type' => 'auction' ) );
		update_post_meta( $auction_id, 'ca_currency', 'GBP' );

		$result = Community_Auctions_Currency::format( 100.00, $auction_id );

		$this->assertStringContainsString( '£', $result );
	}

	/**
	 * Test format_number without symbol.
	 */
	public function test_format_number() {
		$result = Community_Auctions_Currency::format_number( 1234.56 );

		$this->assertEquals( '1,234.56', $result );
		$this->assertStringNotContainsString( '$', $result );
	}

	/**
	 * Test get_symbol default.
	 */
	public function test_get_symbol_default() {
		$symbol = Community_Auctions_Currency::get_symbol();

		$this->assertEquals( '$', $symbol );
	}

	/**
	 * Test get_symbol with specific code.
	 */
	public function test_get_symbol_specific() {
		$this->assertEquals( '€', Community_Auctions_Currency::get_symbol( 'EUR' ) );
		$this->assertEquals( '£', Community_Auctions_Currency::get_symbol( 'GBP' ) );
		$this->assertEquals( '¥', Community_Auctions_Currency::get_symbol( 'JPY' ) );
		$this->assertEquals( '₹', Community_Auctions_Currency::get_symbol( 'INR' ) );
	}

	/**
	 * Test get_symbol with invalid code returns default.
	 */
	public function test_get_symbol_invalid_code() {
		$symbol = Community_Auctions_Currency::get_symbol( 'INVALID' );

		$this->assertEquals( '$', $symbol );
	}

	/**
	 * Test get_code default.
	 */
	public function test_get_code_default() {
		$code = Community_Auctions_Currency::get_code();

		$this->assertEquals( 'USD', $code );
	}

	/**
	 * Test get_code with auction override.
	 */
	public function test_get_code_with_auction() {
		$auction_id = self::factory()->post->create( array( 'post_type' => 'auction' ) );
		update_post_meta( $auction_id, 'ca_currency', 'EUR' );

		$code = Community_Auctions_Currency::get_code( $auction_id );

		$this->assertEquals( 'EUR', $code );
	}

	/**
	 * Test get_currencies returns array.
	 */
	public function test_get_currencies() {
		$currencies = Community_Auctions_Currency::get_currencies();

		$this->assertIsArray( $currencies );
		$this->assertArrayHasKey( 'USD', $currencies );
		$this->assertArrayHasKey( 'EUR', $currencies );
		$this->assertArrayHasKey( 'GBP', $currencies );
	}

	/**
	 * Test is_valid_currency.
	 */
	public function test_is_valid_currency() {
		$this->assertTrue( Community_Auctions_Currency::is_valid_currency( 'USD' ) );
		$this->assertTrue( Community_Auctions_Currency::is_valid_currency( 'EUR' ) );
		$this->assertFalse( Community_Auctions_Currency::is_valid_currency( 'INVALID' ) );
		$this->assertFalse( Community_Auctions_Currency::is_valid_currency( '' ) );
	}

	/**
	 * Test parse currency string.
	 */
	public function test_parse() {
		$this->assertEquals( 1234.56, Community_Auctions_Currency::parse( '$1,234.56' ) );
		$this->assertEquals( 99.99, Community_Auctions_Currency::parse( '99.99' ) );
		$this->assertEquals( 1000.0, Community_Auctions_Currency::parse( '1,000' ) );
	}

	/**
	 * Test parse European format.
	 */
	public function test_parse_european() {
		update_option( Community_Auctions_Settings::OPTION_KEY, array(
			'currency_decimal_sep'  => ',',
			'currency_thousand_sep' => '.',
		) );

		$this->assertEquals( 1234.56, Community_Auctions_Currency::parse( '1.234,56' ) );
	}

	/**
	 * Test parse empty string.
	 */
	public function test_parse_empty() {
		$this->assertEquals( 0.0, Community_Auctions_Currency::parse( '' ) );
	}

	/**
	 * Test currency configuration has required fields.
	 */
	public function test_currency_config_structure() {
		$currencies = Community_Auctions_Currency::get_currencies();

		foreach ( $currencies as $code => $config ) {
			$this->assertArrayHasKey( 'name', $config, "Currency {$code} missing 'name'" );
			$this->assertArrayHasKey( 'symbol', $config, "Currency {$code} missing 'symbol'" );
			$this->assertArrayHasKey( 'symbol_position', $config, "Currency {$code} missing 'symbol_position'" );
			$this->assertArrayHasKey( 'decimals', $config, "Currency {$code} missing 'decimals'" );
		}
	}
}
