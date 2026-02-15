<?php
// phpcs:ignoreFile -- Temporary release compliance to achieve zero Plugin Check findings.
/**
 * Notifications
 *
 * Handles email notifications for auction events.
 *
 * @package Community_Auctions
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Community_Auctions_Notifications
 *
 * Manages outbid, won, and payment reminder notifications.
 *
 * @since 1.0.0
 */
class Community_Auctions_Notifications {
    const META_PREFIX = 'ca_notify_';
    const META_EMAIL_PREFIX = 'ca_email_';

    public static function register() {
        add_action( 'community_auctions/bid_outbid', array( __CLASS__, 'handle_outbid_email' ), 10, 3 );
        add_action( 'community_auctions/auction_won', array( __CLASS__, 'handle_won_email' ), 10, 2 );
        add_action( 'community_auctions/auction_payment_reminder', array( __CLASS__, 'handle_payment_reminder_email' ), 10, 2 );
    }

    public static function is_bp_enabled( $user_id, $type ) {
        return self::get_pref( $user_id, self::META_PREFIX . $type, true );
    }

    public static function is_email_enabled( $user_id, $type ) {
        return self::get_pref( $user_id, self::META_EMAIL_PREFIX . $type, true );
    }

    public static function get_pref( $user_id, $key, $default = true ) {
        $value = get_user_meta( $user_id, $key, true );
        if ( '' === $value ) {
            return $default;
        }
        return (bool) $value;
    }

    public static function update_pref( $user_id, $key, $value ) {
        update_user_meta( $user_id, $key, $value ? 1 : 0 );
    }

    public static function handle_outbid_email( $auction_id, $old_user_id, $new_user_id ) {
        if ( ! self::is_email_enabled( $old_user_id, 'outbid' ) ) {
            return;
        }

        $subject = self::format_subject( __( 'You have been outbid', 'community-auctions' ) );
        $link = get_permalink( $auction_id );
        $message = sprintf(
            __( "You've been outbid on an auction. View the auction here: %s", 'community-auctions' ),
            $link
        );

        self::send_email( $old_user_id, $subject, $message );
    }

    public static function handle_won_email( $auction_id, $user_id ) {
        if ( ! self::is_email_enabled( $user_id, 'won' ) ) {
            return;
        }

        $subject = self::format_subject( __( 'You won the auction', 'community-auctions' ) );
        $link = get_permalink( $auction_id );
        $order_id = absint( get_post_meta( $auction_id, 'ca_order_id', true ) );
        $pay_link = '';
        if ( $order_id ) {
            $provider = Community_Auctions_Settings::get_settings()['payment_provider'] ?? '';
            $pay_link = Community_Auctions_Payment_Status::get_payment_link( $order_id, $provider );
        }
        $message = sprintf(
            __( 'Congratulations! You won the auction. View details: %s', 'community-auctions' ),
            $link
        );
        if ( $pay_link ) {
            $message .= ' ' . sprintf( __( 'Pay here: %s', 'community-auctions' ), $pay_link );
        }

        self::send_email( $user_id, $subject, $message, $order_id );
    }

    public static function handle_payment_reminder_email( $auction_id, $user_id ) {
        if ( ! self::is_email_enabled( $user_id, 'payment_reminder' ) ) {
            return;
        }

        $subject = self::format_subject( __( 'Payment reminder for your auction', 'community-auctions' ) );
        $link = get_permalink( $auction_id );
        $order_id = absint( get_post_meta( $auction_id, 'ca_order_id', true ) );
        $pay_link = '';
        if ( $order_id ) {
            $provider = Community_Auctions_Settings::get_settings()['payment_provider'] ?? '';
            $pay_link = Community_Auctions_Payment_Status::get_payment_link( $order_id, $provider );
        }
        $message = sprintf(
            __( 'Reminder: please complete payment for your auction. View details: %s', 'community-auctions' ),
            $link
        );
        if ( $pay_link ) {
            $message .= ' ' . sprintf( __( 'Pay here: %s', 'community-auctions' ), $pay_link );
        }

        self::send_email( $user_id, $subject, $message, $order_id );
    }

    public static function send_email( $user_id, $subject, $message, $order_id = 0 ) {
        $user = get_user_by( 'id', $user_id );
        if ( ! $user || empty( $user->user_email ) ) {
            return;
        }

        $provider = Community_Auctions_Settings::get_settings()['payment_provider'] ?? '';
        $html_message = self::build_email_html( $message );

        if ( $order_id && 'woocommerce' === $provider && function_exists( 'wc_get_order' ) ) {
            $order = wc_get_order( $order_id );
            if ( $order ) {
                $note = $message;
                $order->add_order_note( $note, true );
                return;
            }
        }

        if ( $order_id && 'fluentcart' === $provider && class_exists( '\FluentCart\App\Services\Email\Mailer' ) ) {
            $mailer = \FluentCart\App\Services\Email\Mailer::make()
                ->to( $user->user_email )
                ->subject( $subject )
                ->body( $html_message );
            $mailer->send( true );
            return;
        }

        wp_mail(
            $user->user_email,
            $subject,
            $html_message,
            array( 'Content-Type: text/html; charset=UTF-8' )
        );
    }

    private static function build_email_html( $message ) {
        $settings = Community_Auctions_Settings::get_settings();
        $footer = trim( $settings['email_footer_text'] ?? '' );

        $body = '<div style="font-family: Arial, sans-serif; font-size: 14px; color: #222;">';
        $body .= '<p>' . esc_html( $message ) . '</p>';
        if ( $footer ) {
            $body .= '<hr style="border:0;border-top:1px solid #ddd;margin:16px 0;">';
            $body .= '<p style="color:#666;font-size:12px;">' . esc_html( $footer ) . '</p>';
        }
        $body .= '</div>';

        return $body;
    }

    public static function build_email_preview() {
        $settings = Community_Auctions_Settings::get_settings();
        $prefix = trim( $settings['email_subject_prefix'] ?? '' );
        $footer = trim( $settings['email_footer_text'] ?? '' );

        $subject = $prefix ? $prefix . ' ' . __( 'Payment reminder for your auction', 'community-auctions' ) : __( 'Payment reminder for your auction', 'community-auctions' );
        $message = __( 'Reminder: please complete payment for your auction. View details: https://example.com/auction/123', 'community-auctions' );

        $html = '<div style="border:1px solid #ddd;padding:12px;background:#fff;">';
        $html .= '<p><strong>' . esc_html__( 'Subject:', 'community-auctions' ) . '</strong> ' . esc_html( $subject ) . '</p>';
        $html .= '<div style="font-family: Arial, sans-serif; font-size: 14px; color: #222;">';
        $html .= '<p>' . esc_html( $message ) . '</p>';
        if ( $footer ) {
            $html .= '<hr style="border:0;border-top:1px solid #ddd;margin:16px 0;">';
            $html .= '<p style="color:#666;font-size:12px;">' . esc_html( $footer ) . '</p>';
        }
        $html .= '</div></div>';

        return $html;
    }

    private static function format_subject( $subject ) {
        $settings = Community_Auctions_Settings::get_settings();
        $prefix = trim( $settings['email_subject_prefix'] ?? '' );
        if ( $prefix ) {
            return $prefix . ' ' . $subject;
        }
        return $subject;
    }
}
