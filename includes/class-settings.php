<?php
/**
 * Plugin Settings
 *
 * Manages plugin configuration options.
 *
 * @package Community_Auctions
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Community_Auctions_Settings
 *
 * Handles settings registration, retrieval, and defaults.
 *
 * @since 1.0.0
 */
class Community_Auctions_Settings {
    const OPTION_KEY = 'community_auctions_settings';

    public static function register() {
        // Menu is now handled by Community_Auctions_Admin_Panel.
        add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
    }

    public static function register_settings() {
        register_setting(
            'community_auctions',
            self::OPTION_KEY,
            array( __CLASS__, 'sanitize_settings' )
        );

        add_settings_section(
            'community_auctions_general',
            __( 'General Settings', 'community-auctions' ),
            '__return_false',
            'community-auctions'
        );

        add_settings_field(
            'payment_provider',
            __( 'Payment Provider', 'community-auctions' ),
            array( __CLASS__, 'render_provider_field' ),
            'community-auctions',
            'community_auctions_general'
        );

        add_settings_field(
            'allowed_roles_create',
            __( 'Auction Creation Roles', 'community-auctions' ),
            array( __CLASS__, 'render_roles_field' ),
            'community-auctions',
            'community_auctions_general',
            array(
                'key'   => 'allowed_roles_create',
                'label' => __( 'Roles allowed to create auctions.', 'community-auctions' ),
            )
        );

        add_settings_field(
            'allowed_roles_bid',
            __( 'Bidding Roles', 'community-auctions' ),
            array( __CLASS__, 'render_roles_field' ),
            'community-auctions',
            'community_auctions_general',
            array(
                'key'   => 'allowed_roles_bid',
                'label' => __( 'Roles allowed to place bids.', 'community-auctions' ),
            )
        );

        add_settings_field(
            'admin_approval',
            __( 'Require Admin Approval', 'community-auctions' ),
            array( __CLASS__, 'render_checkbox_field' ),
            'community-auctions',
            'community_auctions_general',
            array(
                'key'   => 'admin_approval',
                'label' => __( 'Require approval before auctions go live.', 'community-auctions' ),
            )
        );

        add_settings_field(
            'proxy_default',
            __( 'Proxy Bidding Default', 'community-auctions' ),
            array( __CLASS__, 'render_checkbox_field' ),
            'community-auctions',
            'community_auctions_general',
            array(
                'key'   => 'proxy_default',
                'label' => __( 'Enable proxy bidding by default for new auctions.', 'community-auctions' ),
            )
        );

        add_settings_field(
            'group_visibility_default',
            __( 'Group Visibility Default', 'community-auctions' ),
            array( __CLASS__, 'render_visibility_field' ),
            'community-auctions',
            'community_auctions_general'
        );

        add_settings_field(
            'anti_sniping_minutes',
            __( 'Anti-Sniping (minutes)', 'community-auctions' ),
            array( __CLASS__, 'render_number_field' ),
            'community-auctions',
            'community_auctions_general',
            array(
                'key'   => 'anti_sniping_minutes',
                'min'   => 0,
                'step'  => 1,
            )
        );

        add_settings_field(
            'payment_reminder_hours',
            __( 'Payment Reminder (hours)', 'community-auctions' ),
            array( __CLASS__, 'render_number_field' ),
            'community-auctions',
            'community_auctions_general',
            array(
                'key'   => 'payment_reminder_hours',
                'min'   => 1,
                'step'  => 1,
            )
        );

        add_settings_field(
            'enable_cod',
            __( 'Cash on Delivery (WooCommerce)', 'community-auctions' ),
            array( __CLASS__, 'render_checkbox_field' ),
            'community-auctions',
            'community_auctions_general',
            array(
                'key'   => 'enable_cod',
                'label' => __( 'Allow COD for auction orders (WooCommerce only).', 'community-auctions' ),
            )
        );

        add_settings_field(
            'fluentcart_webhook_secret',
            __( 'FluentCart Webhook Secret', 'community-auctions' ),
            array( __CLASS__, 'render_fluentcart_webhook_field' ),
            'community-auctions',
            'community_auctions_general',
            array(
                'key' => 'fluentcart_webhook_secret',
            )
        );

        add_settings_field(
            'email_subject_prefix',
            __( 'Email Subject Prefix', 'community-auctions' ),
            array( __CLASS__, 'render_text_field' ),
            'community-auctions',
            'community_auctions_general',
            array(
                'key' => 'email_subject_prefix',
            )
        );

        add_settings_field(
            'email_footer_text',
            __( 'Email Footer Text', 'community-auctions' ),
            array( __CLASS__, 'render_textarea_field' ),
            'community-auctions',
            'community_auctions_general',
            array(
                'key' => 'email_footer_text',
            )
        );

        add_settings_field(
            'email_preview',
            __( 'Email Preview', 'community-auctions' ),
            array( __CLASS__, 'render_email_preview' ),
            'community-auctions',
            'community_auctions_general'
        );

        add_settings_section(
            'community_auctions_bidding',
            __( 'Bidding Rules', 'community-auctions' ),
            '__return_false',
            'community-auctions'
        );

        add_settings_field(
            'block_seller_bidding',
            __( 'Block Seller Bidding', 'community-auctions' ),
            array( __CLASS__, 'render_checkbox_field' ),
            'community-auctions',
            'community_auctions_bidding',
            array(
                'key'   => 'block_seller_bidding',
                'label' => __( 'Prevent sellers from bidding on their own auctions.', 'community-auctions' ),
            )
        );

        add_settings_field(
            'prevent_duplicate_highest',
            __( 'Prevent Duplicate Bids', 'community-auctions' ),
            array( __CLASS__, 'render_checkbox_field' ),
            'community-auctions',
            'community_auctions_bidding',
            array(
                'key'   => 'prevent_duplicate_highest',
                'label' => __( 'Prevent users from bidding when they are already the highest bidder.', 'community-auctions' ),
            )
        );

        add_settings_field(
            'max_bid_limit',
            __( 'Maximum Bid Limit', 'community-auctions' ),
            array( __CLASS__, 'render_number_field' ),
            'community-auctions',
            'community_auctions_bidding',
            array(
                'key'   => 'max_bid_limit',
                'min'   => 0,
                'step'  => 0.01,
                'label' => __( 'Set to 0 for no limit.', 'community-auctions' ),
            )
        );

        add_settings_section(
            'community_auctions_realtime',
            __( 'Real-time Updates', 'community-auctions' ),
            '__return_false',
            'community-auctions'
        );

        add_settings_field(
            'realtime_enabled',
            __( 'Enable Real-time Updates', 'community-auctions' ),
            array( __CLASS__, 'render_checkbox_field' ),
            'community-auctions',
            'community_auctions_realtime',
            array(
                'key'   => 'realtime_enabled',
                'label' => __( 'Automatically refresh bid information on auction pages.', 'community-auctions' ),
            )
        );

        add_settings_field(
            'realtime_poll_interval',
            __( 'Poll Interval (seconds)', 'community-auctions' ),
            array( __CLASS__, 'render_number_field' ),
            'community-auctions',
            'community_auctions_realtime',
            array(
                'key'   => 'realtime_poll_interval',
                'min'   => 5,
                'step'  => 1,
                'label' => __( 'Minimum 5 seconds.', 'community-auctions' ),
            )
        );

        add_settings_section(
            'community_auctions_buy_now',
            __( 'Buy It Now', 'community-auctions' ),
            '__return_false',
            'community-auctions'
        );

        add_settings_field(
            'buy_now_enabled',
            __( 'Enable Buy It Now', 'community-auctions' ),
            array( __CLASS__, 'render_checkbox_field' ),
            'community-auctions',
            'community_auctions_buy_now',
            array(
                'key'   => 'buy_now_enabled',
                'label' => __( 'Allow sellers to set a Buy It Now price.', 'community-auctions' ),
            )
        );

        add_settings_field(
            'buy_now_ends_auction',
            __( 'Buy It Now Ends Auction', 'community-auctions' ),
            array( __CLASS__, 'render_checkbox_field' ),
            'community-auctions',
            'community_auctions_buy_now',
            array(
                'key'   => 'buy_now_ends_auction',
                'label' => __( 'Immediately end auction when someone uses Buy It Now.', 'community-auctions' ),
            )
        );

        add_settings_section(
            'community_auctions_fees',
            __( 'Fees', 'community-auctions' ),
            '__return_false',
            'community-auctions'
        );

        add_settings_field(
            'listing_fee_enabled',
            __( 'Listing Fee', 'community-auctions' ),
            array( __CLASS__, 'render_checkbox_field' ),
            'community-auctions',
            'community_auctions_fees',
            array(
                'key'   => 'listing_fee_enabled',
                'label' => __( 'Enable listing fee.', 'community-auctions' ),
            )
        );

        add_settings_field(
            'listing_fee_mode',
            __( 'Listing Fee Mode', 'community-auctions' ),
            array( __CLASS__, 'render_fee_mode_field' ),
            'community-auctions',
            'community_auctions_fees',
            array(
                'key' => 'listing_fee_mode',
            )
        );

        add_settings_field(
            'listing_fee_amount',
            __( 'Listing Fee Amount', 'community-auctions' ),
            array( __CLASS__, 'render_number_field' ),
            'community-auctions',
            'community_auctions_fees',
            array(
                'key'  => 'listing_fee_amount',
                'min'  => 0,
                'step' => 0.01,
            )
        );

        add_settings_field(
            'success_fee_enabled',
            __( 'Success Fee', 'community-auctions' ),
            array( __CLASS__, 'render_checkbox_field' ),
            'community-auctions',
            'community_auctions_fees',
            array(
                'key'   => 'success_fee_enabled',
                'label' => __( 'Enable success fee.', 'community-auctions' ),
            )
        );

        add_settings_field(
            'success_fee_mode',
            __( 'Success Fee Mode', 'community-auctions' ),
            array( __CLASS__, 'render_fee_mode_field' ),
            'community-auctions',
            'community_auctions_fees',
            array(
                'key' => 'success_fee_mode',
            )
        );

        add_settings_field(
            'success_fee_amount',
            __( 'Success Fee Amount', 'community-auctions' ),
            array( __CLASS__, 'render_number_field' ),
            'community-auctions',
            'community_auctions_fees',
            array(
                'key'  => 'success_fee_amount',
                'min'  => 0,
                'step' => 0.01,
            )
        );
    }

