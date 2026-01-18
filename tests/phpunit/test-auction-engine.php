<?php
/**
 * Tests for the Auction Engine class.
 *
 * @package Community_Auctions
 */

class Community_Auctions_Auction_Engine_Test extends WP_UnitTestCase {

	/**
	 * Set up before each test.
	 */
	public function set_up() {
		parent::set_up();
		Community_Auctions_Bid_Repository::create_table();

		// Enable WooCommerce as payment provider for tests.
		update_option( 'community_auctions_settings', array(
			'payment_provider'         => 'woocommerce',
			'allowed_roles_create'     => array( 'administrator' ),
			'allowed_roles_bid'        => array( 'administrator' ),
			'block_seller_bidding'     => 1,
			'prevent_duplicate_highest' => 1,
			'max_bid_limit'            => 0,
		) );
	}

	/**
	 * Create a test auction.
	 *
	 * @param array $args Optional auction arguments.
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
		update_post_meta( $auction_id, 'ca_visibility', 'public' );

		return $auction_id;
	}

	/**
	 * Test placing a valid bid.
	 */
	public function test_place_bid_success() {
		$auction_id = $this->create_auction();
		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );

		$result = Community_Auctions_Auction_Engine::place_bid( $auction_id, $user_id, 15.00 );

		$this->assertIsArray( $result );
		$this->assertEquals( $auction_id, $result['auction_id'] );
		$this->assertEquals( 15.00, $result['amount'] );

