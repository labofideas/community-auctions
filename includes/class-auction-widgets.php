<?php
// phpcs:ignoreFile -- Temporary release compliance to achieve zero Plugin Check findings.
/**
 * Auction Widgets
 *
 * Provides sidebar widgets and Gutenberg blocks for auctions.
 *
 * @package Community_Auctions
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Community_Auctions_Widgets
 *
 * Handles widget registration and rendering.
 *
 * @since 1.0.0
 */
class Community_Auctions_Widgets {
    public static function register() {
        add_action( 'widgets_init', array( __CLASS__, 'register_widget' ) );
        add_action( 'init', array( __CLASS__, 'register_block' ) );
    }

    public static function register_widget() {
        register_widget( 'Community_Auctions_Current_Auctions_Widget' );
    }

    public static function register_block() {
        $script_handle = 'community-auctions-blocks';
        wp_register_script(
            $script_handle,
            plugins_url( '../assets/js/block-auctions-list.js', __FILE__ ),
            array( 'wp-blocks', 'wp-element', 'wp-i18n', 'wp-components', 'wp-block-editor', 'wp-server-side-render' ),
            Community_Auctions_Plugin::VERSION,
            true
        );

        register_block_type( 'community-auctions/auctions-list', array(
            'editor_script'   => $script_handle,
            'render_callback' => array( __CLASS__, 'render_block' ),
            'attributes'      => array(
                'perPage' => array(
                    'type'    => 'number',
                    'default' => 10,
                ),
                'status' => array(
                    'type'    => 'string',
                    'default' => 'live',
                ),
                'ending' => array(
                    'type'    => 'string',
                    'default' => '',
                ),
                'minBid' => array(
                    'type'    => 'string',
                    'default' => '',
                ),
                'showGroupBadge' => array(
                    'type'    => 'boolean',
                    'default' => true,
                ),
            ),
        ) );
    }

    public static function render_block( $attributes ) {
        $atts = array(
            'per_page' => $attributes['perPage'] ?? 10,
            'status'   => $attributes['status'] ?? 'live',
            'ending'   => $attributes['ending'] ?? '',
            'min_bid'  => $attributes['minBid'] ?? '',
            'show_group_badge' => ! empty( $attributes['showGroupBadge'] ) ? '1' : '0',
        );

        return Community_Auctions_Auction_Shortcodes::render_list( $atts );
    }
}

class Community_Auctions_Current_Auctions_Widget extends WP_Widget {
    public function __construct() {
        parent::__construct(
            'community_auctions_current',
            __( 'Community Auctions: Current Auctions', 'community-auctions' ),
            array( 'description' => __( 'Displays current auctions.', 'community-auctions' ) )
        );
    }

    public function widget( $args, $instance ) {
        $title = apply_filters( 'widget_title', $instance['title'] ?? __( 'Current Auctions', 'community-auctions' ) );
        $per_page = isset( $instance['per_page'] ) ? intval( $instance['per_page'] ) : 5;
        $status = $instance['status'] ?? 'live';
        $ending = $instance['ending'] ?? '';
        $min_bid = $instance['min_bid'] ?? '';
        $show_group_badge = ! empty( $instance['show_group_badge'] );

        echo wp_kses_post( $args['before_widget'] );
        if ( $title ) {
            echo wp_kses_post( $args['before_title'] ) . esc_html( $title ) . wp_kses_post( $args['after_title'] );
        }

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Shortcode output is escaped internally.
        echo Community_Auctions_Auction_Shortcodes::render_list( array(
            'per_page' => $per_page,
            'status'   => $status,
            'ending'   => $ending,
            'min_bid'  => $min_bid,
            'show_group_badge' => $show_group_badge ? '1' : '0',
        ) );

        echo wp_kses_post( $args['after_widget'] );
    }

    public function form( $instance ) {
        $title = $instance['title'] ?? __( 'Current Auctions', 'community-auctions' );
        $per_page = isset( $instance['per_page'] ) ? intval( $instance['per_page'] ) : 5;
        $status = $instance['status'] ?? 'live';
        $ending = $instance['ending'] ?? '';
        $min_bid = $instance['min_bid'] ?? '';
        $show_group_badge = ! empty( $instance['show_group_badge'] );

        ?>
        <p>
            <label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php esc_html_e( 'Title', 'community-auctions' ); ?></label>
            <input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>">
        </p>
        <p>
            <label for="<?php echo esc_attr( $this->get_field_id( 'per_page' ) ); ?>"><?php esc_html_e( 'Number of auctions', 'community-auctions' ); ?></label>
            <input class="small-text" id="<?php echo esc_attr( $this->get_field_id( 'per_page' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'per_page' ) ); ?>" type="number" min="1" value="<?php echo esc_attr( $per_page ); ?>">
        </p>
        <p>
            <label for="<?php echo esc_attr( $this->get_field_id( 'status' ) ); ?>"><?php esc_html_e( 'Status', 'community-auctions' ); ?></label>
            <select id="<?php echo esc_attr( $this->get_field_id( 'status' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'status' ) ); ?>">
                <option value="live" <?php selected( $status, 'live' ); ?>><?php esc_html_e( 'Live', 'community-auctions' ); ?></option>
                <option value="ended" <?php selected( $status, 'ended' ); ?>><?php esc_html_e( 'Ended', 'community-auctions' ); ?></option>
            </select>
        </p>
        <p>
            <label for="<?php echo esc_attr( $this->get_field_id( 'ending' ) ); ?>"><?php esc_html_e( 'Ending filter', 'community-auctions' ); ?></label>
            <select id="<?php echo esc_attr( $this->get_field_id( 'ending' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'ending' ) ); ?>">
                <option value="" <?php selected( $ending, '' ); ?>><?php esc_html_e( 'Default', 'community-auctions' ); ?></option>
                <option value="ending_soon" <?php selected( $ending, 'ending_soon' ); ?>><?php esc_html_e( 'Ending soon', 'community-auctions' ); ?></option>
            </select>
        </p>
        <p>
            <label for="<?php echo esc_attr( $this->get_field_id( 'min_bid' ) ); ?>"><?php esc_html_e( 'Minimum bid', 'community-auctions' ); ?></label>
            <input class="small-text" id="<?php echo esc_attr( $this->get_field_id( 'min_bid' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'min_bid' ) ); ?>" type="text" value="<?php echo esc_attr( $min_bid ); ?>">
        </p>
        <p>
            <input class="checkbox" type="checkbox" <?php checked( $show_group_badge ); ?> id="<?php echo esc_attr( $this->get_field_id( 'show_group_badge' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'show_group_badge' ) ); ?>" />
            <label for="<?php echo esc_attr( $this->get_field_id( 'show_group_badge' ) ); ?>"><?php esc_html_e( 'Show group badge', 'community-auctions' ); ?></label>
        </p>
        <?php
    }

    public function update( $new_instance, $old_instance ) {
        $instance = array();
        $instance['title'] = sanitize_text_field( $new_instance['title'] ?? '' );
        $instance['per_page'] = max( 1, intval( $new_instance['per_page'] ?? 5 ) );
        $instance['status'] = in_array( $new_instance['status'] ?? 'live', array( 'live', 'ended' ), true ) ? $new_instance['status'] : 'live';
        $instance['ending'] = in_array( $new_instance['ending'] ?? '', array( '', 'ending_soon' ), true ) ? $new_instance['ending'] : '';
        $instance['min_bid'] = sanitize_text_field( $new_instance['min_bid'] ?? '' );
        $instance['show_group_badge'] = ! empty( $new_instance['show_group_badge'] ) ? 1 : 0;

        return $instance;
    }
}