    public static function sanitize_settings( $input ) {
        $output = array();

        // Labels & URL settings.
        $output['label_singular'] = sanitize_text_field( $input['label_singular'] ?? 'Auction' );
        $output['label_plural']   = sanitize_text_field( $input['label_plural'] ?? 'Auctions' );

        // Sanitize URL slug - lowercase, alphanumeric and hyphens only.
        $new_slug = sanitize_title( $input['url_slug'] ?? 'auctions' );
        $new_slug = preg_replace( '/[^a-z0-9-]/', '', $new_slug );
        $output['url_slug'] = ! empty( $new_slug ) ? $new_slug : 'auctions';

        // Check if slug changed and flush rewrite rules.
        $old_settings = get_option( self::OPTION_KEY, array() );
        $old_slug     = $old_settings['url_slug'] ?? 'auctions';
        if ( $old_slug !== $output['url_slug'] ) {
            // Schedule a rewrite rules flush.
            update_option( 'community_auctions_flush_rewrite', true );
        }

        $output['payment_provider'] = in_array( $input['payment_provider'] ?? '', array( 'woocommerce', 'fluentcart' ), true )
            ? $input['payment_provider']
            : '';

        $output['allowed_roles_create'] = self::sanitize_roles( $input['allowed_roles_create'] ?? array() );
        $output['allowed_roles_bid'] = self::sanitize_roles( $input['allowed_roles_bid'] ?? array() );

        $output['admin_approval'] = ! empty( $input['admin_approval'] ) ? 1 : 0;
        $output['proxy_default'] = ! empty( $input['proxy_default'] ) ? 1 : 0;

        $visibility = $input['group_visibility_default'] ?? 'public';
        $output['group_visibility_default'] = in_array( $visibility, array( 'public', 'group_only' ), true ) ? $visibility : 'public';

        $output['anti_sniping_minutes'] = max( 0, intval( $input['anti_sniping_minutes'] ?? 0 ) );
        $output['payment_reminder_hours'] = max( 1, intval( $input['payment_reminder_hours'] ?? 24 ) );
        $output['email_subject_prefix'] = sanitize_text_field( $input['email_subject_prefix'] ?? '' );
        $output['email_footer_text'] = sanitize_textarea_field( $input['email_footer_text'] ?? '' );
        $output['enable_cod'] = ! empty( $input['enable_cod'] ) ? 1 : 0;
        $output['fluentcart_webhook_secret'] = sanitize_text_field( $input['fluentcart_webhook_secret'] ?? '' );

        $output['block_seller_bidding'] = ! empty( $input['block_seller_bidding'] ) ? 1 : 0;
        $output['prevent_duplicate_highest'] = ! empty( $input['prevent_duplicate_highest'] ) ? 1 : 0;
        $output['max_bid_limit'] = max( 0, floatval( $input['max_bid_limit'] ?? 0 ) );

        $output['realtime_enabled'] = ! empty( $input['realtime_enabled'] ) ? 1 : 0;
        $output['realtime_poll_interval'] = max( 5, intval( $input['realtime_poll_interval'] ?? 15 ) );

        $output['buy_now_enabled'] = ! empty( $input['buy_now_enabled'] ) ? 1 : 0;
        $output['buy_now_ends_auction'] = ! empty( $input['buy_now_ends_auction'] ) ? 1 : 0;

        $output['listing_fee_enabled'] = ! empty( $input['listing_fee_enabled'] ) ? 1 : 0;
        $output['listing_fee_mode'] = in_array( $input['listing_fee_mode'] ?? '', array( 'flat', 'percent' ), true )
            ? $input['listing_fee_mode']
            : 'flat';
        $output['listing_fee_amount'] = max( 0, floatval( $input['listing_fee_amount'] ?? 0 ) );

        $output['success_fee_enabled'] = ! empty( $input['success_fee_enabled'] ) ? 1 : 0;
        $output['success_fee_mode'] = in_array( $input['success_fee_mode'] ?? '', array( 'flat', 'percent' ), true )
            ? $input['success_fee_mode']
            : 'flat';
        $output['success_fee_amount'] = max( 0, floatval( $input['success_fee_amount'] ?? 0 ) );

        Community_Auctions_Plugin::refresh_caps( $output );

        return $output;
    }

