<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Integration for WC Vendors.
 */
class TFM_Integration_WC_Vendors {

    /**
     * Constructor.
     *
     * Registers action hooks and filters.
     */
    public function __construct() {
        $this->includes();

        add_filter( 'tfm_form_field_callback', array( $this, 'set_field_callback' ) );
        add_filter( 'tfm_default_vendor_addresses', array( $this, 'get_default_vendor_addresses' ), 10, 2 );
    }

    /**
     * Includes all required files.
     */
    private function includes() {
        require_once __DIR__ . '/class-tfm-wc-vendors-form-helper.php';
        require_once __DIR__ . '/class-tfm-wc-vendors-admin.php';
        require_once __DIR__ . '/class-tfm-wc-vendors-dashboard.php';
        require_once __DIR__ . '/class-tfm-wc-vendors-settings-manager.php';
    }


    /**
     * Sets the callback for displaying vendor settings form fields.
     *
     * @return callable
     */
    public function set_field_callback() {
        return array( $this, 'display_field' );
    }

    /**
     * Displays a settings field in the admin or frontend context.
     *
     * @param array $field Field definition
     * @param string $context 'admin' or 'frontend'
     */
    public function display_field( $field, $context ) {
        $callback = null;

        switch ( $field['type'] ) {
            case 'text':
            case 'checkbox':
            case 'radio':
                $callback = array( 'TFM_WC_Vendors_Form_Helper', 'input' );
                break;

            case 'select':
            case 'textarea':
            case 'custom_field':
                $callback = array( 'TFM_WC_Vendors_Form_Helper', $field['type'] );
                break;
        }

        if ( is_callable( $callback ) ) {
            $callback( $field, $context );
        }
    }

    /**
     * Gets the default nexus addresses for a vendor.
     *
     * @param array $addresses
     * @param int $vendor_id
     *
     * @return array
     */
    public function get_default_vendor_addresses( $addresses, $vendor_id ) {
        // Store address
        $addresses[] = [
            'description' => __( 'Inherited from your store settings', 'taxjar-for-marketplaces' ),
            'country'     => get_user_meta( $vendor_id, '_wcv_store_country', true ),
            'postcode'    => get_user_meta( $vendor_id, '_wcv_store_postcode', true ),
            'state'       => get_user_meta( $vendor_id, '_wcv_store_state', true ),
            'city'        => get_user_meta( $vendor_id, '_wcv_store_city', true ),
            'address_1'   => get_user_meta( $vendor_id, '_wcv_store_address1', true ),
        ];

        // Vendor shipping 'Ship From' address
        $methods = WC()->shipping()->get_shipping_methods();

        if ( isset( $methods['wcv_pro_vendor_shipping'] ) && $methods['wcv_pro_vendor_shipping']->is_enabled() ) {
            $settings = $this->get_vendor_shipping_settings( $vendor_id );

            if ( 'other' === $settings['shipping_from'] ) {
                $addresses[] = [
                    'description' => __( 'Inherited from your shipping settings', 'taxjar-for-marketplaces' ),
                    'country'     => $settings['shipping_address']['country'],
                    'postcode'    => $settings['shipping_address']['postcode'],
                    'state'       => $settings['shipping_address']['state'],
                    'city'        => $settings['shipping_address']['city'],
                    'address_1'   => $settings['shipping_address']['address1'],
                ];
            }
        }

        return $addresses;
    }

    /**
     * Gets a vendor's shipping settings.
     *
     * @param int $vendor_id
     *
     * @return array Shipping settings with some defaults set
     */
    private function get_vendor_shipping_settings( $vendor_id ) {
        $settings = get_user_meta( $vendor_id, '_wcv_shipping', true );

        if ( ! is_array( $settings ) ) {
            $settings = [];
        }

        $settings = wp_parse_args(
            $settings,
            [
                'shipping_from'    => 'store_address',
                'shipping_address' => [
                    'country'  => '',
                    'postcode' => '',
                    'state'    => '',
                    'city'     => '',
                    'address1' => '',
                ],
            ]
        );

        return $settings;
    }

}
