<?php
/**
 * REST endpoint for billing credits.
 *
 * @package WH4U_Domains
 * @license GPL-2.0-or-later
 * @link    https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WH4U_REST_Credits extends WH4U_REST_Controller {

    /**
     * Register credits REST route.
     *
     * @return void
     */
    public function register_routes() {
        register_rest_route( $this->namespace, '/credits', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'get_credits' ),
            'permission_callback' => array( $this, 'check_manage_permission' ),
        ) );
    }

    /**
     * Get the reseller's billing credits.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function get_credits( $request ) {
        $user_id   = get_current_user_id();
        $cache_key = 'wh4u_credits_u' . $user_id;

        $cached = get_transient( $cache_key );
        if ( false !== $cached ) {
            return new WP_REST_Response( $cached, 200 );
        }

        $client = $this->get_client_with_rate_check( 'lookup' );
        if ( is_wp_error( $client ) ) {
            return $this->error_response( $client );
        }

        $response = $client->get( '/billing/credits' );

        if ( is_wp_error( $response ) ) {
            return $this->error_response( $response );
        }

        set_transient( $cache_key, $response, 5 * MINUTE_IN_SECONDS );

        return new WP_REST_Response( $response, 200 );
    }
}
