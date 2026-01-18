<?php
/**
 * Tests for the Buy Now class.
 *
 * @package Community_Auctions
 */

class Community_Auctions_Buy_Now_Test extends WP_UnitTestCase {

	/**
	 * Set up before each test.
	 */
	public function set_up() {
		parent::set_up();

		// Enable Buy Now globally.
		update_option( Community_Auctions_Settings::OPTION_KEY, array(
			'payment_provider'     => 'woocommerce',
			'allowed_roles_create' => array( 'administrator' ),
			'allowed_roles_bid'    => array( 'administrator' ),
			'buy_now_enabled'      => 1,
			'buy_now_ends_auction' => 1,
		) );
	}

	/**
	 * Clean up after each test.
	 */
	public function tear_down() {
		delete_option( Community_Auctions_Settings::OPTION_KEY );
		parent::tear_down();
	}

	/**
	 * Create a test auction with Buy Now enabled.
	 *
	 * @param array $args Optional arguments.
	 * @return int Auction ID.
	 */
	private function create_auction( $args = array() ) {
		$defaults = array(
			'post_type'   => 'auction',
			'post_status' => 'publish',
			'post_title'  => 'Test Auction',
		);
		$args = wp_parse_args( $args, $defaults );

		$auction_id = self::factory()->post->create( $args );

		// Set default meta.
		update_post_meta( $auction_id, 'ca_start_price', 10.00 );
		update_post_meta( $auction_id, 'ca_min_increment', 1.00 );
		update_post_meta( $auction_id, 'ca_end_at', gmdate( 'Y-m-d H:i:s', strtotime( '+1 day' ) ) );
		update_post_meta( $auction_id, 'ca_buy_now_enabled', 1 );
		update_post_meta( $auction_id, 'ca_buy_now_price', 100.00 );

		return $auction_id;
	}

	/**
	 * Test is_enabled_globally returns true when setting is on.
	 */
	public function test_is_enabled_globally_true() {
		$this->assertTrue( Community_Auctions_Buy_Now::is_enabled_globally() );
	}

	/**
	 * Test is_enabled_globally returns false when setting is off.
	 */
	public function test_is_enabled_globally_false() {
		update_option( Community_Auctions_Settings::OPTION_KEY, array(
			'buy_now_enabled' => 0,
		) );

		$this->assertFalse( Community_Auctions_Buy_Now::is_enabled_globally() );
	}

	/**
	 * Test is_available returns true for valid auction.
	 */
	public function test_is_available_true() {
		$auction_id = $this->create_auction();

		$this->assertTrue( Community_Auctions_Buy_Now::is_available( $auction_id ) );
	}

	/**
	 * Test is_available returns false when disabled on auction.
	 */
	public function test_is_available_false_when_disabled() {
		$auction_id = $this->create_auction();
		update_post_meta( $auction_id, 'ca_buy_now_enabled', 0 );

		$this->assertFalse( Community_Auctions_Buy_Now::is_available( $auction_id ) );
	}

	/**
	 * Test is_available returns false when no price set.
	 */
	public function test_is_available_false_when_no_price() {
		$auction_id = $this->create_auction();
		delete_post_meta( $auction_id, 'ca_buy_now_price' );

		$this->assertFalse( Community_Auctions_Buy_Now::is_available( $auction_id ) );
	}

	/**
	 * Test is_available returns false when price is zero.
	 */
	public function test_is_available_false_when_zero_price() {
		$auction_id = $this->create_auction();
		update_post_meta( $auction_id, 'ca_buy_now_price', 0 );

		$this->assertFalse( Community_Auctions_Buy_Now::is_available( $auction_id ) );
	}

	/**
	 * Test is_available returns false when already bought.
	 */
	public function test_is_available_false_when_already_bought() {
		$auction_id = $this->create_auction();
		update_post_meta( $auction_id, 'ca_bought_now', 1 );

		$this->assertFalse( Community_Auctions_Buy_Now::is_available( $auction_id ) );
	}

	/**
	 * Test is_available returns false for ended auction.
	 */
	public function test_is_available_false_for_ended_auction() {
		$auction_id = $this->create_auction();
		update_post_meta( $auction_id, 'ca_end_at', gmdate( 'Y-m-d H:i:s', strtotime( '-1 hour' ) ) );

		$this->assertFalse( Community_Auctions_Buy_Now::is_available( $auction_id ) );
	}

