<?php
/**
 * BuddyPress Integration
 *
 * Integrates auctions with BuddyPress member profiles and groups.
 *
 * @package Community_Auctions
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Community_Auctions_BuddyPress_Integration
 *
 * Handles BuddyPress profile tabs, groups, and activity integration.
 *
 * @since 1.0.0
 */
class Community_Auctions_BuddyPress_Integration {
    const COMPONENT = 'community-auctions';

    public static function is_active() {
        return function_exists( 'bp_is_active' ) && bp_is_active( 'activity' );
    }

    public static function register() {
        add_action( 'bp_init', array( __CLASS__, 'register_activity_actions' ) );
        add_action( 'bp_setup_nav', array( __CLASS__, 'register_member_nav' ), 100 );
        add_action( 'bp_setup_nav', array( __CLASS__, 'register_settings_nav' ), 110 );
        add_action( 'bp_init', array( __CLASS__, 'register_group_extension' ) );
        add_filter( 'bp_notifications_get_registered_components', array( __CLASS__, 'register_notification_component' ) );
        add_filter( 'bp_notifications_get_notifications_for_user', array( __CLASS__, 'format_notification' ), 10, 8 );

        add_action( 'community_auctions/auction_created', array( __CLASS__, 'activity_auction_created' ), 10, 2 );
        add_action( 'community_auctions/bid_placed', array( __CLASS__, 'activity_bid_placed' ), 10, 3 );
        add_action( 'community_auctions/auction_ended', array( __CLASS__, 'activity_auction_ended' ), 10, 2 );

        add_action( 'community_auctions/bid_outbid', array( __CLASS__, 'notify_outbid' ), 10, 3 );
        add_action( 'community_auctions/auction_won', array( __CLASS__, 'notify_winner' ), 10, 2 );
        add_action( 'community_auctions/auction_payment_reminder', array( __CLASS__, 'notify_payment_reminder' ), 10, 2 );
    }

    public static function register_activity_actions() {
        if ( ! function_exists( 'bp_activity_set_action' ) ) {
            return;
        }

        bp_activity_set_action(
            self::COMPONENT,
            'auction_created',
            __( 'created an auction', 'community-auctions' ),
            array( __CLASS__, 'format_activity_action' )
        );

        bp_activity_set_action(
            self::COMPONENT,
            'bid_placed',
            __( 'placed a bid', 'community-auctions' ),
            array( __CLASS__, 'format_activity_action' )
        );

        bp_activity_set_action(
            self::COMPONENT,
            'auction_ended',
            __( 'ended an auction', 'community-auctions' ),
            array( __CLASS__, 'format_activity_action' )
        );
    }

    public static function register_notification_component( $components ) {
        if ( ! is_array( $components ) ) {
            $components = array();
        }

        if ( ! in_array( self::COMPONENT, $components, true ) ) {
            $components[] = self::COMPONENT;
        }

        return $components;
    }

    public static function format_notification( $action, $item_id, $secondary_item_id, $total_items, $format, $component_action_name, $component_name, $notification_id ) {
        if ( self::COMPONENT !== $component_name ) {
            return $action;
        }

        $auction_id = absint( $item_id );
        $link = $auction_id ? get_permalink( $auction_id ) : bp_loggedin_user_url();

        $text = '';
        switch ( $component_action_name ) {
            case 'outbid':
                $text = $total_items > 1
                    ? __( 'You have been outbid on multiple auctions.', 'community-auctions' )
                    : __( 'You have been outbid.', 'community-auctions' );
                break;
            case 'won':
                $text = $total_items > 1
                    ? __( 'You won multiple auctions.', 'community-auctions' )
                    : __( 'You won the auction.', 'community-auctions' );
                break;
            case 'payment_reminder':
                $text = $total_items > 1
                    ? __( 'Payment reminders for your auctions.', 'community-auctions' )
                    : __( 'Reminder: Please complete payment for your auction.', 'community-auctions' );
                break;
        }

        if ( 'object' === $format ) {
            return array(
                'text' => $text,
                'link' => $link,
            );
        }

        return '<a href="' . esc_url( $link ) . '">' . esc_html( $text ) . '</a>';
    }

