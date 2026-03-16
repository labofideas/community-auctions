<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$group_id = function_exists( 'groups_get_current_group_id' ) ? groups_get_current_group_id() : 0;

echo '<h2>' . esc_html__( 'Group Auctions', 'community-auctions' ) . '</h2>';

if ( $group_id ) {
    $status = isset( $_GET['ca_status'] ) ? sanitize_text_field( wp_unslash( $_GET['ca_status'] ) ) : 'live';
    if ( ! in_array( $status, array( 'live', 'ended', 'all' ), true ) ) {
        $status = 'live';
    }
    $page = isset( $_GET['ca_page'] ) ? max( 1, absint( $_GET['ca_page'] ) ) : 1;

    echo '<nav class="community-auctions-group-filters" aria-label="' . esc_attr__( 'Group auction filters', 'community-auctions' ) . '">';
    echo '<a href="' . esc_url( add_query_arg( array( 'ca_status' => 'live', 'ca_page' => 1 ) ) ) . '">' . esc_html__( 'Live', 'community-auctions' ) . '</a> | ';
    echo '<a href="' . esc_url( add_query_arg( array( 'ca_status' => 'ended', 'ca_page' => 1 ) ) ) . '">' . esc_html__( 'Ended', 'community-auctions' ) . '</a> | ';
    echo '<a href="' . esc_url( add_query_arg( array( 'ca_status' => 'all', 'ca_page' => 1 ) ) ) . '">' . esc_html__( 'All', 'community-auctions' ) . '</a>';
    echo '</nav>';

    echo Community_Auctions_Frontend_Forms::render_submit_form();
    echo Community_Auctions_Auction_Shortcodes::render_list( array(
        'group_id' => $group_id,
        'status'   => $status,
        'paged'    => $page,
    ) );
} else {
    echo '<p>' . esc_html__( 'Group auctions are unavailable.', 'community-auctions' ) . '</p>';
}
