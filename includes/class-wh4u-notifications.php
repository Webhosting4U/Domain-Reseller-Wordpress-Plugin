<?php
/**
 * Notification system: email (wp_mail) and webhook dispatch with HMAC signature.
 *
 * Uses WordPress hooks (do_action / add_action) so other plugins can extend
 * or replace the notification behaviour.
 *
 * @package WH4U_Domains
 * @license GPL-2.0-or-later
 * @link    https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WH4U_Notifications {

    /**
     * Register WordPress hooks for notification dispatch.
     *
     * @return void
     */
    public static function init_hooks() {
        add_action( 'wh4u_new_public_order', array( __CLASS__, 'handle_new_public_order' ), 10, 2 );
    }

    /**
     * Handle notification for a new public domain registration order.
     *
     * Reads order data from post meta and dispatches admin email via wp_mail.
     *
     * @param int   $post_id WP post ID of the public order.
     * @param array $meta    Sanitized order meta values.
     * @return void
     */
    public static function handle_new_public_order( $post_id, $meta ) {
        $admin_email = get_option( 'admin_email' );
        if ( empty( $admin_email ) ) {
            return;
        }

        $domain = isset( $meta['_wh4u_domain'] ) ? $meta['_wh4u_domain'] : get_the_title( $post_id );

        $subject = sprintf(
            /* translators: 1: domain name */
            __( '[WH4U Domains] New Public Registration Request: %s', 'wh4u-domains' ),
            $domain
        );

        $fields = array(
            __( 'Order ID', 'wh4u-domains' )  => '#' . $post_id,
            __( 'Domain', 'wh4u-domains' )     => $domain,
            __( 'Period', 'wh4u-domains' )     => ( isset( $meta['_wh4u_reg_period'] ) ? $meta['_wh4u_reg_period'] : 1 ) . ' ' . __( 'year(s)', 'wh4u-domains' ),
            __( 'Name', 'wh4u-domains' )       => ( isset( $meta['_wh4u_first_name'] ) ? $meta['_wh4u_first_name'] : '' ) . ' ' . ( isset( $meta['_wh4u_last_name'] ) ? $meta['_wh4u_last_name'] : '' ),
            __( 'Email', 'wh4u-domains' )      => isset( $meta['_wh4u_email'] ) ? $meta['_wh4u_email'] : '',
            __( 'Phone', 'wh4u-domains' )      => isset( $meta['_wh4u_phone'] ) ? $meta['_wh4u_phone'] : '',
            __( 'Company', 'wh4u-domains' )    => isset( $meta['_wh4u_company'] ) ? $meta['_wh4u_company'] : '',
            __( 'Address', 'wh4u-domains' )    => isset( $meta['_wh4u_address'] ) ? $meta['_wh4u_address'] : '',
            __( 'City', 'wh4u-domains' )       => isset( $meta['_wh4u_city'] ) ? $meta['_wh4u_city'] : '',
            __( 'State', 'wh4u-domains' )      => isset( $meta['_wh4u_state'] ) ? $meta['_wh4u_state'] : '',
            __( 'Country', 'wh4u-domains' )    => isset( $meta['_wh4u_country'] ) ? $meta['_wh4u_country'] : '',
            __( 'Zip', 'wh4u-domains' )        => isset( $meta['_wh4u_zip'] ) ? $meta['_wh4u_zip'] : '',
        );

        $body = '<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">';
        $body .= '<h2>' . esc_html__( 'New Public Domain Registration Request', 'wh4u-domains' ) . '</h2>';
        $body .= '<table style="width: 100%; border-collapse: collapse;">';

        foreach ( $fields as $label => $value ) {
            $body .= '<tr>';
            $body .= '<td style="padding: 8px; border: 1px solid #ddd; font-weight: bold;">' . esc_html( $label ) . '</td>';
            $body .= '<td style="padding: 8px; border: 1px solid #ddd;">' . esc_html( $value ) . '</td>';
            $body .= '</tr>';
        }

        $body .= '</table>';
        $body .= '<p style="margin-top:16px;">';
        $body .= esc_html__( 'Review this order in the WordPress admin under Domains > Public Orders.', 'wh4u-domains' );
        $body .= '</p></div>';

        wp_mail( $admin_email, $subject, $body, array( 'Content-Type: text/html; charset=UTF-8' ) );
    }

    /**
     * Send notifications for an authenticated order event.
     *
     * @param int    $order_id Order ID.
     * @param string $event    Event type (e.g. 'completed', 'failed', 'pending_manual').
     * @return void
     */
    public static function send_order_notification( $order_id, $event ) {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $order = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}wh4u_orders WHERE id = %d",
                $order_id
            )
        );

        if ( ! $order ) {
            return;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $reseller = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}wh4u_reseller_settings WHERE user_id = %d",
                $order->user_id
            )
        );

        if ( ! $reseller ) {
            return;
        }

        self::send_email_notification( $order, $reseller, $event );

        if ( ! empty( $reseller->webhook_url ) ) {
            self::send_webhook_notification( $order, $reseller, $event );
        }
    }

    /**
     * Send an email notification about an order.
     *
     * @param object $order    Order row.
     * @param object $reseller Reseller settings row.
     * @param string $event    Event type.
     * @return bool
     */
    private static function send_email_notification( $order, $reseller, $event ) {
        global $wpdb;

        $to = ! empty( $reseller->reseller_email ) ? $reseller->reseller_email : '';
        if ( empty( $to ) || ! is_email( $to ) ) {
            $user = get_userdata( $order->user_id );
            $to   = $user ? $user->user_email : '';
        }

        if ( empty( $to ) ) {
            return false;
        }

        $subject = sprintf(
            /* translators: 1: event type, 2: domain name */
            __( '[WH4U Domains] Order %1$s: %2$s', 'wh4u-domains' ),
            ucfirst( $event ),
            $order->domain
        );

        $body = self::build_email_body( $order, $event );

        $headers = array( 'Content-Type: text/html; charset=UTF-8' );
        $sent    = wp_mail( $to, $subject, $body, $headers );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $wpdb->insert(
            $wpdb->prefix . 'wh4u_notifications',
            array(
                'user_id'    => $order->user_id,
                'order_id'   => $order->id,
                'type'       => 'email',
                'status'     => $sent ? 'sent' : 'failed',
                'recipient'  => sanitize_email( $to ),
                'subject'    => sanitize_text_field( $subject ),
                'body'       => wp_kses_post( $body ),
                'created_at' => current_time( 'mysql', true ),
            ),
            array( '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
        );

        return $sent;
    }

    /**
     * Build HTML email body for an order notification.
     *
     * @param object $order Order row.
     * @param string $event Event type.
     * @return string HTML body.
     */
    private static function build_email_body( $order, $event ) {
        $status_labels = array(
            'completed'      => __( 'Completed', 'wh4u-domains' ),
            'failed'         => __( 'Failed', 'wh4u-domains' ),
            'pending'        => __( 'Pending', 'wh4u-domains' ),
            'pending_manual' => __( 'Pending Manual Processing', 'wh4u-domains' ),
            'processing'     => __( 'Processing', 'wh4u-domains' ),
        );

        $status_label = isset( $status_labels[ $event ] ) ? $status_labels[ $event ] : ucfirst( $event );

        ob_start();
        ?>
        <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
            <h2><?php
				/* translators: %s: order status label (e.g. "Completed", "Failed") */
				echo esc_html( sprintf( __( 'Domain Order %s', 'wh4u-domains' ), $status_label ) );
			?></h2>
            <table style="width: 100%; border-collapse: collapse;">
                <tr>
                    <td style="padding: 8px; border: 1px solid #ddd; font-weight: bold;"><?php esc_html_e( 'Domain', 'wh4u-domains' ); ?></td>
                    <td style="padding: 8px; border: 1px solid #ddd;"><?php echo esc_html( $order->domain ); ?></td>
                </tr>
                <tr>
                    <td style="padding: 8px; border: 1px solid #ddd; font-weight: bold;"><?php esc_html_e( 'Type', 'wh4u-domains' ); ?></td>
                    <td style="padding: 8px; border: 1px solid #ddd;"><?php echo esc_html( ucfirst( $order->order_type ) ); ?></td>
                </tr>
                <tr>
                    <td style="padding: 8px; border: 1px solid #ddd; font-weight: bold;"><?php esc_html_e( 'Status', 'wh4u-domains' ); ?></td>
                    <td style="padding: 8px; border: 1px solid #ddd;"><?php echo esc_html( $status_label ); ?></td>
                </tr>
                <tr>
                    <td style="padding: 8px; border: 1px solid #ddd; font-weight: bold;"><?php esc_html_e( 'Period', 'wh4u-domains' ); ?></td>
                    <td style="padding: 8px; border: 1px solid #ddd;"><?php echo esc_html( $order->reg_period . ' ' . __( 'year(s)', 'wh4u-domains' ) ); ?></td>
                </tr>
                <tr>
                    <td style="padding: 8px; border: 1px solid #ddd; font-weight: bold;"><?php esc_html_e( 'Order ID', 'wh4u-domains' ); ?></td>
                    <td style="padding: 8px; border: 1px solid #ddd;">#<?php echo esc_html( $order->id ); ?></td>
                </tr>
                <?php if ( ! empty( $order->error_message ) && $event === 'failed' ) : ?>
                <tr>
                    <td style="padding: 8px; border: 1px solid #ddd; font-weight: bold;"><?php esc_html_e( 'Error', 'wh4u-domains' ); ?></td>
                    <td style="padding: 8px; border: 1px solid #ddd;"><?php echo esc_html( $order->error_message ); ?></td>
                </tr>
                <?php endif; ?>
                <tr>
                    <td style="padding: 8px; border: 1px solid #ddd; font-weight: bold;"><?php esc_html_e( 'Date', 'wh4u-domains' ); ?></td>
                    <td style="padding: 8px; border: 1px solid #ddd;"><?php echo esc_html( $order->created_at ); ?></td>
                </tr>
            </table>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Send a webhook notification with HMAC signature.
     *
     * @param object $order    Order row.
     * @param object $reseller Reseller settings row.
     * @param string $event    Event type.
     * @return bool
     */
    private static function send_webhook_notification( $order, $reseller, $event ) {
        global $wpdb;

        $webhook_url = esc_url_raw( $reseller->webhook_url );
        if ( empty( $webhook_url ) ) {
            return false;
        }

        if ( ! self::is_safe_webhook_url( $webhook_url ) ) {
            return false;
        }

        $payload = wp_json_encode( array(
            'event'      => $event,
            'order_id'   => (int) $order->id,
            'domain'     => $order->domain,
            'order_type' => $order->order_type,
            'status'     => $order->status,
            'reg_period' => (int) $order->reg_period,
            'timestamp'  => current_time( 'c' ),
        ) );

        $headers = array(
            'Content-Type' => 'application/json',
        );

        $webhook_secret = '';
        if ( ! empty( $reseller->webhook_secret_encrypted ) ) {
            $webhook_secret = WH4U_Encryption::decrypt( $reseller->webhook_secret_encrypted );
        }

        if ( ! empty( $webhook_secret ) ) {
            $headers['X-WH4U-Webhook-Signature'] = hash_hmac( 'sha256', $payload, $webhook_secret );
        }

        $response = wp_remote_post( $webhook_url, array(
            'headers' => $headers,
            'body'    => $payload,
            'timeout' => 15,
        ) );

        $success = ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) >= 200 && wp_remote_retrieve_response_code( $response ) < 300;

        $response_text = is_wp_error( $response ) ? $response->get_error_message() : wp_remote_retrieve_body( $response );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $wpdb->insert(
            $wpdb->prefix . 'wh4u_notifications',
            array(
                'user_id'    => $order->user_id,
                'order_id'   => $order->id,
                'type'       => 'webhook',
                'status'     => $success ? 'sent' : 'failed',
                'recipient'  => sanitize_text_field( $webhook_url ),
                'subject'    => sanitize_text_field( 'Order ' . $event ),
                'body'       => $payload,
                'response'   => sanitize_text_field( substr( $response_text, 0, 1000 ) ),
                'created_at' => current_time( 'mysql', true ),
            ),
            array( '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
        );

        return $success;
    }

    /**
     * Validate that a webhook URL is safe (SSRF prevention).
     *
     * Blocks localhost, private/reserved IP ranges, link-local, and non-HTTP(S) schemes.
     *
     * @param string $url URL to validate.
     * @return bool True if safe to request.
     */
    private static function is_safe_webhook_url( $url ) {
        $parsed = wp_parse_url( $url );
        if ( ! $parsed || empty( $parsed['host'] ) ) {
            return false;
        }

        $scheme = isset( $parsed['scheme'] ) ? strtolower( $parsed['scheme'] ) : '';
        if ( $scheme !== 'https' && $scheme !== 'http' ) {
            return false;
        }

        $port = isset( $parsed['port'] ) ? (int) $parsed['port'] : ( $scheme === 'https' ? 443 : 80 );
        $allowed_ports = array( 80, 443, 8080, 8443 );
        if ( ! in_array( $port, $allowed_ports, true ) ) {
            return false;
        }

        $host = strtolower( $parsed['host'] );
        $blocked_hosts = array( 'localhost', '127.0.0.1', '::1', '0.0.0.0' );
        if ( in_array( $host, $blocked_hosts, true ) ) {
            return false;
        }

        $ip = gethostbyname( $host );
        if ( $ip === $host ) {
            return true;
        }

        $long = ip2long( $ip );
        if ( $long === false ) {
            return true;
        }

        $blocked_ranges = array(
            array( '10.0.0.0', '10.255.255.255' ),
            array( '172.16.0.0', '172.31.255.255' ),
            array( '192.168.0.0', '192.168.255.255' ),
            array( '169.254.0.0', '169.254.255.255' ),
            array( '127.0.0.0', '127.255.255.255' ),
            array( '0.0.0.0', '0.255.255.255' ),
        );

        foreach ( $blocked_ranges as $range ) {
            $low  = ip2long( $range[0] );
            $high = ip2long( $range[1] );
            if ( $long >= $low && $long <= $high ) {
                return false;
            }
        }

        return true;
    }
}
