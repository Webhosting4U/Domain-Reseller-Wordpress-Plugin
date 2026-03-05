<?php
/**
 * Handles AES-256-CBC encryption and decryption for sensitive data at rest.
 *
 * @package WH4U_Domains
 * @license GPL-2.0-or-later
 * @link    https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WH4U_Encryption {

    private const CIPHER = 'aes-256-cbc';

    /**
     * Retrieve the encryption key.
     *
     * Prefers the WH4U_ENCRYPTION_KEY constant defined in wp-config.php.
     * Falls back to an auto-generated key stored in wp_options with an admin warning.
     *
     * @return string Raw binary key (32 bytes).
     */
    private static function get_key() {
        if ( defined( 'WH4U_ENCRYPTION_KEY' ) && WH4U_ENCRYPTION_KEY !== '' ) {
            return hash( 'sha256', WH4U_ENCRYPTION_KEY, true );
        }

        $stored = get_option( 'wh4u_auto_encryption_key' );
        if ( $stored !== false && $stored !== '' ) {
            return hash( 'sha256', $stored, true );
        }

        $generated = wp_generate_password( 64, true, true );
        update_option( 'wh4u_auto_encryption_key', $generated, false );

        return hash( 'sha256', $generated, true );
    }

    /**
     * Show an admin notice if using the fallback key.
     *
     * @return void
     */
    public static function maybe_show_key_warning() {
        if ( defined( 'WH4U_ENCRYPTION_KEY' ) && WH4U_ENCRYPTION_KEY !== '' ) {
            return;
        }
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        add_action( 'admin_notices', array( __CLASS__, 'render_key_warning' ) );
    }

    /**
     * Render the encryption key warning notice.
     *
     * @return void
     */
    public static function render_key_warning() {
        echo '<div class="notice notice-warning is-dismissible"><p>';
        echo esc_html__(
            'WH4U Domains: For maximum security, define WH4U_ENCRYPTION_KEY in your wp-config.php. The plugin is currently using an auto-generated key stored in the database.',
            'wh4u-domains'
        );
        echo '</p></div>';
    }

    /**
     * Encrypt a plaintext string.
     *
     * @param string $plaintext The data to encrypt.
     * @return string Base64-encoded IV + ciphertext, or empty string on failure.
     */
    public static function encrypt( $plaintext ) {
        if ( $plaintext === '' || $plaintext === null ) {
            return '';
        }

        $key    = self::get_key();
        $iv_len = openssl_cipher_iv_length( self::CIPHER );
        $iv     = openssl_random_pseudo_bytes( $iv_len );

        if ( $iv === false ) {
            return '';
        }

        $ciphertext = openssl_encrypt( $plaintext, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv );

        if ( $ciphertext === false ) {
            return '';
        }

        $hmac = hash_hmac( 'sha256', $iv . $ciphertext, $key, true );

        return base64_encode( $hmac . $iv . $ciphertext );
    }

    /**
     * Decrypt a previously encrypted string.
     *
     * @param string $encoded Base64-encoded HMAC + IV + ciphertext.
     * @return string Decrypted plaintext, or empty string on failure.
     */
    public static function decrypt( $encoded ) {
        if ( $encoded === '' || $encoded === null ) {
            return '';
        }

        $raw = base64_decode( $encoded, true );
        if ( $raw === false ) {
            return '';
        }

        $key      = self::get_key();
        $hmac_len = 32;
        $iv_len   = openssl_cipher_iv_length( self::CIPHER );

        if ( strlen( $raw ) < $hmac_len + $iv_len + 1 ) {
            return '';
        }

        $hmac       = substr( $raw, 0, $hmac_len );
        $iv         = substr( $raw, $hmac_len, $iv_len );
        $ciphertext = substr( $raw, $hmac_len + $iv_len );

        $expected_hmac = hash_hmac( 'sha256', $iv . $ciphertext, $key, true );

        if ( ! hash_equals( $expected_hmac, $hmac ) ) {
            return '';
        }

        $plaintext = openssl_decrypt( $ciphertext, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv );

        return ( $plaintext !== false ) ? $plaintext : '';
    }
}

add_action( 'admin_init', array( 'WH4U_Encryption', 'maybe_show_key_warning' ) );