    public static function format_activity_action( $action, $activity ) {
        if ( ! $activity || empty( $activity->type ) ) {
            return $action;
        }

        $user_link = bp_core_get_userlink( $activity->user_id );
        $auction_id = ! empty( $activity->item_id ) ? absint( $activity->item_id ) : 0;
        $auction_title = $auction_id ? get_the_title( $auction_id ) : '';
        $auction_url = $auction_id ? get_permalink( $auction_id ) : '';
        $auction_link = $auction_url && $auction_title
            ? '<a href="' . esc_url( $auction_url ) . '">' . esc_html( $auction_title ) . '</a>'
            : esc_html( $auction_title );

        switch ( $activity->type ) {
            case 'auction_created':
                if ( $auction_link ) {
                    $action = sprintf(
                        /* translators: 1: user link, 2: auction link */
                        __( '%1$s created a new auction: %2$s', 'community-auctions' ),
                        $user_link,
                        $auction_link
                    );
                }
                break;

            case 'bid_placed':
                $bid_amount = '';
                if ( ! empty( $activity->secondary_item_id ) ) {
                    $bid_amount = Community_Auctions_Currency::format( floatval( $activity->secondary_item_id ) );
                }
                if ( $auction_link && $bid_amount ) {
                    $action = sprintf(
                        /* translators: 1: user link, 2: bid amount, 3: auction link */
                        __( '%1$s placed a bid of %2$s on %3$s', 'community-auctions' ),
                        $user_link,
                        '<strong>' . esc_html( $bid_amount ) . '</strong>',
                        $auction_link
                    );
                } elseif ( $auction_link ) {
                    $action = sprintf(
                        /* translators: 1: user link, 2: auction link */
                        __( '%1$s placed a bid on %2$s', 'community-auctions' ),
                        $user_link,
                        $auction_link
                    );
                }
                break;

            case 'auction_ended':
                $winner_id = absint( get_post_meta( $auction_id, 'ca_winner_id', true ) );
                $final_bid = get_post_meta( $auction_id, 'ca_current_bid', true );

                if ( $winner_id && $auction_link ) {
                    $winner_link = bp_core_get_userlink( $winner_id );
                    $final_amount = $final_bid ? Community_Auctions_Currency::format( floatval( $final_bid ) ) : '';

                    if ( $final_amount ) {
                        $action = sprintf(
                            /* translators: 1: auction link, 2: winner link, 3: final amount */
                            __( 'Auction %1$s ended. Won by %2$s for %3$s', 'community-auctions' ),
                            $auction_link,
                            $winner_link,
                            '<strong>' . esc_html( $final_amount ) . '</strong>'
                        );
                    } else {
                        $action = sprintf(
                            /* translators: 1: auction link, 2: winner link */
                            __( 'Auction %1$s ended. Won by %2$s', 'community-auctions' ),
                            $auction_link,
                            $winner_link
                        );
                    }
                } elseif ( $auction_link ) {
                    $action = sprintf(
                        /* translators: %s: auction link */
                        __( 'Auction %s ended with no winner', 'community-auctions' ),
                        $auction_link
                    );
                }
                break;
        }

        return $action;
    }

