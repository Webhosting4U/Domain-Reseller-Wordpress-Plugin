<?php
/**
 * AES-256-CBC encryption with HMAC-SHA-256 integrity (Encrypt-then-MAC) for
 * sensitive data at rest.
 *
 * Key sources (write priority):
 *   1. WH4U_ENCRYPTION_KEY constant in wp-config.php (preferred).
 *   2. HKDF-style derivation from AUTH_KEY + SECURE_AUTH_KEY salts (default).
 *
 * Legacy decrypt-only:
 *   3. wh4u_auto_encryption_key wp_options entry — kept readable so older
 *      installs can still decrypt, but never used to encrypt new data.
 *      migrate_legacy_key() re-encrypts every field with the preferred key
 *      and removes this option.
 *
 * @package WH4U_Domains
 * @license GPL-2.0-or-later
 * @link    https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WH4U_Encryption {

    private const CIPHER         = 'aes-256-cbc';
    private const LEGACY_OPTION  = 'wh4u_auto_encryption_key';
    private const MIGRATION_LOCK = 'wh4u_key_migration_lock';

    /**
     * Key derived from the WH4U_ENCRYPTION_KEY constant, if set.
     *
     * @return string|null 32-byte binary key or null if unavailable.
     */
    private static function derive_constant_key() {
        if ( defined( 'WH4U_ENCRYPTION_KEY' ) && WH4U_ENCRYPTION_KEY !== '' ) {
            return hash( 'sha256', WH4U_ENCRYPTION_KEY, true );
        }
        return null;
    }

    /**
     * Key derived from AUTH_KEY + SECURE_AUTH_KEY.
     *
     * @return string|null 32-byte binary key or null if unavailable.
     */
    private static function derive_salts_key() {
        if ( defined( 'AUTH_KEY' ) && AUTH_KEY !== ''
            && defined( 'SECURE_AUTH_KEY' ) && SECURE_AUTH_KEY !== '' ) {
            return hash_hmac( 'sha256', 'wh4u-domains|v1|at-rest', AUTH_KEY . '|' . SECURE_AUTH_KEY, true );
        }
        return null;
    }

    /**
     * Legacy key read from wp_options. Decrypt-only.
     *
     * @return string|null 32-byte binary key or null if unavailable.
     */
    private static function derive_legacy_key() {
        $stored = get_option( self::LEGACY_OPTION );
        if ( $stored !== false && $stored !== '' ) {
            return hash( 'sha256', $stored, true );
        }
        return null;
    }

    /**
     * Preferred key for new encrypts. Returns null when no strong key source is
     * available; callers surface this as "encryption unavailable" rather than
     * silently downgrading to a DB-stored secret.
     *
     * @return string|null
     */
    private static function get_write_key() {
        $key = self::derive_constant_key();
        if ( $key !== null ) {
            return $key;
        }
        return self::derive_salts_key();
    }

    /**
     * Candidate keys to try during decrypt, preferred first.
     *
     * The HMAC on each ciphertext uniquely identifies which key was used, so
     * trying multiple keys is safe (no chance of mis-decrypting with the wrong
     * key).
     *
     * @return string[]
     */
    private static function get_candidate_keys() {
        $keys = array();

        $k = self::derive_constant_key();
        if ( $k !== null ) {
            $keys[] = $k;
        }
        $k = self::derive_salts_key();
        if ( $k !== null ) {
            $keys[] = $k;
        }
        $k = self::derive_legacy_key();
        if ( $k !== null ) {
            $keys[] = $k;
        }

        return $keys;
    }

    /**
     * Encrypt a plaintext string with the preferred key.
     *
     * @param string $plaintext The data to encrypt.
     * @return string Base64-encoded HMAC + IV + ciphertext, or '' on failure.
     */
    public static function encrypt( $plaintext ) {
        if ( $plaintext === '' || $plaintext === null ) {
            return '';
        }

        $key = self::get_write_key();
        if ( $key === null ) {
            return '';
        }

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
     * Decrypt a previously encrypted string, trying each candidate key.
     *
     * @param string $encoded Base64-encoded HMAC + IV + ciphertext.
     * @return string Decrypted plaintext, or '' on failure.
     */
    public static function decrypt( $encoded ) {
        if ( $encoded === '' || $encoded === null ) {
            return '';
        }

        $raw = base64_decode( $encoded, true );
        if ( $raw === false ) {
            return '';
        }

        $hmac_len = 32;
        $iv_len   = openssl_cipher_iv_length( self::CIPHER );

        if ( strlen( $raw ) < $hmac_len + $iv_len + 1 ) {
            return '';
        }

        $hmac       = substr( $raw, 0, $hmac_len );
        $iv         = substr( $raw, $hmac_len, $iv_len );
        $ciphertext = substr( $raw, $hmac_len + $iv_len );

        foreach ( self::get_candidate_keys() as $key ) {
            $expected = hash_hmac( 'sha256', $iv . $ciphertext, $key, true );
            if ( ! hash_equals( $expected, $hmac ) ) {
                continue;
            }
            $plain = openssl_decrypt( $ciphertext, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv );
            if ( $plain !== false ) {
                return $plain;
            }
        }

        return '';
    }

    /**
     * Decrypt a value if it looks encrypted, otherwise return it as-is.
     *
     * Lets readers transparently migrate from plaintext to encrypted storage:
     * decrypt() fails closed (returns '') on HMAC mismatch, so legacy plaintext
     * values fall through to the original string.
     *
     * @param mixed $value Stored value (encrypted envelope or legacy plaintext).
     * @return string Decrypted plaintext, original plaintext, or ''.
     */
    public static function maybe_decrypt( $value ) {
        if ( $value === '' || $value === null ) {
            return '';
        }
        if ( ! is_string( $value ) ) {
            return '';
        }

        $decoded = self::decrypt( $value );
        if ( $decoded !== '' ) {
            return $decoded;
        }

        return $value;
    }

    /**
     * Decide whether to render the admin notice warning about key configuration.
     *
     * @return void
     */
    public static function maybe_show_key_warning() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $has_strong   = ( self::derive_constant_key() !== null ) || ( self::derive_salts_key() !== null );
        $using_legacy = ( get_option( self::LEGACY_OPTION ) !== false );

        if ( ! $has_strong || $using_legacy ) {
            add_action( 'admin_notices', array( __CLASS__, 'render_key_warning' ) );
        }
    }

    /**
     * Render the encryption key warning notice.
     *
     * @return void
     */
    public static function render_key_warning() {
        $has_strong   = ( self::derive_constant_key() !== null ) || ( self::derive_salts_key() !== null );
        $using_legacy = ( get_option( self::LEGACY_OPTION ) !== false );

        echo '<div class="notice notice-warning is-dismissible"><p>';
        if ( ! $has_strong ) {
            echo esc_html__(
                'WH4U Domains: No strong encryption key source is available. Define WH4U_ENCRYPTION_KEY in wp-config.php or ensure AUTH_KEY and SECURE_AUTH_KEY are configured. Encryption is currently disabled.',
                'wh4u-domains'
            );
        } elseif ( $using_legacy ) {
            echo esc_html__(
                'WH4U Domains: A legacy database-stored encryption key was detected and is being migrated to a stronger key source. This happens automatically on admin page loads and should complete shortly.',
                'wh4u-domains'
            );
        }
        echo '</p></div>';
    }

    /**
     * One-time migration: re-encrypt every ciphertext with the preferred key
     * and remove the legacy wp_options key.
     *
     * Idempotent and safe to run repeatedly. Exits early when:
     *   - the legacy option does not exist, or
     *   - no preferred key is available (can't migrate into nothing).
     *
     * @return void
     */
    public static function migrate_legacy_key() {
        if ( get_option( self::LEGACY_OPTION ) === false ) {
            return;
        }
        if ( self::get_write_key() === null ) {
            return;
        }
        if ( get_transient( self::MIGRATION_LOCK ) ) {
            return;
        }
        set_transient( self::MIGRATION_LOCK, 1, 5 * MINUTE_IN_SECONDS );

        try {
            $all_ok  = true;
            $all_ok &= self::migrate_reseller_settings();
            $all_ok &= self::migrate_orders_contacts();
            $all_ok &= self::migrate_site_settings();
            $all_ok &= self::migrate_public_order_meta();

            // Keep the legacy key around if any row could not be re-encrypted,
            // so a later retry can complete. Deleting early would permanently
            // orphan rows that still hold legacy-key ciphertext.
            if ( $all_ok ) {
                delete_option( self::LEGACY_OPTION );
            }
        } finally {
            delete_transient( self::MIGRATION_LOCK );
        }
    }

    /**
     * Re-encrypt a single legacy ciphertext with the preferred key.
     *
     * Returns the new envelope on success. Empty string if the value is empty
     * (nothing to do) or if decrypt fell through (looks like plaintext). False
     * signals a real failure: the value decoded but re-encryption failed, so
     * the caller must keep the legacy key around for a later retry.
     *
     * @param string $ciphertext Stored ciphertext.
     * @return string|false New ciphertext, '' when nothing to migrate, or false on failure.
     */
    private static function reencrypt_or_fail( $ciphertext ) {
        if ( ! is_string( $ciphertext ) || $ciphertext === '' ) {
            return '';
        }
        $plain = self::decrypt( $ciphertext );
        if ( $plain === '' ) {
            // Decrypt fell through — value is plaintext or unreadable garbage;
            // either way we have nothing to migrate here.
            return '';
        }
        $reenc = self::encrypt( $plain );
        if ( $reenc === '' ) {
            return false;
        }
        return $reenc;
    }

    /**
     * Re-encrypt wh4u_reseller_settings api_key / webhook_secret columns.
     *
     * @return bool True if every eligible row was handled successfully.
     */
    private static function migrate_reseller_settings() {
        global $wpdb;
        $table = $wpdb->prefix . 'wh4u_reseller_settings';
        $ok    = true;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $table from $wpdb->prefix, no user input, one-shot migration
        $rows = $wpdb->get_results( "SELECT id, api_key_encrypted, webhook_secret_encrypted FROM {$table}" );
        foreach ( (array) $rows as $row ) {
            $update = array();
            $format = array();
            foreach ( array( 'api_key_encrypted', 'webhook_secret_encrypted' ) as $field ) {
                $reenc = self::reencrypt_or_fail( $row->$field );
                if ( $reenc === false ) {
                    $ok = false;
                    continue;
                }
                if ( $reenc === '' ) {
                    continue;
                }
                $update[ $field ] = $reenc;
                $format[]         = '%s';
            }
            if ( ! empty( $update ) ) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                $wpdb->update( $table, $update, array( 'id' => (int) $row->id ), $format, array( '%d' ) );
            }
        }
        return $ok;
    }

    /**
     * Re-encrypt wh4u_orders.contacts column (JSON blob).
     *
     * @return bool True if every eligible row was handled successfully.
     */
    private static function migrate_orders_contacts() {
        global $wpdb;
        $table = $wpdb->prefix . 'wh4u_orders';
        $ok    = true;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $table from $wpdb->prefix, no user input, one-shot migration
        $rows = $wpdb->get_results( "SELECT id, contacts FROM {$table} WHERE contacts IS NOT NULL AND contacts != ''" );
        foreach ( (array) $rows as $row ) {
            $reenc = self::reencrypt_or_fail( $row->contacts );
            if ( $reenc === false ) {
                $ok = false;
                continue;
            }
            if ( $reenc === '' ) {
                continue;
            }
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->update( $table, array( 'contacts' => $reenc ), array( 'id' => (int) $row->id ), array( '%s' ), array( '%d' ) );
        }
        return $ok;
    }

    /**
     * Re-encrypt the Turnstile secret inside the wh4u_settings option.
     *
     * @return bool True on success or nothing-to-do.
     */
    private static function migrate_site_settings() {
        $settings = get_option( 'wh4u_settings' );
        if ( ! is_array( $settings ) || empty( $settings['turnstile_secret_key_encrypted'] ) ) {
            return true;
        }
        $reenc = self::reencrypt_or_fail( $settings['turnstile_secret_key_encrypted'] );
        if ( $reenc === false ) {
            return false;
        }
        if ( $reenc === '' ) {
            return true;
        }
        $settings['turnstile_secret_key_encrypted'] = $reenc;
        update_option( 'wh4u_settings', $settings );
        return true;
    }

    /**
     * Re-encrypt the PII postmeta fields on public-order posts.
     *
     * @return bool True if every meta value was handled successfully.
     */
    private static function migrate_public_order_meta() {
        if ( ! class_exists( 'WH4U_REST_Public_Orders' ) ) {
            return true;
        }
        $keys = WH4U_REST_Public_Orders::get_pii_meta_keys();
        if ( empty( $keys ) ) {
            return true;
        }
        $ok = true;

        $post_ids = get_posts( array(
            'post_type'      => 'wh4u_public_order',
            'post_status'    => 'any',
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ) );

        foreach ( (array) $post_ids as $post_id ) {
            foreach ( $keys as $meta_key ) {
                $value = get_post_meta( $post_id, $meta_key, true );
                $reenc = self::reencrypt_or_fail( $value );
                if ( $reenc === false ) {
                    $ok = false;
                    continue;
                }
                if ( $reenc === '' ) {
                    continue;
                }
                update_post_meta( $post_id, $meta_key, $reenc );
            }
        }
        return $ok;
    }
}

add_action( 'admin_init', array( 'WH4U_Encryption', 'maybe_show_key_warning' ) );
add_action( 'admin_init', array( 'WH4U_Encryption', 'migrate_legacy_key' ) );
