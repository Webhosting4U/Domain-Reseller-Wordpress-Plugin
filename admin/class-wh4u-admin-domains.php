<?php
/**
 * Admin pages for domain search, register, and transfer.
 *
 * @package WH4U_Domains
 * @license GPL-2.0-or-later
 * @link    https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WH4U_Admin_Domains {

    /**
     * Render the domain search/availability page.
     *
     * @return void
     */
    public static function render_search_page() {
        if ( ! current_user_can( 'wh4u_manage_domains' ) ) {
            wp_die( esc_html__( 'You do not have sufficient permissions.', 'wh4u-domains' ) );
        }
        ?>
        <div class="wrap wh4u-admin-wrap">
            <h1><?php esc_html_e( 'Domain Search / Availability', 'wh4u-domains' ); ?></h1>
            <div class="wh4u-search-form">
                <form id="wh4u-domain-search-form">
                    <?php wp_nonce_field( 'wp_rest', '_wh4u_rest_nonce' ); ?>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><label for="wh4u-search-term"><?php esc_html_e( 'Domain Name', 'wh4u-domains' ); ?></label></th>
                            <td>
                                <input type="text" id="wh4u-search-term" name="searchTerm"
                                       class="regular-text" placeholder="example.com" required />
                                <p class="description"><?php esc_html_e( 'Enter a domain name with TLD (e.g. example.com) to check a specific extension, or without (e.g. example) to check all available extensions.', 'wh4u-domains' ); ?></p>
                            </td>
                        </tr>
                    </table>
                    <p class="submit">
                        <button type="submit" class="button button-primary" id="wh4u-search-btn">
                            <?php esc_html_e( 'Search Availability', 'wh4u-domains' ); ?>
                        </button>
                        <button type="button" class="button" id="wh4u-suggestions-btn">
                            <?php esc_html_e( 'Get Suggestions', 'wh4u-domains' ); ?>
                        </button>
                    </p>
                </form>
            </div>

            <div id="wh4u-search-results" class="wh4u-results-container" style="display:none;">
                <h2><?php esc_html_e( 'Search Results', 'wh4u-domains' ); ?></h2>
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

            <div id="wh4u-search-loading" style="display:none;">
                <span class="spinner is-active"></span>
                <span><?php esc_html_e( 'Searching...', 'wh4u-domains' ); ?></span>
            </div>

            <div id="wh4u-search-error" class="notice notice-error" style="display:none;">
                <p></p>
            </div>
        </div>
        <?php
    }

    /**
     * Render the domain registration page.
     *
     * @return void
     */
    public static function render_register_page() {
        if ( ! current_user_can( 'wh4u_manage_domains' ) ) {
            wp_die( esc_html__( 'You do not have sufficient permissions.', 'wh4u-domains' ) );
        }

        $user_id    = get_current_user_id();
        $reseller   = self::get_reseller_settings( $user_id );
        $ns_defaults = isset( $reseller['nameservers'] ) ? $reseller['nameservers'] : array();
        ?>
        <div class="wrap wh4u-admin-wrap">
            <h1><?php esc_html_e( 'Register Domain', 'wh4u-domains' ); ?></h1>

            <div id="wh4u-order-notice" style="display:none;"></div>

            <form id="wh4u-register-form" class="wh4u-order-form">
                <?php wp_nonce_field( 'wp_rest', '_wh4u_rest_nonce' ); ?>
                <input type="hidden" name="order_type" value="register" />

                <h2><?php esc_html_e( 'Domain Details', 'wh4u-domains' ); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="reg-domain"><?php esc_html_e( 'Domain', 'wh4u-domains' ); ?></label></th>
                        <td><input type="text" id="reg-domain" name="domain" class="regular-text" placeholder="example.com" required /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="reg-period"><?php esc_html_e( 'Registration Period', 'wh4u-domains' ); ?></label></th>
                        <td>
                            <select id="reg-period" name="regperiod">
                                <?php for ( $y = 1; $y <= 10; $y++ ) : ?>
                                    <option value="<?php echo esc_attr( $y ); ?>"><?php echo esc_html( $y . ' ' . _n( 'year', 'years', $y, 'wh4u-domains' ) ); ?></option>
                                <?php endfor; ?>
                            </select>
                        </td>
                    </tr>
                </table>

                <h2><?php esc_html_e( 'Nameservers', 'wh4u-domains' ); ?></h2>
                <table class="form-table">
                    <?php for ( $i = 1; $i <= 5; $i++ ) :
                        $ns_key = 'ns' . $i;
                        $ns_val = isset( $ns_defaults[ $ns_key ] ) ? $ns_defaults[ $ns_key ] : '';
                    ?>
                    <tr>
                        <th scope="row"><label for="reg-ns<?php echo esc_attr( $i ); ?>">NS<?php echo esc_html( $i ); ?></label></th>
                        <td><input type="text" id="reg-ns<?php echo esc_attr( $i ); ?>" name="nameservers[ns<?php echo esc_attr( $i ); ?>]" class="regular-text" value="<?php echo esc_attr( $ns_val ); ?>" <?php echo esc_attr( $i <= 2 ? 'required' : '' ); ?> /></td>
                    </tr>
                    <?php endfor; ?>
                </table>

                <?php self::render_contact_fields( 'register' ); ?>

                <h2><?php esc_html_e( 'Addons', 'wh4u-domains' ); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e( 'DNS Management', 'wh4u-domains' ); ?></th>
                        <td><input type="checkbox" name="addons[dnsmanagement]" value="1" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Email Forwarding', 'wh4u-domains' ); ?></th>
                        <td><input type="checkbox" name="addons[emailforwarding]" value="1" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'ID Protection', 'wh4u-domains' ); ?></th>
                        <td><input type="checkbox" name="addons[idprotection]" value="1" /></td>
                    </tr>
                </table>

                <p class="submit">
                    <button type="submit" class="button button-primary" id="wh4u-register-btn">
                        <?php esc_html_e( 'Register Domain', 'wh4u-domains' ); ?>
                    </button>
                </p>
            </form>
        </div>
        <?php
    }

    /**
     * Render the domain transfer page.
     *
     * @return void
     */
    public static function render_transfer_page() {
        if ( ! current_user_can( 'wh4u_manage_domains' ) ) {
            wp_die( esc_html__( 'You do not have sufficient permissions.', 'wh4u-domains' ) );
        }

        $user_id     = get_current_user_id();
        $reseller    = self::get_reseller_settings( $user_id );
        $ns_defaults = isset( $reseller['nameservers'] ) ? $reseller['nameservers'] : array();
        ?>
        <div class="wrap wh4u-admin-wrap">
            <h1><?php esc_html_e( 'Transfer Domain', 'wh4u-domains' ); ?></h1>

            <div id="wh4u-order-notice" style="display:none;"></div>

            <form id="wh4u-transfer-form" class="wh4u-order-form">
                <?php wp_nonce_field( 'wp_rest', '_wh4u_rest_nonce' ); ?>
                <input type="hidden" name="order_type" value="transfer" />

                <h2><?php esc_html_e( 'Domain Details', 'wh4u-domains' ); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="transfer-domain"><?php esc_html_e( 'Domain', 'wh4u-domains' ); ?></label></th>
                        <td><input type="text" id="transfer-domain" name="domain" class="regular-text" placeholder="example.com" required /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="transfer-eppcode"><?php esc_html_e( 'EPP / Auth Code', 'wh4u-domains' ); ?></label></th>
                        <td><input type="text" id="transfer-eppcode" name="eppcode" class="regular-text" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="transfer-period"><?php esc_html_e( 'Transfer Period', 'wh4u-domains' ); ?></label></th>
                        <td>
                            <select id="transfer-period" name="regperiod">
                                <?php for ( $y = 1; $y <= 10; $y++ ) : ?>
                                    <option value="<?php echo esc_attr( $y ); ?>"><?php echo esc_html( $y . ' ' . _n( 'year', 'years', $y, 'wh4u-domains' ) ); ?></option>
                                <?php endfor; ?>
                            </select>
                        </td>
                    </tr>
                </table>

                <h2><?php esc_html_e( 'Nameservers', 'wh4u-domains' ); ?></h2>
                <table class="form-table">
                    <?php for ( $i = 1; $i <= 5; $i++ ) :
                        $ns_key = 'ns' . $i;
                        $ns_val = isset( $ns_defaults[ $ns_key ] ) ? $ns_defaults[ $ns_key ] : '';
                    ?>
                    <tr>
                        <th scope="row"><label>NS<?php echo esc_html( $i ); ?></label></th>
                        <td><input type="text" name="nameservers[ns<?php echo esc_attr( $i ); ?>]" class="regular-text" value="<?php echo esc_attr( $ns_val ); ?>" <?php echo esc_attr( $i <= 2 ? 'required' : '' ); ?> /></td>
                    </tr>
                    <?php endfor; ?>
                </table>

                <?php self::render_contact_fields( 'transfer' ); ?>

                <h2><?php esc_html_e( 'Addons', 'wh4u-domains' ); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e( 'DNS Management', 'wh4u-domains' ); ?></th>
                        <td><input type="checkbox" name="addons[dnsmanagement]" value="1" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Email Forwarding', 'wh4u-domains' ); ?></th>
                        <td><input type="checkbox" name="addons[emailforwarding]" value="1" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'ID Protection', 'wh4u-domains' ); ?></th>
                        <td><input type="checkbox" name="addons[idprotection]" value="1" /></td>
                    </tr>
                </table>

                <p class="submit">
                    <button type="submit" class="button button-primary" id="wh4u-transfer-btn">
                        <?php esc_html_e( 'Transfer Domain', 'wh4u-domains' ); ?>
                    </button>
                </p>
            </form>
        </div>
        <?php
    }

    /**
     * Render contact detail fields for register/transfer forms.
     *
     * @param string $prefix Form prefix.
     * @return void
     */
    private static function render_contact_fields( $prefix ) {
        $contact_types = array(
            'registrant' => __( 'Registrant', 'wh4u-domains' ),
            'admin'      => __( 'Admin', 'wh4u-domains' ),
            'tech'       => __( 'Technical', 'wh4u-domains' ),
            'billing'    => __( 'Billing', 'wh4u-domains' ),
        );

        $fields = array(
            'firstname'   => __( 'First Name', 'wh4u-domains' ),
            'lastname'    => __( 'Last Name', 'wh4u-domains' ),
            'companyname' => __( 'Company', 'wh4u-domains' ),
            'email'       => __( 'Email', 'wh4u-domains' ),
            'address1'    => __( 'Address', 'wh4u-domains' ),
            'city'        => __( 'City', 'wh4u-domains' ),
            'state'       => __( 'State / Province', 'wh4u-domains' ),
            'postcode'    => __( 'Post Code', 'wh4u-domains' ),
            'country'     => __( 'Country Code', 'wh4u-domains' ),
            'phonenumber' => __( 'Phone Number', 'wh4u-domains' ),
        );

        foreach ( $contact_types as $type => $label ) :
            ?>
            <h2>
                <?php
                /* translators: %s: contact type (e.g. Registrant, Admin, Technical, Billing) */
                echo esc_html( sprintf( __( '%s Contact', 'wh4u-domains' ), $label ) );
                ?>
                <?php if ( $type !== 'registrant' ) : ?>
                    <label class="wh4u-copy-contact">
                        <input type="checkbox" class="wh4u-copy-registrant" data-target="<?php echo esc_attr( $type ); ?>" />
                        <small><?php esc_html_e( 'Copy from Registrant', 'wh4u-domains' ); ?></small>
                    </label>
                <?php endif; ?>
            </h2>
            <table class="form-table wh4u-contact-table" data-contact-type="<?php echo esc_attr( $type ); ?>">
                <?php foreach ( $fields as $field_key => $field_label ) : ?>
                <tr>
                    <th scope="row"><label><?php echo esc_html( $field_label ); ?></label></th>
                    <td>
                        <input type="<?php echo esc_attr( $field_key === 'email' ? 'email' : 'text' ); ?>"
                               name="contacts[<?php echo esc_attr( $type ); ?>][<?php echo esc_attr( $field_key ); ?>]"
                               class="regular-text wh4u-contact-field"
                               data-field="<?php echo esc_attr( $field_key ); ?>"
                               required />
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>
            <?php
        endforeach;
    }

    /**
     * Get reseller settings for the current user.
     *
     * @param int $user_id WordPress user ID.
     * @return array
     */
    private static function get_reseller_settings( $user_id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'wh4u_reseller_settings';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $table from $wpdb->prefix
        $row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE user_id = %d", $user_id ) );

        if ( ! $row ) {
            return array( 'nameservers' => array() );
        }

        $nameservers = json_decode( $row->default_nameservers, true );
        return array(
            'nameservers' => is_array( $nameservers ) ? $nameservers : array(),
        );
    }
}