    public static function register_member_nav() {
        if ( ! function_exists( 'bp_core_new_nav_item' ) ) {
            return;
        }

        $slug = 'auctions';
        $name = __( 'Auctions', 'community-auctions' );

        bp_core_new_nav_item( array(
            'name'                => $name,
            'slug'                => $slug,
            'screen_function'     => array( __CLASS__, 'screen_member_auctions' ),
            'default_subnav_slug' => 'my-auctions',
            'position'            => 70,
        ) );

        bp_core_new_subnav_item( array(
            'name'            => __( 'My Auctions', 'community-auctions' ),
            'slug'            => 'my-auctions',
            'parent_url'      => trailingslashit( bp_displayed_user_domain() . $slug ),
            'parent_slug'     => $slug,
            'screen_function' => array( __CLASS__, 'screen_member_auctions' ),
            'position'        => 10,
        ) );

        bp_core_new_subnav_item( array(
            'name'            => __( 'My Bids', 'community-auctions' ),
            'slug'            => 'my-bids',
            'parent_url'      => trailingslashit( bp_displayed_user_domain() . $slug ),
            'parent_slug'     => $slug,
            'screen_function' => array( __CLASS__, 'screen_member_bids' ),
            'position'        => 20,
        ) );
    }

    public static function register_settings_nav() {
        if ( ! function_exists( 'bp_core_new_subnav_item' ) ) {
            return;
        }

        bp_core_new_subnav_item( array(
            'name'            => __( 'Auction Notifications', 'community-auctions' ),
            'slug'            => 'auction-notifications',
            'parent_slug'     => 'settings',
            'parent_url'      => trailingslashit( bp_loggedin_user_domain() . 'settings' ),
            'screen_function' => array( __CLASS__, 'screen_notification_settings' ),
            'position'        => 40,
        ) );
    }

    public static function screen_notification_settings() {
        if ( ! bp_is_my_profile() ) {
            bp_core_redirect( bp_loggedin_user_domain() );
        }

        if ( 'POST' === $_SERVER['REQUEST_METHOD'] ) {
            self::handle_notification_settings_save();
        }

        add_action( 'bp_template_content', array( __CLASS__, 'render_notification_settings' ) );
        bp_core_load_template( 'members/single/plugins' );
    }

