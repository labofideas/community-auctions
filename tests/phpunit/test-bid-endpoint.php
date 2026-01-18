<?php

class Community_Auctions_Bid_Endpoint_Test extends WP_UnitTestCase {
    private function set_provider( $provider ) {
        update_option( 'community_auctions_settings', array(
            'payment_provider' => $provider,
            'allowed_roles_create' => array( 'administrator' ),
            'allowed_roles_bid' => array( 'administrator' ),
        ) );
    }

    public function test_bid_endpoint_requires_capability() {
        $this->set_provider( 'woocommerce' );
        $user_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
        wp_set_current_user( $user_id );

        $request = new WP_REST_Request( 'POST', '/community-auctions/v1/bid' );
        $request->set_param( 'auction_id', 1 );
        $request->set_param( 'amount', 10 );
        $request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );

        $response = rest_get_server()->dispatch( $request );
        $this->assertEquals( 401, $response->get_status() );
    }

    public function test_bid_endpoint_requires_auth() {
        $this->set_provider( 'woocommerce' );
        $request = new WP_REST_Request( 'POST', '/community-auctions/v1/bid' );
        $request->set_param( 'auction_id', 1 );
        $request->set_param( 'amount', 10 );

        $response = rest_get_server()->dispatch( $request );
        $this->assertEquals( 401, $response->get_status() );
    }

    public function test_bid_endpoint_requires_nonce() {
        $this->set_provider( 'woocommerce' );
        $user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
        wp_set_current_user( $user_id );

        $request = new WP_REST_Request( 'POST', '/community-auctions/v1/bid' );
        $request->set_param( 'auction_id', 1 );
        $request->set_param( 'amount', 10 );

        $response = rest_get_server()->dispatch( $request );
        $this->assertEquals( 401, $response->get_status() );
    }

    public function test_bid_endpoint_requires_provider_selection() {
        $user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
        wp_set_current_user( $user_id );

        $request = new WP_REST_Request( 'POST', '/community-auctions/v1/bid' );
        $request->set_param( 'auction_id', 1 );
        $request->set_param( 'amount', 10 );
        $request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );

        $response = rest_get_server()->dispatch( $request );
        $this->assertEquals( 401, $response->get_status() );
    }
}
