<?php
/**
 * Admin menu registration and page routing.
 *
 * @package WH4U_Domains
 * @license GPL-2.0-or-later
 * @link    https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WH4U_Admin {

    /** @var array<string> Admin page hook suffixes for conditional asset loading. */
    private $hook_suffixes = array();

    /**
     * Initialize admin hooks.
     *
     * @return void
     */
    public function init() {
        add_action( 'admin_menu', array( $this, 'register_menus' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        add_action( 'admin_init', array( 'WH4U_Admin_Settings', 'register_settings' ) );
        add_action( 'admin_init', array( 'WH4U_Admin_Appearance', 'register_settings' ) );
        WH4U_Admin_Appearance::register_preview_ajax();
        add_action( 'in_admin_header', array( $this, 'render_branded_header' ) );
    }

    /**
     * Register admin menu and submenus.
     *
     * @return void
     */
    public function register_menus() {
        $capability = 'wh4u_manage_domains';

        $dashboard_hook = add_menu_page(
            __( 'WH4U Domains', 'wh4u-domains' ),
            __( 'Domains', 'wh4u-domains' ),
            $capability,
            'wh4u-domains',
            array( 'WH4U_Admin_Dashboard', 'render_page' ),
            'dashicons-admin-site-alt3',
            30
        );
        $this->hook_suffixes[] = $dashboard_hook;

        WH4U_Admin_Dashboard::set_screen_id( $dashboard_hook );
        add_action( 'load-' . $dashboard_hook, array( 'WH4U_Admin_Dashboard', 'on_load' ) );

        $this->hook_suffixes[] = add_submenu_page(
            'wh4u-domains',
            __( 'Dashboard', 'wh4u-domains' ),
            __( 'Dashboard', 'wh4u-domains' ),
            $capability,
            'wh4u-domains',
            array( 'WH4U_Admin_Dashboard', 'render_page' )
        );

        $this->hook_suffixes[] = add_submenu_page(
            'wh4u-domains',
            __( 'Search / Availability', 'wh4u-domains' ),
            __( 'Search', 'wh4u-domains' ),
            $capability,
            'wh4u-domains-search',
            array( 'WH4U_Admin_Domains', 'render_search_page' )
        );

        $this->hook_suffixes[] = add_submenu_page(
            'wh4u-domains',
            __( 'Register Domain', 'wh4u-domains' ),
            __( 'Register', 'wh4u-domains' ),
            $capability,
            'wh4u-domains-register',
            array( 'WH4U_Admin_Domains', 'render_register_page' )
        );

        $this->hook_suffixes[] = add_submenu_page(
            'wh4u-domains',
            __( 'Transfer Domain', 'wh4u-domains' ),
            __( 'Transfer', 'wh4u-domains' ),
            $capability,
            'wh4u-domains-transfer',
            array( 'WH4U_Admin_Domains', 'render_transfer_page' )
        );

        $this->hook_suffixes[] = add_submenu_page(
            'wh4u-domains',
            __( 'TLD Pricing', 'wh4u-domains' ),
            __( 'Pricing', 'wh4u-domains' ),
            $capability,
            'wh4u-domains-pricing',
            array( 'WH4U_Admin_Pricing', 'render_page' )
        );

        $this->hook_suffixes[] = add_submenu_page(
            'wh4u-domains',
            __( 'Order History', 'wh4u-domains' ),
            __( 'History', 'wh4u-domains' ),
            $capability,
            'wh4u-domains-history',
            array( 'WH4U_Admin_History', 'render_page' )
        );

        $this->hook_suffixes[] = add_submenu_page(
            'wh4u-domains',
            __( 'Queue Status', 'wh4u-domains' ),
            __( 'Queue', 'wh4u-domains' ),
            $capability,
            'wh4u-domains-queue',
            array( 'WH4U_Admin_History', 'render_queue_page' )
        );

        $this->hook_suffixes[] = add_submenu_page(
            'wh4u-domains',
            __( 'Public Orders', 'wh4u-domains' ),
            __( 'Public Orders', 'wh4u-domains' ),
            'manage_options',
            'wh4u-domains-public-orders',
            array( 'WH4U_Admin_History', 'render_public_orders_page' )
        );

        $this->hook_suffixes[] = add_submenu_page(
            'wh4u-domains',
            __( 'Settings', 'wh4u-domains' ),
            __( 'Settings', 'wh4u-domains' ),
            $capability,
            'wh4u-domains-settings',
            array( 'WH4U_Admin_Settings', 'render_page' )
        );
    }

    /**
     * Enqueue admin CSS and JS on plugin pages only.
     *
     * @param string $hook_suffix Current admin page hook suffix.
     * @return void
     */
    public function enqueue_assets( $hook_suffix ) {
        if ( ! $this->is_plugin_page( $hook_suffix ) ) {
            return;
        }

        wp_enqueue_style(
            'wh4u-admin-css',
            WH4U_DOMAINS_PLUGIN_URL . 'admin/css/wh4u-admin.css',
            array(),
            WH4U_DOMAINS_VERSION
        );

        wp_enqueue_script(
            'wh4u-admin-js',
            WH4U_DOMAINS_PLUGIN_URL . 'admin/js/wh4u-admin.js',
            array( 'jquery' ),
            WH4U_DOMAINS_VERSION,
            true
        );

        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only admin page navigation
        $is_appearance_tab = isset( $_GET['page'] )
            && sanitize_text_field( wp_unslash( $_GET['page'] ) ) === 'wh4u-domains-settings'
            && isset( $_GET['tab'] )
            && sanitize_text_field( wp_unslash( $_GET['tab'] ) ) === 'appearance';
        // phpcs:enable WordPress.Security.NonceVerification.Recommended

        if ( $is_appearance_tab ) {
            wp_enqueue_style( 'wp-color-picker' );
            wp_enqueue_style(
                'wh4u-appearance-css',
                WH4U_DOMAINS_PLUGIN_URL . 'admin/css/wh4u-appearance.css',
                array(),
                WH4U_DOMAINS_VERSION
            );
            wp_enqueue_script(
                'wh4u-appearance-js',
                WH4U_DOMAINS_PLUGIN_URL . 'admin/js/wh4u-appearance.js',
                array( 'jquery', 'wp-color-picker' ),
                WH4U_DOMAINS_VERSION,
                true
            );
            $theme_tokens = class_exists( 'WH4U_Theme_Compat' ) ? WH4U_Theme_Compat::get_tokens() : array();
            wp_localize_script( 'wh4u-appearance-js', 'wh4uAppearance', array(
                'optionKey'   => WH4U_Admin_Appearance::OPTION_KEY,
                'themeTokens' => $theme_tokens,
                'themeName'   => wp_get_theme()->get( 'Name' ),
            ) );
        }

        wp_localize_script( 'wh4u-admin-js', 'wh4uAdmin', array(
            'restUrl'  => esc_url_raw( rest_url( 'wh4u/v1/' ) ),
            'nonce'    => wp_create_nonce( 'wp_rest' ),
            'i18n'     => array(
                'searching'          => __( 'Searching...', 'wh4u-domains' ),
                'processing'         => __( 'Processing...', 'wh4u-domains' ),
                'error'              => __( 'An error occurred. Please try again.', 'wh4u-domains' ),
                'available'          => __( 'Available', 'wh4u-domains' ),
                'unavailable'        => __( 'Unavailable', 'wh4u-domains' ),
                'confirm'            => __( 'Are you sure?', 'wh4u-domains' ),
                'retrying'           => __( 'Retrying...', 'wh4u-domains' ),
                'success'            => __( 'Success!', 'wh4u-domains' ),
                'register'           => __( 'Register', 'wh4u-domains' ),
                'searchAvailability' => __( 'Search Availability', 'wh4u-domains' ),
                'noResults'          => __( 'No results found.', 'wh4u-domains' ),
                'retryNow'           => __( 'Retry Now', 'wh4u-domains' ),
                'processNow'         => __( 'Process Now', 'wh4u-domains' ),
                'noPricing'          => __( 'No pricing data available.', 'wh4u-domains' ),
                'approve'            => __( 'Approve', 'wh4u-domains' ),
                'reject'             => __( 'Reject', 'wh4u-domains' ),
                /* translators: %s: action name (e.g. "approve" or "reject") */
                'confirmStatus'      => __( 'Are you sure you want to %s this order?', 'wh4u-domains' ),
            ),
        ) );
    }

    /**
     * Check if the current page is a WH4U plugin page.
     *
     * @param string $hook_suffix Current hook suffix.
     * @return bool
     */
    private function is_plugin_page( $hook_suffix ) {
        foreach ( $this->hook_suffixes as $suffix ) {
            if ( $hook_suffix === $suffix ) {
                return true;
            }
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only admin page check
        if ( isset( $_GET['page'] ) && strpos( sanitize_text_field( wp_unslash( $_GET['page'] ) ), 'wh4u-domains' ) === 0 ) {
            return true;
        }

        return false;
    }

    /**
     * Render the branded header on all plugin admin pages.
     *
     * @return void
     */
    public function render_branded_header() {
        $screen = get_current_screen();
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only admin header rendering
        if ( ! $screen || ! isset( $_GET['page'] ) ) {
            return;
        }

        $page = sanitize_text_field( wp_unslash( $_GET['page'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( strpos( $page, 'wh4u-domains' ) !== 0 ) {
            return;
        }
        ?>
        <div class="wh4u-branded-header">
            <a href="https://webhosting4u.gr" target="_blank" rel="noopener noreferrer" class="wh4u-brand-link">
                <img src="<?php echo esc_url( WH4U_DOMAINS_PLUGIN_URL . 'admin/images/footer-logo.png' ); ?>" alt="WebHosting4U" style="height:24px;width:auto;" />
            </a>
            <span class="wh4u-brand-tagline"><?php esc_html_e( 'Domain Management', 'wh4u-domains' ); ?></span>
        </div>
        <?php
    }
}
