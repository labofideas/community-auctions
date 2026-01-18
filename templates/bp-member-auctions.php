<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$user_id = function_exists( 'bp_displayed_user_id' ) ? bp_displayed_user_id() : get_current_user_id();
$status = isset( $_GET['ca_status'] ) ? sanitize_text_field( wp_unslash( $_GET['ca_status'] ) ) : 'all';
if ( ! in_array( $status, array( 'live', 'ended', 'all' ), true ) ) {
    $status = 'all';
}
$page = isset( $_GET['ca_page'] ) ? max( 1, absint( $_GET['ca_page'] ) ) : 1;

echo '<h2>' . esc_html__( 'My Auctions', 'community-auctions' ) . '</h2>';

echo '<nav class="community-auctions-member-filters" aria-label="' . esc_attr__( 'Member auction filters', 'community-auctions' ) . '">';
echo '<a href="' . esc_url( add_query_arg( array( 'ca_status' => 'live', 'ca_page' => 1 ) ) ) . '">' . esc_html__( 'Live', 'community-auctions' ) . '</a> | ';
echo '<a href="' . esc_url( add_query_arg( array( 'ca_status' => 'ended', 'ca_page' => 1 ) ) ) . '">' . esc_html__( 'Ended', 'community-auctions' ) . '</a> | ';
echo '<a href="' . esc_url( add_query_arg( array( 'ca_status' => 'all', 'ca_page' => 1 ) ) ) . '">' . esc_html__( 'All', 'community-auctions' ) . '</a>';
echo '</nav>';

echo Community_Auctions_Auction_Shortcodes::render_list( array(
    'author_id' => $user_id,
    'status'    => $status,
    'per_page'  => 10,
    'paged'     => $page,
) );
