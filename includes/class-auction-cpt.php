<?php
/**
 * Auction Custom Post Type
 *
 * Registers the auction post type, statuses, and meta boxes.
 *
 * @package Community_Auctions
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Community_Auctions_Auction_CPT
 *
 * Handles auction post type registration and admin meta boxes.
 *
 * @since 1.0.0
 */
class Community_Auctions_Auction_CPT {
    public static function register() {
        // If init has already fired, register directly. Otherwise, hook to init.
        if ( did_action( 'init' ) ) {
            self::register_post_type();
            self::register_statuses();
            self::register_post_meta();
        } else {
            add_action( 'init', array( __CLASS__, 'register_post_type' ) );
            add_action( 'init', array( __CLASS__, 'register_statuses' ) );
            add_action( 'init', array( __CLASS__, 'register_post_meta' ) );
        }
        add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_boxes' ) );
        add_action( 'save_post_auction', array( __CLASS__, 'save_meta' ) );
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_assets' ) );
        add_action( 'enqueue_block_editor_assets', array( __CLASS__, 'enqueue_sidebar_script' ) );
        add_filter( 'manage_auction_posts_columns', array( __CLASS__, 'add_columns' ) );
        add_action( 'manage_auction_posts_custom_column', array( __CLASS__, 'render_columns' ), 10, 2 );
        add_filter( 'manage_edit-auction_sortable_columns', array( __CLASS__, 'sortable_columns' ) );
        add_action( 'pre_get_posts', array( __CLASS__, 'handle_sorting' ) );
        add_action( 'restrict_manage_posts', array( __CLASS__, 'render_visibility_filter' ) );
        add_action( 'pre_get_posts', array( __CLASS__, 'handle_visibility_filter' ) );
        add_filter( 'bulk_actions-edit-auction', array( __CLASS__, 'add_bulk_actions' ) );
        add_filter( 'handle_bulk_actions-edit-auction', array( __CLASS__, 'handle_bulk_actions' ), 10, 3 );
        add_action( 'admin_notices', array( __CLASS__, 'bulk_action_notice' ) );
    }

    public static function register_post_type() {
        $settings = Community_Auctions_Settings::get_settings();

        $singular = $settings['label_singular'] ?? __( 'Auction', 'community-auctions' );
        $plural   = $settings['label_plural'] ?? __( 'Auctions', 'community-auctions' );
        $slug     = $settings['url_slug'] ?? 'auctions';

        $labels = array(
            'name'               => $plural,
            'singular_name'      => $singular,
            'add_new'            => __( 'Add New', 'community-auctions' ),
            /* translators: %s: singular label */
            'add_new_item'       => sprintf( __( 'Add New %s', 'community-auctions' ), $singular ),
            /* translators: %s: singular label */
            'edit_item'          => sprintf( __( 'Edit %s', 'community-auctions' ), $singular ),
            /* translators: %s: singular label */
            'new_item'           => sprintf( __( 'New %s', 'community-auctions' ), $singular ),
            /* translators: %s: singular label */
            'view_item'          => sprintf( __( 'View %s', 'community-auctions' ), $singular ),
            /* translators: %s: plural label */
            'search_items'       => sprintf( __( 'Search %s', 'community-auctions' ), $plural ),
            /* translators: %s: plural label (lowercase) */
            'not_found'          => sprintf( __( 'No %s found', 'community-auctions' ), strtolower( $plural ) ),
            /* translators: %s: plural label (lowercase) */
            'not_found_in_trash' => sprintf( __( 'No %s found in Trash', 'community-auctions' ), strtolower( $plural ) ),
        );

        $args = array(
            'labels'             => $labels,
            'public'             => true,
            'has_archive'        => true,
            'show_in_rest'       => true,
            'show_in_menu'       => false, // Hidden - Admin Panel handles the menu.
            'supports'           => array( 'title', 'editor', 'excerpt', 'thumbnail', 'author' ),
            'rewrite'            => array( 'slug' => $slug, 'with_front' => false ),
        );

        register_post_type( 'auction', $args );

        // Flush rewrite rules if slug was changed.
        if ( get_option( 'community_auctions_flush_rewrite' ) ) {
            flush_rewrite_rules();
            delete_option( 'community_auctions_flush_rewrite' );
        }
    }

