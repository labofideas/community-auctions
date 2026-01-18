<?php
/**
 * Auction Shortcodes
 *
 * Provides shortcodes for displaying auctions and submission forms.
 *
 * @package Community_Auctions
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Community_Auctions_Auction_Shortcodes
 *
 * Handles auction listing and single auction shortcodes.
 *
 * @since 1.0.0
 */
class Community_Auctions_Auction_Shortcodes {
    public static function register() {
        add_shortcode( 'community_auctions_list', array( __CLASS__, 'render_list' ) );
        add_shortcode( 'community_auction_single', array( __CLASS__, 'render_single' ) );
    }

    public static function render_list( $atts ) {
        $atts = shortcode_atts( array(
            'per_page' => 10,
            'status'   => 'live',
            'ending'   => '',
            'min_bid'  => '',
            'show_group_badge' => '1',
            'show_category' => '1',
            'paged'    => 1,
            'group_id' => 0,
            'author_id' => 0,
            'category' => '',
        ), $atts );

        $post_status = array( 'publish', 'ca_live' );
        if ( 'ended' === $atts['status'] ) {
            $post_status = array( 'ca_ended', 'ca_closed' );
        } elseif ( 'all' === $atts['status'] ) {
            $post_status = array( 'publish', 'ca_live', 'ca_pending', 'ca_ended', 'ca_closed' );
        }

        $meta_query = array(
            'relation' => 'AND',
            self::build_visibility_query(),
        );

        $group_id = absint( $atts['group_id'] );
        if ( $group_id ) {
            $meta_query[] = array(
                'key'     => 'ca_group_id',
                'value'   => $group_id,
                'compare' => '=',
            );
        }

        if ( '' !== $atts['min_bid'] ) {
            $meta_query[] = array(
                'key'     => 'ca_current_bid',
                'value'   => floatval( $atts['min_bid'] ),
                'type'    => 'NUMERIC',
                'compare' => '>=',
            );
        }

        $orderby = 'date';
        $order = 'DESC';
        if ( 'ending_soon' === $atts['ending'] ) {
            $orderby = 'meta_value';
            $order = 'ASC';
            $meta_query[] = array(
                'key'     => 'ca_end_at',
                'compare' => 'EXISTS',
            );
        }

        $query_args = array(
            'post_type'      => 'auction',
            'post_status'    => $post_status,
            'posts_per_page' => intval( $atts['per_page'] ),
            'meta_query'     => $meta_query,
            'orderby'        => $orderby,
            'order'          => $order,
            'meta_key'       => ( 'ending_soon' === $atts['ending'] ) ? 'ca_end_at' : '',
            'paged'          => max( 1, intval( $atts['paged'] ) ),
        );

        // Category filtering.
        if ( ! empty( $atts['category'] ) ) {
            $category_terms = array_map( 'trim', explode( ',', $atts['category'] ) );
            $query_args['tax_query'] = array(
                array(
                    'taxonomy' => Community_Auctions_Taxonomy::TAXONOMY,
                    'field'    => is_numeric( $category_terms[0] ) ? 'term_id' : 'slug',
                    'terms'    => $category_terms,
                ),
            );
        }

        $author_id = absint( $atts['author_id'] );
        if ( $author_id ) {
            $query_args['author'] = $author_id;
        }

        $query = new WP_Query( $query_args );

        if ( ! $query->have_posts() ) {
            return '<p role="status">' . esc_html__( 'No auctions found.', 'community-auctions' ) . '</p>';
        }

        self::enqueue_assets();

        // Get currency symbol.
        $settings        = Community_Auctions_Settings::get_settings();
        $currency_symbol = isset( $settings['currency_symbol'] ) ? $settings['currency_symbol'] : '$';

        ob_start();
        echo '<div class="ca-auctions-grid" role="list">';
        while ( $query->have_posts() ) {
            $query->the_post();
            $auction_id  = get_the_ID();
            $current_bid = get_post_meta( $auction_id, 'ca_current_bid', true );
            $start_price = get_post_meta( $auction_id, 'ca_start_price', true );
            $end_at      = get_post_meta( $auction_id, 'ca_end_at', true );
            $order_id    = absint( get_post_meta( $auction_id, 'ca_order_id', true ) );
            $status      = get_post_status( $auction_id );
            $bid_count   = absint( get_post_meta( $auction_id, 'ca_bid_count', true ) );
            $thumbnail   = get_the_post_thumbnail( $auction_id, 'medium', array( 'class' => 'ca-card-image', 'alt' => get_the_title() ) );

            $payment_badge = '';
            if ( $order_id && in_array( $status, array( 'ca_ended', 'ca_closed' ), true ) ) {
                $provider = $settings['payment_provider'] ?? '';
                $paid     = Community_Auctions_Payment_Status::is_order_paid( $order_id, $provider );
                $payment_badge = $paid
                    ? '<span class="ca-badge ca-badge--paid">' . esc_html__( 'Paid', 'community-auctions' ) . '</span>'
                    : '<span class="ca-badge ca-badge--pending">' . esc_html__( 'Pending', 'community-auctions' ) . '</span>';
            }

            // Determine status badge.
            $status_badge = '';
            if ( 'ca_live' === $status || 'publish' === $status ) {
                $end_timestamp = strtotime( $end_at );
                $seconds_left  = $end_timestamp ? max( 0, $end_timestamp - time() ) : 0;
                if ( $seconds_left > 0 && $seconds_left < 3600 ) {
                    $status_badge = '<span class="ca-badge ca-badge--urgent">' . esc_html__( 'Ending Soon', 'community-auctions' ) . '</span>';
                } else {
                    $status_badge = '<span class="ca-badge ca-badge--live">' . esc_html__( 'Live', 'community-auctions' ) . '</span>';
                }
            } elseif ( in_array( $status, array( 'ca_ended', 'ca_closed' ), true ) ) {
                $status_badge = '<span class="ca-badge ca-badge--ended">' . esc_html__( 'Ended', 'community-auctions' ) . '</span>';
            }

            // Display price - current bid or starting price.
            $display_price = $current_bid ? $current_bid : $start_price;
            ?>
            <article class="ca-auction-card" role="listitem">
                <a href="<?php echo esc_url( get_permalink( $auction_id ) ); ?>" class="ca-card-link">
                    <div class="ca-card-image-wrapper">
                        <?php if ( $thumbnail ) : ?>
                            <?php echo wp_kses_post( $thumbnail ); ?>
                        <?php else : ?>
                            <div class="ca-card-placeholder">
                                <span class="dashicons dashicons-format-image"></span>
                            </div>
                        <?php endif; ?>
                        <div class="ca-card-badges">
                            <?php echo wp_kses_post( $status_badge ); ?>
                            <?php if ( $payment_badge ) : ?>
                                <?php echo wp_kses_post( $payment_badge ); ?>
                            <?php endif; ?>
                            <?php if ( '1' === $atts['show_group_badge'] && 'group_only' === get_post_meta( $auction_id, 'ca_visibility', true ) ) : ?>
                                <span class="ca-badge ca-badge--group"><?php esc_html_e( 'Group', 'community-auctions' ); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="ca-card-content">
                        <h3 class="ca-card-title"><?php the_title(); ?></h3>
                        <?php if ( '1' === $atts['show_category'] ) : ?>
                            <?php
                            $categories = Community_Auctions_Taxonomy::get_auction_categories( $auction_id );
                            if ( ! empty( $categories ) ) :
                                ?>
                                <div class="ca-card-category">
                                    <?php echo esc_html( $categories[0]->name ); ?>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                        <div class="ca-card-price">
                            <span class="ca-card-price-label"><?php echo $current_bid ? esc_html__( 'Current Bid', 'community-auctions' ) : esc_html__( 'Starting At', 'community-auctions' ); ?></span>
                            <span class="ca-card-price-value"><?php echo esc_html( $currency_symbol . number_format( floatval( $display_price ), 2 ) ); ?></span>
                        </div>
                        <div class="ca-card-meta">
                            <span class="ca-card-bids">
                                <span class="dashicons dashicons-chart-bar"></span>
                                <?php echo esc_html( sprintf( _n( '%d bid', '%d bids', $bid_count, 'community-auctions' ), $bid_count ) ); ?>
                            </span>
                            <span class="ca-card-time">
                                <?php echo Community_Auctions_Countdown_Timer::render_inline( $auction_id, 'end' ); ?>
                            </span>
                        </div>
                    </div>
                </a>
            </article>
            <?php
        }
        echo '</div>';
        wp_reset_postdata();

        $pagination = '';
        if ( $query->max_num_pages > 1 ) {
            $pagination = paginate_links( array(
                'base'      => esc_url_raw( add_query_arg( 'ca_page', '%#%' ) ),
                'format'    => '',
                'current'   => max( 1, intval( $atts['paged'] ) ),
                'total'     => $query->max_num_pages,
                'prev_text' => __( '&laquo; Prev', 'community-auctions' ),
                'next_text' => __( 'Next &raquo;', 'community-auctions' ),
                'type'      => 'list',
            ) );
        }

        if ( $pagination ) {
            echo '<nav class="community-auctions-pagination" aria-label="' . esc_attr__( 'Auction pagination', 'community-auctions' ) . '">';
            echo wp_kses_post( $pagination );
            echo '</nav>';
        }

        return ob_get_clean();
    }

