<?php
/**
 * REST endpoint for manual queue retry.
 *
 * @package WH4U_Domains
 * @license GPL-2.0-or-later
 * @link    https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WH4U_REST_Queue extends WH4U_REST_Controller {

    /**
     * Register queue REST routes.
     *
     * @return void
     */
    public function register_routes() {
        register_rest_route( $this->namespace, '/queue/(?P<id>\d+)/retry', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array( $this, 'retry' ),
            'permission_callback' => array( $this, 'check_manage_permission' ),
            'args'                => array(
                'id' => array(
                    'required'          => true,
                    'type'              => 'integer',
                    'sanitize_callback' => 'absint',
                    'validate_callback' => function ( $value ) {
                        return is_numeric( $value ) && (int) $value > 0;
                    },
                ),
            ),
        ) );
    }

    /**
     * Manually retry a queue item.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function retry( $request ) {
        if ( WH4U_Rate_Limiter::is_limited( 'order', get_current_user_id() ) ) {
            return new WP_REST_Response(
                array( 'code' => 'wh4u_rate_limited', 'message' => __( 'Rate limit exceeded.', 'wh4u-domains' ) ),
                429
            );
        }

        $queue_id = $request->get_param( 'id' );

        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $item = $wpdb->get_row( $wpdb->prepare(
            "SELECT q.*, o.user_id FROM {$wpdb->prefix}wh4u_queue q INNER JOIN {$wpdb->prefix}wh4u_orders o ON q.order_id = o.id WHERE q.id = %d",
            $queue_id
        ) );

        if ( ! $item ) {
            return new WP_REST_Response(
                array( 'code' => 'not_found', 'message' => __( 'Queue item not found.', 'wh4u-domains' ) ),
                404
            );
        }

        if ( (int) $item->user_id !== get_current_user_id() && ! current_user_can( 'manage_options' ) ) {
            return new WP_REST_Response(
                array( 'code' => 'forbidden', 'message' => __( 'You cannot retry this item.', 'wh4u-domains' ) ),
                403
            );
        }

        $success = WH4U_Queue::manual_retry( $queue_id );

        if ( ! $success ) {
            return new WP_REST_Response(
                array( 'code' => 'retry_failed', 'message' => __( 'Failed to re-queue item.', 'wh4u-domains' ) ),
                500
            );
        }

        return new WP_REST_Response( array(
            'message' => __( 'Item re-queued for retry.', 'wh4u-domains' ),
        ), 200 );
    }
}
