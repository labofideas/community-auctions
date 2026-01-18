<?php

class Community_Auctions_FluentCart_Webhook_Test extends WP_UnitTestCase {
    public function test_webhook_requires_secret() {
        update_option( 'community_auctions_settings', array(
            'payment_provider' => 'fluentcart',
            'fluentcart_webhook_secret' => '',
        ) );

        $request = new WP_REST_Request( 'POST', '/community-auctions/v1/fluentcart/webhook' );
        $request->set_json_params( array( 'order_id' => 1, 'payment_status' => 'paid' ) );

        $response = Community_Auctions_Payment_FluentCart::handle_webhook( $request );
        $this->assertEquals( 401, $response->get_status() );
    }

    public function test_webhook_rejects_invalid_secret() {
        update_option( 'community_auctions_settings', array(
            'payment_provider' => 'fluentcart',
            'fluentcart_webhook_secret' => 'expected-secret',
        ) );

        $request = new WP_REST_Request( 'POST', '/community-auctions/v1/fluentcart/webhook' );
        $request->set_json_params( array( 'order_id' => 1, 'payment_status' => 'paid' ) );
        $request->set_header( 'X-CA-Webhook-Secret', 'wrong-secret' );

        $response = Community_Auctions_Payment_FluentCart::handle_webhook( $request );
        $this->assertEquals( 401, $response->get_status() );
    }
}
