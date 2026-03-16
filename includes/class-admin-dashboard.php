<?php
/**
 * Admin Dashboard Widget
 *
 * Provides auction statistics widget for the WordPress admin dashboard.
 *
 * @package Community_Auctions
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Community_Auctions_Admin_Dashboard
 *
 * Handles the admin dashboard widget functionality.
 *
 * @since 1.0.0
 */
class Community_Auctions_Admin_Dashboard {
    public static function register() {
        add_action( 'wp_dashboard_setup', array( __CLASS__, 'add_widget' ) );
    }

    public static function add_widget() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        wp_add_dashboard_widget(
            'community_auctions_dashboard',
            __( 'Community Auctions', 'community-auctions' ),
            array( __CLASS__, 'render_widget' )
        );
    }

    public static function render_widget() {
        $counts = wp_count_posts( 'auction' );
        $live = isset( $counts->ca_live ) ? intval( $counts->ca_live ) : 0;
        $pending = isset( $counts->ca_pending ) ? intval( $counts->ca_pending ) : 0;
        $ended = isset( $counts->ca_ended ) ? intval( $counts->ca_ended ) : 0;

        $pending_payments = self::count_pending_payments();

        echo '<p><strong>' . esc_html__( 'Live auctions:', 'community-auctions' ) . '</strong> ' . esc_html( $live ) . '</p>';
        echo '<p><strong>' . esc_html__( 'Pending approval:', 'community-auctions' ) . '</strong> ' . esc_html( $pending ) . '</p>';
        echo '<p><strong>' . esc_html__( 'Ended auctions:', 'community-auctions' ) . '</strong> ' . esc_html( $ended ) . '</p>';
        echo '<p><strong>' . esc_html__( 'Pending payments:', 'community-auctions' ) . '</strong> ' . esc_html( $pending_payments ) . '</p>';

        echo '<p><a href="' . esc_url( admin_url( 'edit.php?post_type=auction' ) ) . '">' . esc_html__( 'Manage auctions', 'community-auctions' ) . '</a></p>';
        echo '<p><a href="' . esc_url( admin_url( 'options-general.php?page=community-auctions' ) ) . '">' . esc_html__( 'Settings', 'community-auctions' ) . '</a></p>';
    }

    private static function count_pending_payments() {
        $query = new WP_Query( array(
            'post_type'      => 'auction',
            'post_status'    => array( 'ca_ended', 'ca_closed' ),
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'meta_query'     => array(
                array(
                    'key'     => 'ca_order_id',
                    'compare' => 'EXISTS',
                ),
                array(
                    'key'     => 'ca_paid_at',
                    'compare' => 'NOT EXISTS',
                ),
            ),
        ) );

        return intval( $query->found_posts );
    }
}