    private static function handle_notification_settings_save() {
        if ( empty( $_POST['community_auctions_settings_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['community_auctions_settings_nonce'] ) ), 'community_auctions_settings' ) ) {
            return;
        }

        $user_id = get_current_user_id();
        Community_Auctions_Notifications::update_pref( $user_id, Community_Auctions_Notifications::META_PREFIX . 'outbid', ! empty( $_POST['ca_notify_outbid'] ) );
        Community_Auctions_Notifications::update_pref( $user_id, Community_Auctions_Notifications::META_PREFIX . 'won', ! empty( $_POST['ca_notify_won'] ) );
        Community_Auctions_Notifications::update_pref( $user_id, Community_Auctions_Notifications::META_PREFIX . 'payment_reminder', ! empty( $_POST['ca_notify_payment_reminder'] ) );
        Community_Auctions_Notifications::update_pref( $user_id, Community_Auctions_Notifications::META_EMAIL_PREFIX . 'outbid', ! empty( $_POST['ca_email_outbid'] ) );
        Community_Auctions_Notifications::update_pref( $user_id, Community_Auctions_Notifications::META_EMAIL_PREFIX . 'won', ! empty( $_POST['ca_email_won'] ) );
        Community_Auctions_Notifications::update_pref( $user_id, Community_Auctions_Notifications::META_EMAIL_PREFIX . 'payment_reminder', ! empty( $_POST['ca_email_payment_reminder'] ) );

        if ( function_exists( 'bp_core_add_message' ) ) {
            bp_core_add_message( __( 'Auction notification settings saved.', 'community-auctions' ) );
        }

        if ( function_exists( 'bp_core_redirect' ) ) {
            bp_core_redirect( trailingslashit( bp_loggedin_user_domain() . 'settings/auction-notifications' ) );
        }
    }

    public static function render_notification_settings() {
        $user_id = get_current_user_id();
        $notify_outbid = Community_Auctions_Notifications::is_bp_enabled( $user_id, 'outbid' );
        $notify_won = Community_Auctions_Notifications::is_bp_enabled( $user_id, 'won' );
        $notify_payment = Community_Auctions_Notifications::is_bp_enabled( $user_id, 'payment_reminder' );

        $email_outbid = Community_Auctions_Notifications::is_email_enabled( $user_id, 'outbid' );
        $email_won = Community_Auctions_Notifications::is_email_enabled( $user_id, 'won' );
        $email_payment = Community_Auctions_Notifications::is_email_enabled( $user_id, 'payment_reminder' );
        $preview = Community_Auctions_Notifications::build_email_preview();

        ?>
        <form method="post">
            <?php wp_nonce_field( 'community_auctions_settings', 'community_auctions_settings_nonce' ); ?>
            <fieldset>
                <legend><?php esc_html_e( 'BuddyPress Notifications', 'community-auctions' ); ?></legend>
                <label><input type="checkbox" name="ca_notify_outbid" value="1" <?php checked( $notify_outbid ); ?> /> <?php esc_html_e( 'Outbid', 'community-auctions' ); ?></label><br>
                <label><input type="checkbox" name="ca_notify_won" value="1" <?php checked( $notify_won ); ?> /> <?php esc_html_e( 'Auction won', 'community-auctions' ); ?></label><br>
                <label><input type="checkbox" name="ca_notify_payment_reminder" value="1" <?php checked( $notify_payment ); ?> /> <?php esc_html_e( 'Payment reminder', 'community-auctions' ); ?></label>
            </fieldset>

            <fieldset>
                <legend><?php esc_html_e( 'Email Notifications', 'community-auctions' ); ?></legend>
                <label><input type="checkbox" name="ca_email_outbid" value="1" <?php checked( $email_outbid ); ?> /> <?php esc_html_e( 'Outbid', 'community-auctions' ); ?></label><br>
                <label><input type="checkbox" name="ca_email_won" value="1" <?php checked( $email_won ); ?> /> <?php esc_html_e( 'Auction won', 'community-auctions' ); ?></label><br>
                <label><input type="checkbox" name="ca_email_payment_reminder" value="1" <?php checked( $email_payment ); ?> /> <?php esc_html_e( 'Payment reminder', 'community-auctions' ); ?></label>
            </fieldset>

            <h3><?php esc_html_e( 'Email Preview', 'community-auctions' ); ?></h3>
            <?php echo wp_kses_post( $preview ); ?>

            <p><button type="submit"><?php esc_html_e( 'Save Settings', 'community-auctions' ); ?></button></p>
        </form>
        <?php
    }

    public static function screen_member_auctions() {
        add_action( 'bp_template_content', array( __CLASS__, 'render_member_auctions' ) );
        bp_core_load_template( 'members/single/plugins' );
    }

    public static function screen_member_bids() {
        add_action( 'bp_template_content', array( __CLASS__, 'render_member_bids' ) );
        bp_core_load_template( 'members/single/plugins' );
    }

    public static function render_member_auctions() {
        self::render_template( 'bp-member-auctions.php' );
    }

    public static function render_member_bids() {
        $user_id = function_exists( 'bp_displayed_user_id' ) ? bp_displayed_user_id() : get_current_user_id();
        $per_page = 10;
        $page = isset( $_GET['bids-page'] ) ? max( 1, absint( $_GET['bids-page'] ) ) : 1;
        $offset = ( $page - 1 ) * $per_page;
        $bids = Community_Auctions_Bid_Repository::get_user_bids( $user_id, $per_page, $offset );
        $total = Community_Auctions_Bid_Repository::count_user_bids( $user_id );

        echo '<h2>' . esc_html__( 'My Bids', 'community-auctions' ) . '</h2>';

        echo '<div class="community-auctions-legend">';
        echo '<span class="ca-badge ca-badge--highest">' . esc_html__( 'Highest', 'community-auctions' ) . '</span> ';
        echo '<span class="ca-badge ca-badge--outbid">' . esc_html__( 'Outbid', 'community-auctions' ) . '</span>';
        echo '<button type="button" class="ca-legend-toggle" aria-expanded="false" aria-controls="ca-legend-tooltip">' . esc_html__( 'Legend', 'community-auctions' ) . '</button>';
        echo '<div id="ca-legend-tooltip" class="ca-legend-tooltip" role="status" aria-live="polite" hidden>';
        echo '<p><strong>' . esc_html__( 'Highest', 'community-auctions' ) . ':</strong> ' . esc_html__( 'Your bid is currently leading.', 'community-auctions' ) . '</p>';
        echo '<p><strong>' . esc_html__( 'Outbid', 'community-auctions' ) . ':</strong> ' . esc_html__( 'Another bidder is ahead.', 'community-auctions' ) . '</p>';
        echo '</div>';
        echo '</div>';

        if ( empty( $bids ) ) {
            echo '<p>' . esc_html__( 'No bids found.', 'community-auctions' ) . '</p>';
            return;
        }

        echo '<ul class="community-auctions-list" role="list">';
        foreach ( $bids as $bid ) {
            $auction_id = absint( $bid->auction_id );
            $title = $bid->post_title ? $bid->post_title : __( 'Auction', 'community-auctions' );
            $amount_value = floatval( $bid->amount );
            $amount = number_format_i18n( $amount_value, 2 );
            $link = $auction_id ? get_permalink( $auction_id ) : '';
            $placed_at = ! empty( $bid->created_at ) ? mysql2date( get_option( 'date_format' ), $bid->created_at ) : '';
            $status = $bid->post_status ? $bid->post_status : '';
            $current_bid = floatval( get_post_meta( $auction_id, 'ca_current_bid', true ) );
            $current_bidder = absint( get_post_meta( $auction_id, 'ca_current_bidder', true ) );
            $is_highest = ( $current_bidder === $user_id && $amount_value > 0 && abs( $current_bid - $amount_value ) < 0.01 );
            $is_outbid = ( $amount_value > 0 && $current_bid > 0 && ( $current_bidder !== $user_id ) );

            echo '<li class="community-auction-card">';
            if ( $link ) {
                echo '<h3><a href="' . esc_url( $link ) . '">' . esc_html( $title ) . '</a></h3>';
            } else {
                echo '<h3>' . esc_html( $title ) . '</h3>';
            }
            echo '<p><strong>' . esc_html__( 'Your Bid:', 'community-auctions' ) . '</strong> ' . esc_html( $amount ) . '</p>';
            if ( $placed_at ) {
                echo '<p><strong>' . esc_html__( 'Placed:', 'community-auctions' ) . '</strong> ' . esc_html( $placed_at ) . '</p>';
            }
            if ( $status ) {
                echo '<p><strong>' . esc_html__( 'Status:', 'community-auctions' ) . '</strong> ' . esc_html( $status ) . '</p>';
            }
            if ( $is_highest ) {
                echo '<p><strong>' . esc_html__( 'Position:', 'community-auctions' ) . '</strong> ' . esc_html__( 'Highest bid', 'community-auctions' ) . '</p>';
                echo '<span class="ca-badge ca-badge--highest">' . esc_html__( 'Highest', 'community-auctions' ) . '</span>';
            } elseif ( $is_outbid ) {
                echo '<p><strong>' . esc_html__( 'Position:', 'community-auctions' ) . '</strong> ' . esc_html__( 'Outbid', 'community-auctions' ) . '</p>';
                echo '<span class="ca-badge ca-badge--outbid">' . esc_html__( 'Outbid', 'community-auctions' ) . '</span>';
            }
            if ( $link && 'ca_ended' !== $status && 'ca_closed' !== $status ) {
                echo '<p class="community-auction-actions">';
                echo '<a href="' . esc_url( $link ) . '">' . esc_html__( 'View auction', 'community-auctions' ) . '</a>';
                echo '<span class="ca-action-sep" aria-hidden="true">•</span>';
                echo '<a href="' . esc_url( $link ) . '#ca-bid-form-' . esc_attr( $auction_id ) . '">' . esc_html__( 'Bid now', 'community-auctions' ) . '</a>';
                echo '</p>';
            }
            echo '</li>';
        }
        echo '</ul>';

        $total_pages = ( $total > 0 ) ? (int) ceil( $total / $per_page ) : 1;
        if ( $total_pages > 1 ) {
            echo '<nav class="community-auctions-pagination" aria-label="' . esc_attr__( 'Bid pagination', 'community-auctions' ) . '">';
            echo paginate_links( array(
                'base'      => esc_url_raw( add_query_arg( 'bids-page', '%#%' ) ),
                'format'    => '',
                'current'   => $page,
                'total'     => $total_pages,
                'prev_text' => __( '&laquo; Prev', 'community-auctions' ),
                'next_text' => __( 'Next &raquo;', 'community-auctions' ),
                'type'      => 'list',
            ) );
            echo '</nav>';
        }
    }

    public static function register_group_extension() {
        if ( ! class_exists( 'BP_Group_Extension' ) ) {
            return;
        }

        if ( class_exists( 'Community_Auctions_Group_Extension' ) ) {
            bp_register_group_extension( 'Community_Auctions_Group_Extension' );
        }
    }

    public static function render_template( $template ) {
        $path = plugin_dir_path( __DIR__ ) . 'templates/' . $template;
        if ( file_exists( $path ) ) {
            include $path;
            return;
        }

        echo '<p>' . esc_html__( 'Template not found.', 'community-auctions' ) . '</p>';
    }

    public static function render_group_settings( $group_id ) {
        if ( ! function_exists( 'groups_is_user_admin' ) || ! function_exists( 'groups_is_user_mod' ) ) {
            return;
        }

        $user_id = get_current_user_id();
        if ( ! groups_is_user_admin( $user_id, $group_id ) && ! groups_is_user_mod( $user_id, $group_id ) ) {
            return;
        }

        if ( 'POST' === $_SERVER['REQUEST_METHOD'] && ! empty( $_POST['community_auctions_group_nonce'] ) && wp_verify_nonce( sanitize_key( wp_unslash( $_POST['community_auctions_group_nonce'] ) ), 'community_auctions_group_settings' ) ) {
            $restrict = ! empty( $_POST['ca_group_only'] ) ? 1 : 0;
            groups_update_groupmeta( $group_id, 'ca_group_only', $restrict );
            if ( function_exists( 'bp_core_add_message' ) ) {
                bp_core_add_message( __( 'Group auction settings saved.', 'community-auctions' ) );
            }
        }

        $restrict = groups_get_groupmeta( $group_id, 'ca_group_only', true );
        ?>
        <form method="post" class="community-auctions-group-settings">
            <?php wp_nonce_field( 'community_auctions_group_settings', 'community_auctions_group_nonce' ); ?>
            <label>
                <input type="checkbox" name="ca_group_only" value="1" <?php checked( $restrict ); ?> />
                <?php esc_html_e( 'Restrict auctions to group members only', 'community-auctions' ); ?>
            </label>
            <button type="submit"><?php esc_html_e( 'Save', 'community-auctions' ); ?></button>
        </form>
        <?php
    }

    public static function activity_auction_created( $auction_id, $user_id ) {
        if ( ! function_exists( 'bp_activity_add' ) ) {
            return;
        }

        $auction_url = get_permalink( $auction_id );
        $start_price = get_post_meta( $auction_id, 'ca_start_price', true );
        $end_at = get_post_meta( $auction_id, 'ca_end_at', true );
        $formatted_price = Community_Auctions_Currency::format( floatval( $start_price ) );

        // Build rich content.
        $content = '<div class="ca-activity-auction">';

        // Add auction thumbnail if available.
        if ( has_post_thumbnail( $auction_id ) ) {
            $content .= '<div class="ca-activity-thumbnail">';
            $content .= '<a href="' . esc_url( $auction_url ) . '">';
            $content .= get_the_post_thumbnail( $auction_id, 'medium', array( 'class' => 'ca-activity-image' ) );
            $content .= '</a>';
            $content .= '</div>';
        }

        $content .= '<div class="ca-activity-details">';
        $content .= '<p class="ca-activity-price">';
        $content .= sprintf(
            /* translators: %s: starting price */
            __( 'Starting price: %s', 'community-auctions' ),
            '<strong>' . esc_html( $formatted_price ) . '</strong>'
        );
        $content .= '</p>';

        if ( $end_at ) {
            $end_date = wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $end_at ) );
            $content .= '<p class="ca-activity-ends">';
            $content .= sprintf(
                /* translators: %s: end date/time */
                __( 'Ends: %s', 'community-auctions' ),
                esc_html( $end_date )
            );
            $content .= '</p>';
        }

        $content .= '<p class="ca-activity-cta">';
        $content .= '<a href="' . esc_url( $auction_url ) . '" class="ca-activity-btn">' . esc_html__( 'View Auction', 'community-auctions' ) . '</a>';
        $content .= '</p>';
        $content .= '</div>';
        $content .= '</div>';

        bp_activity_add( array(
            'user_id'      => $user_id,
            'component'    => self::COMPONENT,
            'type'         => 'auction_created',
            'item_id'      => $auction_id,
            'content'      => $content,
            'primary_link' => $auction_url,
        ) );
    }

