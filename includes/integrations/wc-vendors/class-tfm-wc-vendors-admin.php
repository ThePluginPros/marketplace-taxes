<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * WC Vendors Admin class.
 *
 * Provides all functionality in the WP admin dashboard.
 */
class TFM_WC_Vendors_Admin {

    /**
     * Constructor.
     *
     * Registers action hooks and filters.
     */
    public function __construct() {
        if ( 'vendor' === TFM()->settings->get( 'merchant_of_record' ) ) {
            add_action( 'admin_menu', array( $this, 'add_admin_menu_item' ) );
            add_action( 'admin_init', array( $this, 'admin_save_settings' ) );
            add_filter( 'woocommerce_screen_ids', array( $this, 'register_admin_screen' ) );
        }
    }

    /**
     * Adds a 'Taxes' sub menu item under the 'Shop Settings' menu in WP admin.
     */
    public function add_admin_menu_item() {
        add_submenu_page(
            'wcv-vendor-shopsettings',
            __( 'Tax Settings', 'taxjar-for-marketplaces' ),
            __( 'Taxes', 'taxjar-for-marketplaces' ),
            'manage_product',
            'tax-settings',
            array( $this, 'display_admin_page' )
        );
    }

    /**
     * Displays the 'Tax settings' page.
     */
    public function display_admin_page() {
        $form = $this->form();

        require __DIR__ . '/views/html-admin-tax-settings-page.php';
    }

    /**
     * Handles settings form submissions on the backend.
     */
    public function admin_save_settings() {
        if ( isset( $_REQUEST['tfm_settings_save'] ) && check_admin_referer( 'save-tax-settings' ) ) {
            $this->form()->save( $_POST );

            TFM()->admin->add_notice( 'settings-saved', 'success', __( 'Settings saved.', 'taxjar-for-marketplaces' ) );
        }
    }

    /**
     * Registers our admin screen with WooCommerce.
     *
     * @param array $screen_ids
     *
     * @return array
     */
    public function register_admin_screen( $screen_ids ) {
        $screen_ids[] = 'shop-settings_page_tax-settings';

        return $screen_ids;
    }

    /**
     * Returns the single settings form instance.
     *
     * @return TFM_Vendor_Settings_Form
     */
    private function form() {
        static $instance = null;

        if ( is_null( $instance ) ) {
            $instance = new TFM_Vendor_Settings_Form( get_current_user_id(), 'admin' );
        }
        return $instance;
    }

}

new TFM_WC_Vendors_Admin();
