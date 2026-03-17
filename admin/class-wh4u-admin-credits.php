<?php
/**
 * Admin page for credits balance display.
 *
 * @package WH4U_Domains
 * @license GPL-2.0-or-later
 * @link    https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WH4U_Admin_Credits {

    /**
     * Render the credits balance page.
     *
     * @return void
     */
    public static function render_page() {
        if ( ! current_user_can( 'wh4u_manage_domains' ) ) {
            wp_die( esc_html__( 'You do not have sufficient permissions.', 'wh4u-domains' ) );
        }
        ?>
        <div class="wrap wh4u-admin-wrap">
            <h1><?php esc_html_e( 'Credits Balance', 'wh4u-domains' ); ?></h1>

            <div id="wh4u-credits-loading" style="display:none;">
                <span class="spinner is-active"></span>
            </div>

            <div id="wh4u-credits-error" class="notice notice-error" style="display:none;">
                <p></p>
            </div>

            <div id="wh4u-credits-container" style="display:none;">
                <div class="wh4u-credits-card">
                    <h2><?php esc_html_e( 'Available Credits', 'wh4u-domains' ); ?></h2>
                    <p class="wh4u-credits-amount" id="wh4u-credits-amount">--</p>
                </div>
            </div>
        </div>
        <?php
    }
}