    public static function activity_bid_placed( $auction_id, $user_id, $amount ) {
        if ( ! function_exists( 'bp_activity_add' ) ) {
            return;
        }

        $auction_title = get_the_title( $auction_id );
        $auction_url = get_permalink( $auction_id );
        $bid_count = absint( get_post_meta( $auction_id, 'ca_bid_count', true ) );
        $formatted_amount = Community_Auctions_Currency::format( floatval( $amount ) );

        // Build rich content for activity stream.
        $content = '<div class="ca-activity-bid">';
        $content .= '<p class="ca-activity-bid-info">';
        $content .= sprintf(
            /* translators: %s: formatted bid amount */
            __( 'Bid amount: %s', 'community-auctions' ),
            '<strong>' . esc_html( $formatted_amount ) . '</strong>'
        );
        $content .= '</p>';

        // Add auction thumbnail if available.
        if ( has_post_thumbnail( $auction_id ) ) {
            $content .= '<div class="ca-activity-thumbnail">';
            $content .= '<a href="' . esc_url( $auction_url ) . '">';
            $content .= get_the_post_thumbnail( $auction_id, 'thumbnail', array( 'class' => 'ca-activity-image' ) );
            $content .= '</a>';
            $content .= '</div>';
        }

        $content .= '<p class="ca-activity-meta">';
        $content .= sprintf(
            /* translators: %d: total number of bids */
            _n( '%d bid total', '%d bids total', $bid_count, 'community-auctions' ),
            $bid_count
        );
        $content .= '</p>';
        $content .= '</div>';

        bp_activity_add( array(
            'user_id'           => $user_id,
            'component'         => self::COMPONENT,
            'type'              => 'bid_placed',
            'item_id'           => $auction_id,
            'secondary_item_id' => floatval( $amount ), // Store amount for action formatting.
            'content'           => $content,
            'primary_link'      => $auction_url,
        ) );
    }

