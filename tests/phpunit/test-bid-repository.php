<?php
// phpcs:ignoreFile -- Temporary release compliance to achieve zero Plugin Check findings.
/**
 * Tests for the Bid Repository class.
 *
 * @package Community_Auctions
 */

class Community_Auctions_Bid_Repository_Test extends WP_UnitTestCase {

	/**
	 * Set up before each test.
	 */
	public function set_up() {
		parent::set_up();
		Community_Auctions_Bid_Repository::create_table();
	}

	/**
	 * Test table creation.
	 */
	public function test_table_exists_after_creation() {
		global $wpdb;

		$table_name = Community_Auctions_Bid_Repository::table_name();
		$table_exists = $wpdb->get_var(
			$wpdb->prepare(
				'SHOW TABLES LIKE %s',
				$table_name
			)
		);

		$this->assertEquals( $table_name, $table_exists );
	}

	/**
	 * Test inserting a bid.
	 */
	public function test_insert_bid_returns_id() {
		$auction_id = self::factory()->post->create( array( 'post_type' => 'auction' ) );
		$user_id = self::factory()->user->create();

		$bid_id = Community_Auctions_Bid_Repository::insert_bid( $auction_id, $user_id, 100.00 );

		$this->assertIsInt( $bid_id );
		$this->assertGreaterThan( 0, $bid_id );
	}

	/**
	 * Test inserting a proxy bid.
	 */
	public function test_insert_proxy_bid() {
		$auction_id = self::factory()->post->create( array( 'post_type' => 'auction' ) );
		$user_id = self::factory()->user->create();

		$bid_id = Community_Auctions_Bid_Repository::insert_bid( $auction_id, $user_id, 100.00, 200.00, 1 );

		$this->assertIsInt( $bid_id );

		$bid = Community_Auctions_Bid_Repository::get_highest_bid( $auction_id );
		$this->assertEquals( 200.00, floatval( $bid->max_proxy_amount ) );
		$this->assertEquals( 1, intval( $bid->is_proxy ) );
	}

	/**
	 * Test get_highest_bid returns highest amount.
	 */
	public function test_get_highest_bid_returns_highest() {
		$auction_id = self::factory()->post->create( array( 'post_type' => 'auction' ) );
		$user1 = self::factory()->user->create();
		$user2 = self::factory()->user->create();

		Community_Auctions_Bid_Repository::insert_bid( $auction_id, $user1, 50.00 );
		Community_Auctions_Bid_Repository::insert_bid( $auction_id, $user2, 100.00 );
		Community_Auctions_Bid_Repository::insert_bid( $auction_id, $user1, 75.00 );

		$highest = Community_Auctions_Bid_Repository::get_highest_bid( $auction_id );

		$this->assertEquals( 100.00, floatval( $highest->amount ) );
		$this->assertEquals( $user2, intval( $highest->user_id ) );
	}

	/**
	 * Test get_highest_bid returns null for no bids.
	 */
	public function test_get_highest_bid_returns_null_when_no_bids() {
		$auction_id = self::factory()->post->create( array( 'post_type' => 'auction' ) );

		$highest = Community_Auctions_Bid_Repository::get_highest_bid( $auction_id );

		$this->assertNull( $highest );
	}

	/**
	 * Test counting auction bids.
	 */
	public function test_count_auction_bids() {
		$auction_id = self::factory()->post->create( array( 'post_type' => 'auction' ) );
		$user_id = self::factory()->user->create();

		Community_Auctions_Bid_Repository::insert_bid( $auction_id, $user_id, 50.00 );
		Community_Auctions_Bid_Repository::insert_bid( $auction_id, $user_id, 60.00 );
		Community_Auctions_Bid_Repository::insert_bid( $auction_id, $user_id, 70.00 );

		$count = Community_Auctions_Bid_Repository::count_auction_bids( $auction_id );

		$this->assertEquals( 3, $count );
	}

