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
 * @package TaxJar_For_Marketplaces
 */
class TFM_Form_Helper {

    /**
     * Output address table.
     *
     * @param array $options (unused for now)
     */
    public static function address_table( $options ) {
        TFM()->assets->enqueue(
            'script',
            'taxjar-for-marketplaces.address-table',
            [
                'deps' => [ 'jquery', 'wp-util', 'underscore', 'backbone', 'wcv-country-select' ],
                'localize' => [
                    'wcv_tax_address_table_localize' => [
                        'addresses' => is_array( $options['value'] ) ? $options['value'] : [],
                        'strings'   => [
                            'locations_error' => __(
                                'At least one business address is required.',
                                'taxjar-for-marketplaces'
                            ),
                        ],
                    ],
                ],
            ]
        );

        if ( WC()->countries->get_allowed_countries() ) {
            $countries = WC()->countries->get_allowed_countries();
        } else {
            $countries = WC()->countries->get_shipping_countries();
        }

        require_once __DIR__ . '/views/html-field-address-table.php';
    }

}
