<?php
/**
 * Rate limiter for API endpoints using transients.
 *
 * @package WH4U_Domains
 * @license GPL-2.0-or-later
 * @link    https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WH4U_Rate_Limiter {

    /**
     * Default rate limits per endpoint group.
     *
     * @var array<string, array{max: int, window: int}>
     */
    private static $limits = array(
        'lookup'       => array( 'max' => 10, 'window' => 60 ),
        'order'        => array( 'max' => 5,  'window' => 60 ),
        'public'       => array( 'max' => 30, 'window' => 60 ),
        'public_order' => array( 'max' => 3,  'window' => 300 ),
        'read'         => array( 'max' => 20, 'window' => 60 ),
    );

    /**
     * Anonymous rate limit (by IP hash).
     *
     * @var array{max: int, window: int}
     */
    private static $anonymous_limit = array( 'max' => 5, 'window' => 60 );

    /**
     * Check if a request is rate-limited.
     *
     * @param string $endpoint_group The endpoint group (lookup, order).
     * @param int    $user_id        WordPress user ID (0 for anonymous).
     * @return bool True if rate-limited (should block), false if allowed.
     */
    public static function is_limited( $endpoint_group, $user_id = 0 ) {
        $config = self::get_config( $endpoint_group, $user_id );
        $key    = self::build_key( $endpoint_group, $user_id );

        $data = get_transient( $key );

        if ( $data === false ) {
            set_transient( $key, array( 'count' => 1, 'start' => time() ), $config['window'] );
            return false;
        }

        if ( ! is_array( $data ) || ! isset( $data['count'], $data['start'] ) ) {
            set_transient( $key, array( 'count' => 1, 'start' => time() ), $config['window'] );
            return false;
        }

        if ( ( time() - $data['start'] ) >= $config['window'] ) {
            set_transient( $key, array( 'count' => 1, 'start' => time() ), $config['window'] );
            return false;
        }

        if ( $data['count'] >= $config['max'] ) {
            return true;
        }

        $data['count']++;
        $remaining_ttl = $config['window'] - ( time() - $data['start'] );
        set_transient( $key, $data, max( 1, $remaining_ttl ) );

        return false;
    }

    /**
     * Get the rate limit config for a given group and user type.
     *
     * @param string $endpoint_group Endpoint group.
     * @param int    $user_id        User ID.
     * @return array{max: int, window: int}
     */
    private static function get_config( $endpoint_group, $user_id ) {
        if ( isset( self::$limits[ $endpoint_group ] ) ) {
            $group_config = self::$limits[ $endpoint_group ];
            if ( $user_id === 0 ) {
                return array(
                    'max'    => min( $group_config['max'], self::$anonymous_limit['max'] ),
                    'window' => max( $group_config['window'], self::$anonymous_limit['window'] ),
                );
            }
            return $group_config;
        }
        return self::$anonymous_limit;
    }

    /**
     * Build a unique transient key for rate limiting.
     *
     * @param string $endpoint_group Endpoint group.
     * @param int    $user_id        User ID.
     * @return string Transient key.
     */
    private static function build_key( $endpoint_group, $user_id ) {
        if ( $user_id > 0 ) {
            return 'wh4u_rl_' . $endpoint_group . '_u' . $user_id;
        }

        $ip = self::get_client_ip();
        $ip_hash = hash( 'sha256', $ip . wp_salt( 'auth' ) );

        return 'wh4u_rl_' . $endpoint_group . '_ip' . substr( $ip_hash, 0, 16 );
    }

    /**
     * Get client IP address safely.
     *
     * @return string IP address.
     */
    private static function get_client_ip() {
        if ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
            return sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
        }
        return '0.0.0.0';
    }

    /**
     * Get remaining requests for a user/endpoint combo.
     *
     * @param string $endpoint_group Endpoint group.
     * @param int    $user_id        User ID.
     * @return int Remaining requests in the current window.
     */
    public static function get_remaining( $endpoint_group, $user_id = 0 ) {
        $config = self::get_config( $endpoint_group, $user_id );
        $key    = self::build_key( $endpoint_group, $user_id );
        $data   = get_transient( $key );

        if ( $data === false || ! is_array( $data ) ) {
            return $config['max'];
        }

        if ( ( time() - $data['start'] ) >= $config['window'] ) {
            return $config['max'];
        }

        return max( 0, $config['max'] - $data['count'] );
    }
}
