<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Vendor order manager.
 */
class MT_WC_Vendors_Order_Manager {

    /**
     * @var array Order properties inherited from the parent order.
     */
    protected static $inherited_props = [
        'billing_first_name',
        'billing_last_name',
        'billing_company',
        'billing_address_1',
        'billing_address_2',
        'billing_city',
        'billing_state',
        'billing_postcode',
        'billing_country',
        'billing_email',
        'billing_phone',
        'shipping_first_name',
        'shipping_last_name',
        'shipping_company',
        'shipping_address_1',
        'shipping_address_2',
        'shipping_city',
        'shipping_state',
        'shipping_postcode',
        'shipping_country',
        'payment_method',
        'payment_method_title',
        'customer_ip_address',
        'customer_user_agent',
        'customer_id',
        'date_paid',
    ];

    public function __construct() {
        add_action( 'woocommerce_vendor_order_created', array( $this, 'on_order_created' ) );
        add_action( 'mt_vendor_order_created', array( $this, 'on_order_created' ) );
        add_action( 'woocommerce_before_order_object_save', array( $this, 'update_sub_orders' ) );
        add_filter( 'mt_refund_uploader_orders_query', array( $this, 'filter_refund_query' ) );
    }

    /**
     * Completes newly created vendor orders by adding shipping lines and
     * setting all inherited properties.
     *
     * @param int $order_id
     */
    public function on_order_created( $order_id ) {
        $order  = wc_get_order( $order_id );
        $parent = wc_get_order( $order->get_parent_id() );

        // Bail if the sub order or parent no longer exists
        if ( ! $parent || ! $order ) {
            return;
        }

        // Add shipping lines as needed
        $this->add_shipping_lines( $order );

        $order->update_meta_data( '_sub_order_version', '0.0.1' );

        // Set inherited order properties and save
        $this->set_inherited_properties( $parent, $order );
    }

    /**
     * Adds shipping lines to newly created vendor orders when necessary.
     *
     * @param WC_Order $order
     */
    protected function add_shipping_lines( &$order ) {
        // Bail if shipping lines were already added
        if ( 0 < sizeof( $order->get_items( 'shipping' ) ) ) {
            return;
        }

        // Map from product IDs to original order items
        $item_map = [];

        foreach ( $order->get_items() as $item ) {
            $product_id = $item->get_product()->get_id();
            $item_id    = $item->get_meta( '_vendor_order_item_id', true );
            if ( ( $original_item = WC_Order_Factory::get_order_item( $item_id ) ) ) {
                $item_map[ $product_id ] = $original_item;
            }
        }

        // Add shipping line(s) for the vendor as needed
        $parent    = wc_get_order( $order->get_parent_id() );
        $vendor_id = $order->get_meta( '_vendor_id', true );

        foreach ( $parent->get_items( 'shipping' ) as $shipping_item ) {
            $product_ids = mt_wcv_get_shipped_product_ids( $shipping_item );
            $vendor_cost = 0;

            foreach ( $product_ids as $product_id ) {
                if ( isset( $item_map[ $product_id ] ) ) {
                    $shipping_costs = WCV_Shipping::get_shipping_due(
                        $parent->get_id(),
                        $item_map[ $product_id ],
                        $vendor_id,
                        $product_id
                    );
                    $vendor_cost    += $shipping_costs['amount'];
                }
            }

            if ( 0 < $vendor_cost ) {
                $new_item = new WC_Order_Item_Shipping();

                try {
                    $new_item->set_instance_id( $shipping_item->get_instance_id() );
                    $new_item->set_method_id( $shipping_item->get_method_id() );
                    $new_item->set_method_title( $shipping_item->get_method_title() );
                    $new_item->set_name( $shipping_item->get_name() );
                    $new_item->set_total( $vendor_cost );
                    $new_item->set_taxes( $shipping_item->get_taxes() );
                    $new_item->update_meta_data( '_vendor_order_item_id', $shipping_item->get_id() );
                } catch ( Exception $ex ) {
                    continue;
                }

                $order->add_item( $new_item );
            }
        }

        $order->update_taxes();
        $order->calculate_totals( false );
    }

    /**
     * Updates the inherited properties for all vendor sub orders when a
     * parent order is saved.
     *
     * @param WC_Order $order Parent order.
     */
    public function update_sub_orders( $order ) {
        if ( is_a( $order, 'WC_Order_Vendor' ) ) {
            return;
        }

        $sub_orders = wc_get_orders(
            [
                'type'   => 'shop_order_vendor',
                'parent' => $order->get_id(),
            ]
        );

        foreach ( $sub_orders as $sub_order ) {
            $this->set_inherited_properties( $order, $sub_order );
        }
    }

    /**
     * Update the inherited properties for a sub order to match the parent
     * order.
     *
     * @param WC_Order $order     Parent order.
     * @param WC_Order $sub_order Vendor sub order.
     */
    protected function set_inherited_properties( &$order, &$sub_order ) {
        foreach ( self::$inherited_props as $prop ) {
            $sub_order->{"set_$prop"}( $order->{"get_$prop"}() );
        }

        $sub_order->save();
    }

    /**
     * Filters the refund uploader order query.
     *
     * Ensures that only refunds for updated sub orders are uploaded to TaxJar.
     *
     * @param array $query Args for WP_Query.
     *
     * @return array
     */
    public function filter_refund_query( $query ) {
        global $wpdb;

        if ( 'vendor' === MT()->settings->get( 'merchant_of_record', 'vendor' ) ) {
            $sub_order_ids = $wpdb->get_col(
                "SELECT DISTINCT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_sub_order_version'"
            );

            $query['post_parent'] = $sub_order_ids;
        }

        return $query;
    }

}

new MT_WC_Vendors_Order_Manager();
