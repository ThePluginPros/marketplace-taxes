<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Integration for WC Vendors.
 */
class TFM_Integration_WC_Vendors extends TFM_Integration {

    /**
     * @var bool Is WC Vendors Pro installed?
     */
    protected $is_pro = false;

    /**
     * Constructor.
     *
     * Registers action hooks and filters.
     */
    public function __construct() {
        $this->includes();

        add_filter( 'tfm_form_field_callback', array( $this, 'set_field_callback' ) );
        add_filter( 'tfm_default_vendor_addresses', array( $this, 'get_default_vendor_addresses' ), 10, 2 );
        add_filter( 'tfm_vendor_settings_hooks', array( $this, 'register_settings_hooks' ) );
        add_filter( 'tfm_vendor_address_query', array( $this, 'filter_no_addresses_query' ) );
        add_filter( 'tfm_should_display_vendor_notice', array( $this, 'should_display_vendor_notice' ) );

        $this->is_pro = class_exists( 'WCVendors_Pro' );
    }

    /**
     * Includes all required files.
     */
    private function includes() {
        require_once __DIR__ . '/class-tfm-wc-vendors-form-helper.php';
        require_once __DIR__ . '/class-tfm-wc-vendors-admin.php';
        require_once __DIR__ . '/class-tfm-wc-vendors-dashboard.php';
        require_once __DIR__ . '/class-tfm-wc-vendors-settings-manager.php';
        require_once __DIR__ . '/functions.php';
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
        // There are no store address fields in WC Vendors free
        if ( ! $this->is_pro ) {
            return $addresses;
        }

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

    /**
     * Registers the 'vendor settings saved' hooks for WC Vendors.
     *
     * @param array $hooks
     *
     * @return array
     */
    public function register_settings_hooks( $hooks ) {
        return array_merge(
            $hooks,
            [
                'wcv_pro_store_settings_saved',
                'wcvendors_shop_settings_saved',
                'wcvendors_shop_settings_admin_saved',
                'edit_user_profile_update',
            ]
        );
    }

    /**
     * Filters the user query used to find vendors with no addresses.
     *
     * Ensures that only those vendors without default addresses are returned.
     *
     * @param array $args User query args.
     *
     * @return array Modified query args.
     */
    public function filter_no_addresses_query( $args ) {
        // Default addresses are only available if WC Vendors Pro is installed
        if ( ! $this->is_pro ) {
            return $args;
        }

        // Country, state, and postcode are required for an address to be valid
        $args['meta_query'][] = [
            'relation' => 'OR',
            [
                'key'     => '_wcv_store_country',
                'value'   => '',
                'compare' => '=',
            ],
            [
                'key'     => '_wcv_store_country',
                'value'   => '',
                'compare' => 'NOT EXISTS',
            ],
            [
                'key'     => '_wcv_store_state',
                'value'   => '',
                'compare' => '=',
            ],
            [
                'key'     => '_wcv_store_state',
                'value'   => '',
                'compare' => 'NOT EXISTS',
            ],
            [
                'key'     => '_wcv_store_postcode',
                'value'   => '',
                'compare' => '=',
            ],
            [
                'key'     => '_wcv_store_postcode',
                'value'   => '',
                'compare' => 'NOT EXISTS',
            ],
        ];

        return $args;
    }

    /**
     * Checks whether the given user is a vendor.
     *
     * @param int $user_id
     *
     * @return bool
     */
    public function is_vendor( $user_id ) {
        return WCV_Vendors::is_vendor( $user_id );
    }

    /**
     * Gets the name of a user's vendors store.
     *
     * @param int $user_id
     *
     * @return string
     */
    public function get_vendor_shop_name( $user_id ) {
        return WCV_Vendors::get_vendor_shop_name( $user_id );
    }

    /**
     * Checks whether the vendor address notice should be displayed on the
     * current page.
     *
     * @param bool $display
     *
     * @return bool
     */
    public function should_display_vendor_notice( $display ) {
        return is_page( tfm_wcv_get_dashboard_page_ids() );
    }

    /**
     * Returns the steps required for a vendor to complete their tax setup.
     *
     * @param string $context The context in which the steps are being displayed ('admin' or 'frontend')
     *
     * @return array
     */
    public function get_vendor_setup_steps( $context = 'frontend' ) {
        $steps   = [];
        $user_id = get_current_user_id();

        // Prompt the vendor to complete their store address
        $address_complete = TFM_Addresses::is_address_valid(
            [
                'country'  => get_user_meta( $user_id, '_wcv_store_country', true ),
                'postcode' => get_user_meta( $user_id, '_wcv_store_postcode', true ),
                'state'    => get_user_meta( $user_id, '_wcv_store_state', true ),
            ]
        );

        if ( 'admin' === $context ) {
            $store_address_url = add_query_arg( 'page', 'wcv-vendor-shopsettings', admin_url( 'admin.php' ) );
        } else {
            if ( ! $this->is_pro ) {
                $store_address_url = get_permalink( get_option( 'wcvendors_shop_settings_page_id' ) );
            } else {
                $store_address_url = WCVendors_Pro_Dashboard::get_dashboard_page_url( 'settings' );
            }
        }

        $steps['complete_store_address'] = [
            'label'    => __( 'Enter a complete store address' ),
            'url'      => $store_address_url . '#address',
            'complete' => $address_complete,
        ];

        // Prompt the vendor to review their tax settings
        if ( ! $this->is_pro || 'admin' === $context ) {
            $tax_settings_url = add_query_arg( 'page', 'tax-settings', admin_url( 'admin.php' ) );
        } else {
            $tax_settings_url = WCVendors_Pro_Dashboard::get_dashboard_page_url( 'settings' ) . '#tax';
        }

        $steps['review_tax_settings'] = [
            'label'    => __( 'Review your tax settings' ),
            'url'      => $tax_settings_url,
            'complete' => get_user_meta( $user_id, 'tax_settings_reviewed', true ),
        ];

        return $steps;
    }

    /**
     * Gets the 'Ship From' address for a vendor.
     *
     * @param int $vendor_id
     *
     * @return array
     */
    public function get_vendor_from_address( $vendor_id ) {
        return [
            'country'  => get_user_meta( $vendor_id, '_wcv_store_country', true ),
            'postcode' => get_user_meta( $vendor_id, '_wcv_store_postcode', true ),
            'city'     => get_user_meta( $vendor_id, '_wcv_store_city', true ),
            'state'    => get_user_meta( $vendor_id, '_wcv_store_state', true ),
            'address'  => get_user_meta( $vendor_id, '_wcv_store_address1', true ),
        ];
    }

    /**
     * Returns the ID of the vendor who created a product.
     *
     * @param int $product_id
     *
     * @return int Vendor ID
     */
    public function get_vendor_from_product( $product_id ) {
        return (int) WCV_Vendors::get_vendor_from_product( $product_id );
    }

    /**
     * Returns the 'Sold by' label for a vendor.
     *
     * @param int $vendor_id
     *
     * @return string
     */
    public function get_vendor_sold_by( $vendor_id ) {
        return WCV_Vendors::get_vendor_sold_by( $vendor_id );
    }

}
