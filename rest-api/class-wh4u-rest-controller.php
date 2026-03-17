<?php
/**
 * Base REST controller for WH4U Domains.
 *
 * @package WH4U_Domains
 * @license GPL-2.0-or-later
 * @link    https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

abstract class WH4U_REST_Controller {

    /** @var string REST namespace. */
    protected $namespace = 'wh4u/v1';

    /**
     * Register routes (implemented by subclasses).
     *
     * @return void
     */
    abstract public function register_routes();

    /**
     * Permission callback: requires wh4u_manage_domains capability.
     *
     * @param WP_REST_Request $request Request object.
     * @return bool|WP_Error
     */
    public function check_manage_permission( $request ) {
        if ( ! is_user_logged_in() ) {
            return new WP_Error(
                'wh4u_rest_not_logged_in',
                __( 'You must be logged in.', 'wh4u-domains' ),
                array( 'status' => 401 )
            );
        }
        if ( ! current_user_can( 'wh4u_manage_domains' ) ) {
            return new WP_Error(
                'wh4u_rest_forbidden',
                __( 'You do not have permission to access this resource.', 'wh4u-domains' ),
                array( 'status' => 403 )
            );
        }
        return true;
    }

    /**
     * Permission callback: allows anonymous access but applies rate limiting.
     *
     * @param WP_REST_Request $request Request object.
     * @return bool|WP_Error
     */
    public function check_public_permission( $request ) {
        $user_id = get_current_user_id();

        if ( WH4U_Rate_Limiter::is_limited( 'public', $user_id ) ) {
            return new WP_Error(
                'wh4u_rate_limited',
                __( 'Rate limit exceeded. Please try again shortly.', 'wh4u-domains' ),
                array( 'status' => 429 )
            );
        }

        return true;
    }

    /**
     * Get the current user's API client, with rate limiting.
     *
     * @param string $rate_group Rate limit group (lookup, order).
     * @return WH4U_Api_Client|WP_Error
     */
    protected function get_client_with_rate_check( $rate_group = 'lookup' ) {
        $user_id = get_current_user_id();

        if ( WH4U_Rate_Limiter::is_limited( $rate_group, $user_id ) ) {
            return new WP_Error(
                'wh4u_rate_limited',
                __( 'Rate limit exceeded. Please try again shortly.', 'wh4u-domains' ),
                array( 'status' => 429 )
            );
        }

        return WH4U_Api_Client::from_user( $user_id );
    }

    /**
     * Convert a WP_Error to a WP_REST_Response.
     *
     * @param WP_Error $error Error object.
     * @return WP_REST_Response
     */
    protected function error_response( $error ) {
        $data   = $error->get_error_data();
        $status = is_array( $data ) && isset( $data['status'] ) ? $data['status'] : 500;

        return new WP_REST_Response(
            array(
                'code'    => $error->get_error_code(),
                'message' => $error->get_error_message(),
            ),
            $status
        );
    }
}
