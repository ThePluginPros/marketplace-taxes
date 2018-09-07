<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class TFM_Addresses
 *
 * Provides static methods for getting marketplace and vendor nexus addresses.
 */
class TFM_Addresses {

    /**
     * Gets the nexus addresses for the marketplace.
     *
     * @return array
     */
    public static function get_base_addresses() {
        $defaults   = array_map( [ __CLASS__, 'format_default_address' ], self::get_default_base_addresses() );
        $additional = array_map( [ __CLASS__, 'format_additional_address' ], self::get_additional_base_addresses() );

        return array_merge( $defaults, $additional );
    }

    /**
     * Gets the default nexus addresses for the marketplace.
     *
     * @return array
     */
    public static function get_default_base_addresses() {
        // Add store base address by default
        $defaults = [
            [
                'description' => __( 'Inherited from your general shop settings' ),
                'country'     => WC()->countries->get_base_country(),
                'postcode'    => WC()->countries->get_base_postcode(),
                'state'       => WC()->countries->get_base_state(),
                'city'        => WC()->countries->get_base_city(),
                'address_1'   => WC()->countries->get_base_address(),
            ],
        ];

        return apply_filters( 'tfm_default_base_addresses', $defaults );
    }

    /**
     * Gets any additional nexus addresses for the marketplace.
     *
     * @return array
     */
    private static function get_additional_base_addresses() {
        $addresses = TFM()->settings->get( 'nexus_addresses' );

        if ( ! is_array( $addresses ) ) {
            return [];
        }

        return apply_filters( 'tfm_additional_base_addresses', $addresses );
    }

    /**
     * Gets the nexus addresses for a vendor.
     *
     * @param int $vendor_id
     *
     * @return array
     */
    public static function get_vendor_addresses( $vendor_id ) {
        $defaults   = array_map(
            [ __CLASS__, 'format_default_address' ],
            self::get_default_vendor_addresses( $vendor_id )
        );
        $additional = array_map(
            [ __CLASS__, 'format_additional_address' ],
            self::get_additional_vendor_addresses( $vendor_id )
        );

        return array_merge( $defaults, $additional );
    }

    /**
     * Gets the default nexus addresses for a vendor.
     *
     * @param int $vendor_id
     *
     * @return array
     */
    public static function get_default_vendor_addresses( $vendor_id ) {
        // Defer to marketplace plugin integrations
        return apply_filters( 'tfm_default_vendor_addresses', [], $vendor_id );
    }

    /**
     * Gets any additional nexus addresses for a vendor.
     *
     * @param int $vendor_id
     *
     * @return array
     */
    private static function get_additional_vendor_addresses( $vendor_id ) {
        $addresses = get_user_meta( $vendor_id, 'tfm_nexus_addresses', true );

        if ( ! is_array( $addresses ) ) {
            $addresses = [];
        }

        return apply_filters( 'tfm_additional_vendor_addresses', $addresses, $vendor_id );
    }

    /**
     * Formats a default address.
     *
     * @param array $address
     *
     * @return array Address with `default` flag and `description` set.
     */
    private static function format_default_address( $address ) {
        if ( ! isset( $address['description'] ) ) {
            $address['description'] = __( 'Default address', 'taxjar-for-marketplaces' );
        }

        $address['default'] = true;

        return $address;
    }

    /**
     * Formats an additional address.
     *
     * @param array $address
     *
     * @return array Address with `default` flag unset.
     */
    private static function format_additional_address( $address ) {
        $address['default'] = false;
        return $address;
    }

}
