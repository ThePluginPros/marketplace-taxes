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
     * @var array Settings sections.
     */
    private $sections = array();

    /**
     * Constructor. Registers action/filter hooks.
     *
     * @since 0.0.1
     */
    public function __construct() {
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_styles' ) );
        add_filter( 'plugin_action_links_' . plugin_basename( WCV_TAX_FILE ), array( $this, 'add_settings_link' ) );
        add_filter( 'wc_prd_vendor_options', array( $this, 'add_tax_tab' ) );

        $this->init_sections();
    }

    /**
     * Initialize the settings sections.
     *
     * @since 0.0.1
     */
    private function init_sections() {
        // General settings
        $this->sections['general'] = array(
            'name'    => __( 'General', 'wcv-taxes' ),
            'desc'    => __( 'Use this tab to configure your WC Vendors Taxes installation. For help, shoot us an email at support@thepluginpros.com.', 'wcv-taxes' ),
            'options' => array(
                array(
                    'name'  => __( 'Enabled', 'wcv-taxes' ),
                    'id'    => 'taxes_enabled',
                    'type'  => 'checkbox',
                    'desc'  => __( 'Enable tax calculations during checkout', 'wcv-tax' ),
                    'std'   => true,
                ),
                array(
                    'name'    => __( 'Merchant of Record', 'wcv-taxes' ),
                    'id'      => 'merchant_of_record',
                    'type'    => 'select',
                    'options' => array(
                        'vendor'      => __( 'Vendor', 'wcv-taxes' ),
                        'marketplace' => __( 'Marketplace', 'wcv-taxes' ),
                    ),
                    'std'     => 'vendor',
                    'tip'     => __( 'The merchant of record is responsible for collecting and remitting sales tax for each sale. The sales tax collected will be given to the merchant of record.', 'wcv-taxes' ),
                ),
            ),
        );

        // TaxJar settings
        $this->sections['taxjar'] = array(
            'name'    => __( 'TaxJar', 'wcv-taxes' ),
            'desc'    => __( '<a href="#" target="_blank">TaxJar</a> is an easy-to-use tax reporting and calculation engine for small business owners and sales tax professionals. Enabling TaxJar allows vendors to take advantage of TaxJar\'s tax calculation, reporting, and filing services. As the marketplace owner, you may choose to cover the cost of tax calculations at checkout, or require vendors to pay for their own calculations.', 'wcv-taxes' ),
            'options' => array(
                array(
                    'name' => __( 'Enabled', 'wcv-taxes' ),
                    'id'   => 'taxjar_enabled',
                    'type' => 'checkbox',
                    'desc' => __( 'Allow vendors to use TaxJar for tax calculations', 'wcv-tax' ),
                    'std'  => true,
                ),
                array(
                    'name' => __( 'API Key', 'wcv-taxes' ),
                    'id'   => 'taxjar_api_key',
                    'type' => 'text',
                    'tip'  => __( 'Enter your TaxJar API key.', 'wcv-taxes' ),
                    'desc' => sprintf( '<a href="#" target="_blank">%s</a> | <a href="#" target="_blank">%s</a>', __( 'Create TaxJar account', 'wcv-taxes' ), __( 'Obtain API key', 'wcv-taxes' ) ),
                ),
                array(
                    'name' => __( 'Who Pays?', 'wcv-taxes' ),
                    'id'   => 'taxjar_who_pays',
                    'type' => 'select',
                    'options' => array(
                        'marketplace' => __( 'Marketplace', 'wcv-taxes' ),
                        'vendor'      => __( 'Vendor', 'wcv-taxes' ),
                    ),
                    'tip'  => __( 'Who pays for tax calculations during checkout?', 'wcv-taxes' ),
                    'std'  => 'marketplace',
                ),
            ),
        );

        // RateSync settings
        $this->sections['ratesync'] = array(
            'name'    => __( 'RateSync', 'wcv-taxes' ),
            'desc'    => __( 'The <a href="#" target="_blank">RateSync</a> provider extends the WooCommerce tax system to support multi-nexus tax collection. It uses a combination of custom tax rules and rates from TaxRates.com to perform tax calculations. RateSync is free to use, though you should be warned that it is far less accurate than TaxJar.', 'wcv-taxes' ),
            'options' => array(
                array(
                    'name' => __( 'Enabled', 'wcv-taxes' ),
                    'id'   => 'ratesync_enabled',
                    'type' => 'checkbox',
                    'desc' => __( 'Allow vendors to use RateSync for tax calculations', 'wcv-tax' ),
                    'std'  => false,
                ),
            ),
        );
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
     * Get HTML for settings header.
     *
     * @since 0.0.1
     *
     * @param  string $current Current section.
     * @return string
     */
    private function get_settings_header( $current ) {
        $html = '';

        // Add settings links
        foreach ( $this->sections as $key => $section ) {
            $class = 'wcv-tax-section-link' . ( $current == $key ? ' current' : '' );
            $href  = admin_url( 'admin.php?page=wc_prd_vendor&tab=tax&section=' . $key );
            $html .= sprintf( '<a href="%s" class="%s">%s</a> | ', $href, $class, $section['name'] );
        }
        
        $html = substr( $html, 0, -3 );

        // Add description
        if ( isset( $this->sections[ $current ]['desc'] ) ) {
           $html .= '</p><p class="wcv-tax-section-desc">' . $this->sections[ $current ]['desc'];
        }

        return $html;
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
        
        // Add 'Tax' tab & options for current section
        $options[] = array(
            'name' => __( 'Tax', 'wcv-taxes' ),
            'type' => 'heading',
        );
        $options[] = array(
            'name' => __( 'Tax options', 'wcv-taxes' ),
            'type' => 'title',
            'desc' => $this->get_settings_header( $current ),
        );

        $options = array_merge( $options, $this->sections[ $current ]['options'] );

        return $options;
    }

}

new WCV_Taxes_Admin();