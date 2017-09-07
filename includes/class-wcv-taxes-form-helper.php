<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Form helper.
 *
 * Defines static methods for generating form elements.
 *
 * @author  Brett Porcelli
 * @package WCV_Taxes
 * @since   0.0.1
 */
class WCV_Taxes_Form_Helper {

    /**
     * Output address table.
     *
     * @since 0.0.1
     *
     * @param array $options (unused for now)
     */
    public static function address_table( $options ) {
        wp_enqueue_script( 'wcv-tax-address-table', WCV_TAX_URL . '/assets/js/address-table.js', array( 'jquery', 'wp-util', 'underscore', 'backbone', 'wcv-country-select' ), WCV_TAX_VERSION );
        
        wp_localize_script( 'wcv-tax-address-table', 'wcv_tax_address_table_localize', array(
            'addresses' => is_array( $options['value'] ) ? $options['value'] : array(),
            'strings'   => array(
                'locations_error' => __( 'At least one business address is required.', 'wcv-taxes' ),
            ),
        ) );
        
        $countries = ( WC()->countries->get_allowed_countries() ) ? WC()->countries->get_allowed_countries() : WC()->countries->get_shipping_countries();

        require_once WCV_TAX_PATH . '/includes/views/html-field-address-table.php';
    }

}