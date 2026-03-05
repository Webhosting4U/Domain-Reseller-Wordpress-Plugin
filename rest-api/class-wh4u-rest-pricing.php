<?php
/**
 * REST endpoints for TLD listing and pricing.
 *
 * @package WH4U_Domains
 * @license GPL-2.0-or-later
 * @link    https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WH4U_REST_Pricing extends WH4U_REST_Controller {

    /**
     * Register pricing-related REST routes.
     *
     * @return void
     */
    public function register_routes() {
        register_rest_route( $this->namespace, '/tlds', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'get_tlds' ),
            'permission_callback' => array( $this, 'check_public_permission' ),
        ) );

        register_rest_route( $this->namespace, '/tlds/pricing', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'get_tld_pricing' ),
            'permission_callback' => array( $this, 'check_public_permission' ),
            'args'                => array(
                'debug' => array(
                    'required'          => false,
                    'type'              => 'string',
                    'default'           => '0',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
            ),
        ) );

        register_rest_route( $this->namespace, '/pricing/(?P<type>register|transfer)', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'get_order_pricing' ),
            'permission_callback' => array( $this, 'check_manage_permission' ),
            'args'                => array(
                'type' => array(
                    'required'          => true,
                    'type'              => 'string',
                    'validate_callback' => function ( $value ) {
                        return in_array( $value, array( 'register', 'transfer' ), true );
                    },
                ),
                'domain' => array(
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
            ),
        ) );
    }

    /**
     * Get available TLDs.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function get_tlds( $request ) {
        $cached = get_transient( 'wh4u_tlds_cache' );
        if ( $cached !== false ) {
            return new WP_REST_Response( $cached, 200 );
        }

        $client = $this->get_public_client();
        if ( is_wp_error( $client ) ) {
            return $this->error_response( $client );
        }

        $response = $client->get( '/tlds' );
        if ( is_wp_error( $response ) ) {
            return $this->error_response( $response );
        }

        set_transient( 'wh4u_tlds_cache', $response, 12 * HOUR_IN_SECONDS );

        return new WP_REST_Response( $response, 200 );
    }

    /**
     * Get TLD pricing.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function get_tld_pricing( $request ) {
        $debug = $request->get_param( 'debug' ) === '1' && current_user_can( 'manage_options' );

        if ( ! $debug ) {
            $cached = get_transient( 'wh4u_tld_pricing_v2' );
            if ( is_array( $cached ) && ! empty( $cached ) && wp_is_numeric_array( $cached ) ) {
                return new WP_REST_Response( $cached, 200 );
            }
        }

        $client = $this->get_public_client();
        if ( is_wp_error( $client ) ) {
            return $this->error_response( $client );
        }

        $response = $client->get( '/tlds/pricing' );
        if ( is_wp_error( $response ) ) {
            return $this->error_response( $response );
        }

        $normalized = $this->normalize_pricing( $response );

        if ( ! empty( $normalized ) && ! $debug ) {
            set_transient( 'wh4u_tld_pricing_v2', $normalized, 12 * HOUR_IN_SECONDS );
        }

        if ( $debug ) {
            return new WP_REST_Response( array(
                'raw'        => $response,
                'normalized' => $normalized,
            ), 200 );
        }

        return new WP_REST_Response( $normalized, 200 );
    }

    /**
     * Normalize raw API pricing into a flat, predictable array.
     *
     * Handles multiple response shapes from WHMCS / DomainsReseller:
     *  - { pricing: { com: { register: { "1": "14.95" }, ... }, ... } }
     *  - { com: { register: { "1": "14.95" }, ... }, ... }
     *  - [ { tld: "com", register: "14.95", ... }, ... ]
     *
     * @param mixed $response Raw decoded API response.
     * @return array Flat array of { tld, register, transfer } items.
     */
    private function normalize_pricing( $response ) {
        if ( ! is_array( $response ) ) {
            return array();
        }

        $pricing_map = $response;
        if ( isset( $response['pricing'] ) && is_array( $response['pricing'] ) ) {
            $pricing_map = $response['pricing'];
        }

        $result = array();

        if ( wp_is_numeric_array( $pricing_map ) ) {
            foreach ( $pricing_map as $item ) {
                if ( ! is_array( $item ) ) {
                    continue;
                }
                $tld = '';
                if ( isset( $item['tld'] ) ) {
                    $tld = $item['tld'];
                } elseif ( isset( $item['extension'] ) ) {
                    $tld = $item['extension'];
                }
                $result[] = array(
                    'tld'      => $tld,
                    'register' => $this->extract_price( $item, 'register' ),
                    'transfer' => $this->extract_price( $item, 'transfer' ),
                );
            }
        } else {
            foreach ( $pricing_map as $key => $val ) {
                if ( ! is_array( $val ) ) {
                    continue;
                }
                $tld = isset( $val['tld'] ) ? $val['tld'] : $key;
                $result[] = array(
                    'tld'      => $tld,
                    'register' => $this->extract_price( $val, 'register' ),
                    'transfer' => $this->extract_price( $val, 'transfer' ),
                );
            }
        }

        return $result;
    }

    /**
     * Extract a single price from an API item, trying known key variants.
     *
     * DomainsReseller uses: registrationPrice, transferPrice
     * WHMCS standard uses:  register { "1": "14.95" }, transfer
     *
     * @param array  $item API pricing item.
     * @param string $type Normalized type: register or transfer.
     * @return string Price string or empty string.
     */
    private function extract_price( $item, $type ) {
        $key_map = array(
            'register' => array( 'registrationPrice', 'register', 'Register', 'registration_price' ),
            'transfer' => array( 'transferPrice', 'transfer', 'Transfer', 'transfer_price' ),
        );

        $keys = isset( $key_map[ $type ] ) ? $key_map[ $type ] : array( $type );

        foreach ( $keys as $key ) {
            if ( isset( $item[ $key ] ) && $item[ $key ] !== null ) {
                $val = $item[ $key ];
                if ( is_array( $val ) ) {
                    if ( isset( $val['1'] ) ) {
                        return (string) $val['1'];
                    }
                    if ( isset( $val[1] ) ) {
                        return (string) $val[1];
                    }
                    $first = reset( $val );
                    return ( false !== $first ) ? (string) $first : '';
                }
                return (string) $val;
            }
        }

        return '';
    }

    /**
     * Get order pricing for a specific domain and type.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function get_order_pricing( $request ) {
        $client = $this->get_client_with_rate_check( 'lookup' );
        if ( is_wp_error( $client ) ) {
            return $this->error_response( $client );
        }

        $type   = $request->get_param( 'type' );
        $domain = $request->get_param( 'domain' );

        $response = $client->get( '/order/pricing/domains/' . rawurlencode( $type ), array( 'domain' => $domain ) );

        if ( is_wp_error( $response ) ) {
            return $this->error_response( $response );
        }

        return new WP_REST_Response( $response, 200 );
    }

    /**
     * Get an API client for public (unauthenticated) requests.
     *
     * @return WH4U_Api_Client|WP_Error
     */
    private function get_public_client() {
        $user_id = get_current_user_id();
        if ( $user_id > 0 ) {
            return WH4U_Api_Client::from_user( $user_id );
        }

        global $wpdb;
        $table = $wpdb->prefix . 'wh4u_reseller_settings';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $table from $wpdb->prefix, no user input
        $first = $wpdb->get_row( "SELECT user_id FROM {$table} WHERE api_key_encrypted != '' LIMIT 1" );

        if ( $first ) {
            return WH4U_Api_Client::from_user( $first->user_id );
        }

        return new WP_Error(
            'wh4u_no_credentials',
            __( 'No API credentials configured.', 'wh4u-domains' ),
            array( 'status' => 503 )
        );
    }
}
