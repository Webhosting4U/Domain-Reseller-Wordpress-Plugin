<?php
/**
 * Shopping cart redirect URL builder for WHMCS, Blesta, ClientExec, Upmind, and custom carts.
 *
 * @package WH4U_Domains
 * @license GPL-2.0-or-later
 * @link    https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WH4U_Cart_Redirect
 */
class WH4U_Cart_Redirect {

	/** @var string[] Valid cart types. */
	const CART_TYPES = array( 'whmcs', 'blesta', 'clientexec', 'upmind', 'custom' );

	/**
	 * Build redirect URL for a domain and action (register or transfer).
	 *
	 * Returns empty string if cart is not configured or URL cannot be built.
	 * Output is validated to be HTTPS only (SSRF prevention).
	 *
	 * @param string $domain Full domain name (e.g. example.com).
	 * @param string $action 'register' or 'transfer'.
	 * @return string Redirect URL or empty string.
	 */
	public static function get_redirect_url( $domain, $action ) {
		$domain = self::normalize_domain( $domain );
		if ( $domain === '' ) {
			return '';
		}

		$settings = get_option( 'wh4u_settings', array() );
		$cart_type = isset( $settings['cart_type'] ) ? $settings['cart_type'] : '';
		if ( $cart_type === '' || ! in_array( $cart_type, self::CART_TYPES, true ) ) {
			return '';
		}

		$url = '';
		if ( $cart_type === 'custom' ) {
			$template = $action === 'transfer'
				? ( isset( $settings['cart_transfer_url'] ) ? $settings['cart_transfer_url'] : '' )
				: ( isset( $settings['cart_register_url'] ) ? $settings['cart_register_url'] : '' );
			$url = self::replace_placeholders( $template, $domain );
		} else {
			$base = isset( $settings['cart_base_url'] ) ? trim( $settings['cart_base_url'] ) : '';
			if ( $base === '' ) {
				return '';
			}
			$base = rtrim( $base, '/' );
			$url  = self::build_preset_url( $base, $cart_type, $domain, $action );
		}

		return self::validate_redirect_url( $url ) ? $url : '';
	}

	/**
	 * Check if cart redirect is configured (so frontend can skip form and redirect).
	 *
	 * @return bool
	 */
	public static function is_configured() {
		$settings = get_option( 'wh4u_settings', array() );
		$cart_type = isset( $settings['cart_type'] ) ? $settings['cart_type'] : '';
		if ( $cart_type === '' || ! in_array( $cart_type, self::CART_TYPES, true ) ) {
			return false;
		}
		if ( $cart_type === 'custom' ) {
			$reg = isset( $settings['cart_register_url'] ) ? trim( $settings['cart_register_url'] ) : '';
			$xfer = isset( $settings['cart_transfer_url'] ) ? trim( $settings['cart_transfer_url'] ) : '';
			return ( $reg !== '' && strpos( $reg, '{domain}' ) !== false )
				|| ( $xfer !== '' && strpos( $xfer, '{domain}' ) !== false );
		}
		$base = isset( $settings['cart_base_url'] ) ? trim( $settings['cart_base_url'] ) : '';
		return $base !== '';
	}

	/**
	 * Normalize and validate domain (allow only valid hostname characters).
	 *
	 * @param string $domain Raw domain.
	 * @return string Sanitized domain or empty.
	 */
	private static function normalize_domain( $domain ) {
		$domain = is_string( $domain ) ? trim( $domain ) : '';
		$domain = preg_replace( '/^https?:\/\//', '', $domain );
		$domain = preg_replace( '/\/.*$/', '', $domain );
		$domain = strtolower( $domain );
		if ( $domain === '' || strlen( $domain ) > 253 ) {
			return '';
		}
		// Allow letters, digits, hyphens, dots (no spaces or special chars).
		if ( preg_match( '/^[a-z0-9]([a-z0-9.-]*[a-z0-9])?$/i', $domain ) !== 1 ) {
			return '';
		}
		return $domain;
	}

