<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Integration for Dokan.
 */
class MT_Integration_Dokan extends MT_Integration {

    /**
     * Constructor.
     *
     * Registers action hooks and filters.
     */
    public function __construct() {
        $this->includes();

        add_filter( 'mt_form_field_callback', array( $this, 'set_field_callback' ) );
        add_filter( 'mt_default_vendor_addresses', array( $this, 'get_default_vendor_addresses' ), 10, 2 );
        add_filter( 'mt_vendor_settings_hooks', array( $this, 'register_settings_hooks' ) );
        add_filter( 'mt_vendors_with_no_address', array( $this, 'filter_vendors_with_no_address' ) );
        add_filter( 'mt_vendor_order_vendor_key', array( $this, 'filter_vendor_order_vendor_key' ) );

        // Hide the default vendor setup notice - we will output our own
        add_filter( 'mt_should_display_vendor_notice', '__return_false' );
    }

    /**
     * Includes all required files.
     */
    private function includes() {
        include_once 'class-mt-dokan-form-helper.php';
        include_once 'class-mt-dokan-dashboard.php';
        include_once 'class-mt-dokan-order-manager.php';
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
     * For Dokan, only the frontend context is used.
     *
     * @param array  $field   Field definition
     * @param string $context 'admin' or 'frontend'
     */
    public function display_field( $field, $context ) {
        if ( 'frontend' !== $context ) {
            return;
        }

        $callback = null;

        switch ( $field['type'] ) {
            case 'text':
            case 'checkbox':
            case 'radio':
                $callback = array( 'MT_Dokan_Form_Helper', 'input' );
                break;

            case 'select':
            case 'textarea':
            case 'custom_field':
                $callback = array( 'MT_Dokan_Form_Helper', $field['type'] );
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
     * @param int   $vendor_id
     *
     * @return array
     */
    public function get_default_vendor_addresses( $addresses, $vendor_id ) {
        $vendor_address = $this->get_vendor_address( $vendor_id );

        $addresses[] = [
            'description' => __( 'Inherited from your store settings.', 'marketplace-taxes' ),
            'country'     => $vendor_address['country'],
            'postcode'    => $vendor_address['postcode'],
            'state'       => $vendor_address['state'],
            'city'        => $vendor_address['city'],
            'address_1'   => $vendor_address['address'],
        ];

        return $addresses;
    }

    /**
     * Registers the 'vendor settings saved' hooks for WC Vendors.
     *
     * @param array $hooks
     *
     * @return array
     */
    public function register_settings_hooks( $hooks ) {
        $hooks[] = 'dokan_store_profile_saved';

        return $hooks;
    }

    /**
     * Filters the user query used to find vendors with no addresses.
     *
     * Ensures that only those vendors without default addresses are returned.
     *
     * @param array $vendors User IDs returned by get_users().
     *
     * @return array
     */
    public function filter_vendors_with_no_address( $vendors ) {
        foreach ( $vendors as $key => $vendor_id ) {
            $vendor_address = $this->get_vendor_address( $vendor_id );

            if ( MT_Addresses::is_address_valid( $vendor_address ) ) {
                unset( $vendors[ $key ] );
            }
        }

        return $vendors;
    }

    /**
     * Checks whether the given user is a vendor.
     *
     * @param int $user_id
     *
     * @return bool
     */
    public function is_vendor( $user_id ) {
        $user = get_user_by( 'id', $user_id );

        return count( array_intersect( $this->get_vendor_roles(), $user->roles ) ) > 0;
    }

    /**
     * Gets the name of a user's vendors store.
     *
     * @param int $user_id
     *
     * @return string
     */
    public function get_vendor_shop_name( $user_id ) {
        $seller = dokan_get_vendor( $user_id );

        return $seller->get_shop_name();
    }

    /**
     * Returns the steps required for a vendor to complete their tax setup.
     *
     * @param string $context The context in which the steps are being displayed ('admin' or 'frontend')
     *
     * @return array
     */
    public function get_vendor_setup_steps( $context = 'frontend' ) {
        if ( 'frontend' !== $context ) {
            return [];
        }

        $steps   = [];
        $user_id = dokan_get_current_user_id();

        // Prompt the vendor to complete their store address
        $address_valid = MT_Addresses::is_address_valid( $this->get_vendor_address( $user_id ) );

        $steps['complete_store_address'] = [
            'label'    => __( 'Enter a complete store address', 'marketplace-taxes' ),
            'url'      => dokan_get_navigation_url( 'settings/store' ) . '#address',
            'complete' => $address_valid,
        ];

        // Prompt the vendor to review their tax settings
        $steps['review_tax_settings'] = [
            'label'    => __( 'Review your tax settings', 'marketplace-taxes' ),
            'url'      => dokan_get_navigation_url( 'settings/tax' ),
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
        return $this->get_vendor_address( $vendor_id );
    }

    /**
     * Returns a vendor's address.
     *
     * @param int $vendor_id
     *
     * @return array
     */
    private function get_vendor_address( $vendor_id ) {
        $vendor = dokan_get_vendor( $vendor_id );

        $defaults = [
            'country'  => '',
            'zip'      => '',
            'city'     => '',
            'state'    => '',
            'street_1' => '',
        ];

        $address = wp_parse_args( $vendor->get_address(), $defaults );

        return [
            'country'  => $address['country'],
            'postcode' => $address['zip'],
            'city'     => $address['city'],
            'state'    => $address['state'],
            'address'  => $address['street_1'],
        ];
    }

    /**
     * Returns the 'Sold by' label for a vendor.
     *
     * @param int $vendor_id
     *
     * @return string
     */
    public function get_vendor_sold_by( $vendor_id ) {
        return $this->get_vendor_shop_name( $vendor_id );
    }

    /**
     * Returns all vendor user roles.
     *
     * @return array
     */
    public function get_vendor_roles() {
        return [ 'seller' ];
    }

    /**
     * Sets the meta key used to store the vendor for vendor sub orders.
     *
     * @return string
     */
    public function filter_vendor_order_vendor_key() {
        return '_dokan_vendor_id';
    }

    /**
     * Gets a vendor's shipping method from an order.
     *
     * @param int      $vendor_id Vendor ID.
     * @param WC_Order $order     Order object.
     *
     * @return int Shipping method ID, or 0 if no shipping method was found.
     */
    public function get_vendor_shipping_method_id( $vendor_id, $order ) {
        foreach ( $order->get_shipping_methods() as $item_id => $method ) {
            $shipping_seller_id = $method->get_meta( 'seller_id', true );

            if ( $vendor_id == $shipping_seller_id ) {
                return $method->get_id();
            }
        }

        return 0;
    }

}
