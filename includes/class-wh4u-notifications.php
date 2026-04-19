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
            __( 'Period', 'wh4u-domains' )     => sprintf(
                /* translators: %d: number of years */
                _n( '%d year', '%d years', (int) ( isset( $meta['_wh4u_reg_period'] ) ? $meta['_wh4u_reg_period'] : 1 ), 'wh4u-domains' ),
                (int) ( isset( $meta['_wh4u_reg_period'] ) ? $meta['_wh4u_reg_period'] : 1 )
            ),
            __( 'Name', 'wh4u-domains' )       => trim( ( isset( $meta['_wh4u_first_name'] ) ? $meta['_wh4u_first_name'] : '' ) . ' ' . ( isset( $meta['_wh4u_last_name'] ) ? $meta['_wh4u_last_name'] : '' ) ),
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
                    <td style="padding: 8px; border: 1px solid #ddd;"><?php
                        /* translators: %d: number of years */
                        echo esc_html( sprintf( _n( '%d year', '%d years', (int) $order->reg_period, 'wh4u-domains' ), (int) $order->reg_period ) );
                    ?></td>
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

        $pinned = self::resolve_and_validate_webhook_host( $webhook_url );
        if ( is_wp_error( $pinned ) ) {
            return false;
        }

        // Pin the DNS answer at the cURL layer so the HTTP request connects to
        // the exact IP we validated. This closes the DNS-rebinding window
        // without breaking TLS SNI (the hostname stays in the URL).
        $pin_curl = function ( $handle ) use ( $pinned ) {
            if ( ! empty( $pinned['host'] ) && ! empty( $pinned['ip'] ) ) {
                $resolve = array(
                    $pinned['host'] . ':80:'  . $pinned['ip'],
                    $pinned['host'] . ':443:' . $pinned['ip'],
                    $pinned['host'] . ':8080:' . $pinned['ip'],
                    $pinned['host'] . ':8443:' . $pinned['ip'],
                );
                curl_setopt( $handle, CURLOPT_RESOLVE, $resolve );
            }
        };
        add_action( 'http_api_curl', $pin_curl, 10, 1 );

        // redirection => 0 closes an SSRF bypass: without it, a public host could
        // 302 to an internal IP, and CURLOPT_RESOLVE only pins the original host.
        $response = wp_remote_post( $webhook_url, array(
            'headers'     => $headers,
            'body'        => $payload,
            'timeout'     => 15,
            'sslverify'   => true,
            'redirection' => 0,
        ) );

        remove_action( 'http_api_curl', $pin_curl, 10 );

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
     * Checks scheme, port, blocked hostnames. DNS-based IP validation is handled
     * by resolve_and_validate_webhook_host() so the IP can be pinned for the
     * actual request (closing the DNS-rebinding window).
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

        // Delegate IP resolution to the pinning helper so we use the exact same
        // answer for validation and for the outgoing request (no rebinding).
        $pinned = self::resolve_and_validate_webhook_host( $url );
        return ! is_wp_error( $pinned );
    }

    /**
     * Resolve the webhook host once and validate the resolved IP is public.
     *
     * Returns {host, ip} for the caller to pin via CURLOPT_RESOLVE. Fails
     * closed when DNS resolution yields no usable answer — we never fall back
     * to trusting an unresolved hostname.
     *
     * @param string $url Webhook URL.
     * @return array{host:string,ip:string}|WP_Error
     */
    private static function resolve_and_validate_webhook_host( $url ) {
        $parsed = wp_parse_url( $url );
        if ( ! $parsed || empty( $parsed['host'] ) ) {
            return new WP_Error( 'wh4u_webhook_invalid', 'Invalid webhook URL.' );
        }

        $host = strtolower( $parsed['host'] );

        // If the host is a literal IP, validate it and pin it to itself.
        if ( filter_var( $host, FILTER_VALIDATE_IP ) ) {
            if ( ! self::is_public_ip( $host ) ) {
                return new WP_Error( 'wh4u_webhook_private_ip', 'Webhook host is a private IP.' );
            }
            return array( 'host' => $host, 'ip' => $host );
        }

        // Resolve IPv4. If dns_get_record is unavailable (some hardened hosts),
        // fall back to gethostbyname but require a successful resolution.
        $ip = '';
        if ( function_exists( 'dns_get_record' ) ) {
            $records = @dns_get_record( $host, DNS_A );
            if ( is_array( $records ) ) {
                foreach ( $records as $rec ) {
                    if ( ! empty( $rec['ip'] ) && filter_var( $rec['ip'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
                        $ip = $rec['ip'];
                        break;
                    }
                }
            }
        }

        if ( $ip === '' ) {
            $resolved = @gethostbyname( $host );
            if ( $resolved !== $host && filter_var( $resolved, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
                $ip = $resolved;
            }
        }

        if ( $ip === '' ) {
            return new WP_Error( 'wh4u_webhook_dns_fail', 'Could not resolve webhook host.' );
        }

        if ( ! self::is_public_ip( $ip ) ) {
            return new WP_Error( 'wh4u_webhook_private_ip', 'Webhook host resolves to a private IP.' );
        }

        return array( 'host' => $host, 'ip' => $ip );
    }

    /**
     * Return true if the given IP is a public (routable) address.
     *
     * Uses PHP's built-in filter flags which exclude private, reserved, and
     * link-local ranges for both IPv4 and IPv6.
     *
     * @param string $ip IP address.
     * @return bool
     */
    private static function is_public_ip( $ip ) {
        return (bool) filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        );
    }

    /**
     * Delete notification rows older than the given number of days.
     *
     * Timestamps are stored in UTC (current_time( 'mysql', true )) so the
     * comparison uses UTC_TIMESTAMP() to avoid server-timezone drift.
     *
     * @param int $days Retention period in whole days. Values below 1 are clamped to 1.
     * @return int|false Number of rows deleted, or false on DB error.
     */
    public static function prune( $days ) {
        global $wpdb;

        $days  = max( 1, absint( $days ) );
        $table = $wpdb->prefix . 'wh4u_notifications';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $table from $wpdb->prefix, $days is prepared
        return $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$table} WHERE created_at < (UTC_TIMESTAMP() - INTERVAL %d DAY)",
                $days
            )
        );
    }
}