    public static function render_single( $atts ) {
        $atts = shortcode_atts( array(
            'id' => 0,
            'show_group_badge' => '1',
            'show_bid_history' => '1',
            'show_gallery' => '1',
        ), $atts );

        $auction_id = absint( $atts['id'] );
        if ( ! $auction_id ) {
            $auction_id = get_the_ID();
        }

        $auction = get_post( $auction_id );
        if ( ! $auction || 'auction' !== $auction->post_type ) {
            return '<p>' . esc_html__( 'Auction not found.', 'community-auctions' ) . '</p>';
        }

        if ( ! self::can_view_auction( $auction_id ) ) {
            return '<p>' . esc_html__( 'This auction is only visible to group members.', 'community-auctions' ) . '</p>';
        }

        self::enqueue_assets();

        $current_bid = get_post_meta( $auction_id, 'ca_current_bid', true );
        $end_at = get_post_meta( $auction_id, 'ca_end_at', true );
        $min_increment = get_post_meta( $auction_id, 'ca_min_increment', true );
        $min_increment_id = 'ca-min-increment-' . $auction_id;
        $bid_status_id = 'ca-bid-status-' . $auction_id;
        $payment_section = self::build_payment_section( $auction_id );
        $proxy_enabled = ! empty( get_post_meta( $auction_id, 'ca_proxy_enabled', true ) );
        $bid_anchor = 'ca-bid-form-' . $auction_id;

        $can_bid = is_user_logged_in() && self::is_payment_provider_selected();
        $status = get_post_status( $auction_id );
        $bid_link_visible = ! in_array( $status, array( 'ca_ended', 'ca_closed' ), true );
        $ended_label = in_array( $status, array( 'ca_ended', 'ca_closed' ), true ) ? __( 'Auction ended', 'community-auctions' ) : '';
        $current_bidder = absint( get_post_meta( $auction_id, 'ca_current_bidder', true ) );
        $end_timestamp = strtotime( $end_at );
        $seconds_left = $end_timestamp ? max( 0, $end_timestamp - time() ) : 0;

        ob_start();
        ?>
        <div class="community-auction-single<?php echo esc_attr( $ended_label ? ' ca-auction--ended' : '' ); ?>"
            data-auction-id="<?php echo esc_attr( $auction_id ); ?>"
            data-current-bid="<?php echo esc_attr( floatval( $current_bid ) ); ?>"
            data-current-bidder="<?php echo esc_attr( $current_bidder ); ?>"
            data-seconds-left="<?php echo esc_attr( $seconds_left ); ?>">
            <h2><?php echo esc_html( get_the_title( $auction_id ) ); ?></h2>
            <?php if ( $ended_label ) : ?>
                <span class="ca-badge ca-badge--ended"><?php echo esc_html( $ended_label ); ?></span>
            <?php endif; ?>
            <div class="community-auction-actions-bar">
                <?php if ( $bid_link_visible ) : ?>
                    <a class="community-auction-bid-link" href="#<?php echo esc_attr( $bid_anchor ); ?>">
                        <?php esc_html_e( 'Go to bid form', 'community-auctions' ); ?>
                    </a>
                <?php endif; ?>
                <?php echo Community_Auctions_Watchlist::render_button( $auction_id ); ?>
            </div>
            <?php if ( '1' === $atts['show_gallery'] ) : ?>
                <?php echo Community_Auctions_Image_Gallery::render_inline( $auction_id ); ?>
            <?php endif; ?>
            <div class="community-auction-content"><?php echo wp_kses_post( wpautop( $auction->post_content ) ); ?></div>
            <p><strong><?php esc_html_e( 'Current Bid:', 'community-auctions' ); ?></strong> <span class="ca-current-bid" aria-live="polite"><?php echo esc_html( $current_bid ? $current_bid : '-' ); ?></span></p>
            <p class="ca-ends-section">
                <strong><?php esc_html_e( 'Ends:', 'community-auctions' ); ?></strong>
                <?php echo Community_Auctions_Countdown_Timer::render_inline( $auction_id, 'end' ); ?>
                <span class="ca-end-date">(<?php echo esc_html( $end_at ? $end_at : '-' ); ?>)</span>
            </p>
            <?php if ( '1' === $atts['show_group_badge'] && 'group_only' === get_post_meta( $auction_id, 'ca_visibility', true ) ) : ?>
                <span class="ca-badge"><?php esc_html_e( 'Group Only', 'community-auctions' ); ?></span>
            <?php endif; ?>
            <?php echo Community_Auctions_Buy_Now::render_button( $auction_id ); ?>
            <?php if ( $payment_section ) : ?>
                <div class="community-auction-payment">
                    <?php echo wp_kses_post( $payment_section ); ?>
                </div>
            <?php endif; ?>
            <?php if ( $can_bid ) : ?>
                <form class="community-auction-bid-form" id="<?php echo esc_attr( $bid_anchor ); ?>" aria-describedby="<?php echo esc_attr( $bid_status_id ); ?>">
                    <label for="ca-bid-amount-<?php echo esc_attr( $auction_id ); ?>">
                        <?php esc_html_e( 'Your Bid', 'community-auctions' ); ?>
                    </label>
                    <input
                        type="number"
                        step="0.01"
                        min="0"
                        id="ca-bid-amount-<?php echo esc_attr( $auction_id ); ?>"
                        name="amount"
                        required
                        <?php echo $min_increment ? 'aria-describedby="' . esc_attr( $min_increment_id ) . '"' : ''; ?>
                    />
                    <?php if ( $proxy_enabled ) : ?>
                        <label for="ca-bid-proxy-<?php echo esc_attr( $auction_id ); ?>">
                            <?php esc_html_e( 'Proxy Max (optional)', 'community-auctions' ); ?>
                        </label>
                        <input
                            type="number"
                            step="0.01"
                            min="0"
                            id="ca-bid-proxy-<?php echo esc_attr( $auction_id ); ?>"
                            name="proxy_max"
                        />
                    <?php endif; ?>
                    <?php if ( $min_increment ) : ?>
                        <p id="<?php echo esc_attr( $min_increment_id ); ?>"><?php echo esc_html( sprintf( __( 'Minimum increment: %s', 'community-auctions' ), $min_increment ) ); ?></p>
                    <?php endif; ?>
                    <button type="submit"><?php esc_html_e( 'Place Bid', 'community-auctions' ); ?></button>
                    <div class="ca-bid-message" id="<?php echo esc_attr( $bid_status_id ); ?>" aria-live="polite" role="status"></div>
                </form>
            <?php elseif ( ! is_user_logged_in() ) : ?>
                <p><?php esc_html_e( 'Please log in to place a bid.', 'community-auctions' ); ?></p>
            <?php else : ?>
                <p><?php esc_html_e( 'Please select a payment provider before bidding.', 'community-auctions' ); ?></p>
            <?php endif; ?>

            <?php if ( '1' === $atts['show_bid_history'] ) : ?>
                <div class="community-auction-bid-history-section">
                    <h3><?php esc_html_e( 'Bid History', 'community-auctions' ); ?></h3>
                    <?php echo Community_Auctions_Bid_History::render_inline( $auction_id ); ?>
                </div>
            <?php endif; ?>
        </div>
        <?php

        return ob_get_clean();
    }