    public static function register_statuses() {
        register_post_status( 'ca_pending', array(
            'label'                     => _x( 'Pending Approval', 'auction status', 'community-auctions' ),
            'public'                    => false,
            'show_in_admin_status_list' => true,
            'show_in_admin_all_list'    => true,
        ) );

        register_post_status( 'ca_live', array(
            'label'                     => _x( 'Live', 'auction status', 'community-auctions' ),
            'public'                    => true,
            'show_in_admin_status_list' => true,
            'show_in_admin_all_list'    => true,
        ) );

        register_post_status( 'ca_ended', array(
            'label'                     => _x( 'Ended', 'auction status', 'community-auctions' ),
            'public'                    => true,
            'show_in_admin_status_list' => true,
            'show_in_admin_all_list'    => true,
        ) );

        register_post_status( 'ca_closed', array(
            'label'                     => _x( 'Closed', 'auction status', 'community-auctions' ),
            'public'                    => true,
            'show_in_admin_status_list' => true,
            'show_in_admin_all_list'    => true,
        ) );
    }

    /**
     * Enqueue admin assets for auction editor.
     *
     * @param string $hook Current admin page hook.
     */
    public static function enqueue_admin_assets( $hook ) {
        global $post_type;

        if ( 'auction' !== $post_type ) {
            return;
        }

        if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
            return;
        }

