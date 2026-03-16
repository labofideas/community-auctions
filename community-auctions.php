<?php
/**
 * Plugin Name: Community Auctions
 * Plugin URI: https://github.com/labofideas/community-auctions
 * Description: BuddyPress-compatible auctions with WooCommerce or FluentCart payments.
 * Version: 0.1.0
 * Author: Wbcom Designs
 * Author URI: https://wbcomdesigns.com
 * License: GPL-2.0+
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: community-auctions
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Community_Auctions_Plugin {
    const VERSION    = '0.1.0';
    const DB_VERSION = '1.0.0';

    /**
     * Default pagination values.
     */
    const DEFAULT_PER_PAGE       = 10;
    const ADMIN_BATCH_SIZE       = 100;
    const LARGE_BATCH_SIZE       = 500;
    const REALTIME_POLL_INTERVAL = 15;
    const CRON_BATCH_SIZE        = 50;

    public static function instance() {
        static $instance = null;
        if ( null === $instance ) {
            $instance = new self();
        }
        return $instance;
    }

    private function __construct() {
        $this->load_autoloader();
        add_action( 'plugins_loaded', array( $this, 'init' ) );
    }

    /**
     * Load Composer autoloader, falling back to manual includes.
     */
    private function load_autoloader() {
        $autoloader = __DIR__ . '/vendor/autoload.php';
        if ( file_exists( $autoloader ) ) {
            require_once $autoloader;
            return;
        }

        // Fallback: load files directly when Composer autoload is unavailable.
        foreach ( glob( __DIR__ . '/includes/class-*.php' ) as $file ) {
            require_once $file;
        }
    }

    public function init() {
        load_plugin_textdomain( 'community-auctions', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

        self::add_caps();

        Community_Auctions_Auction_CPT::register();
        Community_Auctions_Taxonomy::register();
        Community_Auctions_Settings::register();
        Community_Auctions_Auction_Engine::register();
        Community_Auctions_Bid_History::register();
        Community_Auctions_Image_Gallery::register();
        Community_Auctions_Countdown_Timer::register();
        Community_Auctions_Bid_Confirmation::register();
        Community_Auctions_Realtime_Updates::register();
        Community_Auctions_Watchlist::register();
        Community_Auctions_Buy_Now::register();
        Community_Auctions_Upcoming::register();
        Community_Auctions_Seller_Dashboard::register();
        Community_Auctions_Buyer_Dashboard::register();
        Community_Auctions_Currency::register();
        Community_Auctions_Timezone::register();
        Community_Auctions_Email_Templates::register();
        Community_Auctions_REST_API::register();
        Community_Auctions_Search_Filter::register();
        Community_Auctions_Performance::register();
        Community_Auctions_Admin_Panel::register();
        Community_Auctions_Frontend_Forms::register();
        Community_Auctions_Auction_Shortcodes::register();
        Community_Auctions_Auction_Cron::register();
        Community_Auctions_Notifications::register();
        Community_Auctions_Widgets::register();
        Community_Auctions_Admin_Dashboard::register();
        Community_Auctions_Payment_WooCommerce::register();
        Community_Auctions_Payment_FluentCart::register();

        // Initialize Gutenberg blocks.
        Community_Auctions_Blocks::init( plugin_dir_path( __FILE__ ), plugin_dir_url( __FILE__ ) );
        Community_Auctions_Blocks::maybe_show_build_notice();

        // Initialize frontend templates.
        Community_Auctions_Frontend_Templates::register();

        // Initialize demo data importer.
        Community_Auctions_Demo_Data::register();

        if ( Community_Auctions_BuddyPress_Integration::is_active() ) {
            Community_Auctions_BuddyPress_Integration::register();
        }
    }

    public static function activate() {
        Community_Auctions_Auction_CPT::register_post_type();
        flush_rewrite_rules();
        Community_Auctions_Bid_Repository::create_table();
        Community_Auctions_Watchlist::create_table();
        self::add_caps();
        update_option( 'community_auctions_db_version', self::DB_VERSION );
    }

    public static function deactivate() {
        if ( class_exists( 'Community_Auctions_Auction_Cron' ) ) {
            wp_clear_scheduled_hook( Community_Auctions_Auction_Cron::HOOK );
        }
    }

    public static function is_woocommerce_active() {
        return class_exists( 'WooCommerce' ) || defined( 'WC_PLUGIN_FILE' );
    }

    public static function is_fluentcart_active() {
        return defined( 'FLUENTCART_PLUGIN_PATH' );
    }

    public static function refresh_caps( $settings ) {
        $roles = wp_roles();
        if ( ! $roles ) {
            return;
        }

        foreach ( $roles->roles as $role_key => $role_data ) {
            $role = get_role( $role_key );
            if ( ! $role ) {
                continue;
            }
            $role->remove_cap( 'ca_create_auction' );
            $role->remove_cap( 'ca_place_bid' );
        }

        self::add_caps_with_settings( $settings );
    }

    private static function add_caps() {
        $settings = Community_Auctions_Settings::get_settings();
        self::add_caps_with_settings( $settings );
    }

    private static function add_caps_with_settings( $settings ) {
        $create_roles = isset( $settings['allowed_roles_create'] ) ? (array) $settings['allowed_roles_create'] : array( 'administrator' );
        $bid_roles = isset( $settings['allowed_roles_bid'] ) ? (array) $settings['allowed_roles_bid'] : array( 'administrator' );
        $all_roles = array_unique( array_merge( $create_roles, $bid_roles, array( 'administrator' ) ) );

        foreach ( $all_roles as $role_key ) {
            $role = get_role( $role_key );
            if ( ! $role ) {
                continue;
            }

            if ( in_array( $role_key, $create_roles, true ) || 'administrator' === $role_key ) {
                $role->add_cap( 'ca_create_auction' );
            }

            if ( in_array( $role_key, $bid_roles, true ) || 'administrator' === $role_key ) {
                $role->add_cap( 'ca_place_bid' );
            }

            if ( 'administrator' === $role_key ) {
                $role->add_cap( 'ca_manage_auctions' );
            }
        }
    }
}

register_activation_hook( __FILE__, array( 'Community_Auctions_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Community_Auctions_Plugin', 'deactivate' ) );
Community_Auctions_Plugin::instance();
