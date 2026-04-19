<?php
/**
 * Secure API request/response logger with secret masking.
 *
 * @package WH4U_Domains
 * @license GPL-2.0-or-later
 * @link    https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WH4U_Logger {

    /**
     * Patterns to mask in logged data.
     *
     * @var array<string>
     */
    private static $sensitive_keys = array(
        'token',
        'api_key',
        'apikey',
        'api_key_encrypted',
        'password',
        'secret',
        'eppcode',
        'webhook_secret',
        'authorization',
    );

    /**
     * Log an API request and response.
     *
     * @param int    $user_id       WordPress user ID (0 for anonymous).
     * @param string $endpoint      API endpoint called.
     * @param string $method        HTTP method.
     * @param mixed  $request_body  Request parameters.
     * @param int    $response_code HTTP response code.
     * @param mixed  $response_body Response body.
     * @param int    $duration_ms   Request duration in milliseconds.
     * @return int|false Inserted log ID or false on failure.
     */
    public static function log( $user_id, $endpoint, $method, $request_body, $response_code, $response_body, $duration_ms ) {
        global $wpdb;

        $masked_request  = self::mask_secrets( $request_body );
        $masked_response = self::mask_secrets( $response_body );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $result = $wpdb->insert(
            $wpdb->prefix . 'wh4u_api_logs',
            array(
                'user_id'       => absint( $user_id ),
                'endpoint'      => sanitize_text_field( $endpoint ),
                'method'        => sanitize_text_field( strtoupper( $method ) ),
                'request_body'  => wp_json_encode( $masked_request ),
                'response_code' => absint( $response_code ),
                'response_body' => wp_json_encode( $masked_response ),
                'duration_ms'   => absint( $duration_ms ),
                'created_at'    => current_time( 'mysql', true ),
            ),
            array( '%d', '%s', '%s', '%s', '%d', '%s', '%d', '%s' )
        );

        return ( $result !== false ) ? $wpdb->insert_id : false;
    }

    /**
     * Recursively mask sensitive values in data.
     *
     * @param mixed $data Data to mask.
     * @return mixed Masked data.
     */
    public static function mask_secrets( $data ) {
        if ( is_string( $data ) ) {
            $decoded = json_decode( $data, true );
            if ( json_last_error() === JSON_ERROR_NONE && is_array( $decoded ) ) {
                return self::mask_secrets( $decoded );
            }
            return $data;
        }

        if ( ! is_array( $data ) ) {
            return $data;
        }

        $masked = array();
        foreach ( $data as $key => $value ) {
            $lower_key = strtolower( (string) $key );
            $is_sensitive = false;

            foreach ( self::$sensitive_keys as $pattern ) {
                if ( strpos( $lower_key, $pattern ) !== false ) {
                    $is_sensitive = true;
                    break;
                }
            }

            if ( $is_sensitive && is_string( $value ) && $value !== '' ) {
                $masked[ $key ] = '***REDACTED(' . strlen( $value ) . ')***';
            } elseif ( is_array( $value ) ) {
                $masked[ $key ] = self::mask_secrets( $value );
            } else {
                $masked[ $key ] = $value;
            }
        }

        return $masked;
    }

    /**
     * Delete log rows older than the given number of days.
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
        $table = $wpdb->prefix . 'wh4u_api_logs';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $table from $wpdb->prefix, $days is prepared
        return $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$table} WHERE created_at < (UTC_TIMESTAMP() - INTERVAL %d DAY)",
                $days
            )
        );
    }
}
