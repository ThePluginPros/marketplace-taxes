<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Refund manager.
 *
 * Creates and updates refunds for vendor sub orders.
 */
class MT_Refund_Manager {

    /**
     * Constructor.
     */
    public function __construct() {
        add_action( 'woocommerce_refund_created', array( $this, 'create_sub_order_refunds' ), 10, 2 );
        add_action( 'woocommerce_refund_deleted', array( $this, 'delete_sub_order_refunds' ) );
    }

    /**
     * Creates refunds for vendor sub orders when a parent order is refunded.
     *
     * @param int   $refund_id ID of newly created refund
     * @param array $args      Arguments passed to wc_create_refund
     */
    public function create_sub_order_refunds( $refund_id, $args ) {
        $refund     = wc_get_order( $refund_id );
        $sub_orders = wc_get_orders(
            [
                'type'   => apply_filters( 'mt_vendor_order_post_type', 'shop_order' ),
                'parent' => $refund->get_parent_id(),
            ]
        );

        if ( empty( $sub_orders ) ) {
            return;
        }

        $parent_order = wc_get_order( $refund->get_parent_id() );
        $refund_items = $args['line_items'];

        foreach ( $sub_orders as $sub_order ) {
            $refund_amount  = 0;
            $refunded_items = [];

            $vendor_id_key = apply_filters( 'mt_vendor_order_vendor_key', '_vendor_id' );
            $vendor_id     = $sub_order->get_meta( $vendor_id_key, true );

            foreach ( $sub_order->get_items( [ 'line_item', 'fee', 'shipping' ] ) as $item_id => $item ) {
                $order_item_id = MT()->integration->get_parent_order_item_id( $item, $parent_order );

                if ( ! $order_item_id && 'shipping' === $item->get_type() ) {
                    $order_item_id = MT()->integration->get_vendor_shipping_method_id( $vendor_id, $parent_order );
                }

                if ( isset( $refund_items[ $order_item_id ] ) ) {
                    $refund_item                = $refund_items[ $order_item_id ];
                    $refunded_items[ $item_id ] = $refund_item;
                    $refund_amount              += $refund_item['refund_total'];
                    $refund_amount              += array_sum( $refund_item['refund_tax'] );
                }
            }

            if ( 0 >= $refund_amount ) {
                continue;
            }

            remove_action( 'woocommerce_refund_created', array( $this, 'create_sub_order_refunds' ) );

            try {
                $refund = wc_create_refund(
                    [
                        'order_id'       => $sub_order->get_id(),
                        'line_items'     => $refunded_items,
                        'amount'         => wc_format_decimal( $refund_amount ),
                        'restock_items'  => false,
                        'refund_payment' => false,
                        'reason'         => $args['reason'],
                    ]
                );

                if ( is_wp_error( $refund ) ) {
                    throw new Exception( $refund->get_error_message() );
                }

                $refund->update_meta_data( '_vendor_id', $vendor_id );
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

}

new MT_Refund_Manager();
