<?php
/**
 * Plugin Name:       WH4U Domains
 * Description:       Domain reseller plugin for searching, registering, and transferring domains via the DomainsReseller API.
 * Version:           1.2.0
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Author:            WebHosting4U
 * Author URI:        https://webhosting4u.gr/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wh4u-domains
 * Domain Path:       /languages
 *
 * @package WH4U_Domains
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'WH4U_DOMAINS_VERSION', '1.2.0' );
define( 'WH4U_DOMAINS_DB_VERSION', '1.1.0' );
define( 'WH4U_DOMAINS_PLUGIN_FILE', __FILE__ );
define( 'WH4U_DOMAINS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WH4U_DOMAINS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WH4U_DOMAINS_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

require_once WH4U_DOMAINS_PLUGIN_DIR . 'includes/class-wh4u-encryption.php';
require_once WH4U_DOMAINS_PLUGIN_DIR . 'includes/class-wh4u-logger.php';
require_once WH4U_DOMAINS_PLUGIN_DIR . 'includes/class-wh4u-rate-limiter.php';
require_once WH4U_DOMAINS_PLUGIN_DIR . 'includes/class-wh4u-api-client.php';
require_once WH4U_DOMAINS_PLUGIN_DIR . 'includes/class-wh4u-queue.php';
require_once WH4U_DOMAINS_PLUGIN_DIR . 'includes/class-wh4u-cart-redirect.php';
require_once WH4U_DOMAINS_PLUGIN_DIR . 'includes/class-wh4u-theme-compat.php';
require_once WH4U_DOMAINS_PLUGIN_DIR . 'includes/class-wh4u-activator.php';
require_once WH4U_DOMAINS_PLUGIN_DIR . 'includes/class-wh4u-deactivator.php';

require_once WH4U_DOMAINS_PLUGIN_DIR . 'rest-api/class-wh4u-rest-controller.php';
require_once WH4U_DOMAINS_PLUGIN_DIR . 'rest-api/class-wh4u-rest-domains.php';
require_once WH4U_DOMAINS_PLUGIN_DIR . 'rest-api/class-wh4u-rest-orders.php';
require_once WH4U_DOMAINS_PLUGIN_DIR . 'rest-api/class-wh4u-rest-pricing.php';
require_once WH4U_DOMAINS_PLUGIN_DIR . 'rest-api/class-wh4u-rest-credits.php';
require_once WH4U_DOMAINS_PLUGIN_DIR . 'rest-api/class-wh4u-rest-queue.php';
require_once WH4U_DOMAINS_PLUGIN_DIR . 'rest-api/class-wh4u-rest-public-orders.php';

require_once WH4U_DOMAINS_PLUGIN_DIR . 'includes/class-wh4u-notifications.php';
require_once WH4U_DOMAINS_PLUGIN_DIR . 'includes/class-wh4u-public-order-processor.php';

require_once WH4U_DOMAINS_PLUGIN_DIR . 'admin/class-wh4u-admin.php';
require_once WH4U_DOMAINS_PLUGIN_DIR . 'admin/class-wh4u-admin-dashboard.php';
require_once WH4U_DOMAINS_PLUGIN_DIR . 'admin/class-wh4u-admin-settings.php';
require_once WH4U_DOMAINS_PLUGIN_DIR . 'admin/class-wh4u-admin-reseller.php';
require_once WH4U_DOMAINS_PLUGIN_DIR . 'admin/class-wh4u-admin-appearance.php';
require_once WH4U_DOMAINS_PLUGIN_DIR . 'admin/class-wh4u-admin-domains.php';
require_once WH4U_DOMAINS_PLUGIN_DIR . 'admin/class-wh4u-admin-pricing.php';
require_once WH4U_DOMAINS_PLUGIN_DIR . 'admin/class-wh4u-admin-credits.php';
require_once WH4U_DOMAINS_PLUGIN_DIR . 'admin/class-wh4u-admin-history.php';

require_once WH4U_DOMAINS_PLUGIN_DIR . 'public/class-wh4u-public.php';

register_activation_hook( __FILE__, array( 'WH4U_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'WH4U_Deactivator', 'deactivate' ) );

/**
 * Initialize plugin on plugins_loaded.
 *
 * @return void
 */