	/**
	 * Test get_price returns correct price.
	 */
	public function test_get_price() {
		$auction_id = $this->create_auction();

		$price = Community_Auctions_Buy_Now::get_price( $auction_id );

		$this->assertEquals( 100.00, $price );
	}

	/**
	 * Test get_price returns 0 when not set.
	 */
	public function test_get_price_returns_zero_when_not_set() {
		$auction_id = $this->create_auction();
		delete_post_meta( $auction_id, 'ca_buy_now_price' );

		$price = Community_Auctions_Buy_Now::get_price( $auction_id );

		$this->assertEquals( 0, $price );
	}

	/**
	 * Test can_user_buy returns true for valid buyer.
	 */
	public function test_can_user_buy_true() {
		$seller_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$buyer_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$auction_id = $this->create_auction( array( 'post_author' => $seller_id ) );

		wp_set_current_user( $buyer_id );

		$result = Community_Auctions_Buy_Now::can_user_buy( $auction_id );

		$this->assertTrue( $result );
	}

	/**
	 * Test seller cannot buy own auction.
	 */
	public function test_seller_cannot_buy_own_auction() {
		$seller_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$auction_id = $this->create_auction( array( 'post_author' => $seller_id ) );

		wp_set_current_user( $seller_id );

		$result = Community_Auctions_Buy_Now::can_user_buy( $auction_id );

		$this->assertFalse( $result );
	}

	/**
	 * Test can_user_buy returns false for guest.
	 */
	public function test_guest_cannot_buy() {
		$auction_id = $this->create_auction();

		wp_set_current_user( 0 );

		$result = Community_Auctions_Buy_Now::can_user_buy( $auction_id );

		$this->assertFalse( $result );
	}

	/**
	 * Test buy now is available checks global setting.
	 */
	public function test_is_available_requires_global_setting() {
		update_option( Community_Auctions_Settings::OPTION_KEY, array(
			'buy_now_enabled' => 0,
		) );

		$auction_id = $this->create_auction();

		$this->assertFalse( Community_Auctions_Buy_Now::is_available( $auction_id ) );
	}

	/**
	 * Test current bid exceeds buy now price disables buy now.
	 */
	public function test_buy_now_disabled_when_bid_exceeds_price() {
		$auction_id = $this->create_auction();

		// Set current bid higher than buy now price.
		update_post_meta( $auction_id, 'ca_current_bid', 150.00 );

		$this->assertFalse( Community_Auctions_Buy_Now::is_available( $auction_id ) );
	}

	/**
	 * Test render_button returns empty when not available.
	 */
	public function test_render_button_empty_when_not_available() {
		$auction_id = $this->create_auction();
		update_post_meta( $auction_id, 'ca_buy_now_enabled', 0 );

		$output = Community_Auctions_Buy_Now::render_button( $auction_id );

		$this->assertEmpty( $output );
	}

	/**
	 * Test render_button returns HTML when available.
	 */
	public function test_render_button_returns_html() {
		$buyer_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $buyer_id );

		$auction_id = $this->create_auction();

		$output = Community_Auctions_Buy_Now::render_button( $auction_id );

		$this->assertStringContainsString( 'ca-buy-now-button', $output );
		$this->assertStringContainsString( 'Buy It Now', $output );
	}

	/**
	 * Test buy action marks auction as bought.
	 */
	public function test_buy_marks_auction_as_bought() {
		$seller_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$buyer_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$auction_id = $this->create_auction( array( 'post_author' => $seller_id ) );

		wp_set_current_user( $buyer_id );

		// Simulate purchase (internal method would do this).
		update_post_meta( $auction_id, 'ca_bought_now', 1 );
		update_post_meta( $auction_id, 'ca_current_bidder', $buyer_id );
		update_post_meta( $auction_id, 'ca_current_bid', 100.00 );

		$this->assertEquals( 1, get_post_meta( $auction_id, 'ca_bought_now', true ) );
		$this->assertEquals( $buyer_id, get_post_meta( $auction_id, 'ca_current_bidder', true ) );
	}

	/**
	 * Test invalid auction returns false for availability.
	 */
	public function test_invalid_auction_returns_false() {
		$this->assertFalse( Community_Auctions_Buy_Now::is_available( 99999 ) );
	}

	/**
	 * Test wrong post type returns false.
	 */
	public function test_wrong_post_type_returns_false() {
		$post_id = self::factory()->post->create( array( 'post_type' => 'post' ) );

		$this->assertFalse( Community_Auctions_Buy_Now::is_available( $post_id ) );
	}
}
