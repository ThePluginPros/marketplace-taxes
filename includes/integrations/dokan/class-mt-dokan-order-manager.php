<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Dokan order manager.
 */
class MT_Dokan_Order_Manager {

    /**
     * Constructor.
     */
    public function __construct() {
        add_action( 'woocommerce_order_before_calculate_taxes', array( $this, 'maybe_restore_order_taxes' ), 10, 2 );
    }

    /**
     * Saves and restores the calculated taxes for vendor sub orders.
     *
     * This is required to prevent Dokan from removing all sub order
     * tax lines.
     *
     * @param array $args
     * @param WC_Order $order
     */
    public function maybe_restore_order_taxes( $args, $order ) {
        if ( 'dokan' !== $order->get_created_via() ) {
            return;
        }

        $mt_tax_rates = [];
        foreach ( $order->get_taxes() as $tax ) {
            if ( 0 === strpos( $tax->get_rate_code(), 'TFM' ) ) {
                $mt_tax_rates[] = $tax->get_rate_id();
            }
        }

        if ( empty( $mt_tax_rates ) ) {
            return;
        }

        // Save taxes
        $item_taxes = [];
        foreach ( $order->get_items( [ 'line_item', 'fee', 'shipping' ] ) as $item_id => $item ) {
            $item_taxes[ $item_id ] = $item->get_taxes();
        }

        // Restore after order totals are recalculated
        $restore_callback = function ( $order_id ) use ( &$restore_callback, $mt_tax_rates, $item_taxes ) {
            foreach ( $mt_tax_rates as $tax_rate_id ) {
                foreach ( $item_taxes as $item_id => $taxes ) {
                    $item      = WC_Order_Factory::get_order_item( $item_id );
                    $new_taxes = $item->get_taxes();

                    foreach ( array_keys( $new_taxes ) as $total_type ) {
                        if ( isset( $taxes[ $total_type ][ $tax_rate_id ] ) ) {
                            $new_taxes[ $total_type ][ $tax_rate_id ] = $taxes[ $total_type ][ $tax_rate_id ];
                        }
                    }

                    $item->set_taxes( $new_taxes );
                    $item->save();
                }
            }

            $order = wc_get_order( $order_id );

            $order->update_taxes();
            $order->calculate_totals( false );

            remove_action( 'dokan_checkout_update_order_meta', $restore_callback );
        };

        add_action( 'dokan_checkout_update_order_meta', $restore_callback );
    }

}

new MT_Dokan_Order_Manager();