    public static function maybe_show_provider_notice() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $has_woo = Community_Auctions_Plugin::is_woocommerce_active();
        $has_fluent = Community_Auctions_Plugin::is_fluentcart_active();
        if ( ! $has_woo || ! $has_fluent ) {
            return;
        }

        $settings = self::get_settings();
        if ( ! empty( $settings['payment_provider'] ) ) {
            return;
        }

        echo '<div class="notice notice-warning"><p>' .
            esc_html__( 'Both WooCommerce and FluentCart are active. Please select a payment provider for Community Auctions.', 'community-auctions' ) .
            '</p></div>';
    }

    public static function render_page() {
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__( 'Community Auctions', 'community-auctions' ) . '</h1>';
        echo '<form method="post" action="options.php">';
        settings_fields( 'community_auctions' );
        do_settings_sections( 'community-auctions' );
        submit_button();
        echo '</form>';
        echo '</div>';
    }

    public static function render_provider_field() {
        $settings = self::get_settings();
        $value = $settings['payment_provider'] ?? '';
        $has_woo = Community_Auctions_Plugin::is_woocommerce_active();
        $has_fluent = Community_Auctions_Plugin::is_fluentcart_active();

        echo '<select name="' . esc_attr( self::OPTION_KEY ) . '[payment_provider]">';
        echo '<option value="">' . esc_html__( 'Select a provider', 'community-auctions' ) . '</option>';
        if ( $has_woo ) {
            echo '<option value="woocommerce"' . selected( $value, 'woocommerce', false ) . '>' . esc_html__( 'WooCommerce', 'community-auctions' ) . '</option>';
        }
        if ( $has_fluent ) {
            echo '<option value="fluentcart"' . selected( $value, 'fluentcart', false ) . '>' . esc_html__( 'FluentCart', 'community-auctions' ) . '</option>';
        }
        echo '</select>';
    }

    public static function render_roles_field( $args ) {
        $settings = self::get_settings();
        $key = $args['key'] ?? '';
        $label = $args['label'] ?? '';
        $selected = isset( $settings[ $key ] ) && is_array( $settings[ $key ] ) ? $settings[ $key ] : array();
        $roles = wp_roles();

        if ( $label ) {
            echo '<p class="description">' . esc_html( $label ) . '</p>';
        }

        if ( ! $roles ) {
            echo '<p>' . esc_html__( 'No roles available.', 'community-auctions' ) . '</p>';
            return;
        }

        foreach ( $roles->roles as $role_key => $role_data ) {
            echo '<label style="display:block; margin:4px 0;">';
            echo '<input type="checkbox" name="' . esc_attr( self::OPTION_KEY ) . '[' . esc_attr( $key ) . '][]" value="' . esc_attr( $role_key ) . '" ' . checked( in_array( $role_key, $selected, true ), true, false ) . ' /> ';
            echo esc_html( $role_data['name'] );
            echo '</label>';
        }
    }

    public static function render_visibility_field() {
        $settings = self::get_settings();
        $value = $settings['group_visibility_default'] ?? 'public';
        echo '<select name="' . esc_attr( self::OPTION_KEY ) . '[group_visibility_default]">';
        echo '<option value="public"' . selected( $value, 'public', false ) . '>' . esc_html__( 'Public', 'community-auctions' ) . '</option>';
        echo '<option value="group_only"' . selected( $value, 'group_only', false ) . '>' . esc_html__( 'Group Only', 'community-auctions' ) . '</option>';
        echo '</select>';
    }

    public static function render_fee_mode_field( $args ) {
        $settings = self::get_settings();
        $key = $args['key'];
        $value = $settings[ $key ] ?? 'flat';
        echo '<select name="' . esc_attr( self::OPTION_KEY ) . '[' . esc_attr( $key ) . ']">';
        echo '<option value="flat"' . selected( $value, 'flat', false ) . '>' . esc_html__( 'Flat', 'community-auctions' ) . '</option>';
        echo '<option value="percent"' . selected( $value, 'percent', false ) . '>' . esc_html__( 'Percentage', 'community-auctions' ) . '</option>';
        echo '</select>';
    }

    public static function render_checkbox_field( $args ) {
        $settings = self::get_settings();
        $key = $args['key'];
        $label = $args['label'] ?? '';
        $checked = ! empty( $settings[ $key ] );

        echo '<label>';
        echo '<input type="checkbox" name="' . esc_attr( self::OPTION_KEY ) . '[' . esc_attr( $key ) . ']" value="1"' . checked( $checked, true, false ) . ' /> ';
        echo esc_html( $label );
        echo '</label>';
    }

    public static function render_number_field( $args ) {
        $settings = self::get_settings();
        $key = $args['key'];
        $min = isset( $args['min'] ) ? $args['min'] : 0;
        $step = isset( $args['step'] ) ? $args['step'] : 1;
        $value = $settings[ $key ] ?? '';

        echo '<input type="number" min="' . esc_attr( $min ) . '" step="' . esc_attr( $step ) . '" name="' . esc_attr( self::OPTION_KEY ) . '[' . esc_attr( $key ) . ']" value="' . esc_attr( $value ) . '" />';
    }

    public static function render_text_field( $args ) {
        $settings = self::get_settings();
        $key = $args['key'];
        $value = $settings[ $key ] ?? '';

        echo '<input type="text" name="' . esc_attr( self::OPTION_KEY ) . '[' . esc_attr( $key ) . ']" value="' . esc_attr( $value ) . '" class="regular-text" />';
    }

    public static function render_fluentcart_webhook_field( $args ) {
        $settings = self::get_settings();
        $key = $args['key'];
        $value = $settings[ $key ] ?? '';
        $url = rest_url( 'community-auctions/v1/fluentcart/webhook' );

        echo '<input type="text" name="' . esc_attr( self::OPTION_KEY ) . '[' . esc_attr( $key ) . ']" value="' . esc_attr( $value ) . '" class="regular-text" />';
        echo '<p class="description">' . esc_html__( 'Use this secret in FluentCart webhook settings.', 'community-auctions' ) . '</p>';
        echo '<p class="description">' . esc_html__( 'Webhook URL:', 'community-auctions' ) . ' ' . esc_html( $url ) . '</p>';
    }

    public static function render_textarea_field( $args ) {
        $settings = self::get_settings();
        $key = $args['key'];
        $value = $settings[ $key ] ?? '';

        echo '<textarea name="' . esc_attr( self::OPTION_KEY ) . '[' . esc_attr( $key ) . ']" rows="3" class="large-text">' . esc_textarea( $value ) . '</textarea>';
    }

    public static function render_email_preview() {
        echo Community_Auctions_Notifications::build_email_preview();
    }
    public static function get_settings() {
        $defaults = array(
            'label_singular'          => 'Auction',
            'label_plural'            => 'Auctions',
            'url_slug'                => 'auctions',
            'payment_provider'        => '',
            'allowed_roles_create'    => array( 'administrator' ),
            'allowed_roles_bid'       => array( 'administrator' ),
            'admin_approval'          => 0,
            'proxy_default'           => 0,
            'group_visibility_default'=> 'public',
            'anti_sniping_minutes'    => 0,
            'payment_reminder_hours'  => 24,
            'email_subject_prefix'    => '',
            'email_footer_text'       => '',
            'enable_cod'              => 0,
            'fluentcart_webhook_secret' => '',
            'block_seller_bidding'    => 1,
            'prevent_duplicate_highest' => 0,
            'max_bid_limit'           => 0,
            'realtime_enabled'        => 1,
            'realtime_poll_interval'  => 15,
            'buy_now_enabled'         => 0,
            'buy_now_ends_auction'    => 1,
            'listing_fee_enabled'     => 0,
            'listing_fee_mode'        => 'flat',
            'listing_fee_amount'      => 0,
            'success_fee_enabled'     => 0,
            'success_fee_mode'        => 'flat',
            'success_fee_amount'      => 0,
        );

        $settings = get_option( self::OPTION_KEY, array() );
        return wp_parse_args( $settings, $defaults );
    }

    private static function sanitize_roles( $roles ) {
        $roles = is_array( $roles ) ? $roles : array();
        $available = array_keys( wp_roles()->roles );
        $roles = array_intersect( array_map( 'sanitize_text_field', $roles ), $available );
        return array_values( $roles );
    }
}
