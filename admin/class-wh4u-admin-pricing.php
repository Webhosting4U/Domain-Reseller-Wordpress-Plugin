<?php
/**
 * Admin page for TLD pricing display.
 *
 * @package WH4U_Domains
 * @license GPL-2.0-or-later
 * @link    https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WH4U_Admin_Pricing {

    /**
     * Render the TLD pricing page.
     *
     * @return void
     */
    public static function render_page() {
        if ( ! current_user_can( 'wh4u_manage_domains' ) ) {
            wp_die( esc_html__( 'You do not have sufficient permissions.', 'wh4u-domains' ) );
        }
        ?>
        <div class="wrap wh4u-admin-wrap">
            <h1><?php esc_html_e( 'TLD Pricing', 'wh4u-domains' ); ?></h1>

            <div id="wh4u-pricing-loading">
                <span class="spinner is-active"></span>
                <span><?php esc_html_e( 'Loading pricing data from the API...', 'wh4u-domains' ); ?></span>
            </div>

            <div id="wh4u-pricing-error" class="notice notice-error" style="display:none;">
                <p></p>
            </div>

            <div id="wh4u-pricing-container" style="display:none;">
                <table class="wp-list-table widefat fixed striped" id="wh4u-pricing-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'TLD', 'wh4u-domains' ); ?></th>
                            <th><?php esc_html_e( 'Register', 'wh4u-domains' ); ?></th>
                            <th><?php esc_html_e( 'Transfer', 'wh4u-domains' ); ?></th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
        <?php
    }
}
