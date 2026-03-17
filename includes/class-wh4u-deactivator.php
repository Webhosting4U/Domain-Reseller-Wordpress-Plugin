<?php
/**
 * Handles plugin deactivation: clears cron and flushes rewrite rules.
 *
 * @package WH4U_Domains
 * @license GPL-2.0-or-later
 * @link    https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WH4U_Deactivator {

    /**
     * Run on plugin deactivation.
     *
     * @return void
     */
    public static function deactivate() {
        $timestamp = wp_next_scheduled( 'wh4u_process_queue' );
        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, 'wh4u_process_queue' );
        }

        wp_clear_scheduled_hook( 'wh4u_process_queue' );

        flush_rewrite_rules();
    }
}