    private static function build_payment_section( $auction_id ) {
        $winner_id = absint( get_post_meta( $auction_id, 'ca_winner_id', true ) );
        if ( ! $winner_id || get_current_user_id() !== $winner_id ) {
            return '';
        }

        $order_id = absint( get_post_meta( $auction_id, 'ca_order_id', true ) );
        if ( ! $order_id ) {
            return '<p>' . esc_html__( 'Your payment will be created shortly.', 'community-auctions' ) . '</p>';
        }

        $settings = Community_Auctions_Settings::get_settings();
        $provider = $settings['payment_provider'] ?? '';
        $paid = Community_Auctions_Payment_Status::is_order_paid( $order_id, $provider );

        if ( $paid ) {
            return '<p>' . esc_html__( 'Payment received. Thank you!', 'community-auctions' ) . '</p>';
        }

        $pay_url = Community_Auctions_Payment_Status::get_payment_link( $order_id, $provider );
        if ( $pay_url ) {
            return '<p><a class="button" href="' . esc_url( $pay_url ) . '">' . esc_html__( 'Pay Now', 'community-auctions' ) . '</a></p>';
        }

        return '<p>' . esc_html__( 'Please check your email for payment instructions.', 'community-auctions' ) . '</p>';
    }

    private static function enqueue_assets() {
        $handle = 'community-auctions';
        $url = plugin_dir_url( __DIR__ ) . 'assets/js/auction.js';
        $css_url = plugin_dir_url( __DIR__ ) . 'assets/css/auction.css';

        if ( wp_script_is( $handle, 'enqueued' ) ) {
            return;
        }

        wp_enqueue_style( $handle, $css_url, array(), Community_Auctions_Plugin::VERSION );
        wp_enqueue_script( $handle, $url, array(), Community_Auctions_Plugin::VERSION, true );
        wp_localize_script( $handle, 'CommunityAuctions', array(
            'restUrl'       => esc_url_raw( rest_url( 'community-auctions/v1/bid' ) ),
            'restBase'      => esc_url_raw( rest_url( 'community-auctions/v1/' ) ),
            'nonce'         => wp_create_nonce( 'wp_rest' ),
            'currentUserId' => get_current_user_id(),
        ) );

        // Enqueue realtime updates script.
        Community_Auctions_Realtime_Updates::enqueue_assets();
    }