        wp_enqueue_style(
            'ca-admin-meta-box',
            plugin_dir_url( dirname( __FILE__ ) ) . 'assets/css/admin-meta-box.css',
            array(),
            '1.0.0'
        );
    }

    /**
     * Register post meta fields for REST API access (used by Gutenberg sidebar).
     */
    public static function register_post_meta() {
        $meta_fields = array(
            'ca_start_at'              => array( 'type' => 'string', 'default' => '' ),
            'ca_end_at'                => array( 'type' => 'string', 'default' => '' ),
            'ca_start_price'           => array( 'type' => 'string', 'default' => '' ),
            'ca_reserve_price'         => array( 'type' => 'string', 'default' => '' ),
            'ca_min_increment'         => array( 'type' => 'string', 'default' => '' ),
            'ca_visibility'            => array( 'type' => 'string', 'default' => 'public' ),
            'ca_proxy_enabled'         => array( 'type' => 'string', 'default' => '' ),
            'ca_payment_reminder_hours' => array( 'type' => 'string', 'default' => '' ),
            'ca_buy_now_enabled'       => array( 'type' => 'string', 'default' => '' ),
            'ca_buy_now_price'         => array( 'type' => 'string', 'default' => '' ),
        );

        foreach ( $meta_fields as $meta_key => $args ) {
            register_post_meta(
                'auction',
                $meta_key,
                array(
                    'show_in_rest'      => true,
                    'single'            => true,
                    'type'              => $args['type'],
                    'default'           => $args['default'],
                    'sanitize_callback' => 'sanitize_text_field',
                    'auth_callback'     => function() {
                        return current_user_can( 'edit_posts' );
                    },
                )
            );
        }
    }

    /**
     * Enqueue the Gutenberg sidebar script for auction editor.
     */
    public static function enqueue_sidebar_script() {
        global $post_type;

        if ( 'auction' !== $post_type ) {
            return;
        }

        $settings        = Community_Auctions_Settings::get_settings();
        $currency_symbol = isset( $settings['currency_symbol'] ) ? $settings['currency_symbol'] : '$';
        $buy_now_enabled = class_exists( 'Community_Auctions_Buy_Now' ) && Community_Auctions_Buy_Now::is_enabled_globally();

        wp_enqueue_script(
            'ca-auction-sidebar',
            plugin_dir_url( dirname( __FILE__ ) ) . 'assets/js/auction-sidebar.js',
            array( 'wp-plugins', 'wp-edit-post', 'wp-components', 'wp-data', 'wp-element', 'wp-i18n' ),
            '1.0.0',
            true
        );

        wp_localize_script(
            'ca-auction-sidebar',
            'caAuctionSidebar',
            array(
                'currencySymbol' => $currency_symbol,
                'buyNowEnabled'  => $buy_now_enabled,
            )
        );
    }

    public static function add_meta_boxes() {
        add_meta_box(
            'community_auctions_meta',
            __( 'Auction Details', 'community-auctions' ),
            array( __CLASS__, 'render_meta_box' ),
            'auction',
            'normal',
            'high'
        );
    }

    public static function render_meta_box( $post ) {
        wp_nonce_field( 'community_auctions_meta', 'community_auctions_meta_nonce' );

        $start_at       = get_post_meta( $post->ID, 'ca_start_at', true );
        $end_at         = get_post_meta( $post->ID, 'ca_end_at', true );
        $start_price    = get_post_meta( $post->ID, 'ca_start_price', true );
        $reserve_price  = get_post_meta( $post->ID, 'ca_reserve_price', true );
        $min_increment  = get_post_meta( $post->ID, 'ca_min_increment', true );
        $visibility     = get_post_meta( $post->ID, 'ca_visibility', true );
        $proxy_enabled  = get_post_meta( $post->ID, 'ca_proxy_enabled', true );
        $reminder_hours = get_post_meta( $post->ID, 'ca_payment_reminder_hours', true );

        // Buy It Now fields.
        $buy_now_enabled = get_post_meta( $post->ID, 'ca_buy_now_enabled', true );
        $buy_now_price   = get_post_meta( $post->ID, 'ca_buy_now_price', true );

        $settings        = Community_Auctions_Settings::get_settings();
        $currency_symbol = isset( $settings['currency_symbol'] ) ? $settings['currency_symbol'] : '$';

        if ( ! $visibility ) {
            $visibility = $settings['group_visibility_default'] ?? 'public';
        }

        // Default dates for new auctions.
        if ( ! $start_at ) {
            $start_at = gmdate( 'Y-m-d\TH:i', strtotime( '+1 hour' ) );
        }
        if ( ! $end_at ) {
            $end_at = gmdate( 'Y-m-d\TH:i', strtotime( '+7 days' ) );
        }
        ?>
        <div class="ca-admin-meta-box">
            <!-- Schedule Section -->
            <div class="ca-meta-section">
                <h4 class="ca-meta-section-title">
                    <span class="dashicons dashicons-calendar-alt"></span>
                    <?php esc_html_e( 'Schedule', 'community-auctions' ); ?>
                </h4>
                <div class="ca-meta-row">
                    <div class="ca-meta-field ca-meta-field-half">
                        <label for="ca_start_at" class="ca-meta-label">
                            <?php esc_html_e( 'Start Date & Time', 'community-auctions' ); ?>
                        </label>
                        <input
                            type="datetime-local"
                            id="ca_start_at"
                            name="ca_start_at"
                            class="ca-meta-input"
                            value="<?php echo esc_attr( $start_at ); ?>"
                        />
                        <span class="ca-meta-hint"><?php esc_html_e( 'When bidding opens', 'community-auctions' ); ?></span>
                    </div>
                    <div class="ca-meta-field ca-meta-field-half">
                        <label for="ca_end_at" class="ca-meta-label">
                            <?php esc_html_e( 'End Date & Time', 'community-auctions' ); ?>
                        </label>
                        <input
                            type="datetime-local"
                            id="ca_end_at"
                            name="ca_end_at"
                            class="ca-meta-input"
                            value="<?php echo esc_attr( $end_at ); ?>"
                        />
                        <span class="ca-meta-hint"><?php esc_html_e( 'When bidding closes', 'community-auctions' ); ?></span>
                    </div>
                </div>
            </div>

            <!-- Pricing Section -->
            <div class="ca-meta-section">
                <h4 class="ca-meta-section-title">
                    <span class="dashicons dashicons-money-alt"></span>
                    <?php esc_html_e( 'Pricing', 'community-auctions' ); ?>
                </h4>
                <div class="ca-meta-row">
                    <div class="ca-meta-field ca-meta-field-third">
                        <label for="ca_start_price" class="ca-meta-label">
                            <?php esc_html_e( 'Starting Price', 'community-auctions' ); ?>
                        </label>
                        <div class="ca-meta-input-group">
                            <span class="ca-meta-input-prefix"><?php echo esc_html( $currency_symbol ); ?></span>
                            <input
                                type="number"
                                step="0.01"
                                min="0"
                                id="ca_start_price"
                                name="ca_start_price"
                                class="ca-meta-input ca-meta-input-currency"
                                value="<?php echo esc_attr( $start_price ); ?>"
                                placeholder="0.00"
                            />
                        </div>
                    </div>
                    <div class="ca-meta-field ca-meta-field-third">
                        <label for="ca_min_increment" class="ca-meta-label">
                            <?php esc_html_e( 'Bid Increment', 'community-auctions' ); ?>
                        </label>
                        <div class="ca-meta-input-group">
                            <span class="ca-meta-input-prefix"><?php echo esc_html( $currency_symbol ); ?></span>
                            <input
                                type="number"
                                step="0.01"
                                min="0"
                                id="ca_min_increment"
                                name="ca_min_increment"
                                class="ca-meta-input ca-meta-input-currency"
                                value="<?php echo esc_attr( $min_increment ); ?>"
                                placeholder="1.00"
                            />
                        </div>
                    </div>
                    <div class="ca-meta-field ca-meta-field-third">
                        <label for="ca_reserve_price" class="ca-meta-label">
                            <?php esc_html_e( 'Reserve Price', 'community-auctions' ); ?>
                            <span class="ca-meta-optional"><?php esc_html_e( '(Optional)', 'community-auctions' ); ?></span>
                        </label>
                        <div class="ca-meta-input-group">
                            <span class="ca-meta-input-prefix"><?php echo esc_html( $currency_symbol ); ?></span>
                            <input
                                type="number"
                                step="0.01"
                                min="0"
                                id="ca_reserve_price"
                                name="ca_reserve_price"
                                class="ca-meta-input ca-meta-input-currency"
                                value="<?php echo esc_attr( $reserve_price ); ?>"
                                placeholder="0.00"
                            />
                        </div>
                        <span class="ca-meta-hint"><?php esc_html_e( 'Hidden minimum price', 'community-auctions' ); ?></span>
                    </div>
                </div>

                <?php if ( class_exists( 'Community_Auctions_Buy_Now' ) && Community_Auctions_Buy_Now::is_enabled_globally() ) : ?>
                    <div class="ca-meta-row ca-meta-row-toggle">
                        <div class="ca-meta-field">
                            <label class="ca-meta-toggle">
                                <input
                                    type="checkbox"
                                    name="ca_buy_now_enabled"
                                    value="1"
                                    id="ca_buy_now_toggle"
                                    <?php checked( $buy_now_enabled ); ?>
                                />
                                <span class="ca-meta-toggle-slider"></span>
                                <span class="ca-meta-toggle-label"><?php esc_html_e( 'Enable Buy It Now', 'community-auctions' ); ?></span>
                            </label>
                        </div>
                        <div class="ca-meta-field ca-meta-field-conditional" id="ca_buy_now_field" style="<?php echo esc_attr( $buy_now_enabled ? '' : 'display:none;' ); ?>">
                            <label for="ca_buy_now_price" class="ca-meta-label">
                                <?php esc_html_e( 'Buy It Now Price', 'community-auctions' ); ?>
                            </label>
                            <div class="ca-meta-input-group">
                                <span class="ca-meta-input-prefix"><?php echo esc_html( $currency_symbol ); ?></span>
                                <input
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    id="ca_buy_now_price"
                                    name="ca_buy_now_price"
                                    class="ca-meta-input ca-meta-input-currency"
                                    value="<?php echo esc_attr( $buy_now_price ); ?>"
                                    placeholder="0.00"
                                />
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Settings Section -->
            <div class="ca-meta-section">
                <h4 class="ca-meta-section-title">
                    <span class="dashicons dashicons-admin-settings"></span>
                    <?php esc_html_e( 'Settings', 'community-auctions' ); ?>
                </h4>
                <div class="ca-meta-row">
                    <div class="ca-meta-field ca-meta-field-half">
                        <label for="ca_visibility" class="ca-meta-label">
                            <?php esc_html_e( 'Visibility', 'community-auctions' ); ?>
                        </label>
                        <select id="ca_visibility" name="ca_visibility" class="ca-meta-select">
                            <option value="public" <?php selected( $visibility, 'public' ); ?>>
                                <?php esc_html_e( 'Public - Anyone can view and bid', 'community-auctions' ); ?>
                            </option>
                            <option value="group_only" <?php selected( $visibility, 'group_only' ); ?>>
                                <?php esc_html_e( 'Group Only - Only group members', 'community-auctions' ); ?>
                            </option>
                        </select>
                    </div>
                    <div class="ca-meta-field ca-meta-field-half">
                        <label for="ca_payment_reminder_hours" class="ca-meta-label">
                            <?php esc_html_e( 'Payment Reminder', 'community-auctions' ); ?>
                            <span class="ca-meta-optional"><?php esc_html_e( '(Override)', 'community-auctions' ); ?></span>
                        </label>
                        <div class="ca-meta-input-group">
                            <input
                                type="number"
                                step="1"
                                min="1"
                                id="ca_payment_reminder_hours"
                                name="ca_payment_reminder_hours"
                                class="ca-meta-input"
                                value="<?php echo esc_attr( $reminder_hours ); ?>"
                                placeholder="48"
                            />
                            <span class="ca-meta-input-suffix"><?php esc_html_e( 'hours', 'community-auctions' ); ?></span>
                        </div>
                        <span class="ca-meta-hint"><?php esc_html_e( 'Leave empty to use global setting', 'community-auctions' ); ?></span>
                    </div>
                </div>

                <div class="ca-meta-row ca-meta-row-toggle">
                    <div class="ca-meta-field">
                        <label class="ca-meta-toggle">
                            <input type="checkbox" name="ca_proxy_enabled" value="1" <?php checked( $proxy_enabled ); ?> />
                            <span class="ca-meta-toggle-slider"></span>
                            <span class="ca-meta-toggle-label"><?php esc_html_e( 'Enable Proxy Bidding', 'community-auctions' ); ?></span>
                        </label>
                        <span class="ca-meta-hint ca-meta-hint-toggle"><?php esc_html_e( 'Allow bidders to set a maximum bid and auto-bid up to that amount', 'community-auctions' ); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <script>
        (function() {
            var toggle = document.getElementById('ca_buy_now_toggle');
            var field = document.getElementById('ca_buy_now_field');
            if (toggle && field) {
                toggle.addEventListener('change', function() {
                    field.style.display = this.checked ? 'block' : 'none';
                });
            }
        })();
        </script>
        <?php
    }

    public static function save_meta( $post_id ) {
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        if ( empty( $_POST['community_auctions_meta_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['community_auctions_meta_nonce'] ) ), 'community_auctions_meta' ) ) {
            return;
        }

        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        $start_at = sanitize_text_field( wp_unslash( $_POST['ca_start_at'] ?? '' ) );
        $end_at = sanitize_text_field( wp_unslash( $_POST['ca_end_at'] ?? '' ) );
        $start_price = floatval( wp_unslash( $_POST['ca_start_price'] ?? 0 ) );
        $reserve_price = floatval( wp_unslash( $_POST['ca_reserve_price'] ?? 0 ) );
        $min_increment = floatval( wp_unslash( $_POST['ca_min_increment'] ?? 0 ) );
        $visibility = sanitize_text_field( wp_unslash( $_POST['ca_visibility'] ?? 'public' ) );
        $proxy_enabled = ! empty( $_POST['ca_proxy_enabled'] ) ? 1 : 0;
        $reminder_hours = intval( wp_unslash( $_POST['ca_payment_reminder_hours'] ?? 0 ) );

        update_post_meta( $post_id, 'ca_start_at', $start_at );
        update_post_meta( $post_id, 'ca_end_at', $end_at );
        update_post_meta( $post_id, 'ca_start_price', $start_price );
        update_post_meta( $post_id, 'ca_reserve_price', $reserve_price );
        update_post_meta( $post_id, 'ca_min_increment', $min_increment );
        update_post_meta( $post_id, 'ca_visibility', $visibility );
        update_post_meta( $post_id, 'ca_proxy_enabled', $proxy_enabled );

        if ( $reminder_hours > 0 ) {
            update_post_meta( $post_id, 'ca_payment_reminder_hours', $reminder_hours );
        } else {
            delete_post_meta( $post_id, 'ca_payment_reminder_hours' );
        }

        // Handle Buy It Now fields.
        if ( class_exists( 'Community_Auctions_Buy_Now' ) && Community_Auctions_Buy_Now::is_enabled_globally() ) {
            $buy_now_enabled = ! empty( $_POST['ca_buy_now_enabled'] ) ? 1 : 0;
            $buy_now_price   = floatval( wp_unslash( $_POST['ca_buy_now_price'] ?? 0 ) );

            update_post_meta( $post_id, 'ca_buy_now_enabled', $buy_now_enabled );
            if ( $buy_now_enabled && $buy_now_price > 0 ) {
                update_post_meta( $post_id, 'ca_buy_now_price', $buy_now_price );
            } else {
                delete_post_meta( $post_id, 'ca_buy_now_price' );
            }
        }
    }

    public static function add_columns( $columns ) {
        $columns['ca_reminder_hours'] = __( 'Reminder (hrs)', 'community-auctions' );
        return $columns;
    }

    public static function render_columns( $column, $post_id ) {
        if ( 'ca_reminder_hours' !== $column ) {
            return;
        }

        $hours = get_post_meta( $post_id, 'ca_payment_reminder_hours', true );
        echo esc_html( $hours ? $hours : '-' );
    }

    public static function sortable_columns( $columns ) {
        $columns['ca_reminder_hours'] = 'ca_reminder_hours';
        return $columns;
    }

    public static function handle_sorting( $query ) {
        if ( ! is_admin() || ! $query->is_main_query() ) {
            return;
        }

        if ( 'auction' !== $query->get( 'post_type' ) ) {
            return;
        }

        if ( 'ca_reminder_hours' === $query->get( 'orderby' ) ) {
            $query->set( 'meta_key', 'ca_payment_reminder_hours' );
            $query->set( 'orderby', 'meta_value_num' );
        }
    }

    public static function render_visibility_filter() {
        global $typenow;
        if ( 'auction' !== $typenow ) {
            return;
        }

        $selected = isset( $_GET['ca_visibility'] ) ? sanitize_text_field( wp_unslash( $_GET['ca_visibility'] ) ) : '';
        ?>
        <select name="ca_visibility">
            <option value=""><?php esc_html_e( 'All Visibilities', 'community-auctions' ); ?></option>
            <option value="public" <?php selected( $selected, 'public' ); ?>><?php esc_html_e( 'Public', 'community-auctions' ); ?></option>
            <option value="group_only" <?php selected( $selected, 'group_only' ); ?>><?php esc_html_e( 'Group Only', 'community-auctions' ); ?></option>
        </select>
        <?php
    }

    public static function handle_visibility_filter( $query ) {
        if ( ! is_admin() || ! $query->is_main_query() ) {
            return;
        }

        if ( 'auction' !== $query->get( 'post_type' ) ) {
            return;
        }

        if ( empty( $_GET['ca_visibility'] ) ) {
            return;
        }

        $visibility = sanitize_text_field( wp_unslash( $_GET['ca_visibility'] ) );
        $meta_query = array(
            array(
                'key'     => 'ca_visibility',
                'value'   => $visibility,
                'compare' => '=',
            ),
        );
        $query->set( 'meta_query', $meta_query );
    }

    public static function add_bulk_actions( $actions ) {
        $actions['ca_visibility_public'] = __( 'Set visibility: Public', 'community-auctions' );
        $actions['ca_visibility_group_only'] = __( 'Set visibility: Group Only', 'community-auctions' );
        return $actions;
    }

    public static function handle_bulk_actions( $redirect, $action, $post_ids ) {
        if ( ! in_array( $action, array( 'ca_visibility_public', 'ca_visibility_group_only' ), true ) ) {
            return $redirect;
        }

        $value = ( 'ca_visibility_group_only' === $action ) ? 'group_only' : 'public';
        foreach ( $post_ids as $post_id ) {
            update_post_meta( $post_id, 'ca_visibility', $value );
        }

        return add_query_arg( array( 'ca_visibility_updated' => count( $post_ids ) ), $redirect );
    }

    public static function bulk_action_notice() {
        if ( empty( $_GET['ca_visibility_updated'] ) ) {
            return;
        }

        $count = intval( $_GET['ca_visibility_updated'] );
        echo '<div class="notice notice-success is-dismissible"><p>' .
            esc_html( sprintf( __( 'Updated visibility for %d auctions.', 'community-auctions' ), $count ) ) .
            '</p></div>';
    }
}
