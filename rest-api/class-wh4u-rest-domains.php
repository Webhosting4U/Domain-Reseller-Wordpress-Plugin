<?php
/**
 * REST endpoints for domain lookup, suggestions, and information.
 *
 * @package WH4U_Domains
 * @license GPL-2.0-or-later
 * @link    https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WH4U_REST_Domains extends WH4U_REST_Controller {

    /**
     * Register domain-related REST routes.
     *
     * @return void
     */
    public function register_routes() {
        register_rest_route( $this->namespace, '/domains/lookup', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array( $this, 'lookup' ),
            'permission_callback' => array( $this, 'check_lookup_permission' ),
            'args'                => array(
                'searchTerm' => array(
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                    'validate_callback' => function ( $value ) {
                        return is_string( $value ) && strlen( $value ) >= 2 && strlen( $value ) <= 253;
                    },
                ),
                'tldsToInclude' => array(
                    'required'          => false,
                    'type'              => 'array',
                    'sanitize_callback' => function ( $value ) {
                        return is_array( $value ) ? array_map( 'sanitize_text_field', $value ) : array();
                    },
                ),
                'premiumEnabled' => array(
                    'required' => false,
                    'type'     => 'boolean',
                    'default'  => false,
                ),
            ),
        ) );

        register_rest_route( $this->namespace, '/domains/lookup/suggestions', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array( $this, 'suggestions' ),
            'permission_callback' => array( $this, 'check_lookup_permission' ),
            'args'                => array(
                'searchTerm' => array(
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
            ),
        ) );

        register_rest_route( $this->namespace, '/domains/(?P<domain>[a-zA-Z0-9.-]+)/info', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'info' ),
            'permission_callback' => array( $this, 'check_manage_permission' ),
            'args'                => array(
                'domain' => array(
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                    'validate_callback' => function ( $value ) {
                        return preg_match( '/^[a-zA-Z0-9][a-zA-Z0-9.-]{1,251}[a-zA-Z0-9]$/', $value ) === 1;
                    },
                ),
            ),
        ) );

        register_rest_route( $this->namespace, '/domains/cart-redirect', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'cart_redirect' ),
            'permission_callback' => array( $this, 'check_public_permission' ),
            'args'                => array(
                'domain' => array(
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                    'validate_callback' => function ( $value ) {
                        return is_string( $value ) && strlen( $value ) >= 2 && strlen( $value ) <= 253
                            && preg_match( '/^[a-zA-Z0-9][a-zA-Z0-9.-]*[a-zA-Z0-9]$/', $value ) === 1;
                    },
                ),
                'action' => array(
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                    'validate_callback' => function ( $value ) {
                        return $value === 'register' || $value === 'transfer';
                    },
                ),
            ),
        ) );
    }

    /**
     * Permission for lookup: allow anonymous but rate-limit.
     *
     * @param WP_REST_Request $request Request object.
     * @return bool|WP_Error
     */
    public function check_lookup_permission( $request ) {
        $user_id = get_current_user_id();

        if ( WH4U_Rate_Limiter::is_limited( 'lookup', $user_id ) ) {
            return new WP_Error(
                'wh4u_rate_limited',
                __( 'Rate limit exceeded. Please try again shortly.', 'wh4u-domains' ),
                array( 'status' => 429 )
            );
        }

        return true;
    }

    /**
     * Return shopping cart redirect URL for a domain and action (register/transfer).
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error
     */
    public function cart_redirect( $request ) {
        $domain = $request->get_param( 'domain' );
        $action = $request->get_param( 'action' );
        $url    = WH4U_Cart_Redirect::get_redirect_url( $domain, $action );
        if ( $url === '' ) {
            return new WP_Error(
                'wh4u_cart_not_configured',
                __( 'Shopping cart redirect is not configured or the URL could not be built.', 'wh4u-domains' ),
                array( 'status' => 404 )
            );
        }
        return new WP_REST_Response( array( 'url' => $url ), 200 );
    }

    /**
     * Check domain availability.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error
     */
    public function lookup( $request ) {
        $client = $this->get_lookup_client();
        if ( is_wp_error( $client ) ) {
            return $this->error_response( $client );
        }

        $search_term = $request->get_param( 'searchTerm' );
        $tlds        = $request->get_param( 'tldsToInclude' );

        $extracted = $this->extract_tld_from_search_term( $search_term );
        if ( null !== $extracted ) {
            $search_term = $extracted['sld'];
            if ( empty( $tlds ) ) {
                $tlds = array( $extracted['tld'] );
            }
        }

        $params = array(
            'searchTerm' => $search_term,
        );

        if ( empty( $tlds ) ) {
            $tlds = $this->get_user_tlds( $client );
            if ( is_wp_error( $tlds ) ) {
                return $this->error_response( $tlds );
            }
        }

        $params['tldsToInclude'] = $tlds;

        $premium = $request->get_param( 'premiumEnabled' );
        if ( $premium ) {
            $params['premiumEnabled'] = true;
        }

        $response = $client->post( '/domains/lookup', $params );

        if ( is_wp_error( $response ) ) {
            return $this->error_response( $response );
        }

        return new WP_REST_Response( $response, 200 );
    }

    /**
     * Get domain suggestions.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error
     */
    public function suggestions( $request ) {
        $client = $this->get_lookup_client();
        if ( is_wp_error( $client ) ) {
            return $this->error_response( $client );
        }

        $params = array(
            'searchTerm' => $request->get_param( 'searchTerm' ),
        );

        $response = $client->post( '/domains/lookup/suggestions', $params );

        if ( is_wp_error( $response ) ) {
            return $this->error_response( $response );
        }

        return new WP_REST_Response( $response, 200 );
    }

    /**
     * Get domain information.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error
     */
    public function info( $request ) {
        $client = $this->get_client_with_rate_check( 'lookup' );
        if ( is_wp_error( $client ) ) {
            return $this->error_response( $client );
        }

        $domain   = $request->get_param( 'domain' );
        $response = $client->get( '/domains/' . rawurlencode( $domain ) . '/information' );

        if ( is_wp_error( $response ) ) {
            return $this->error_response( $response );
        }

        return new WP_REST_Response( $response, 200 );
    }

    /**
     * Resolve TLD list: user-configured allowed TLDs, or fetch all from API.
     *
     * @param WH4U_Api_Client $client Active API client.
     * @return array|WP_Error Array of TLD strings or WP_Error.
     */
    private function get_user_tlds( $client ) {
        $user_id = get_current_user_id();

        if ( $user_id > 0 ) {
            global $wpdb;
            $table = $wpdb->prefix . 'wh4u_reseller_settings';
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $table from $wpdb->prefix
            $raw   = $wpdb->get_var( $wpdb->prepare(
                "SELECT allowed_tlds FROM {$table} WHERE user_id = %d",
                $user_id
            ) );
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

            if ( ! empty( $raw ) ) {
                $parsed = json_decode( $raw, true );
                if ( is_array( $parsed ) && ! empty( $parsed ) ) {
                    return $parsed;
                }
            }
        }

        $cached = get_transient( 'wh4u_all_tlds' );
        if ( is_array( $cached ) && ! empty( $cached ) ) {
            return $cached;
        }

        $response = $client->get( '/tlds' );
        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $tlds = array();
        if ( is_array( $response ) ) {
            foreach ( $response as $item ) {
                if ( is_string( $item ) ) {
                    $tlds[] = $item;
                } elseif ( is_array( $item ) && isset( $item['tld'] ) ) {
                    $tlds[] = $item['tld'];
                } elseif ( is_array( $item ) && isset( $item['extension'] ) ) {
                    $tlds[] = $item['extension'];
                }
            }
        }

        if ( empty( $tlds ) ) {
            return new WP_Error(
                'wh4u_no_tlds',
                __( 'Could not retrieve TLD list from API.', 'wh4u-domains' ),
                array( 'status' => 500 )
            );
        }

        set_transient( 'wh4u_all_tlds', $tlds, DAY_IN_SECONDS );

        return $tlds;
    }

    /**
     * Extract TLD from a search term that contains a dot (e.g. "example.com").
     *
     * Returns null if no recognisable TLD is found, so the caller falls back
     * to the default multi-TLD behaviour.
     *
     * @param string $search_term Raw search input.
     * @return array|null Array with 'sld' and 'tld' keys, or null.
     */
    private function extract_tld_from_search_term( $search_term ) {
        $search_term = trim( $search_term );

        $dot_pos = strpos( $search_term, '.' );
        if ( false === $dot_pos || $dot_pos === 0 ) {
            return null;
        }

        $sld       = substr( $search_term, 0, $dot_pos );
        $candidate = substr( $search_term, $dot_pos + 1 );

        if ( empty( $candidate ) || empty( $sld ) ) {
            return null;
        }

        $candidate = strtolower( ltrim( $candidate, '.' ) );

        if ( ! preg_match( '/^[a-z]{2,}(\.[a-z]{2,})?$/', $candidate ) ) {
            return null;
        }

        return array(
            'sld' => $sld,
            'tld' => '.' . $candidate,
        );
    }

    /**
     * Get an API client for lookup.
     *
     * Logged-in users use their own reseller credentials.
     * Anonymous visitors use the explicitly designated public-lookup reseller
     * (configured under Domains > Settings > General). When unset, anonymous
     * lookups are refused — callers must not fall back to arbitrary resellers,
     * because that would bill API calls to whichever reseller was inserted first.
     *
     * @return WH4U_Api_Client|WP_Error
     */
    private function get_lookup_client() {
        $user_id = get_current_user_id();

        if ( $user_id > 0 ) {
            return WH4U_Api_Client::from_user( $user_id );
        }

        $public_reseller_id = WH4U_Admin_Settings::get_public_lookup_reseller_id();
        if ( $public_reseller_id > 0 ) {
            return WH4U_Api_Client::from_user( $public_reseller_id );
        }

        return new WP_Error(
            'wh4u_public_lookup_disabled',
            __( 'Anonymous domain lookups are not enabled on this site. Please sign in or ask the site administrator to configure a designated reseller under Domains > Settings.', 'wh4u-domains' ),
            array( 'status' => 503 )
        );
    }
}
