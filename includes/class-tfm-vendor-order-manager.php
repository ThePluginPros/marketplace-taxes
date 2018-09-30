<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Vendor order manager.
 */
class TFM_Vendor_Order_Manager {

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
        add_action( 'woocommerce_vendor_order_created', array( $this, 'add_shipping_lines' ), 20 );
        add_action( 'woocommerce_vendor_order_created', array( $this, 'set_inherited_properties' ) );
        add_filter( 'user_has_cap', array( $this, 'grant_permissions' ), 10, 3 );
        add_action( 'woocommerce_refund_created', array( $this, 'create_sub_order_refunds' ), 10, 2 );
        add_action( 'woocommerce_refund_deleted', array( $this, 'delete_sub_order_refunds' ) );
        add_action( 'woocommerce_before_order_object_save', array( $this, 'update_sub_orders' ) );
    }

    /**
     * Adds shipping lines to newly created vendor orders when necessary.
     *
     * @param int $order_id
     */
    public function add_shipping_lines( $order_id ) {
        $order = wc_get_order( $order_id );

        // Bail if shipping lines were already added
        if ( 0 < sizeof( $order->get_items( 'shipping' ) ) ) {
            return;
        }

        // Map from product IDs to original order items
        $item_map = [];

        foreach ( $order->get_items() as $item ) {
            $product_id              = $item->get_product()->get_id();
            $item_id                 = $item->get_meta( '_vendor_order_item_id', true );
            $item_map[ $product_id ] = new WC_Order_Item_Product( $item_id );
        }

        // Add shipping line(s) for the vendor as needed
        $parent    = wc_get_order( $order->get_parent_id() );
        $vendor_id = $order->get_meta( '_vendor_id', true );

        foreach ( $parent->get_items( 'shipping' ) as $shipping_item ) {
            $product_ids = $this->get_shipped_product_ids( $shipping_item );
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
     * Sets the inherited properties for a newly created vendor sub order.
     *
     * @param int $vendor_order_id
     */
    public function set_inherited_properties( $vendor_order_id ) {
        $vendor_order = wc_get_order( $vendor_order_id );
        $parent_order = wc_get_order( $vendor_order->get_parent_id() );

        // Set inherited properties and save
        $this->update_sub_order( $parent_order, $vendor_order );
    }

    /**
     * Gets the IDS of the products associated with a particular shipping item.
     *
     * @param WC_Order_Item_Shipping $shipping_item
     *
     * @return array
     */
    private function get_shipped_product_ids( $shipping_item ) {
        $item_map = [];

        if ( empty( $item_map ) ) {
            $order = $shipping_item->get_order();

            foreach ( $order->get_items() as $item ) {
                $product_id = $item->get_product()->get_id();

                // Map item name to item ID
                $item_map[ $item->get_name() ] = $product_id;

                // Map vendor IDs to vendor item IDs
                $vendor_id = \WCV_Vendors::get_vendor_from_product( $product_id );

                if ( ! isset( $item_map[ $vendor_id ] ) ) {
                    $item_map[ $vendor_id ] = [];
                }
                $item_map[ $vendor_id ][] = $product_id;
            }
        }

        $vendor_id     = $shipping_item->get_meta( 'vendor_id', true );    // TRS
        $vendor_costs  = $shipping_item->get_meta( 'vendor_costs', true ); // Pro shipping
        $package_items = $shipping_item->get_meta( 'Items', true );        // Other methods

        $product_ids = [];

        if ( $vendor_id ) {
            if ( isset( $item_map[ $vendor_id ] ) ) {
                $product_ids = $item_map[ $vendor_id ];
            }
        } elseif ( $vendor_costs ) {
            $product_ids = wp_list_pluck( $vendor_costs['items'], 'product_id' );
        } elseif ( $package_items ) {
            foreach ( explode( ',', $package_items ) as $item ) {
                $item_name = trim( current( explode( '&times;', $item ) ) );
                if ( isset( $item_map[ $item_name ] ) ) {
                    $product_ids[] = $item_map[ $item_name ];
                }
            }
        }

        return $product_ids;
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
        if ( ! in_array( $args[0], [ 'read_private_shop_orders', 'read_private_shop_order_refunds' ] ) ) {
            return $all_caps;
        }

        if ( ! WCV_Vendors::is_vendor( $args[1] ) ) {
            return $all_caps;
        }

        $vendor_id = get_post_meta( $args[2], '_vendor_id', true );

        if ( $vendor_id ) {
            $all_caps[ $cap[0] ] = $vendor_id == $args[1];
        }

        return $all_caps;
    }

    /**
     * Creates refunds for vendor sub orders when a parent order is refunded.
     *
     * @param int $refund_id ID of newly created refund
     * @param array $args Arguments passed to wc_create_refund
     */
    public function create_sub_order_refunds( $refund_id, $args ) {
        $refund     = wc_get_order( $refund_id );
        $sub_orders = wc_get_orders(
            [
                'type'   => 'shop_order_vendor',
                'parent' => $refund->get_parent_id(),
            ]
        );

        if ( empty( $sub_orders ) ) {
            return;
        }

        $refund_items = $args['line_items'];

        foreach ( $sub_orders as $sub_order ) {
            $refunded_items = [];

            foreach ( $sub_order->get_items( [ 'line_item', 'fee', 'shipping' ] ) as $item_id => $item ) {
                $order_item_id = $item->get_meta( '_vendor_order_item_id', true );

                if ( $order_item_id && isset( $refund_items[ $order_item_id ] ) ) {
                    $refunded_items[ $item_id ] = $refund_items[ $order_item_id ];
                }
            }

            if ( empty( $refunded_items ) ) {
                continue;
            }

            remove_action( 'woocommerce_refund_created', array( $this, 'create_sub_order_refunds' ) );

            try {
                $refund = wc_create_refund(
                    [
                        'order_id'       => $sub_order->get_id(),
                        'line_items'     => $refunded_items,
                        'amount'         => array_sum( wp_list_pluck( $refunded_items, 'refund_total' ) ),
                        'restock_items'  => false,
                        'refund_payment' => false,
                        'reason'         => $args['reason'],
                    ]
                );

                $refund->update_meta_data( '_vendor_id', $sub_order->get_meta( '_vendor_id', true ) );
                $refund->update_meta_data( '_parent_refund_id', $refund_id );

                $refund->save();
            } catch ( Exception $ex ) {
                wc_get_logger()->error(
                    'Failed to create refund for sub order ' . $sub_order->get_id() . ': ' . $ex->getMessage()
                );
            } finally {
                add_action( 'woocommerce_refund_created', array( $this, 'create_sub_order_refunds' ), 10, 2 );
            }
        }
    }

    /**
     * Deletes refunds for vendor sub orders when a parent refund is deleted.
     *
     * @param int $refund_id ID of deleted refund
     */
    public function delete_sub_order_refunds( $refund_id ) {
        $child_refunds = wc_get_orders(
            [
                'type'       => 'shop_order_refund',
                'meta_key'   => '_parent_refund_id',
                'meta_value' => $refund_id,
            ]
        );

        foreach ( $child_refunds as $child_refund ) {
            $child_refund->delete( true );
        }
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
            $this->update_sub_order( $order, $sub_order );
        }
    }

    /**
     * Update the inherited properties for a sub order to match the parent
     * order.
     *
     * @param WC_Order $order Parent order.
     * @param WC_Order $sub_order Vendor sub order.
     */
    protected function update_sub_order( $order, $sub_order ) {
        foreach ( self::$inherited_props as $prop ) {
            $sub_order->{"set_$prop"}( $order->{"get_$prop"}() );
        }

        $sub_order->save();
    }

}

new TFM_Vendor_Order_Manager();