	/**
	 * Replace {domain}, {sld}, {tld} in template.
	 *
	 * @param string $template URL template.
	 * @param string $domain    Full domain.
	 * @return string
	 */
	private static function replace_placeholders( $template, $domain ) {
		if ( $template === '' ) {
			return '';
		}
		$parts = self::split_domain( $domain );
		$url   = str_replace( array( '{domain}', '{sld}', '{tld}' ), array( $domain, $parts['sld'], $parts['tld'] ), $template );
		return $url;
	}

	/**
	 * Split domain into sld and tld (simple: last dot separates tld).
	 *
	 * @param string $domain Full domain.
	 * @return array{ sld: string, tld: string }
	 */
	private static function split_domain( $domain ) {
		$pos = strrpos( $domain, '.' );
		if ( $pos === false ) {
			return array( 'sld' => $domain, 'tld' => '' );
		}
		return array(
			'sld' => substr( $domain, 0, $pos ),
			'tld' => substr( $domain, $pos + 1 ),
		);
	}

	/**
	 * Build URL for preset cart types (WHMCS, Blesta, ClientExec, Upmind).
	 *
	 * @param string $base      Base URL (no trailing slash).
	 * @param string $cart_type One of self::CART_TYPES (excluding 'custom').
	 * @param string $domain    Full domain.
	 * @param string $action    'register' or 'transfer'.
	 * @return string
	 */
	private static function build_preset_url( $base, $cart_type, $domain, $action ) {
		$parts = self::split_domain( $domain );
		$sld   = $parts['sld'];
		$tld   = $parts['tld'];

		switch ( $cart_type ) {
			case 'whmcs':
				$path = '/cart.php';
				$args = array(
					'a'      => 'add',
					'domain' => $action === 'transfer' ? 'transfer' : 'register',
					'sld'    => $sld,
					'tld'    => $tld,
				);
				return $base . $path . '?' . http_build_query( $args );
			case 'blesta':
				// Common order form path; domain passed as query (many Blesta setups use package_id; this supports domain= for custom forms).
				$path = '/order/main/index/';
				$args = array( 'domain' => $domain );
				return $base . $path . '?' . http_build_query( $args );
			case 'clientexec':
				// order.php with productGroup for domains; pass domain for prefill.
				$path = '/order.php';
				$args = array(
					'step'         => '1',
					'productGroup' => '2',
					'domain'       => $domain,
				);
				return $base . $path . '?' . http_build_query( $args );
			case 'upmind':
				// No public standard; pass domain in query for client area / order.
				$path = '/';
				$args = array( 'domain' => $domain, 'action' => $action );
				return $base . $path . '?' . http_build_query( $args );
			default:
				return '';
		}
	}

	/**
	 * Validate URL for redirect: only HTTPS, no javascript or internal hosts (SSRF prevention).
	 *
	 * @param string $url Candidate URL.
	 * @return bool True if safe to redirect.
	 */
	private static function validate_redirect_url( $url ) {
		if ( $url === '' || ! is_string( $url ) ) {
			return false;
		}
		$parsed = wp_parse_url( $url );
		if ( $parsed === false || ! isset( $parsed['host'] ) ) {
			return false;
		}
		$scheme = isset( $parsed['scheme'] ) ? strtolower( $parsed['scheme'] ) : '';
		if ( $scheme !== 'https' ) {
			return false;
		}
		$host = strtolower( $parsed['host'] );
		// Block localhost and private IP ranges.
		if ( in_array( $host, array( 'localhost', '127.0.0.1', '::1' ), true ) ) {
			return false;
		}
		if ( preg_match( '/^(10\.|172\.(1[6-9]|2[0-9]|3[01])\.|192\.168\.|169\.254\.)/', $host ) === 1 ) {
			return false;
		}
		// Allow only valid host characters.
		if ( preg_match( '/^[a-z0-9][a-z0-9.-]*[a-z0-9]$/i', $host ) !== 1 && $host !== 'localhost' ) {
			return false;
		}
		return true;
	}
}