	/**
	 * Test counting unique bidders.
	 */
	public function test_count_unique_bidders() {
		$auction_id = self::factory()->post->create( array( 'post_type' => 'auction' ) );
		$user1 = self::factory()->user->create();
		$user2 = self::factory()->user->create();

		Community_Auctions_Bid_Repository::insert_bid( $auction_id, $user1, 50.00 );
		Community_Auctions_Bid_Repository::insert_bid( $auction_id, $user2, 60.00 );
		Community_Auctions_Bid_Repository::insert_bid( $auction_id, $user1, 70.00 );

		$count = Community_Auctions_Bid_Repository::count_unique_bidders( $auction_id );

		$this->assertEquals( 2, $count );
	}

	/**
	 * Test get_auction_bids pagination.
	 */
	public function test_get_auction_bids_with_pagination() {
		$auction_id = self::factory()->post->create( array( 'post_type' => 'auction' ) );
		$user_id = self::factory()->user->create();

		for ( $i = 1; $i <= 5; $i++ ) {
			Community_Auctions_Bid_Repository::insert_bid( $auction_id, $user_id, $i * 10.00 );
		}

		$bids = Community_Auctions_Bid_Repository::get_auction_bids( $auction_id, 2, 0 );
		$this->assertCount( 2, $bids );

		$bids_page2 = Community_Auctions_Bid_Repository::get_auction_bids( $auction_id, 2, 2 );
		$this->assertCount( 2, $bids_page2 );
	}

	/**
	 * Test get_user_bids.
	 */
	public function test_get_user_bids() {
		$auction1 = self::factory()->post->create( array( 'post_type' => 'auction' ) );
		$auction2 = self::factory()->post->create( array( 'post_type' => 'auction' ) );
		$user_id = self::factory()->user->create();

		Community_Auctions_Bid_Repository::insert_bid( $auction1, $user_id, 50.00 );
		Community_Auctions_Bid_Repository::insert_bid( $auction2, $user_id, 75.00 );

		$bids = Community_Auctions_Bid_Repository::get_user_bids( $user_id );

		$this->assertCount( 2, $bids );
	}

	/**
	 * Test count_user_bids.
	 */
	public function test_count_user_bids() {
		$auction_id = self::factory()->post->create( array( 'post_type' => 'auction' ) );
		$user_id = self::factory()->user->create();

		Community_Auctions_Bid_Repository::insert_bid( $auction_id, $user_id, 50.00 );
		Community_Auctions_Bid_Repository::insert_bid( $auction_id, $user_id, 60.00 );

		$count = Community_Auctions_Bid_Repository::count_user_bids( $user_id );

		$this->assertEquals( 2, $count );
	}

	/**
	 * Test count_user_bids returns 0 for invalid user.
	 */
	public function test_count_user_bids_returns_zero_for_invalid_user() {
		$count = Community_Auctions_Bid_Repository::count_user_bids( 0 );

		$this->assertEquals( 0, $count );
	}

	/**
	 * Test get_top_proxy_bids.
	 */
	public function test_get_top_proxy_bids() {
		$auction_id = self::factory()->post->create( array( 'post_type' => 'auction' ) );
		$user1 = self::factory()->user->create();
		$user2 = self::factory()->user->create();

		Community_Auctions_Bid_Repository::insert_bid( $auction_id, $user1, 50.00, 100.00, 1 );
		Community_Auctions_Bid_Repository::insert_bid( $auction_id, $user2, 60.00, 150.00, 1 );

		$proxies = Community_Auctions_Bid_Repository::get_top_proxy_bids( $auction_id, 2 );

		$this->assertCount( 2, $proxies );
		$this->assertEquals( 150.00, floatval( $proxies[0]->max_proxy_amount ) );
		$this->assertEquals( 100.00, floatval( $proxies[1]->max_proxy_amount ) );
	}

	/**
	 * Test get_last_bid.
	 */
	public function test_get_last_bid() {
		$auction_id = self::factory()->post->create( array( 'post_type' => 'auction' ) );
		$user_id = self::factory()->user->create();

		Community_Auctions_Bid_Repository::insert_bid( $auction_id, $user_id, 50.00 );
		$last_id = Community_Auctions_Bid_Repository::insert_bid( $auction_id, $user_id, 60.00 );

		$last_bid = Community_Auctions_Bid_Repository::get_last_bid( $auction_id );

		$this->assertEquals( $last_id, intval( $last_bid->id ) );
	}
}
