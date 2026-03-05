<?php
/**
 * REST endpoints for domain registration and transfer orders.
 *
 * @package WH4U_Domains
 * @license GPL-2.0-or-later
 * @link    https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WH4U_REST_Orders extends WH4U_REST_Controller {

    /**
     * Register order-related REST routes.
     *
     * @return void
     */
    public function register_routes() {
        register_rest_route( $this->namespace, '/orders/register', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array( $this, 'register_domain' ),
            'permission_callback' => array( $this, 'check_manage_permission' ),
            'args'                => $this->get_order_args( 'register' ),
        ) );

        register_rest_route( $this->namespace, '/orders/transfer', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array( $this, 'transfer_domain' ),
            'permission_callback' => array( $this, 'check_manage_permission' ),
            'args'                => $this->get_order_args( 'transfer' ),
        ) );

        register_rest_route( $this->namespace, '/orders', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'list_orders' ),
            'permission_callback' => array( $this, 'check_manage_permission' ),
            'args'                => array(
                'page'     => array( 'default' => 1, 'type' => 'integer', 'sanitize_callback' => 'absint' ),
                'per_page' => array( 'default' => 20, 'type' => 'integer', 'sanitize_callback' => 'absint' ),
                'status'   => array( 'default' => '', 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ),
            ),
        ) );
    }

    /**
     * Get validation args for order endpoints.
     *
     * @param string $type Order type.
     * @return array
     */
    private function get_order_args( $type ) {
        $args = array(
            'domain' => array(
                'required'          => true,
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'validate_callback' => function ( $value ) {
                    return is_string( $value ) && strlen( $value ) >= 3 && strlen( $value ) <= 253;
                },
            ),
            'regperiod' => array(
                'required'          => true,
                'type'              => 'integer',
                'sanitize_callback' => 'absint',
                'validate_callback' => function ( $value ) {
                    return is_numeric( $value ) && (int) $value >= 1 && (int) $value <= 10;
                },
            ),
        );

        if ( $type === 'register' || $type === 'transfer' ) {
            $args['contacts'] = array(
                'required'          => true,
                'type'              => 'object',
                'sanitize_callback' => array( $this, 'sanitize_contacts' ),
                'validate_callback' => function ( $value ) {
                    return is_array( $value ) || is_object( $value );
                },
            );
            $args['nameservers'] = array(
                'required'          => true,
                'type'              => 'object',
                'sanitize_callback' => array( $this, 'sanitize_nameservers' ),
                'validate_callback' => function ( $value ) {
                    return is_array( $value ) || is_object( $value );
                },
            );
            $args['addons'] = array(
                'required'          => false,
                'type'              => 'object',
                'default'           => array(),
                'sanitize_callback' => array( $this, 'sanitize_addons' ),
            );
        }

        if ( $type === 'transfer' ) {
            $args['eppcode'] = array(
                'required'          => false,
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default'           => '',
            );
        }

        return $args;
    }

    /**
     * Register a domain.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function register_domain( $request ) {
        return $this->process_order( $request, 'register', '/order/domains/register' );
    }

    /**
     * Transfer a domain.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function transfer_domain( $request ) {
        return $this->process_order( $request, 'transfer', '/order/domains/transfer' );
    }


    /**
     * Process a domain order (register/transfer).
     *
     * @param WP_REST_Request $request     Request object.
     * @param string          $order_type  Order type.
     * @param string          $api_action  API endpoint path.
     * @return WP_REST_Response
     */
    private function process_order( $request, $order_type, $api_action ) {
        global $wpdb;

        $user_id = get_current_user_id();

        if ( WH4U_Rate_Limiter::is_limited( 'order', $user_id ) ) {
            return new WP_REST_Response(
                array( 'code' => 'wh4u_rate_limited', 'message' => __( 'Rate limit exceeded.', 'wh4u-domains' ) ),
                429
            );
        }

        $domain     = $request->get_param( 'domain' );
        $regperiod  = $request->get_param( 'regperiod' );
        $contacts   = $request->get_param( 'contacts' );
        $nameservers = $request->get_param( 'nameservers' );
        $addons     = $request->get_param( 'addons' );
        $eppcode    = $request->get_param( 'eppcode' );

        $contacts_encrypted = '';
        if ( ! empty( $contacts ) ) {
            $contacts_encrypted = WH4U_Encryption::encrypt( wp_json_encode( $contacts ) );
        }

        $order_data = array(
            'user_id'      => $user_id,
            'domain'       => sanitize_text_field( $domain ),
            'order_type'   => sanitize_text_field( $order_type ),
            'status'       => 'processing',
            'reg_period'   => absint( $regperiod ),
            'contacts'     => $contacts_encrypted,
            'nameservers'  => ! empty( $nameservers ) ? wp_json_encode( $nameservers ) : '',
            'addons'       => ! empty( $addons ) ? wp_json_encode( $addons ) : '',
            'eppcode'      => sanitize_text_field( $eppcode ? $eppcode : '' ),
            'created_at'   => current_time( 'mysql', true ),
            'updated_at'   => current_time( 'mysql', true ),
        );

        $settings = get_option( 'wh4u_settings', array() );
        $mode     = isset( $settings['mode'] ) ? $settings['mode'] : 'realtime';

        if ( $mode === 'notification' ) {
            $order_data['status'] = 'pending_manual';
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter
            $wpdb->insert( $wpdb->prefix . 'wh4u_orders', $order_data,
                array( '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
            );
            $order_id = $wpdb->insert_id;

            WH4U_Notifications::send_order_notification( $order_id, 'pending_manual' );

            return new WP_REST_Response( array(
                'order_id' => $order_id,
                'status'   => 'pending_manual',
                'message'  => __( 'Order created. The reseller has been notified for manual processing.', 'wh4u-domains' ),
            ), 201 );
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $wpdb->insert( $wpdb->prefix . 'wh4u_orders', $order_data,
            array( '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
        );
        $order_id = $wpdb->insert_id;

        $client = WH4U_Api_Client::from_user( $user_id );
        if ( is_wp_error( $client ) ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
            $wpdb->update(
                $wpdb->prefix . 'wh4u_orders',
                array( 'status' => 'failed', 'error_message' => $client->get_error_message(), 'updated_at' => current_time( 'mysql', true ) ),
                array( 'id' => $order_id ),
                array( '%s', '%s', '%s' ),
                array( '%d' )
            );
            return $this->error_response( $client );
        }

        $api_params = array(
            'domain'    => $domain,
            'regperiod' => (string) $regperiod,
        );

        if ( ! empty( $contacts ) ) {
            $api_params['contacts'] = $contacts;
        }
        if ( ! empty( $nameservers ) ) {
            $api_params['nameservers'] = $nameservers;
        }
        if ( ! empty( $addons ) ) {
            $api_params['addons'] = $addons;
        }
        if ( $order_type === 'transfer' && ! empty( $eppcode ) ) {
            $api_params['eppcode'] = $eppcode;
        }

        $response = $client->post( $api_action, $api_params );

        if ( is_wp_error( $response ) ) {
            if ( WH4U_Api_Client::is_retryable( $response ) ) {
                WH4U_Queue::enqueue( $order_id, $api_action, $api_params );

                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
                $wpdb->update(
                    $wpdb->prefix . 'wh4u_orders',
                    array(
                        'status'        => 'pending',
                        'error_message' => $response->get_error_message(),
                        'updated_at'    => current_time( 'mysql', true ),
                    ),
                    array( 'id' => $order_id ),
                    array( '%s', '%s', '%s' ),
                    array( '%d' )
                );

                return new WP_REST_Response( array(
                    'order_id' => $order_id,
                    'status'   => 'pending',
                    'message'  => __( 'Order queued for retry due to a temporary API issue.', 'wh4u-domains' ),
                ), 202 );
            }

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
            $wpdb->update(
                $wpdb->prefix . 'wh4u_orders',
                array(
                    'status'        => 'failed',
                    'error_message' => $response->get_error_message(),
                    'api_response'  => wp_json_encode( WH4U_Logger::mask_secrets( $response->get_error_data() ) ),
                    'updated_at'    => current_time( 'mysql', true ),
                ),
                array( 'id' => $order_id ),
                array( '%s', '%s', '%s', '%s' ),
                array( '%d' )
            );

            WH4U_Notifications::send_order_notification( $order_id, 'failed' );

            return $this->error_response( $response );
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $wpdb->update(
            $wpdb->prefix . 'wh4u_orders',
            array(
                'status'       => 'completed',
                'api_response' => wp_json_encode( WH4U_Logger::mask_secrets( $response ) ),
                'updated_at'   => current_time( 'mysql', true ),
            ),
            array( 'id' => $order_id ),
            array( '%s', '%s', '%s' ),
            array( '%d' )
        );

        WH4U_Notifications::send_order_notification( $order_id, 'completed' );

        return new WP_REST_Response( array(
            'order_id' => $order_id,
            'status'   => 'completed',
            'message'  => __( 'Domain order completed successfully.', 'wh4u-domains' ),
            'data'     => $response,
        ), 200 );
    }

    /**
     * List orders for the current user.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function list_orders( $request ) {
        if ( WH4U_Rate_Limiter::is_limited( 'read', get_current_user_id() ) ) {
            return new WP_REST_Response(
                array( 'code' => 'wh4u_rate_limited', 'message' => __( 'Rate limit exceeded.', 'wh4u-domains' ) ),
                429
            );
        }

        global $wpdb;

        $user_id  = get_current_user_id();
        $page     = $request->get_param( 'page' );
        $per_page = min( $request->get_param( 'per_page' ), 100 );
        $status   = $request->get_param( 'status' );
        $table    = $wpdb->prefix . 'wh4u_orders';

        $where  = 'user_id = %d';
        $params = array( $user_id );

        $valid_statuses = array( 'pending', 'pending_manual', 'processing', 'completed', 'failed', 'cancelled' );
        if ( ! empty( $status ) && in_array( $status, $valid_statuses, true ) ) {
            $where   .= ' AND status = %s';
            $params[] = $status;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- custom table, not cacheable list query
        $total = (int) $wpdb->get_var(
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $table and $where built from safe prefix/allowlisted values, dynamic param count
            $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE {$where}", $params )
        );

        $offset   = max( 0, ( $page - 1 ) * $per_page );
        $params[] = $per_page;
        $params[] = $offset;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- custom table, paginated list query
        $items = $wpdb->get_results(
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $table and $where built from safe prefix/allowlisted values, dynamic param count
            $wpdb->prepare( "SELECT id, domain, order_type, status, reg_period, retry_count, error_message, created_at, updated_at FROM {$table} WHERE {$where} ORDER BY created_at DESC LIMIT %d OFFSET %d", $params )
        );

        return new WP_REST_Response( array(
            'items'      => $items ? $items : array(),
            'total'      => $total,
            'page'       => $page,
            'per_page'   => $per_page,
            'total_pages' => (int) ceil( $total / $per_page ),
        ), 200 );
    }

    /**
     * Recursively sanitize a contacts object (registrant, admin, tech, billing).
     *
     * Each role contains string fields like firstname, lastname, email, etc.
     *
     * @param mixed $value Raw contacts parameter.
     * @return array Sanitized contacts array.
     */
    public function sanitize_contacts( $value ) {
        if ( ! is_array( $value ) ) {
            return array();
        }

        $allowed_roles  = array( 'registrant', 'admin', 'tech', 'billing' );
        $allowed_fields = array(
            'firstname', 'lastname', 'companyname', 'email',
            'address1', 'address2', 'city', 'state', 'postcode',
            'country', 'phonenumber',
        );
        $sanitized = array();

        foreach ( $value as $role => $fields ) {
            $role = sanitize_key( $role );
            if ( ! in_array( $role, $allowed_roles, true ) || ! is_array( $fields ) ) {
                continue;
            }
            $sanitized[ $role ] = array();
            foreach ( $fields as $field_key => $field_value ) {
                $field_key = sanitize_key( $field_key );
                if ( ! in_array( $field_key, $allowed_fields, true ) ) {
                    continue;
                }
                if ( $field_key === 'email' ) {
                    $sanitized[ $role ][ $field_key ] = sanitize_email( $field_value );
                } else {
                    $sanitized[ $role ][ $field_key ] = sanitize_text_field( wp_unslash( $field_value ) );
                }
            }
        }

        return $sanitized;
    }

    /**
     * Sanitize a nameservers object (ns1 through ns5).
     *
     * @param mixed $value Raw nameservers parameter.
     * @return array Sanitized nameservers array.
     */
    public function sanitize_nameservers( $value ) {
        if ( ! is_array( $value ) ) {
            return array();
        }

        $allowed_keys = array( 'ns1', 'ns2', 'ns3', 'ns4', 'ns5' );
        $sanitized    = array();

        foreach ( $value as $key => $ns ) {
            $key = sanitize_key( $key );
            if ( ! in_array( $key, $allowed_keys, true ) ) {
                continue;
            }
            $sanitized[ $key ] = sanitize_text_field( wp_unslash( $ns ) );
        }

        return $sanitized;
    }

    /**
     * Sanitize an addons object (boolean flags).
     *
     * @param mixed $value Raw addons parameter.
     * @return array Sanitized addons array.
     */
    public function sanitize_addons( $value ) {
        if ( ! is_array( $value ) ) {
            return array();
        }

        $allowed_keys = array( 'dnsmanagement', 'emailforwarding', 'idprotection' );
        $sanitized    = array();

        foreach ( $value as $key => $flag ) {
            $key = sanitize_key( $key );
            if ( ! in_array( $key, $allowed_keys, true ) ) {
                continue;
            }
            $sanitized[ $key ] = absint( $flag );
        }

        return $sanitized;
    }
}