		// Check post meta updated.
		$this->assertEquals( 15.00, floatval( get_post_meta( $auction_id, 'ca_current_bid', true ) ) );
		$this->assertEquals( $user_id, intval( get_post_meta( $auction_id, 'ca_current_bidder', true ) ) );
	}

	/**
	 * Test bid on invalid auction returns error.
	 */
	public function test_place_bid_on_invalid_auction() {
		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );

		$result = Community_Auctions_Auction_Engine::place_bid( 99999, $user_id, 15.00 );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertEquals( 'ca_invalid_auction', $result->get_error_code() );
	}

	/**
	 * Test bid on wrong post type returns error.
	 */
	public function test_place_bid_on_wrong_post_type() {
		$post_id = self::factory()->post->create( array( 'post_type' => 'post' ) );
		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );

		$result = Community_Auctions_Auction_Engine::place_bid( $post_id, $user_id, 15.00 );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertEquals( 'ca_invalid_auction', $result->get_error_code() );
	}

	/**
	 * Test bid on ended auction returns error.
	 */
	public function test_place_bid_on_ended_auction() {
		$auction_id = $this->create_auction();
		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );

		// Set end time in the past.
		update_post_meta( $auction_id, 'ca_end_at', gmdate( 'Y-m-d H:i:s', strtotime( '-1 hour' ) ) );

		$result = Community_Auctions_Auction_Engine::place_bid( $auction_id, $user_id, 15.00 );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertEquals( 'ca_auction_ended', $result->get_error_code() );
	}

	/**
	 * Test bid below minimum returns error.
	 */
	public function test_place_bid_too_low() {
		$auction_id = $this->create_auction();
		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );

		// Bid at start price (needs increment).
		$result = Community_Auctions_Auction_Engine::place_bid( $auction_id, $user_id, 10.00 );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertEquals( 'ca_bid_too_low', $result->get_error_code() );
	}

	/**
	 * Test bid exactly at minimum passes.
	 */
	public function test_place_bid_at_minimum() {
		$auction_id = $this->create_auction();
		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );

		// Start price (10) + min increment (1) = 11.
		$result = Community_Auctions_Auction_Engine::place_bid( $auction_id, $user_id, 11.00 );

		$this->assertIsArray( $result );
		$this->assertEquals( 11.00, $result['amount'] );
	}

	/**
	 * Test seller cannot bid on own auction.
	 */
	public function test_seller_cannot_bid_on_own_auction() {
		$seller_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$auction_id = $this->create_auction( array( 'post_author' => $seller_id ) );

		$result = Community_Auctions_Auction_Engine::place_bid( $auction_id, $seller_id, 15.00 );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertEquals( 'ca_seller_cannot_bid', $result->get_error_code() );
	}

	/**
	 * Test highest bidder cannot bid again (when setting enabled).
	 */
	public function test_highest_bidder_cannot_bid_again() {
		$auction_id = $this->create_auction();
		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );

		// Place first bid.
		Community_Auctions_Auction_Engine::place_bid( $auction_id, $user_id, 15.00 );

		// Try to bid again as highest bidder.
		$result = Community_Auctions_Auction_Engine::place_bid( $auction_id, $user_id, 20.00 );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertEquals( 'ca_already_highest', $result->get_error_code() );
	}

	/**
	 * Test max bid limit enforcement.
	 */
	public function test_max_bid_limit() {
		$auction_id = $this->create_auction();
		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );

		// Set max bid limit.
		update_option( 'community_auctions_settings', array_merge(
			Community_Auctions_Settings::get_settings(),
			array( 'max_bid_limit' => 100.00 )
		) );

		$result = Community_Auctions_Auction_Engine::place_bid( $auction_id, $user_id, 150.00 );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertEquals( 'ca_bid_exceeds_limit', $result->get_error_code() );
	}

	/**
	 * Test outbid action is fired.
	 */
	public function test_outbid_action_fired() {
		$auction_id = $this->create_auction();
		$user1 = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$user2 = self::factory()->user->create( array( 'role' => 'administrator' ) );

		$outbid_triggered = false;
		$outbid_user = null;

		add_action( 'community_auctions/bid_outbid', function ( $aid, $old_user, $new_user ) use ( &$outbid_triggered, &$outbid_user ) {
			$outbid_triggered = true;
			$outbid_user = $old_user;
		}, 10, 3 );

		// First bid.
		Community_Auctions_Auction_Engine::place_bid( $auction_id, $user1, 15.00 );

		// Second bid outbids first.
		// Disable duplicate check for user1.
		update_option( 'community_auctions_settings', array_merge(
			Community_Auctions_Settings::get_settings(),
			array( 'prevent_duplicate_highest' => 0 )
		) );

		Community_Auctions_Auction_Engine::place_bid( $auction_id, $user2, 20.00 );

		$this->assertTrue( $outbid_triggered );
		$this->assertEquals( $user1, $outbid_user );
	}

	/**
	 * Test bid placed action is fired.
	 */
	public function test_bid_placed_action_fired() {
		$auction_id = $this->create_auction();
		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );

		$action_triggered = false;
		$action_amount = null;

		add_action( 'community_auctions/bid_placed', function ( $aid, $uid, $amount ) use ( &$action_triggered, &$action_amount ) {
			$action_triggered = true;
			$action_amount = $amount;
		}, 10, 3 );

		Community_Auctions_Auction_Engine::place_bid( $auction_id, $user_id, 15.00 );

		$this->assertTrue( $action_triggered );
		$this->assertEquals( 15.00, $action_amount );
	}

	/**
	 * Test bid on draft auction fails.
	 */
	public function test_place_bid_on_draft_auction() {
		$auction_id = $this->create_auction( array( 'post_status' => 'draft' ) );
		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );

		$result = Community_Auctions_Auction_Engine::place_bid( $auction_id, $user_id, 15.00 );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertEquals( 'ca_auction_not_live', $result->get_error_code() );
	}

	/**
	 * Test consecutive bids update correctly.
	 */
	public function test_consecutive_bids() {
		$auction_id = $this->create_auction();
		$user1 = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$user2 = self::factory()->user->create( array( 'role' => 'administrator' ) );

		// Disable duplicate prevention.
		update_option( 'community_auctions_settings', array_merge(
			Community_Auctions_Settings::get_settings(),
			array( 'prevent_duplicate_highest' => 0 )
		) );

		Community_Auctions_Auction_Engine::place_bid( $auction_id, $user1, 15.00 );
		Community_Auctions_Auction_Engine::place_bid( $auction_id, $user2, 20.00 );
		Community_Auctions_Auction_Engine::place_bid( $auction_id, $user1, 25.00 );

		$this->assertEquals( 25.00, floatval( get_post_meta( $auction_id, 'ca_current_bid', true ) ) );
		$this->assertEquals( $user1, intval( get_post_meta( $auction_id, 'ca_current_bidder', true ) ) );

		$bid_count = Community_Auctions_Bid_Repository::count_auction_bids( $auction_id );
		$this->assertEquals( 3, $bid_count );
	}
}
