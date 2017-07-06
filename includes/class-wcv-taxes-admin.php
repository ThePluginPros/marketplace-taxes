<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Admin.
 *
 * Responsible for rendering and controlling the admin settings UI. 
 *
 * @author  Brett Porcelli
 * @package WCV_Taxes
 * @since   0.0.1
 */
class WCV_Taxes_Admin {

    /**
     * Constructor. Registers action/filter hooks.
     *
     * @since 0.0.1
     */
    public function __construct() {
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_styles' ) );
        add_filter( 'plugin_action_links_' . plugin_basename( WCV_TAX_FILE ), array( $this, 'add_settings_link' ) );
        add_filter( 'wc_prd_vendor_options', array( $this, 'add_tax_tab' ) );
    }

    /**
     * Enqueue admin styles.
     *
     * @since 0.0.1
     */
    public function enqueue_styles() {
        wp_enqueue_style( 'tax-admin', WCV_TAX_URL . '/assets/css/admin.css' );
    }

    /**
     * Add plugin action for accessing settings page.
     *
     * @since 0.0.1
     *
     * @param  array $actions Existing actions.
     * @return array
     */
    public function add_settings_link( $actions ) {
        $settings_uri = admin_url( 'admin.php?page=wc_prd_vendor&tab=tax' );

        return array_merge( array(
            'settings' => sprintf( "<a href='%s'>%s</a>", $settings_uri, __( 'Settings', 'wcv-taxes' ) ),
        ), $actions );
    }

    /**
     * Hide/disable core tax options that we want to control.
     * 
     * GENERAL TAB
     * - Taxes (will be controlled by Seller of Record)
     *
     * PRODUCTS TAB
     * - Miscellaneous > Taxes (no longer applicable)
     * 
     * PRODUCT FORM TAB
     * - General > Tax (no longer applicable)
     * - Variations > Tax Class (no longer applicable)
     *
     * @since 0.0.1
     *
     * @param  array $options
     * @return array
     */
    private function remove_core_options( $options ) {
        foreach ( $options as $key => $option ) {
            if ( ! isset( $option['id'] ) ) {
                continue;
            }

            if ( 'give_tax' === $option['id'] ) {
                unset( $options[ $key ] );
            } else if ( 'hide_product_misc' === $option['id'] ) {
                unset( $options[ $key ]['options']['taxes'] );
            } else if ( 'hide_product_general' === $option['id'] ) {
                unset( $options[ $key ]['options']['tax'] );
            } else if ( 'hide_product_variations' === $option['id'] ) {
                unset( $options[ $key ]['options']['tax_class'] );
            }
        }

        return $options;
    }

    /**
     * Get HTML for section links.
     *
     * @since 0.0.1
     *
     * @param  string $current Current section.
     * @return string
     */
    private function get_section_links( $current ) {
        $sections = array( 
            'general'  => __( 'General', 'wcv-taxes' ),
            'taxjar'   => __( 'TaxJar', 'wcv-taxes' ),
            'ratesync' => __( 'RateSync', 'wcv-taxes' ),
        );
        
        // Generate HTML
        ob_start();

        foreach ( $sections as $key => $name ) {
            $class = 'wcv-tax-section-link' . ( $current == $key ? ' current' : '' );
            $href  = admin_url( 'admin.php?page=wc_prd_vendor&tab=tax&section=' . $key );
            
            printf( '<a href="%s" class="%s">%s</a> | ', $href, $class, $name );
        }
        
        // Collect HTML, stripping trailing separator
        $html = ob_get_clean();

        return substr( $html, 0, strlen( $html ) - 3 );
    }

    /**
     * Add options for the 'General' section.
     *
     * @since 0.0.1
     *
     * @param  array $options
     * @return array
     */
    private function add_general_options( $options ) {
        $options[] = array(
            'name'  => __( 'Enabled', 'wcv-taxes' ),
            'id'    => 'taxes_enabled',
            'type'  => 'checkbox',
            'desc'  => __( 'Enable tax calculations during checkout', 'wcv-tax' ),
            'std'   => true,
        );

        $options[] = array(
            'name'    => __( 'Merchant of Record', 'wcv-taxes' ),
            'id'      => 'merchant_of_record',
            'type'    => 'select',
            'options' => array(
                'vendor'      => __( 'Vendor', 'wcv-taxes' ),
                'marketplace' => __( 'Marketplace', 'wcv-taxes' ),
            ),
            'std'     => 'vendor',
            'tip'     => __( 'The merchant of record is responsible for collecting and remitting sales tax for each sale. The sales tax collected will be given to the merchant of record.', 'wcv-taxes' ),
        );
        return $options;
    }

    /**
     * Add options for the 'TaxJar' section.
     *
     * @since 0.0.1
     *
     * @param  array $options
     * @return array
     */
    private function add_taxjar_options( $options ) {
        $options[] = array(
            'name' => __( 'Enabled', 'wcv-taxes' ),
            'id'   => 'taxjar_enabled',
            'type' => 'checkbox',
            'desc' => __( 'Allow vendors to use TaxJar for tax calculations', 'wcv-tax' ),
            'std'  => true,
        );

        $options[] = array(
            'name' => __( 'API Key', 'wcv-taxes' ),
            'id'   => 'taxjar_api_key',
            'type' => 'text',
            'tip'  => __( 'Enter your TaxJar API key.', 'wcv-taxes' ),
            'desc' => sprintf( '<a href="#" target="_blank">%s</a> | <a href="#" target="_blank">%s</a>', __( 'Create TaxJar account', 'wcv-taxes' ), __( 'Obtain API key', 'wcv-taxes' ) ),
        );

        $options[] = array(
            'name' => __( 'Who Pays?', 'wcv-taxes' ),
            'id'   => 'taxjar_who_pays',
            'type' => 'select',
            'options' => array(
                'marketplace' => __( 'Marketplace', 'wcv-taxes' ),
                'vendor'      => __( 'Vendor', 'wcv-taxes' ),
            ),
            'tip'  => __( 'Who pays for tax calculations during checkout?', 'wcv-taxes' ),
            'std'  => 'marketplace',
        );

        return $options;
    }

    /**
     * Add options for the 'RateSync' section.
     *
     * @since 0.0.1
     *
     * @param  array $options
     * @return array
     */
    private function add_ratesync_options( $options ) {
        $options[] = array(
            'name' => __( 'Enabled', 'wcv-taxes' ),
            'id'   => 'ratesync_enabled',
            'type' => 'checkbox',
            'desc' => __( 'Allow vendors to use RateSync for tax calculations', 'wcv-tax' ),
            'std'  => false,
        );

        return $options;
    }

    /**
     * Add 'Tax' tab under WooCommerce > WC Vendors.
     *
     * @since 0.0.1
     *
     * @param  array $options Existing options.
     * @return array
     */
    public function add_tax_tab( $options ) {
        $current = isset( $_GET['section'] ) ? $_GET['section'] : 'general';
        
        // Remove unused core options
        $options = $this->remove_core_options( $options );
        
        // Add 'Tax' tab
        $options[] = array(
            'name' => __( 'Tax', 'wcv-taxes' ),
            'type' => 'heading',
        );

        // Add 'Tax options' title & section links
        $options[] = array(
            'name' => __( 'Tax options', 'wcv-taxes' ),
            'type' => 'title',
            'desc' => $this->get_section_links( $current ),
        );

        // Add other options depending on current section
        $callback = array( $this, 'add_' . $current . '_options' );

        if ( is_callable( $callback ) ) {
            $options = call_user_func( $callback, $options, $current );
        }

        return $options;
    }

}

new WCV_Taxes_Admin();