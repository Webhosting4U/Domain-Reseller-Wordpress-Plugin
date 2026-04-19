<?php
/**
 * Unified settings page: General (admin-only) + Credentials (per-user).
 *
 * @package WH4U_Domains
 * @license GPL-2.0-or-later
 * @link    https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WH4U_Admin_Settings {

    /**
     * Register WP Settings API entries for the General tab.
     *
     * @return void
     */
    public static function register_settings() {
        register_setting( 'wh4u_settings_group', 'wh4u_settings', array(
            'type'              => 'array',
            'sanitize_callback' => array( __CLASS__, 'sanitize_settings' ),
            'default'           => array(
                'api_base_url' => 'https://webhosting4u.gr/customers/modules/addons/DomainsReseller/api/index.php',
                'mode'         => 'realtime',
                'cart_type'    => '',
                'cart_base_url' => '',
                'cart_register_url' => '',
                'cart_transfer_url' => '',
            ),
        ) );

        add_settings_section(
            'wh4u_api_section',
            __( 'API Configuration', 'wh4u-domains' ),
            array( __CLASS__, 'render_api_section' ),
            'wh4u-domains-settings'
        );

        add_settings_field(
            'wh4u_api_base_url',
            __( 'API Base URL', 'wh4u-domains' ),
            array( __CLASS__, 'render_api_base_url_field' ),
            'wh4u-domains-settings',
            'wh4u_api_section'
        );

        add_settings_field(
            'wh4u_mode',
            __( 'Registration Mode', 'wh4u-domains' ),
            array( __CLASS__, 'render_mode_field' ),
            'wh4u-domains-settings',
            'wh4u_api_section'
        );

        add_settings_section(
            'wh4u_cart_section',
            __( 'Shopping Cart Redirect', 'wh4u-domains' ),
            array( __CLASS__, 'render_cart_section' ),
            'wh4u-domains-settings'
        );

        add_settings_field(
            'wh4u_cart_type',
            __( 'Cart Type', 'wh4u-domains' ),
            array( __CLASS__, 'render_cart_type_field' ),
            'wh4u-domains-settings',
            'wh4u_cart_section'
        );

        add_settings_field(
            'wh4u_cart_base_url',
            __( 'Cart Base URL', 'wh4u-domains' ),
            array( __CLASS__, 'render_cart_base_url_field' ),
            'wh4u-domains-settings',
            'wh4u_cart_section'
        );

        add_settings_field(
            'wh4u_cart_register_url',
            __( 'Register URL Template (Custom)', 'wh4u-domains' ),
            array( __CLASS__, 'render_cart_register_url_field' ),
            'wh4u-domains-settings',
            'wh4u_cart_section'
        );

        add_settings_field(
            'wh4u_cart_transfer_url',
            __( 'Transfer URL Template (Custom)', 'wh4u-domains' ),
            array( __CLASS__, 'render_cart_transfer_url_field' ),
            'wh4u-domains-settings',
            'wh4u_cart_section'
        );

        add_settings_section(
            'wh4u_proxy_section',
            __( 'Reverse Proxy / IP Detection', 'wh4u-domains' ),
            array( __CLASS__, 'render_proxy_section' ),
            'wh4u-domains-settings'
        );

        add_settings_field(
            'wh4u_trusted_proxy_header',
            __( 'Trusted Proxy IP Header', 'wh4u-domains' ),
            array( __CLASS__, 'render_trusted_proxy_header_field' ),
            'wh4u-domains-settings',
            'wh4u_proxy_section'
        );

        add_settings_field(
            'wh4u_trusted_proxies',
            __( 'Trusted Proxy IPs', 'wh4u-domains' ),
            array( __CLASS__, 'render_trusted_proxies_field' ),
            'wh4u-domains-settings',
            'wh4u_proxy_section'
        );

        add_settings_section(
            'wh4u_public_lookup_section',
            __( 'Public Domain Lookup', 'wh4u-domains' ),
            array( __CLASS__, 'render_public_lookup_section' ),
            'wh4u-domains-settings'
        );

        add_settings_field(
            'wh4u_public_lookup_reseller_id',
            __( 'Designated Reseller for Public Lookups', 'wh4u-domains' ),
            array( __CLASS__, 'render_public_lookup_reseller_field' ),
            'wh4u-domains-settings',
            'wh4u_public_lookup_section'
        );

        add_settings_section(
            'wh4u_turnstile_section',
            __( 'Cloudflare Turnstile', 'wh4u-domains' ),
            array( __CLASS__, 'render_turnstile_section' ),
            'wh4u-domains-settings'
        );

        add_settings_field(
            'wh4u_turnstile_site_key',
            __( 'Site Key', 'wh4u-domains' ),
            array( __CLASS__, 'render_turnstile_site_key_field' ),
            'wh4u-domains-settings',
            'wh4u_turnstile_section'
        );

        add_settings_field(
            'wh4u_turnstile_secret_key',
            __( 'Secret Key', 'wh4u-domains' ),
            array( __CLASS__, 'render_turnstile_secret_key_field' ),
            'wh4u-domains-settings',
            'wh4u_turnstile_section'
        );
    }

    /**
     * Sanitize global settings on save.
     *
     * @param mixed $input Raw input from the settings form.
     * @return array Sanitized settings.
     */
    public static function sanitize_settings( $input ) {
        if ( ! is_array( $input ) ) {
            return array();
        }

        $input = wp_unslash( $input );

        $sanitized = array();

        $sanitized['api_base_url'] = isset( $input['api_base_url'] )
            ? esc_url_raw( trim( $input['api_base_url'] ) )
            : '';

        $valid_modes = array( 'realtime', 'notification' );
        $sanitized['mode'] = isset( $input['mode'] ) && in_array( $input['mode'], $valid_modes, true )
            ? $input['mode']
            : 'realtime';

        $valid_cart_types = array( '', 'whmcs', 'blesta', 'clientexec', 'upmind', 'custom' );
        $sanitized['cart_type'] = isset( $input['cart_type'] ) && in_array( $input['cart_type'], $valid_cart_types, true )
            ? $input['cart_type']
            : '';
        $sanitized['cart_base_url'] = isset( $input['cart_base_url'] ) ? esc_url_raw( trim( $input['cart_base_url'] ) ) : '';
        $sanitized['cart_register_url'] = isset( $input['cart_register_url'] ) ? self::sanitize_cart_template( $input['cart_register_url'] ) : '';
        $sanitized['cart_transfer_url'] = isset( $input['cart_transfer_url'] ) ? self::sanitize_cart_template( $input['cart_transfer_url'] ) : '';

        $allowed_headers = array( '', 'cf-connecting-ip', 'x-real-ip', 'x-forwarded-for', 'true-client-ip' );
        $submitted_header = isset( $input['trusted_proxy_header'] ) ? strtolower( trim( (string) $input['trusted_proxy_header'] ) ) : '';
        $sanitized['trusted_proxy_header'] = in_array( $submitted_header, $allowed_headers, true ) ? $submitted_header : '';

        $sanitized['trusted_proxies'] = array();
        if ( isset( $input['trusted_proxies'] ) && is_string( $input['trusted_proxies'] ) ) {
            foreach ( preg_split( '/[\s,]+/', $input['trusted_proxies'] ) as $candidate ) {
                $candidate = trim( $candidate );
                if ( $candidate !== '' && filter_var( $candidate, FILTER_VALIDATE_IP ) ) {
                    $sanitized['trusted_proxies'][] = $candidate;
                }
            }
        }

        $sanitized['public_lookup_reseller_id'] = isset( $input['public_lookup_reseller_id'] )
            ? absint( $input['public_lookup_reseller_id'] )
            : 0;

        $sanitized['turnstile_site_key'] = isset( $input['turnstile_site_key'] ) ? sanitize_text_field( $input['turnstile_site_key'] ) : '';

        $existing = get_option( 'wh4u_settings', array() );
        $existing_encrypted = isset( $existing['turnstile_secret_key_encrypted'] ) ? $existing['turnstile_secret_key_encrypted'] : '';

        if ( isset( $input['turnstile_secret_key'] ) ) {
            $submitted_secret = sanitize_text_field( $input['turnstile_secret_key'] );
            if ( $submitted_secret === '' ) {
                $sanitized['turnstile_secret_key_encrypted'] = $existing_encrypted;
            } elseif ( $submitted_secret === '__wh4u_keep__' ) {
                $sanitized['turnstile_secret_key_encrypted'] = $existing_encrypted;
            } else {
                $sanitized['turnstile_secret_key_encrypted'] = WH4U_Encryption::encrypt( $submitted_secret );
            }
        } else {
            $sanitized['turnstile_secret_key_encrypted'] = $existing_encrypted;
        }

        return $sanitized;
    }

    /**
     * Sanitize cart URL template (allows {domain}, {sld}, {tld} placeholders).
     *
     * @param string $input Raw template.
     * @return string
     */
    private static function sanitize_cart_template( $input ) {
        $input = is_string( $input ) ? trim( $input ) : '';
        if ( $input === '' ) {
            return '';
        }
        $input = sanitize_text_field( $input );
        if ( strpos( $input, 'https://' ) !== 0 ) {
            return '';
        }
        // Allow only URL-safe characters and literal {domain}, {sld}, {tld}.
        if ( preg_match( '/^https:\/\/[a-zA-Z0-9._\-~:\/\?\#\[\]@!$&\'()*+,;=%{}]+$/', $input ) !== 1 ) {
            return '';
        }
        return $input;
    }

    /** @return void */
    public static function render_api_section() {
        echo '<p>' . esc_html__( 'Configure the DomainsReseller API connection and registration behavior.', 'wh4u-domains' ) . '</p>';
    }

    /** @return void */
    public static function render_api_base_url_field() {
        $settings = get_option( 'wh4u_settings', array() );
        $value    = isset( $settings['api_base_url'] ) ? $settings['api_base_url'] : '';
        ?>
        <input type="url" name="wh4u_settings[api_base_url]"
               value="<?php echo esc_attr( $value ); ?>"
               class="regular-text" required />
        <p class="description">
            <?php esc_html_e( 'The base URL for the DomainsReseller API.', 'wh4u-domains' ); ?>
        </p>
        <?php
    }

    /** @return void */
    public static function render_mode_field() {
        $settings = get_option( 'wh4u_settings', array() );
        $mode     = isset( $settings['mode'] ) ? $settings['mode'] : 'realtime';
        ?>
        <fieldset>
            <label>
                <input type="radio" name="wh4u_settings[mode]" value="realtime"
                    <?php checked( $mode, 'realtime' ); ?> />
                <?php esc_html_e( 'Real-time Registration', 'wh4u-domains' ); ?>
                <span class="description"><?php esc_html_e( '(API call happens immediately on order submission)', 'wh4u-domains' ); ?></span>
            </label><br />
            <label>
                <input type="radio" name="wh4u_settings[mode]" value="notification"
                    <?php checked( $mode, 'notification' ); ?> />
                <?php esc_html_e( 'Notification-only', 'wh4u-domains' ); ?>
                <span class="description"><?php esc_html_e( '(Store order and notify reseller; process manually via "Process Now")', 'wh4u-domains' ); ?></span>
            </label>
        </fieldset>
        <?php
    }

    /** @return void */
    public static function render_cart_section() {
        echo '<p>' . esc_html__( 'Optional: Send customers to your billing cart (WHMCS, Blesta, ClientExec, Upmind, or custom URL) when they click Register or Transfer. Leave Cart Type empty to use the built-in registration form.', 'wh4u-domains' ) . '</p>';
    }

    /** @return void */
    public static function render_cart_type_field() {
        $settings  = get_option( 'wh4u_settings', array() );
        $cart_type = isset( $settings['cart_type'] ) ? $settings['cart_type'] : '';
        $types     = array(
            ''            => __( 'None (use built-in form)', 'wh4u-domains' ),
            'whmcs'       => 'WHMCS',
            'blesta'      => 'Blesta',
            'clientexec'  => 'ClientExec',
            'upmind'      => 'Upmind',
            'custom'      => __( 'Custom URL template', 'wh4u-domains' ),
        );
        ?>
        <select name="wh4u_settings[cart_type]" id="wh4u_cart_type">
            <?php foreach ( $types as $value => $label ) : ?>
                <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $cart_type, $value ); ?>><?php echo esc_html( $label ); ?></option>
            <?php endforeach; ?>
        </select>
        <p class="description"><?php esc_html_e( 'When set, clicking Register or Transfer will redirect the customer to your cart with the domain pre-filled.', 'wh4u-domains' ); ?></p>
        <?php
    }

    /** @return void */
    public static function render_cart_base_url_field() {
        $settings = get_option( 'wh4u_settings', array() );
        $value    = isset( $settings['cart_base_url'] ) ? $settings['cart_base_url'] : '';
        ?>
        <input type="url" name="wh4u_settings[cart_base_url]" id="wh4u_cart_base_url"
               value="<?php echo esc_attr( $value ); ?>"
               class="regular-text" placeholder="https://billing.example.com" />
        <p class="description"><?php esc_html_e( 'Base URL of your cart (no trailing slash). Used for WHMCS, Blesta, ClientExec, and Upmind.', 'wh4u-domains' ); ?></p>
        <?php
    }

    /** @return void */
    public static function render_cart_register_url_field() {
        $settings = get_option( 'wh4u_settings', array() );
        $value    = isset( $settings['cart_register_url'] ) ? $settings['cart_register_url'] : '';
        ?>
        <input type="url" name="wh4u_settings[cart_register_url]" id="wh4u_cart_register_url"
               value="<?php echo esc_attr( $value ); ?>"
               class="large-text" placeholder="https://billing.example.com/cart.php?a=add&domain=register&sld={sld}&tld={tld}" />
        <p class="description"><?php esc_html_e( 'Only for Custom cart type. Use {domain}, {sld}, or {tld} as placeholders.', 'wh4u-domains' ); ?></p>
        <?php
    }

    /** @return void */
    public static function render_cart_transfer_url_field() {
        $settings = get_option( 'wh4u_settings', array() );
        $value    = isset( $settings['cart_transfer_url'] ) ? $settings['cart_transfer_url'] : '';
        ?>
        <input type="url" name="wh4u_settings[cart_transfer_url]" id="wh4u_cart_transfer_url"
               value="<?php echo esc_attr( $value ); ?>"
               class="large-text" placeholder="https://billing.example.com/cart.php?a=add&domain=transfer&sld={sld}&tld={tld}" />
        <p class="description"><?php esc_html_e( 'Only for Custom cart type. Use {domain}, {sld}, or {tld} as placeholders.', 'wh4u-domains' ); ?></p>
        <?php
    }

    /* ─── Reverse Proxy ────────────────────────────────────────────── */

    /** @return void */
    public static function render_proxy_section() {
        echo '<p>' . esc_html__( 'If your site is behind a reverse proxy (Cloudflare, a load balancer, or nginx), configure which header carries the real client IP for rate-limit purposes. Only enable this when the proxy strips client-supplied values for this header — otherwise rate limits can be bypassed via spoofing.', 'wh4u-domains' ) . '</p>';
    }

    /** @return void */
    public static function render_trusted_proxy_header_field() {
        $settings = get_option( 'wh4u_settings', array() );
        $selected = isset( $settings['trusted_proxy_header'] ) ? $settings['trusted_proxy_header'] : '';
        $options  = array(
            ''                  => __( 'Disabled (use REMOTE_ADDR only)', 'wh4u-domains' ),
            'cf-connecting-ip'  => 'CF-Connecting-IP (Cloudflare)',
            'x-real-ip'         => 'X-Real-IP (nginx)',
            'x-forwarded-for'   => 'X-Forwarded-For (leftmost)',
            'true-client-ip'    => 'True-Client-IP (Akamai/Cloudflare)',
        );
        ?>
        <select name="wh4u_settings[trusted_proxy_header]">
            <?php foreach ( $options as $value => $label ) : ?>
                <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $selected, $value ); ?>><?php echo esc_html( $label ); ?></option>
            <?php endforeach; ?>
        </select>
        <?php
    }

    /** @return void */
    public static function render_trusted_proxies_field() {
        $settings = get_option( 'wh4u_settings', array() );
        $proxies  = isset( $settings['trusted_proxies'] ) ? (array) $settings['trusted_proxies'] : array();
        ?>
        <textarea name="wh4u_settings[trusted_proxies]" rows="3" class="regular-text" placeholder="173.245.48.1&#10;103.21.244.0"><?php echo esc_textarea( implode( "\n", $proxies ) ); ?></textarea>
        <p class="description"><?php esc_html_e( 'One IP per line (no CIDR). The header above is only consulted when the immediate connection comes from one of these IPs.', 'wh4u-domains' ); ?></p>
        <?php
    }

    /* ─── Public Lookup ────────────────────────────────────────────── */

    /** @return void */
    public static function render_public_lookup_section() {
        echo '<p>' . esc_html__( 'Choose which reseller\'s API credentials are used when anonymous visitors perform a domain lookup from the frontend. If no reseller is designated, anonymous domain searches are disabled.', 'wh4u-domains' ) . '</p>';
    }

    /** @return void */
    public static function render_public_lookup_reseller_field() {
        $settings = get_option( 'wh4u_settings', array() );
        $selected = isset( $settings['public_lookup_reseller_id'] ) ? (int) $settings['public_lookup_reseller_id'] : 0;

        global $wpdb;
        $table = $wpdb->prefix . 'wh4u_reseller_settings';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $table from $wpdb->prefix, no user input
        $rows = $wpdb->get_results( "SELECT user_id FROM {$table} WHERE api_key_encrypted != ''" );
        ?>
        <select name="wh4u_settings[public_lookup_reseller_id]">
            <option value="0"><?php esc_html_e( 'Disabled (anonymous lookups will be rejected)', 'wh4u-domains' ); ?></option>
            <?php foreach ( (array) $rows as $row ) :
                $user = get_userdata( (int) $row->user_id );
                if ( ! $user ) { continue; }
                ?>
                <option value="<?php echo esc_attr( (int) $row->user_id ); ?>" <?php selected( $selected, (int) $row->user_id ); ?>>
                    <?php echo esc_html( $user->display_name . ' (' . $user->user_login . ')' ); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <p class="description"><?php esc_html_e( 'API calls made for anonymous visitors will be billed against this reseller\'s account.', 'wh4u-domains' ); ?></p>
        <?php
    }

    /**
     * Get the user ID designated to handle anonymous public lookups.
     *
     * Returns 0 if not configured — callers must treat this as "anonymous lookups disabled".
     *
     * @return int
     */
    public static function get_public_lookup_reseller_id() {
        $settings = get_option( 'wh4u_settings', array() );
        return isset( $settings['public_lookup_reseller_id'] ) ? (int) $settings['public_lookup_reseller_id'] : 0;
    }

    /* ─── Turnstile ──────────────────────────────────────────────── */

    /** @return void */
    public static function render_turnstile_section() {
        $link = '<a href="https://dash.cloudflare.com/?to=/:account/turnstile" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Cloudflare dashboard', 'wh4u-domains' ) . '</a>';
        echo '<p>';
        echo wp_kses(
            sprintf(
                /* translators: %s: link to Cloudflare Turnstile dashboard */
                __( 'Optional: protect public registration and transfer forms with Cloudflare Turnstile (free). Get your keys from the %s.', 'wh4u-domains' ),
                $link
            ),
            array( 'a' => array( 'href' => array(), 'target' => array(), 'rel' => array() ) )
        );
        echo '</p>';
    }

    /** @return void */
    public static function render_turnstile_site_key_field() {
        $settings = get_option( 'wh4u_settings', array() );
        $value    = isset( $settings['turnstile_site_key'] ) ? $settings['turnstile_site_key'] : '';
        ?>
        <input type="text" name="wh4u_settings[turnstile_site_key]"
               value="<?php echo esc_attr( $value ); ?>"
               class="regular-text" placeholder="0x..." autocomplete="off" />
        <?php
    }

    /** @return void */
    public static function render_turnstile_secret_key_field() {
        $settings = get_option( 'wh4u_settings', array() );
        $has_secret = ! empty( $settings['turnstile_secret_key_encrypted'] ) || ! empty( $settings['turnstile_secret_key'] );
        $placeholder = $has_secret ? __( '●●●●●●●● (stored, encrypted)', 'wh4u-domains' ) : '0x...';
        ?>
        <input type="password" name="wh4u_settings[turnstile_secret_key]"
               value=""
               class="regular-text" placeholder="<?php echo esc_attr( $placeholder ); ?>" autocomplete="off" />
        <p class="description">
            <?php if ( $has_secret ) : ?>
                <?php esc_html_e( 'A secret key is stored. Leave blank to keep it, or enter a new value to replace it.', 'wh4u-domains' ); ?>
            <?php else : ?>
                <?php esc_html_e( 'Leave both fields empty to disable Turnstile protection.', 'wh4u-domains' ); ?>
            <?php endif; ?>
        </p>
        <?php
    }

    /**
     * Check whether Turnstile is configured.
     *
     * @return bool
     */
    public static function is_turnstile_enabled() {
        $settings = get_option( 'wh4u_settings', array() );
        $has_secret = ! empty( $settings['turnstile_secret_key_encrypted'] ) || ! empty( $settings['turnstile_secret_key'] );
        return ! empty( $settings['turnstile_site_key'] ) && $has_secret;
    }

    /**
     * Get the Turnstile site key.
     *
     * @return string
     */
    public static function get_turnstile_site_key() {
        $settings = get_option( 'wh4u_settings', array() );
        return isset( $settings['turnstile_site_key'] ) ? $settings['turnstile_site_key'] : '';
    }

    /**
     * Get the Turnstile secret key (decrypted from encrypted storage).
     *
     * Falls back to legacy plaintext field for installs predating encryption.
     *
     * @return string
     */
    public static function get_turnstile_secret_key() {
        $settings = get_option( 'wh4u_settings', array() );

        if ( ! empty( $settings['turnstile_secret_key_encrypted'] ) ) {
            $decrypted = WH4U_Encryption::decrypt( $settings['turnstile_secret_key_encrypted'] );
            if ( $decrypted !== '' ) {
                return $decrypted;
            }
        }

        return isset( $settings['turnstile_secret_key'] ) ? $settings['turnstile_secret_key'] : '';
    }

    /**
     * Verify a Turnstile response token server-side.
     *
     * @param string $token The cf-turnstile-response token from the client.
     * @return bool True if valid.
     */
    public static function verify_turnstile_token( $token ) {
        if ( ! self::is_turnstile_enabled() ) {
            return true;
        }

        if ( empty( $token ) ) {
            return false;
        }

        $response = wp_remote_post( 'https://challenges.cloudflare.com/turnstile/v0/siteverify', array(
            'body' => array(
                'secret'   => self::get_turnstile_secret_key(),
                'response' => $token,
                'remoteip' => isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '',
            ),
            'timeout' => 10,
        ) );

        if ( is_wp_error( $response ) ) {
            return false;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        return is_array( $body ) && ! empty( $body['success'] );
    }

    /* ─── Page Rendering ──────────────────────────────────────────── */

    /**
     * Render the unified settings page.
     *
     * @return void
     */
    public static function render_page() {
        if ( ! current_user_can( 'wh4u_manage_domains' ) ) {
            wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'wh4u-domains' ) );
        }

        $is_admin = current_user_can( 'manage_options' );
        $active   = self::get_active_tab( $is_admin );

        $reseller_saved = false;
        $reseller_error = '';

        if ( 'credentials' === $active && isset( $_POST['wh4u_reseller_save'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce checked on next line
            if ( ! check_admin_referer( 'wh4u_reseller_settings_nonce', '_wh4u_nonce' ) ) {
                wp_die( esc_html__( 'Security check failed.', 'wh4u-domains' ) );
            }
            $result = WH4U_Admin_Reseller::save_reseller_settings( get_current_user_id() );
            if ( is_wp_error( $result ) ) {
                $reseller_error = $result->get_error_message();
            } else {
                $reseller_saved = true;
            }
        }
        ?>
        <div class="wrap wh4u-admin-wrap">
            <h1><?php esc_html_e( 'Settings', 'wh4u-domains' ); ?></h1>

            <nav class="nav-tab-wrapper wh4u-settings-tabs">
                <?php if ( $is_admin ) : ?>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=wh4u-domains-settings&tab=general' ) ); ?>"
                   class="nav-tab <?php echo esc_attr( 'general' === $active ? 'nav-tab-active' : '' ); ?>">
                    <?php esc_html_e( 'General', 'wh4u-domains' ); ?>
                </a>
                <?php endif; ?>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=wh4u-domains-settings&tab=credentials' ) ); ?>"
                   class="nav-tab <?php echo esc_attr( 'credentials' === $active ? 'nav-tab-active' : '' ); ?>">
                    <?php esc_html_e( 'Credentials', 'wh4u-domains' ); ?>
                </a>
                <?php if ( $is_admin ) : ?>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=wh4u-domains-settings&tab=appearance' ) ); ?>"
                   class="nav-tab <?php echo esc_attr( 'appearance' === $active ? 'nav-tab-active' : '' ); ?>">
                    <?php esc_html_e( 'Appearance', 'wh4u-domains' ); ?>
                </a>
                <?php endif; ?>
            </nav>

            <?php
            if ( 'appearance' === $active && $is_admin ) {
                WH4U_Admin_Appearance::render_tab();
            } elseif ( 'general' === $active && $is_admin ) {
                self::render_general_tab();
            } else {
                WH4U_Admin_Reseller::render_credentials_tab( $reseller_saved, $reseller_error );
            }
            ?>
        </div>
        <?php
    }

    /**
     * Determine which tab is active.
     *
     * @param bool $is_admin Whether the user has manage_options.
     * @return string Tab key.
     */
    private static function get_active_tab( $is_admin ) {
        $tab = '';
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only tab navigation
        if ( isset( $_GET['tab'] ) ) {
            $tab = sanitize_text_field( wp_unslash( $_GET['tab'] ) );
        // phpcs:enable WordPress.Security.NonceVerification.Recommended
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- tab from form hidden field, nonce checked in render_page
        } elseif ( isset( $_POST['tab'] ) ) {
            $tab = sanitize_text_field( wp_unslash( $_POST['tab'] ) );
        }
        // phpcs:enable WordPress.Security.NonceVerification.Missing

        if ( 'general' === $tab && $is_admin ) {
            return 'general';
        }
        if ( 'appearance' === $tab && $is_admin ) {
            return 'appearance';
        }
        if ( 'credentials' === $tab ) {
            return 'credentials';
        }

        return $is_admin ? 'general' : 'credentials';
    }

    /* ─── General Tab ─────────────────────────────────────────────── */

    /**
     * Render the General tab (WP Settings API form).
     *
     * @return void
     */
    private static function render_general_tab() {
        ?>
        <form method="post" action="options.php" class="wh4u-settings-form">
            <?php
            settings_fields( 'wh4u_settings_group' );
            do_settings_sections( 'wh4u-domains-settings' );
            submit_button();
            ?>
        </form>
        <?php
    }

}
