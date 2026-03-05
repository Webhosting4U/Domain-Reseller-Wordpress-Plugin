<?php
/**
 * HTTP client for the DomainsReseller API.
 *
 * Handles HMAC authentication, request signing, logging, and error handling.
 * Uses wp_remote_post / wp_remote_get exclusively (no raw cURL).
 *
 * @package WH4U_Domains
 * @license GPL-2.0-or-later
 * @link    https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WH4U_Api_Client {

    /** @var string */
    private $base_url;

    /** @var string */
    private $api_key;

    /** @var string */
    private $email;

    /** @var int WordPress user ID for logging. */
    private $user_id;

    /**
     * @param string $base_url API base URL.
     * @param string $api_key  Raw (decrypted) API key.
     * @param string $email    Reseller email.
     * @param int    $user_id  WordPress user ID.
     */
    public function __construct( $base_url, $api_key, $email, $user_id = 0 ) {
        $this->base_url = rtrim( $base_url, '/' );
        $this->api_key  = $api_key;
        $this->email    = $email;
        $this->user_id  = $user_id;
    }

    /**
     * Build a client from a user's stored settings.
     *
     * @param int $user_id WordPress user ID.
     * @return WH4U_Api_Client|WP_Error Client instance or error.
     */
    public static function from_user( $user_id ) {
        $global_settings = get_option( 'wh4u_settings', array() );
        $base_url = isset( $global_settings['api_base_url'] ) ? $global_settings['api_base_url'] : '';

        if ( empty( $base_url ) ) {
            return new WP_Error( 'wh4u_no_base_url', __( 'API base URL is not configured.', 'wh4u-domains' ) );
        }

        global $wpdb;
        $table    = $wpdb->prefix . 'wh4u_reseller_settings';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $table from $wpdb->prefix
        $settings = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE user_id = %d", $user_id ) );

        if ( ! $settings ) {
            return new WP_Error( 'wh4u_no_reseller_settings', __( 'Reseller settings not found. Please configure your API credentials.', 'wh4u-domains' ) );
        }

        $api_key = WH4U_Encryption::decrypt( $settings->api_key_encrypted );
        if ( empty( $api_key ) ) {
            return new WP_Error(
                'wh4u_decrypt_failed',
                __( 'Failed to decrypt API key. The encryption key may have changed. Please re-save your API key under Domains > My Settings.', 'wh4u-domains' ),
                array( 'status' => 500 )
            );
        }

        if ( empty( $settings->reseller_email ) ) {
            return new WP_Error( 'wh4u_no_email', __( 'Reseller email is not configured.', 'wh4u-domains' ) );
        }

        return new self( $base_url, $api_key, $settings->reseller_email, $user_id );
    }

    /**
     * Generate the HMAC token per the API docs.
     *
     * token = base64_encode(hash_hmac("sha256", api_key, email . ":" . gmdate("y-m-d H")))
     *
     * @return string
     */
    private function generate_token() {
        $time_key = $this->email . ':' . gmdate( 'y-m-d H' );
        return base64_encode( hash_hmac( 'sha256', $this->api_key, $time_key ) );
    }

    /**
     * Build the common headers for API requests.
     *
     * @param string $body_string Request body string for signing.
     * @return array<string, string>
     */
    private function build_headers( $body_string = '' ) {
        $headers = array(
            'username' => $this->email,
            'token'    => $this->generate_token(),
        );

        if ( $body_string !== '' ) {
            $headers['X-WH4U-Signature'] = hash_hmac( 'sha256', $body_string, $this->api_key );
        }

        return $headers;
    }

    /**
     * Make a POST request to the API.
     *
     * @param string $action API action path (e.g. "/order/domains/register").
     * @param array  $params Request parameters.
     * @return array|WP_Error Decoded response array or WP_Error.
     */
    public function post( $action, $params = array() ) {
        return $this->request( 'POST', $action, $params );
    }

    /**
     * Make a GET request to the API.
     *
     * @param string $action API action path.
     * @param array  $params Query parameters.
     * @return array|WP_Error Decoded response array or WP_Error.
     */
    public function get( $action, $params = array() ) {
        return $this->request( 'GET', $action, $params );
    }

    /**
     * Execute an HTTP request to the API.
     *
     * @param string $method HTTP method (GET or POST).
     * @param string $action API action path.
     * @param array  $params Parameters.
     * @return array|WP_Error Decoded response or WP_Error.
     */
    private function request( $method, $action, $params = array() ) {
        $url         = $this->base_url . $action;
        $body_string = '';
        $start_time  = microtime( true );

        if ( $method === 'POST' ) {
            $body_string = http_build_query( $params );
        } elseif ( ! empty( $params ) ) {
            $url = add_query_arg( $params, $url );
        }

        $headers = $this->build_headers( $body_string );

        $args = array(
            'method'    => $method,
            'headers'   => $headers,
            'timeout'   => 30,
            'sslverify' => true,
        );

        if ( $method === 'POST' ) {
            $args['body'] = $params;
        }

        $response    = wp_remote_request( $url, $args );
        $duration_ms = (int) round( ( microtime( true ) - $start_time ) * 1000 );

        if ( is_wp_error( $response ) ) {
            WH4U_Logger::log(
                $this->user_id,
                $action,
                $method,
                $params,
                0,
                array( 'error' => $response->get_error_message() ),
                $duration_ms
            );
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );

        $decoded = json_decode( $body, true );
        if ( json_last_error() !== JSON_ERROR_NONE ) {
            $decoded = array( 'raw' => $body );
        }

        WH4U_Logger::log(
            $this->user_id,
            $action,
            $method,
            $params,
            $code,
            $decoded,
            $duration_ms
        );

        if ( $code < 200 || $code >= 300 ) {
            $message = isset( $decoded['message'] ) ? $decoded['message'] : __( 'API request failed.', 'wh4u-domains' );
            return new WP_Error(
                'wh4u_api_error',
                $message,
                array( 'status' => $code, 'body' => $decoded )
            );
        }

        return $decoded;
    }

    /**
     * Check if a response indicates a retryable failure.
     *
     * @param mixed $response Response from request() or WP_Error.
     * @return bool
     */
    public static function is_retryable( $response ) {
        if ( is_wp_error( $response ) ) {
            $code = $response->get_error_code();
            if ( in_array( $code, array( 'http_request_failed', 'wh4u_api_error' ), true ) ) {
                $data = $response->get_error_data();
                if ( is_array( $data ) && isset( $data['status'] ) ) {
                    $http_code = (int) $data['status'];
                    return $http_code >= 500 || $http_code === 429 || $http_code === 408;
                }
                return true;
            }
        }
        return false;
    }
}
