<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class MT_API
 *
 * Registers our custom WC REST API controller with WooCommerce.
 */
class MT_API {

    public function __construct() {
        add_filter( 'woocommerce_api_classes', array( $this, 'register_api_class' ) );
        add_filter( 'user_has_cap', array( $this, 'grant_permissions' ), 10, 3 );
    }

    /**
     * Replaces the WC legacy orders controller with our own implementation.
     *
     * This is required for TaxJar reporting to work correctly.
     *
     * @param array $classes
     *
     * @return array
     */
    public function register_api_class( $classes ) {
        $key = array_search( 'WC_API_Orders', $classes );

        if ( false !== $key ) {
            unset( $classes[ $key ] );
        }

        require_once __DIR__ . '/class-mt-api-orders.php';

        $classes[] = 'MT_API_Orders';

        return $classes;
    }

    /**
     * Grants vendors permission to read their own orders by filtering the
     * current_user_can() function.
     *
     * @param array $all_caps All capabilities of the user.
     * @param array $cap [0] Required capability.
     * @param array $args [0] Requested capability, [1] User ID, [2] Object ID
     *
     * @return array
     */
    public function grant_permissions( $all_caps, $cap, $args ) {
        if ( ! isset( $args[2] ) ) {
            return $all_caps;
        }

        if ( ! in_array( $args[0], [ 'read_private_shop_orders', 'read_private_shop_order_refunds' ] ) ) {
            return $all_caps;
        }

        if ( ! MT_Vendors::is_vendor( $args[1] ) ) {
            return $all_caps;
        }

        $id_key    = apply_filters( 'mt_vendor_order_vendor_key', '_vendor_id' );
        $vendor_id = get_post_meta( $args[2], $id_key, true );

        if ( $vendor_id ) {
            $all_caps[ $cap[0] ] = $vendor_id == $args[1];
        }

        return $all_caps;
    }

}

new MT_API();
