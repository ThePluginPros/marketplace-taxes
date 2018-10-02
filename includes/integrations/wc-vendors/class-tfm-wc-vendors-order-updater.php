<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class TFM_WC_Vendors_Order_Updater
 *
 * Updates existing vendor sub orders in the background.
 */
class TFM_WC_Vendors_Order_Updater extends WP_Background_Process {

    /**
     * Initiate new background process
     */
    public function __construct() {
        // Uses unique prefix per blog so each blog has separate queue
        $this->prefix = 'wp_' . get_current_blog_id();
        $this->action = 'tfm_wc_vendors_order_updater';

        parent::__construct();
    }

    /**
     * Dispatch
     *
     * @access public
     * @return void
     */
    public function dispatch() {
        $result = parent::dispatch();

        if ( is_wp_error( $result ) ) {
            wc_get_logger()->error(
                sprintf( 'Failed to dispatch sub order updater: %s', $result->get_error_message() ),
                [ 'source' => 'tfm_wcv_order_updates' ]
            );
        }
    }


    /**
     * Task
     *
     * Override this method to perform any actions required on each
     * queue item. Return the modified item for further processing
     * in the next pass through. Or, return false to remove the
     * item from the queue.
     *
     * @param int $order_id ID of sub order to update.
     *
     * @return mixed
     */
    protected function task( $order_id ) {
        wc_maybe_define_constant( 'TFM_UPDATING_ORDERS', true );

        $order = wc_get_order( $order_id );

        // Bail if the order has already been updated
        if ( $order->get_meta( '_sub_order_version', true ) ) {
            return false;
        }

        // Ensure tax data is set for line items and fees
        foreach ( $order->get_items( [ 'line_item', 'fee' ] ) as $item ) {
            $original_item_id = $item->get_meta( '_vendor_order_item_id', true );

            if ( ( $original_item = WC_Order_Factory::get_order_item( $original_item_id ) ) ) {
                $item->set_taxes( $original_item->get_taxes() );
                $item->save();
            }
        }

        // Update taxes and save
        $order->update_taxes();
        $order->calculate_totals( false );

        // Add shipping lines and set the order version
        do_action( 'tfm_vendor_order_created', $order->get_id() );

        wc_get_logger()->info( sprintf( 'Updated sub order #%s', $order_id ), [ 'source' => 'tfm_wcv_order_updates' ] );

        return false;
    }

    /**
     * Complete.
     *
     * Override if applicable, but ensure that the below actions are
     * performed, or, call parent::complete().
     */
    protected function complete() {
        parent::complete();

        wc_get_logger()->info( 'Sub order update complete.', [ 'source' => 'tfm_wcv_order_updates' ] );

        update_option( 'tfm_sub_orders_updated', true );
    }

    /**
     * Checks whether the updater is running.
     *
     * @return bool
     */
    public function is_updating() {
        return false === $this->is_queue_empty();
    }

}
