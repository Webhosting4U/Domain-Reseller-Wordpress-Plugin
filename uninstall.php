<?php
/**
 * Fired when the plugin is uninstalled (deleted via WP Admin).
 *
 * Drops all custom tables, removes all options, clears transients,
 * removes custom capabilities, and unschedules cron events.
 *
 * @package WH4U_Domains
 * @license GPL-2.0-or-later
 * @link    https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    die;
}

/**
 * Performs plugin uninstall: drops tables, deletes posts/options, removes caps.
 */
function wh4u_domains_uninstall() {
    global $wpdb;

    $wh4u_tables = array(
        $wpdb->prefix . 'wh4u_orders',
        $wpdb->prefix . 'wh4u_queue',
        $wpdb->prefix . 'wh4u_api_logs',
        $wpdb->prefix . 'wh4u_reseller_settings',
        $wpdb->prefix . 'wh4u_notifications',
        $wpdb->prefix . 'wh4u_rate_limits',
        $wpdb->prefix . 'wh4u_public_orders',
    );

    foreach ( $wh4u_tables as $wh4u_table ) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Uninstall; table names cannot be prepared.
        $wpdb->query( "DROP TABLE IF EXISTS {$wh4u_table}" );
    }

    $wh4u_public_orders = get_posts( array(
        'post_type'      => 'wh4u_public_order',
        'post_status'    => 'any',
        'posts_per_page' => -1,
        'fields'         => 'ids',
    ) );
    foreach ( $wh4u_public_orders as $wh4u_pid ) {
        wp_delete_post( $wh4u_pid, true );
    }

    $wh4u_options = array(
        'wh4u_settings',
        'wh4u_db_version',
        'wh4u_auto_encryption_key',
    );

    foreach ( $wh4u_options as $wh4u_option ) {
        delete_option( $wh4u_option );
        delete_site_option( $wh4u_option );
    }

    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Uninstall; transient names are fixed prefix.
    $wpdb->query(
        "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_wh4u_%' OR option_name LIKE '_transient_timeout_wh4u_%'"
    );
    // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

    $wh4u_roles = wp_roles();
    foreach ( $wh4u_roles->role_objects as $wh4u_role ) {
        $wh4u_role->remove_cap( 'wh4u_manage_domains' );
    }

    wp_clear_scheduled_hook( 'wh4u_process_queue' );
}

wh4u_domains_uninstall();
