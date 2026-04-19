<?php
/**
 * Plugin dashboard page -- simple, direct, no metaboxes.
 *
 * Layout:
 *  1. Status bar (full-width) -- credits + API status badge.
 *  2. Two columns:
 *     Left  -- Domain search + Quick links.
 *     Right -- Connection information.
 *
 * @package WH4U_Domains
 * @license GPL-2.0-or-later
 * @link    https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WH4U_Admin_Dashboard {

    private static $screen_id = '';

    public static function set_screen_id( $hook_suffix ) {
        self::$screen_id = $hook_suffix;
    }

    public static function on_load() {}

    public static function render_page() {
        if ( ! current_user_can( 'wh4u_manage_domains' ) ) {
            wp_die( esc_html__( 'You do not have sufficient permissions.', 'wh4u-domains' ) );
        }

        $api = self::check_api_status();
        $ok  = $api['connected'];

        $user        = wp_get_current_user();
        $global_opts = get_option( 'wh4u_settings', array() );
        $base_url    = isset( $global_opts['api_base_url'] ) ? $global_opts['api_base_url'] : '';

        global $wpdb;
        $table    = $wpdb->prefix . 'wh4u_reseller_settings';
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $table from $wpdb->prefix
        $settings = $wpdb->get_row( $wpdb->prepare(
            "SELECT reseller_email FROM {$table} WHERE user_id = %d",
            $user->ID
        ) );
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $reseller_email = ( $settings && ! empty( $settings->reseller_email ) ) ? $settings->reseller_email : '';
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Dashboard', 'wh4u-domains' ); ?></h1>

            <?php /* ── Status Bar ───────────────────── */ ?>

            <div class="wh4u-dash-status">
                <div class="wh4u-dash-status-item">
                    <?php if ( $ok ) : ?>
                        <span class="dashicons dashicons-yes-alt" style="color:#46b450;"></span>
                        <strong><?php esc_html_e( 'API Connected', 'wh4u-domains' ); ?></strong>
                        <?php
                        $checks = array(
                            'base_url'       => __( 'URL', 'wh4u-domains' ),
                            'reseller_email' => __( 'Email', 'wh4u-domains' ),
                            'api_key'        => __( 'Key', 'wh4u-domains' ),
                        );
                        foreach ( $checks as $key => $lbl ) {
                            if ( ! isset( $api['details'][ $key ] ) ) {
                                continue;
                            }
                            $pass = $api['details'][ $key ];
                            printf(
                                '<span class="wh4u-dash-check"><span class="dashicons %s" style="color:%s;"></span>%s</span>',
                                esc_attr( $pass ? 'dashicons-yes' : 'dashicons-no' ),
                                esc_attr( $pass ? '#46b450' : '#dc3232' ),
                                esc_html( $lbl )
                            );
                        }
                        ?>
                    <?php else : ?>
                        <span class="dashicons dashicons-warning" style="color:#dba617;"></span>
                        <strong><?php esc_html_e( 'API Not Connected', 'wh4u-domains' ); ?></strong>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=wh4u-domains-settings&tab=credentials' ) ); ?>" class="button button-small">
                            <?php esc_html_e( 'Configure', 'wh4u-domains' ); ?>
                        </a>
                    <?php endif; ?>
                </div>

                <div class="wh4u-dash-status-item">
                    <span class="dashicons dashicons-money-alt" style="color:#646970;"></span>
                    <strong><?php esc_html_e( 'Credits:', 'wh4u-domains' ); ?></strong>
                    <span id="wh4u-dash-credits-loading">
                        <span class="spinner is-active" style="float:none;margin:0;"></span>
                    </span>
                    <span id="wh4u-dash-credits-error" style="display:none;color:#dc3232;font-weight:600;"></span>
                    <span id="wh4u-dash-credits-amount" style="display:none;font-weight:700;color:#2271b1;font-size:15px;"></span>
                    <a href="https://webhosting4u.gr/customers/clientarea.php?action=addfunds"
                       class="button button-primary button-small"
                       target="_blank" rel="noopener noreferrer">
                        <?php esc_html_e( 'Add Credits', 'wh4u-domains' ); ?>
                    </a>
                </div>
            </div>

            <?php /* ── Two-Column Layout ────────────── */ ?>

            <div class="wh4u-dash-columns">

                <div class="wh4u-dash-col-main">

                    <?php /* ── Domain Search ────────── */ ?>
                    <div class="card wh4u-dash-card">
                        <h2><?php esc_html_e( 'Search Domain Availability', 'wh4u-domains' ); ?></h2>
                        <form id="wh4u-domain-search-form">
                            <?php wp_nonce_field( 'wp_rest', '_wh4u_rest_nonce' ); ?>
                            <div class="wh4u-dash-search-row">
                                <label for="wh4u-search-term" class="screen-reader-text">
                                    <?php esc_html_e( 'Domain Name', 'wh4u-domains' ); ?>
                                </label>
                                <input type="text" id="wh4u-search-term" name="searchTerm"
                                       class="regular-text"
                                       placeholder="<?php esc_attr_e( 'example.com or keyword', 'wh4u-domains' ); ?>"
                                       required />
                                <button type="submit" class="button button-primary" id="wh4u-search-btn">
                                    <?php esc_html_e( 'Check Availability', 'wh4u-domains' ); ?>
                                </button>
                                <button type="button" class="button" id="wh4u-suggestions-btn">
                                    <?php esc_html_e( 'Suggestions', 'wh4u-domains' ); ?>
                                </button>
                            </div>
                        </form>

                        <div id="wh4u-search-loading" style="display:none;padding:12px 0;">
                            <span class="spinner is-active" style="float:none;margin:0 5px 0 0;"></span>
                            <span><?php esc_html_e( 'Searching...', 'wh4u-domains' ); ?></span>
                        </div>
                        <div id="wh4u-search-error" class="notice notice-error inline" style="display:none;">
                            <p></p>
                        </div>
                        <div id="wh4u-search-results" style="display:none;margin-top:12px;">
                            <table class="wp-list-table widefat fixed striped" id="wh4u-results-table">
                                <thead>
                                    <tr>
                                        <th><?php esc_html_e( 'Domain', 'wh4u-domains' ); ?></th>
                                        <th><?php esc_html_e( 'Status', 'wh4u-domains' ); ?></th>
                                        <th><?php esc_html_e( 'Action', 'wh4u-domains' ); ?></th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>

                    <?php /* ── Quick Links ──────────── */ ?>
                    <div class="wh4u-dash-links">
                        <?php
                        $links = array(
                            array( 'page' => 'wh4u-domains-register', 'icon' => 'dashicons-plus-alt',      'label' => __( 'Register Domain', 'wh4u-domains' ), 'external' => false ),
                            array( 'page' => 'wh4u-domains-transfer', 'icon' => 'dashicons-migrate',        'label' => __( 'Transfer Domain', 'wh4u-domains' ), 'external' => false ),
                            array( 'url'  => 'https://webhosting4u.gr/customers/index.php?m=DomainsReseller&mg-page=Prices', 'icon' => 'dashicons-tag', 'label' => __( 'TLD Pricing', 'wh4u-domains' ), 'external' => true ),
                            array( 'page' => 'wh4u-domains-history',  'icon' => 'dashicons-list-view',      'label' => __( 'Order History', 'wh4u-domains' ), 'external' => false ),
                            array( 'page' => 'wh4u-domains-settings', 'icon' => 'dashicons-admin-generic',  'label' => __( 'Settings', 'wh4u-domains' ), 'external' => false ),
                        );
                        foreach ( $links as $link ) :
                            $href       = ! empty( $link['external'] ) ? $link['url'] : admin_url( 'admin.php?page=' . $link['page'] );
                            $target_rel = ! empty( $link['external'] ) ? ' target="_blank" rel="noopener noreferrer"' : '';
                            ?>
                            <a href="<?php echo esc_url( $href ); ?>" class="wh4u-dash-link"<?php echo $target_rel; ?>>
                                <span class="dashicons <?php echo esc_attr( $link['icon'] ); ?>"></span>
                                <?php echo esc_html( $link['label'] ); ?>
                                <?php if ( ! empty( $link['external'] ) ) : ?>
                                    <span class="dashicons dashicons-external" style="font-size:14px;width:14px;height:14px;vertical-align:middle;opacity:0.7;"></span>
                                <?php endif; ?>
                            </a>
                        <?php endforeach; ?>
                    </div>

                    <?php /* ── Frontend Integration ── */ ?>
                    <div class="card wh4u-dash-card wh4u-dash-card--shortcodes">
                        <h2>
                            <span class="dashicons dashicons-shortcode" style="font-size:20px;width:20px;height:20px;margin-right:6px;vertical-align:text-bottom;color:#2271b1;"></span>
                            <?php esc_html_e( 'Frontend Integration', 'wh4u-domains' ); ?>
                        </h2>
                        <p class="description" style="margin-top:0;">
                            <?php esc_html_e( 'Add a domain search form to any page or post using the shortcode or the Gutenberg block. Shortcode attributes override global Appearance settings.', 'wh4u-domains' ); ?>
                        </p>

                        <h3><?php esc_html_e( 'Basic Usage', 'wh4u-domains' ); ?></h3>
                        <code class="wh4u-shortcode-display">[wh4u_domain_lookup]</code>
                        <p class="description"><?php esc_html_e( 'Outputs the domain search form with all global Appearance settings applied.', 'wh4u-domains' ); ?></p>

                        <h3><?php esc_html_e( 'Available Shortcode Attributes', 'wh4u-domains' ); ?></h3>
                        <table class="widefat striped wh4u-shortcode-ref">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e( 'Attribute', 'wh4u-domains' ); ?></th>
                                    <th><?php esc_html_e( 'Default', 'wh4u-domains' ); ?></th>
                                    <th><?php esc_html_e( 'Description', 'wh4u-domains' ); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><code>placeholder</code></td>
                                    <td><em><?php esc_html_e( 'Search for your perfect domain...', 'wh4u-domains' ); ?></em></td>
                                    <td><?php esc_html_e( 'Placeholder text inside the search input field.', 'wh4u-domains' ); ?></td>
                                </tr>
                                <tr>
                                    <td><code>button_text</code></td>
                                    <td><em><?php esc_html_e( 'Search', 'wh4u-domains' ); ?></em></td>
                                    <td><?php esc_html_e( 'Label for the search submit button.', 'wh4u-domains' ); ?></td>
                                </tr>
                                <tr>
                                    <td><code>accent_color</code></td>
                                    <td><em><?php esc_html_e( '(theme default)', 'wh4u-domains' ); ?></em></td>
                                    <td><?php esc_html_e( 'Hex color for buttons and accents, e.g. #2271b1.', 'wh4u-domains' ); ?></td>
                                </tr>
                                <tr>
                                    <td><code>style_variant</code></td>
                                    <td><code>elevated</code></td>
                                    <td><?php
                                        printf(
                                            /* translators: %s: list of allowed values */
                                            esc_html__( 'Visual style of the form card. Values: %s.', 'wh4u-domains' ),
                                            '<code>elevated</code>, <code>flat</code>, <code>bordered</code>, <code>minimal</code>'
                                        );
                                    ?></td>
                                </tr>
                                <tr>
                                    <td><code>show_suggestions</code></td>
                                    <td><code>true</code></td>
                                    <td><?php esc_html_e( 'Show alternative TLD suggestions when a domain is unavailable. Values: true / false.', 'wh4u-domains' ); ?></td>
                                </tr>
                                <tr>
                                    <td><code>form_title</code></td>
                                    <td><em><?php esc_html_e( 'Register this domain', 'wh4u-domains' ); ?></em></td>
                                    <td><?php esc_html_e( 'Heading displayed above the registration form.', 'wh4u-domains' ); ?></td>
                                </tr>
                                <tr>
                                    <td><code>form_description</code></td>
                                    <td><em><?php esc_html_e( 'Fill in your details below to secure this domain.', 'wh4u-domains' ); ?></em></td>
                                    <td><?php esc_html_e( 'Description text shown below the form title.', 'wh4u-domains' ); ?></td>
                                </tr>
                                <tr>
                                    <td><code>border_radius</code></td>
                                    <td><code>12</code></td>
                                    <td><?php esc_html_e( 'Corner rounding in pixels (0 = sharp corners, max 32).', 'wh4u-domains' ); ?></td>
                                </tr>
                            </tbody>
                        </table>

                        <h3><?php esc_html_e( 'Examples', 'wh4u-domains' ); ?></h3>

                        <h4 style="margin:8px 0 4px;"><?php esc_html_e( 'Custom text and colors', 'wh4u-domains' ); ?></h4>
                        <code class="wh4u-shortcode-display">[wh4u_domain_lookup placeholder="<?php esc_attr_e( 'Find your domain', 'wh4u-domains' ); ?>" button_text="<?php esc_attr_e( 'Go', 'wh4u-domains' ); ?>" accent_color="#e91e63"]</code>

                        <h4 style="margin:12px 0 4px;"><?php esc_html_e( 'Flat style, sharp corners', 'wh4u-domains' ); ?></h4>
                        <code class="wh4u-shortcode-display">[wh4u_domain_lookup style_variant="flat" border_radius="0"]</code>

                        <h4 style="margin:12px 0 4px;"><?php esc_html_e( 'Minimal style, no suggestions', 'wh4u-domains' ); ?></h4>
                        <code class="wh4u-shortcode-display">[wh4u_domain_lookup style_variant="minimal" show_suggestions="false"]</code>

                        <h4 style="margin:12px 0 4px;"><?php esc_html_e( 'Custom form text', 'wh4u-domains' ); ?></h4>
                        <code class="wh4u-shortcode-display">[wh4u_domain_lookup form_title="<?php esc_attr_e( 'Secure your domain', 'wh4u-domains' ); ?>" form_description="<?php esc_attr_e( 'Complete the form to register.', 'wh4u-domains' ); ?>"]</code>

                        <h3><?php esc_html_e( 'Gutenberg Block', 'wh4u-domains' ); ?></h3>
                        <p class="description">
                            <?php esc_html_e( 'In the block editor, click the + inserter and search for "Domain Lookup". The block provides the same options as the shortcode attributes above, configurable via the block sidebar panel.', 'wh4u-domains' ); ?>
                        </p>

                        <h3><?php esc_html_e( 'Settings Priority', 'wh4u-domains' ); ?></h3>
                        <p class="description">
                            <?php esc_html_e( 'Shortcode/block attributes take highest priority. If an attribute is not specified, the value from Domains > Settings > Appearance is used. If no Appearance setting is saved, the built-in default applies.', 'wh4u-domains' ); ?>
                        </p>
                        <ol class="description" style="margin:4px 0 0 20px;font-style:italic;">
                            <li><?php esc_html_e( 'Shortcode / Block attribute (highest)', 'wh4u-domains' ); ?></li>
                            <li><?php esc_html_e( 'Global Appearance settings', 'wh4u-domains' ); ?></li>
                            <li><?php esc_html_e( 'Built-in defaults (lowest)', 'wh4u-domains' ); ?></li>
                        </ol>
                    </div>

                </div><!-- .wh4u-dash-col-main -->

                <div class="wh4u-dash-col-side">

                    <?php /* ── Connection Info ──────── */ ?>
                    <div class="card wh4u-dash-card">
                        <h2><?php esc_html_e( 'Connection Information', 'wh4u-domains' ); ?></h2>
                        <table class="widefat striped">
                            <tbody>
                                <tr>
                                    <td><strong><?php esc_html_e( 'Status', 'wh4u-domains' ); ?></strong></td>
                                    <td>
                                        <?php if ( $ok ) : ?>
                                            <span style="color:#46b450;font-weight:600;">
                                                <span class="dashicons dashicons-yes-alt" style="font-size:16px;width:16px;height:16px;vertical-align:text-bottom;"></span>
                                                <?php esc_html_e( 'Connected', 'wh4u-domains' ); ?>
                                            </span>
                                        <?php else : ?>
                                            <span style="color:#dba617;font-weight:600;">
                                                <span class="dashicons dashicons-warning" style="font-size:16px;width:16px;height:16px;vertical-align:text-bottom;"></span>
                                                <?php esc_html_e( 'Not Connected', 'wh4u-domains' ); ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td><strong><?php esc_html_e( 'WordPress User', 'wh4u-domains' ); ?></strong></td>
                                    <td><?php echo esc_html( $user->display_name ); ?></td>
                                </tr>
                                <tr>
                                    <td><strong><?php esc_html_e( 'Reseller Email', 'wh4u-domains' ); ?></strong></td>
                                    <td><?php echo $reseller_email ? esc_html( $reseller_email ) : '<em>' . esc_html__( 'Not configured', 'wh4u-domains' ) . '</em>'; ?></td>
                                </tr>
                                <?php if ( $base_url ) : ?>
                                <tr>
                                    <td><strong><?php esc_html_e( 'API Endpoint', 'wh4u-domains' ); ?></strong></td>
                                    <td><code style="font-size:11px;word-break:break-all;"><?php echo esc_html( $base_url ); ?></code></td>
                                </tr>
                                <?php endif; ?>
                                <?php if ( ! empty( $api['api_version'] ) ) : ?>
                                <tr>
                                    <td><strong><?php esc_html_e( 'API Version', 'wh4u-domains' ); ?></strong></td>
                                    <td><code><?php echo esc_html( $api['api_version'] ); ?></code></td>
                                </tr>
                                <?php endif; ?>
                                <?php
                                $checks_side = array(
                                    'base_url'       => __( 'API URL', 'wh4u-domains' ),
                                    'reseller_email' => __( 'Email', 'wh4u-domains' ),
                                    'api_key'        => __( 'API Key', 'wh4u-domains' ),
                                );
                                foreach ( $checks_side as $key => $lbl ) :
                                    if ( ! isset( $api['details'][ $key ] ) ) {
                                        continue;
                                    }
                                    $pass = $api['details'][ $key ];
                                    ?>
                                    <tr>
                                        <td><strong><?php echo esc_html( $lbl ); ?></strong></td>
                                        <td>
                                            <?php if ( $pass ) : ?>
                                                <span style="color:#46b450;"><span class="dashicons dashicons-yes" style="font-size:16px;width:16px;height:16px;vertical-align:text-bottom;"></span> <?php esc_html_e( 'OK', 'wh4u-domains' ); ?></span>
                                            <?php else : ?>
                                                <span style="color:#dc3232;"><span class="dashicons dashicons-no" style="font-size:16px;width:16px;height:16px;vertical-align:text-bottom;"></span> <?php esc_html_e( 'Missing', 'wh4u-domains' ); ?></span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <p style="margin-top:12px;">
                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=wh4u-domains-settings&tab=credentials' ) ); ?>" class="button">
                                <?php esc_html_e( 'Edit Settings', 'wh4u-domains' ); ?>
                            </a>
                            <a href="https://webhosting4u.gr/customers/" class="button" target="_blank" rel="noopener noreferrer">
                                <?php esc_html_e( 'Client Area', 'wh4u-domains' ); ?>
                            </a>
                        </p>
                    </div>

                </div><!-- .wh4u-dash-col-side -->

            </div><!-- .wh4u-dash-columns -->

        </div><!-- .wrap -->
        <?php
    }

    /* ── API Status Check ──────────────────────── */

    /**
     * @return array{connected: bool, message: string, details: array}
     */
    private static function check_api_status() {
        $user_id   = get_current_user_id();
        $cache_key = 'wh4u_api_status_u' . $user_id;

        $cached = get_transient( $cache_key );
        if ( is_array( $cached ) ) {
            return $cached;
        }

        $result = array(
            'connected' => false,
            'message'   => '',
            'details'   => array(),
        );

        $global_settings = get_option( 'wh4u_settings', array() );
        $base_url = isset( $global_settings['api_base_url'] ) ? $global_settings['api_base_url'] : '';

        if ( empty( $base_url ) ) {
            $result['message'] = __( 'API base URL is not configured.', 'wh4u-domains' );
            $result['details']['base_url'] = false;
            return $result;
        }

        $result['details']['base_url'] = true;

        global $wpdb;
        $table    = $wpdb->prefix . 'wh4u_reseller_settings';
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $table from $wpdb->prefix
        $settings = $wpdb->get_row( $wpdb->prepare(
            "SELECT reseller_email, api_key_encrypted FROM {$table} WHERE user_id = %d",
            $user_id
        ) );
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

        if ( ! $settings || empty( $settings->reseller_email ) ) {
            $result['message'] = __( 'Reseller email is not configured.', 'wh4u-domains' );
            $result['details']['reseller_email'] = false;
            $result['details']['api_key'] = false;
            return $result;
        }

        $result['details']['reseller_email'] = true;

        if ( empty( $settings->api_key_encrypted ) ) {
            $result['message'] = __( 'API key is not configured.', 'wh4u-domains' );
            $result['details']['api_key'] = false;
            return $result;
        }

        $result['details']['api_key'] = true;

        $client = WH4U_Api_Client::from_user( $user_id );
        if ( is_wp_error( $client ) ) {
            $result['message'] = $client->get_error_message();
            return $result;
        }

        $response = $client->get( '/tlds' );
        if ( is_wp_error( $response ) ) {
            /* translators: %s: error message returned by the API */
            $result['message'] = sprintf( __( 'API call failed: %s', 'wh4u-domains' ), $response->get_error_message() );
            return $result;
        }

        $result['connected'] = true;
        $result['message']   = __( 'Connected successfully.', 'wh4u-domains' );

        $version_response = $client->get( '/version' );
        if ( ! is_wp_error( $version_response ) ) {
            if ( is_string( $version_response ) ) {
                $result['api_version'] = $version_response;
            } elseif ( is_array( $version_response ) ) {
                $result['api_version'] = isset( $version_response['version'] )
                    ? $version_response['version']
                    : ( isset( $version_response['raw'] ) ? $version_response['raw'] : '' );
            }
        }

        set_transient( $cache_key, $result, 5 * MINUTE_IN_SECONDS );

        return $result;
    }
}
