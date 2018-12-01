<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class MT_Integration
 *
 * Base integration class extended by all marketplace plugin integrations.
 */
class MT_Integration {

    /**
     * Returns all vendor user roles.
     *
     * @return array
     */
    public function get_vendor_roles() {
        return [ 'vendor' ];
    }

    /**
     * Checks whether a user is a vendor by user ID.
     *
     * @param int $user_id
     *
     * @return bool
     */
    public function is_vendor( $user_id ) {
        return false;
    }

    /**
     * Returns the name of a user's vendor store.
     *
     * @param int $user_id
     *
     * @return string
     */
    public function get_vendor_shop_name( $user_id ) {
        return '';
    }

    /**
     * Returns the steps required for a vendor to complete their tax setup.
     *
     * @param string $context The context in which the steps are being displayed ('admin' or 'frontend')
     *
     * @return array
     */
    public function get_vendor_setup_steps( $context = 'frontend' ) {
        return [];
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
            'country'   => '',
            'state'     => '',
            'postcode'  => '',
            'city'      => '',
            'address_1' => '',
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
        return get_post_field( 'post_author', $product_id );
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
     * Returns the ID of the parent order item corresponding to a sub order item.
     *
     * @param WC_Order_Item $item Sub order item.
     * @param WC_Order $parent_order Parent order.
     *
     * @return int Parent order item ID, or 0 if no item is found.
     */
    public function get_parent_order_item_id( $item, $parent_order ) {
        if ( 'line_item' === $item->get_type() ) {
            foreach ( $parent_order->get_items() as $parent_item_id => $parent_item ) {
                if ( $parent_item->get_product_id() === $item->get_product_id() ) {
                    return $parent_item_id;
                }
            }
        }

        return 0;
    }

    /**
     * Returns the ID of a vendor's shipping method.
     *
     * @param int $vendor_id Vendor ID.
     * @param WC_Order $order Order object.
     *
     * @return int Shipping method ID, or 0 if no shipping method is found.
     */
    public function get_vendor_shipping_method_id( $vendor_id, $order ) {
        return 0;
    }

}