    public static function activity_auction_ended( $auction_id, $user_id ) {
        if ( ! function_exists( 'bp_activity_add' ) ) {
            return;
        }

        $auction_url = get_permalink( $auction_id );
        $winner_id = absint( get_post_meta( $auction_id, 'ca_winner_id', true ) );
        $final_bid = get_post_meta( $auction_id, 'ca_current_bid', true );
        $bid_count = absint( get_post_meta( $auction_id, 'ca_bid_count', true ) );
        $seller_id = absint( get_post_field( 'post_author', $auction_id ) );

        // Build rich content.
        $content = '<div class="ca-activity-auction-ended">';

        // Add auction thumbnail if available.
        if ( has_post_thumbnail( $auction_id ) ) {
            $content .= '<div class="ca-activity-thumbnail">';
            $content .= '<a href="' . esc_url( $auction_url ) . '">';
            $content .= get_the_post_thumbnail( $auction_id, 'thumbnail', array( 'class' => 'ca-activity-image' ) );
            $content .= '</a>';
            $content .= '</div>';
        }

        $content .= '<div class="ca-activity-details">';

        if ( $winner_id ) {
            $winner = get_userdata( $winner_id );
            $winner_name = $winner ? $winner->display_name : __( 'Unknown', 'community-auctions' );
            $formatted_amount = Community_Auctions_Currency::format( floatval( $final_bid ) );

            $content .= '<p class="ca-activity-winner">';
            $content .= '<span class="ca-winner-badge">🏆</span> ';
            $content .= sprintf(
                /* translators: 1: winner name, 2: final bid amount */
                __( 'Won by %1$s for %2$s', 'community-auctions' ),
                '<strong>' . esc_html( $winner_name ) . '</strong>',
                '<strong>' . esc_html( $formatted_amount ) . '</strong>'
            );
            $content .= '</p>';
        } else {
            $content .= '<p class="ca-activity-no-winner">';
            $content .= esc_html__( 'Auction ended with no winner (reserve not met)', 'community-auctions' );
            $content .= '</p>';
        }

        $content .= '<p class="ca-activity-stats">';
        $content .= sprintf(
            /* translators: %d: total number of bids */
            _n( '%d bid received', '%d bids received', $bid_count, 'community-auctions' ),
            $bid_count
        );
        $content .= '</p>';

        $content .= '</div>';
        $content .= '</div>';

        // Use seller as the activity author for ended auctions.
        bp_activity_add( array(
            'user_id'           => $seller_id ? $seller_id : $user_id,
            'component'         => self::COMPONENT,
            'type'              => 'auction_ended',
            'item_id'           => $auction_id,
            'secondary_item_id' => $winner_id,
            'content'           => $content,
            'primary_link'      => $auction_url,
        ) );
    }

