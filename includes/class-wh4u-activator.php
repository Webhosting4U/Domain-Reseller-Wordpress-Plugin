<?php
/**
 * Handles plugin activation: creates database tables and registers capabilities.
 *
 * @package WH4U_Domains
 * @license GPL-2.0-or-later
 * @link    https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WH4U_Activator {

    /**
     * Run on plugin activation.
     *
     * @return void
     */
    public static function activate() {
        self::create_tables();
        self::add_capabilities();
        self::schedule_cron();

        add_option( 'wh4u_db_version', WH4U_DOMAINS_DB_VERSION );
        add_option( 'wh4u_settings', array(
            'api_base_url' => 'https://webhosting4u.gr/customers/modules/addons/DomainsReseller/api/index.php',
            'api_key'      => '',
            'mode'         => 'realtime',
        ) );

        flush_rewrite_rules();
    }

    /**
     * Create all custom database tables using dbDelta.
     *
     * @return void
     */
    public static function create_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $table_orders = $wpdb->prefix . 'wh4u_orders';
        $sql_orders = "CREATE TABLE {$table_orders} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            domain varchar(253) NOT NULL,
            order_type varchar(20) NOT NULL DEFAULT 'register',
            status varchar(30) NOT NULL DEFAULT 'pending',
            reg_period int(11) NOT NULL DEFAULT 1,
            contacts longtext,
            nameservers text,
            addons text,
            eppcode varchar(255) DEFAULT '',
            domainfields text,
            idn_language varchar(50) DEFAULT '',
            api_response longtext,
            retry_count int(11) NOT NULL DEFAULT 0,
            next_retry_at datetime DEFAULT NULL,
            error_message text,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY idx_user_id (user_id),
            KEY idx_status (status),
            KEY idx_domain (domain)
        ) {$charset_collate};";

        dbDelta( $sql_orders );

        $table_queue = $wpdb->prefix . 'wh4u_queue';
        $sql_queue = "CREATE TABLE {$table_queue} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            order_id bigint(20) NOT NULL,
            action varchar(100) NOT NULL,
            payload longtext NOT NULL,
            attempts int(11) NOT NULL DEFAULT 0,
            max_attempts int(11) NOT NULL DEFAULT 5,
            status varchar(20) NOT NULL DEFAULT 'pending',
            last_error text,
            scheduled_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            processed_at datetime DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY idx_status (status),
            KEY idx_scheduled_at (scheduled_at)
        ) {$charset_collate};";

        dbDelta( $sql_queue );

        $table_logs = $wpdb->prefix . 'wh4u_api_logs';
        $sql_logs = "CREATE TABLE {$table_logs} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL DEFAULT 0,
            endpoint varchar(255) NOT NULL DEFAULT '',
            method varchar(10) NOT NULL DEFAULT 'GET',
            request_body longtext,
            response_code int(11) DEFAULT NULL,
            response_body longtext,
            duration_ms int(11) DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY idx_user_id (user_id),
            KEY idx_created_at (created_at)
        ) {$charset_collate};";

        dbDelta( $sql_logs );

        $table_reseller = $wpdb->prefix . 'wh4u_reseller_settings';
        $sql_reseller = "CREATE TABLE {$table_reseller} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            reseller_email varchar(255) NOT NULL DEFAULT '',
            api_key_encrypted text,
            default_nameservers text,
            allowed_tlds text,
            webhook_url varchar(2048) DEFAULT '',
            webhook_secret_encrypted varchar(512) DEFAULT '',
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY idx_user_id (user_id)
        ) {$charset_collate};";

        dbDelta( $sql_reseller );

        $table_notifications = $wpdb->prefix . 'wh4u_notifications';
        $sql_notifications = "CREATE TABLE {$table_notifications} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL DEFAULT 0,
            order_id bigint(20) DEFAULT NULL,
            type varchar(20) NOT NULL DEFAULT 'email',
            status varchar(20) NOT NULL DEFAULT 'pending',
            recipient varchar(255) NOT NULL DEFAULT '',
            subject varchar(255) NOT NULL DEFAULT '',
            body longtext,
            response text,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY idx_user_id (user_id),
            KEY idx_order_id (order_id)
        ) {$charset_collate};";

        dbDelta( $sql_notifications );

        $table_rate = $wpdb->prefix . 'wh4u_rate_limits';
        $sql_rate = "CREATE TABLE {$table_rate} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL DEFAULT 0,
            ip_hash varchar(64) NOT NULL DEFAULT '',
            endpoint varchar(100) NOT NULL DEFAULT '',
            window_start datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            request_count int(11) NOT NULL DEFAULT 1,
            PRIMARY KEY  (id),
            KEY idx_user_endpoint (user_id,endpoint,window_start),
            KEY idx_ip_endpoint (ip_hash,endpoint,window_start)
        ) {$charset_collate};";

        dbDelta( $sql_rate );

        $table_public_orders = $wpdb->prefix . 'wh4u_public_orders';
        $sql_public_orders = "CREATE TABLE {$table_public_orders} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            domain varchar(253) NOT NULL,
            reg_period int(11) NOT NULL DEFAULT 1,
            first_name varchar(100) NOT NULL DEFAULT '',
            last_name varchar(100) NOT NULL DEFAULT '',
            email varchar(255) NOT NULL DEFAULT '',
            phone varchar(50) NOT NULL DEFAULT '',
            company varchar(255) DEFAULT '',
            address varchar(255) NOT NULL DEFAULT '',
            city varchar(100) NOT NULL DEFAULT '',
            state varchar(100) NOT NULL DEFAULT '',
            country varchar(2) NOT NULL DEFAULT '',
            zip varchar(20) NOT NULL DEFAULT '',
            ip_hash varchar(64) NOT NULL DEFAULT '',
            status varchar(30) NOT NULL DEFAULT 'pending',
            notes text,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY idx_status (status),
            KEY idx_email (email),
            KEY idx_domain (domain),
            KEY idx_created_at (created_at)
        ) {$charset_collate};";

        dbDelta( $sql_public_orders );
    }

    /**
     * Add the wh4u_manage_domains capability to the administrator role.
     *
     * @return void
     */
    private static function add_capabilities() {
        $admin_role = get_role( 'administrator' );
        if ( $admin_role ) {
            $admin_role->add_cap( 'wh4u_manage_domains', true );
        }
    }

    /**
     * Schedule the queue processing cron event.
     *
     * @return void
     */
    private static function schedule_cron() {
        if ( ! wp_next_scheduled( 'wh4u_process_queue' ) ) {
            wp_schedule_event( time(), 'wh4u_five_minutes', 'wh4u_process_queue' );
        }
    }
}
