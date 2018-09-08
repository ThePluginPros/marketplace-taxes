<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class TFM_Field_Business_Locations
 *
 * Defines and provides validation for the 'Business Locations' field.
 */
class TFM_Field_Business_Locations {

    /**
     * @var TFM_Settings_API WC integration or vendor settings form instance
     */
    protected $integration;

    /**
     * Initializes a new field instance.
     *
     * @param TFM_Settings_API $form Settings form instance.
     *
     * @return array
     */
    public static function init( $form ) {
        $instance = new self( $form );

        $field = [
            'type'              => 'custom_field',
            'path'              => TFM()->path( 'includes/views/html-field-address-table.php' ),
            'context'           => 'admin',
            'countries'         => self::get_country_options(),
            'title'             => __( 'Business Locations', 'taxjar-for-marketplaces' ),
            'description'       => __(
                'Please enter all locations, including stores, warehouses, distribution facilities, etc.',
                'taxjar-for-marketplaces'
            ),
            'sanitize_callback' => array( $instance, 'validate' ),
        ];

        return $field;
    }

    public function __construct( $integration ) {
        $this->integration = $integration;
    }

    /**
     * Validates the addresses entered by the user.
     *
     * @param array $addresses
     *
     * @return array
     *
     * @throws Exception If validation fails
     */
    public function validate( $addresses ) {
        if ( ! is_array( $addresses ) ) {
            $addresses = array();
        }

        // Remove extra whitespace from address fields
        $addresses = array_map(
            function ( $address ) {
                return array_map( 'trim', $address );
            },
            $addresses
        );

        if ( $this->integration->addresses_required() ) {
            $vendor_id     = $this->integration->get_vendor_id();
            $all_addresses = array_merge( TFM()->addresses->get_default( $vendor_id ), $addresses );

            // Filter out addresses with missing fields
            $all_addresses = array_filter( $all_addresses, array( 'TFM_Addresses', 'is_address_valid' ) );

            // No addresses or default addresses? Bail.
            if ( empty( $all_addresses ) ) {
                throw new Exception(
                    __( 'You must provide at least one business location.', 'taxjar-for-marketplaces' )
                );
            }
        }

        return $addresses;
    }

    /**
     * Gets the options for the country select boxes.
     *
     * @return array
     */
    private static function get_country_options() {
        if ( WC()->countries->get_allowed_countries() ) {
            $countries = WC()->countries->get_allowed_countries();
        } else {
            $countries = WC()->countries->get_shipping_countries();
        }

        return array_merge( [ '' => __( 'Select an option...' ) ], $countries );
    }

}
