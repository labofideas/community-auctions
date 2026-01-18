<?php

class Community_Auctions_CPT_Test extends WP_UnitTestCase {
    public function test_auction_post_type_registered() {
        $this->assertTrue( post_type_exists( 'auction' ) );
    }

    public function test_shortcodes_registered() {
        $this->assertTrue( shortcode_exists( 'community_auctions_list' ) );
        $this->assertTrue( shortcode_exists( 'community_auction_single' ) );
        $this->assertTrue( shortcode_exists( 'community_auction_submit' ) );
    }
}