    public static function notify_outbid( $auction_id, $old_user_id, $new_user_id ) {
        if ( ! function_exists( 'bp_notifications_add_notification' ) ) {
            return;
        }

        if ( ! Community_Auctions_Notifications::is_bp_enabled( $old_user_id, 'outbid' ) ) {
            return;
        }

        bp_notifications_add_notification( array(
            'user_id'          => $old_user_id,
            'item_id'          => $auction_id,
            'component_name'   => self::COMPONENT,
            'component_action' => 'outbid',
        ) );
    }

    public static function notify_winner( $auction_id, $user_id ) {
        if ( ! function_exists( 'bp_notifications_add_notification' ) ) {
            return;
        }

        if ( ! Community_Auctions_Notifications::is_bp_enabled( $user_id, 'won' ) ) {
            return;
        }

        bp_notifications_add_notification( array(
            'user_id'          => $user_id,
            'item_id'          => $auction_id,
            'component_name'   => self::COMPONENT,
            'component_action' => 'won',
        ) );
    }

    public static function notify_payment_reminder( $auction_id, $user_id ) {
        if ( ! function_exists( 'bp_notifications_add_notification' ) ) {
            return;
        }

        if ( ! Community_Auctions_Notifications::is_bp_enabled( $user_id, 'payment_reminder' ) ) {
            return;
        }

        bp_notifications_add_notification( array(
            'user_id'          => $user_id,
            'item_id'          => $auction_id,
            'component_name'   => self::COMPONENT,
            'component_action' => 'payment_reminder',
        ) );
    }
}

if ( class_exists( 'BP_Group_Extension' ) && ! class_exists( 'Community_Auctions_Group_Extension' ) ) {
    class Community_Auctions_Group_Extension extends BP_Group_Extension {
        public function __construct() {
            $this->name = __( 'Auctions', 'community-auctions' );
            $this->slug = 'auctions';
        }

        public function display( $group_id = null ) {
            Community_Auctions_BuddyPress_Integration::render_group_settings( $group_id );
            Community_Auctions_BuddyPress_Integration::render_template( 'bp-group-auctions.php' );
        }
    }
}