    private static function is_payment_provider_selected() {
        $settings = Community_Auctions_Settings::get_settings();
        $provider = $settings['payment_provider'] ?? '';
        if ( empty( $provider ) ) {
            return false;
        }

        if ( 'woocommerce' === $provider ) {
            return Community_Auctions_Payment_WooCommerce::is_available();
        }

        if ( 'fluentcart' === $provider ) {
            return Community_Auctions_Payment_FluentCart::is_available();
        }

        return false;
    }

    private static function can_view_auction( $auction_id ) {
        $visibility = get_post_meta( $auction_id, 'ca_visibility', true );
        if ( 'group_only' !== $visibility ) {
            return true;
        }

        $group_id = absint( get_post_meta( $auction_id, 'ca_group_id', true ) );
        if ( ! $group_id || ! function_exists( 'groups_is_user_member' ) ) {
            return false;
        }

        return groups_is_user_member( get_current_user_id(), $group_id );
    }

    private static function build_visibility_query() {
        $meta_query = array(
            'relation' => 'OR',
            array(
                'key'     => 'ca_visibility',
                'value'   => 'public',
                'compare' => '=',
            ),
            array(
                'key'     => 'ca_visibility',
                'compare' => 'NOT EXISTS',
            ),
        );

        if ( ! is_user_logged_in() || ! function_exists( 'groups_get_user_groups' ) ) {
            return $meta_query;
        }

        $groups = groups_get_user_groups( get_current_user_id() );
        $group_ids = isset( $groups['groups'] ) ? array_map( 'absint', $groups['groups'] ) : array();
        if ( $group_ids ) {
            $meta_query[] = array(
                'key'     => 'ca_group_id',
                'value'   => $group_ids,
                'compare' => 'IN',
            );
        }

        return $meta_query;
    }
}