function wh4u_domains_init() {
    $installed_db_version = get_option( 'wh4u_db_version' );
    if ( $installed_db_version !== WH4U_DOMAINS_DB_VERSION ) {
        WH4U_Activator::create_tables();
        update_option( 'wh4u_db_version', WH4U_DOMAINS_DB_VERSION );
    }

    WH4U_Notifications::init_hooks();
    WH4U_Public_Order_Processor::init_hooks();

    if ( is_admin() ) {
        $admin = new WH4U_Admin();
        $admin->init();
    }

    $public = new WH4U_Public();
    $public->init();
}
add_action( 'plugins_loaded', 'wh4u_domains_init' );

/**
 * Register REST API routes.
 *
 * @return void
 */
function wh4u_domains_rest_init() {
    $domains       = new WH4U_REST_Domains();
    $orders        = new WH4U_REST_Orders();
    $pricing       = new WH4U_REST_Pricing();
    $credits       = new WH4U_REST_Credits();
    $queue         = new WH4U_REST_Queue();
    $public_orders = new WH4U_REST_Public_Orders();

    $domains->register_routes();
    $orders->register_routes();
    $pricing->register_routes();
    $credits->register_routes();
    $queue->register_routes();
    $public_orders->register_routes();
}
add_action( 'rest_api_init', 'wh4u_domains_rest_init' );

/**
 * Register the wh4u_public_order Custom Post Type and cron hooks.
 *
 * @return void
 */
function wh4u_domains_register_types() {
    register_post_type( 'wh4u_public_order', array(
        'labels' => array(
            'name'               => _x( 'Public Orders', 'post type general name', 'wh4u-domains' ),
            'singular_name'      => _x( 'Public Order', 'post type singular name', 'wh4u-domains' ),
            'menu_name'          => _x( 'Public Orders', 'admin menu', 'wh4u-domains' ),
            'all_items'          => __( 'All Public Orders', 'wh4u-domains' ),
            'view_item'          => __( 'View Order', 'wh4u-domains' ),
            'search_items'       => __( 'Search Orders', 'wh4u-domains' ),
            'not_found'          => __( 'No orders found.', 'wh4u-domains' ),
            'not_found_in_trash' => __( 'No orders found in Trash.', 'wh4u-domains' ),
        ),
        'public'              => false,
        'show_ui'             => false,
        'show_in_menu'        => false,
        'show_in_rest'        => false,
        'supports'            => array( 'title' ),
        'capability_type'     => 'post',
        'map_meta_cap'        => true,
        'has_archive'         => false,
        'rewrite'             => false,
        'query_var'           => false,
        'can_export'          => true,
        'delete_with_user'    => false,
    ) );

    register_post_status( 'wh4u-pending', array(
        'label'                     => _x( 'Pending Review', 'order status', 'wh4u-domains' ),
        'public'                    => false,
        'internal'                  => true,
        'show_in_admin_all_list'    => true,
        'show_in_admin_status_list' => true,
        /* translators: %s: number of orders */
        'label_count'               => _n_noop( 'Pending <span class="count">(%s)</span>', 'Pending <span class="count">(%s)</span>', 'wh4u-domains' ),
    ) );

    register_post_status( 'wh4u-approved', array(
        'label'                     => _x( 'Approved', 'order status', 'wh4u-domains' ),
        'public'                    => false,
        'internal'                  => true,
        'show_in_admin_all_list'    => true,
        'show_in_admin_status_list' => true,
        /* translators: %s: number of orders */
        'label_count'               => _n_noop( 'Approved <span class="count">(%s)</span>', 'Approved <span class="count">(%s)</span>', 'wh4u-domains' ),
    ) );

    register_post_status( 'wh4u-rejected', array(
        'label'                     => _x( 'Rejected', 'order status', 'wh4u-domains' ),
        'public'                    => false,
        'internal'                  => true,
        'show_in_admin_all_list'    => true,
        'show_in_admin_status_list' => true,
        /* translators: %s: number of orders */
        'label_count'               => _n_noop( 'Rejected <span class="count">(%s)</span>', 'Rejected <span class="count">(%s)</span>', 'wh4u-domains' ),
    ) );

    add_action( 'wh4u_process_queue', array( 'WH4U_Queue', 'process_pending' ) );
}
add_action( 'init', 'wh4u_domains_register_types' );

/**
 * Register custom cron interval.
 *
 * @param array $schedules Existing cron schedules.
 * @return array
 */
function wh4u_domains_cron_schedules( $schedules ) {
    $schedules['wh4u_five_minutes'] = array(
        'interval' => 300,
        'display'  => esc_html__( 'Every Five Minutes', 'wh4u-domains' ),
    );
    return $schedules;
}
add_filter( 'cron_schedules', 'wh4u_domains_cron_schedules' );
