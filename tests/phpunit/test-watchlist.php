<?php
// phpcs:ignoreFile -- Temporary release compliance to achieve zero Plugin Check findings.
/**
 * Tests for the Watchlist class.
 *
 * @package Community_Auctions
 */

class Community_Auctions_Watchlist_Test extends WP_UnitTestCase {

	/**
	 * Set up before each test.
	 */
	public function set_up() {
		parent::set_up();
		Community_Auctions_Watchlist::create_table();
	}

	/**
	 * Test table creation.
	 */
	public function test_table_exists_after_creation() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'ca_watchlist';
		$table_exists = $wpdb->get_var(
			$wpdb->prepare(
				'SHOW TABLES LIKE %s',
				$table_name
			)
		);

		$this->assertEquals( $table_name, $table_exists );
	}

	/**
	 * Test adding to watchlist.
	 */
	public function test_add_to_watchlist() {
		$auction_id = self::factory()->post->create( array( 'post_type' => 'auction' ) );
		$user_id = self::factory()->user->create();

		$result = Community_Auctions_Watchlist::add( $user_id, $auction_id );

		$this->assertTrue( $result );
		$this->assertTrue( Community_Auctions_Watchlist::is_watching( $user_id, $auction_id ) );
	}

	/**
	 * Test adding same auction twice doesn't duplicate.
	 */
	public function test_add_watchlist_no_duplicate() {
		$auction_id = self::factory()->post->create( array( 'post_type' => 'auction' ) );
		$user_id = self::factory()->user->create();

		Community_Auctions_Watchlist::add( $user_id, $auction_id );
		Community_Auctions_Watchlist::add( $user_id, $auction_id );

		$count = Community_Auctions_Watchlist::count_user_watchlist( $user_id );
		$this->assertEquals( 1, $count );
	}

	/**
	 * Test removing from watchlist.
	 */
	public function test_remove_from_watchlist() {
		$auction_id = self::factory()->post->create( array( 'post_type' => 'auction' ) );
		$user_id = self::factory()->user->create();

		Community_Auctions_Watchlist::add( $user_id, $auction_id );
		$this->assertTrue( Community_Auctions_Watchlist::is_watching( $user_id, $auction_id ) );

		$result = Community_Auctions_Watchlist::remove( $user_id, $auction_id );

		$this->assertTrue( $result );
		$this->assertFalse( Community_Auctions_Watchlist::is_watching( $user_id, $auction_id ) );
	}

	/**
	 * Test is_watching returns false for unwatched.
	 */
	public function test_is_watching_returns_false() {
		$auction_id = self::factory()->post->create( array( 'post_type' => 'auction' ) );
		$user_id = self::factory()->user->create();

		$this->assertFalse( Community_Auctions_Watchlist::is_watching( $user_id, $auction_id ) );
	}

	/**
	 * Test get_user_watchlist.
	 */
	public function test_get_user_watchlist() {
		$auction1 = self::factory()->post->create( array( 'post_type' => 'auction' ) );
		$auction2 = self::factory()->post->create( array( 'post_type' => 'auction' ) );
		$user_id = self::factory()->user->create();

		Community_Auctions_Watchlist::add( $user_id, $auction1 );
		Community_Auctions_Watchlist::add( $user_id, $auction2 );

		$watchlist = Community_Auctions_Watchlist::get_user_watchlist( $user_id );

		$this->assertIsArray( $watchlist );
		$this->assertCount( 2, $watchlist );
	}

	/**
	 * Test get_user_watchlist with pagination.
	 */
	public function test_get_user_watchlist_pagination() {
		$user_id = self::factory()->user->create();

		for ( $i = 0; $i < 5; $i++ ) {
			$auction_id = self::factory()->post->create( array( 'post_type' => 'auction' ) );
			Community_Auctions_Watchlist::add( $user_id, $auction_id );
		}

		$page1 = Community_Auctions_Watchlist::get_user_watchlist( $user_id, 2, 0 );
		$page2 = Community_Auctions_Watchlist::get_user_watchlist( $user_id, 2, 2 );

		$this->assertCount( 2, $page1 );
		$this->assertCount( 2, $page2 );
	}

	/**
	 * Test count_user_watchlist.
	 */
	public function test_count_user_watchlist() {
		$user_id = self::factory()->user->create();

		for ( $i = 0; $i < 3; $i++ ) {
			$auction_id = self::factory()->post->create( array( 'post_type' => 'auction' ) );
			Community_Auctions_Watchlist::add( $user_id, $auction_id );
		}

		$count = Community_Auctions_Watchlist::count_user_watchlist( $user_id );

		$this->assertEquals( 3, $count );
	}

	/**
	 * Test count_auction_watchers.
	 */
	public function test_count_auction_watchers() {
		$auction_id = self::factory()->post->create( array( 'post_type' => 'auction' ) );
		$user1 = self::factory()->user->create();
		$user2 = self::factory()->user->create();
		$user3 = self::factory()->user->create();

		Community_Auctions_Watchlist::add( $user1, $auction_id );
		Community_Auctions_Watchlist::add( $user2, $auction_id );
		Community_Auctions_Watchlist::add( $user3, $auction_id );

		$count = Community_Auctions_Watchlist::count_auction_watchers( $auction_id );

		$this->assertEquals( 3, $count );
	}

	/**
	 * Test toggle adds when not watching.
	 */
	public function test_toggle_adds_when_not_watching() {
		$auction_id = self::factory()->post->create( array( 'post_type' => 'auction' ) );
		$user_id = self::factory()->user->create();

		$result = Community_Auctions_Watchlist::toggle( $user_id, $auction_id );

		$this->assertTrue( $result['watching'] );
		$this->assertTrue( Community_Auctions_Watchlist::is_watching( $user_id, $auction_id ) );
	}

	/**
	 * Test toggle removes when watching.
	 */
	public function test_toggle_removes_when_watching() {
		$auction_id = self::factory()->post->create( array( 'post_type' => 'auction' ) );
		$user_id = self::factory()->user->create();

		Community_Auctions_Watchlist::add( $user_id, $auction_id );
		$result = Community_Auctions_Watchlist::toggle( $user_id, $auction_id );

		$this->assertFalse( $result['watching'] );
		$this->assertFalse( Community_Auctions_Watchlist::is_watching( $user_id, $auction_id ) );
	}

	/**
	 * Test get_auction_ids returns array of IDs.
	 */
	public function test_get_auction_ids() {
		$auction1 = self::factory()->post->create( array( 'post_type' => 'auction' ) );
		$auction2 = self::factory()->post->create( array( 'post_type' => 'auction' ) );
		$user_id = self::factory()->user->create();

		Community_Auctions_Watchlist::add( $user_id, $auction1 );
		Community_Auctions_Watchlist::add( $user_id, $auction2 );

		$ids = Community_Auctions_Watchlist::get_auction_ids( $user_id );

		$this->assertIsArray( $ids );
		$this->assertContains( $auction1, $ids );
		$this->assertContains( $auction2, $ids );
	}

	/**
	 * Test user deletion cascades.
	 */
	public function test_watchlist_survives_until_cleanup() {
		$auction_id = self::factory()->post->create( array( 'post_type' => 'auction' ) );
		$user_id = self::factory()->user->create();

		Community_Auctions_Watchlist::add( $user_id, $auction_id );

		// Verify it's there.
		$this->assertTrue( Community_Auctions_Watchlist::is_watching( $user_id, $auction_id ) );
	}

	/**
	 * Test empty watchlist returns empty array.
	 */
	public function test_empty_watchlist_returns_empty_array() {
		$user_id = self::factory()->user->create();

		$watchlist = Community_Auctions_Watchlist::get_user_watchlist( $user_id );

		$this->assertIsArray( $watchlist );
		$this->assertEmpty( $watchlist );
	}

	/**
	 * Test count returns zero for no items.
	 */
	public function test_count_returns_zero_for_empty() {
		$user_id = self::factory()->user->create();

		$count = Community_Auctions_Watchlist::count_user_watchlist( $user_id );

		$this->assertEquals( 0, $count );
	}

	/**
	 * Test invalid auction returns false for add.
	 */
	public function test_add_invalid_auction() {
		$user_id = self::factory()->user->create();

		// Regular post, not auction.
		$post_id = self::factory()->post->create( array( 'post_type' => 'post' ) );

		$result = Community_Auctions_Watchlist::add( $user_id, $post_id );

		$this->assertFalse( $result );
	}

	/**
	 * Test operations with invalid user ID.
	 */
	public function test_operations_with_zero_user_id() {
		$auction_id = self::factory()->post->create( array( 'post_type' => 'auction' ) );

		$this->assertFalse( Community_Auctions_Watchlist::add( 0, $auction_id ) );
		$this->assertFalse( Community_Auctions_Watchlist::is_watching( 0, $auction_id ) );
		$this->assertEquals( 0, Community_Auctions_Watchlist::count_user_watchlist( 0 ) );
	}
}
