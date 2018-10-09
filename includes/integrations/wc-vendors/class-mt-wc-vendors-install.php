<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class MT_WC_Vendors_Install
 *
 * Handles installation of the WC Vendors integration.
 */
class MT_WC_Vendors_Install {

    /**
     * @var MT_WC_Vendors_Order_Updater Updater instance.
     */
    protected static $order_updater = null;

    public function __construct() {
        add_action( 'mt_load_integration', array( $this, 'init_order_updater' ), 5 );
        add_action( 'admin_init', array( $this, 'check_sub_orders' ) );
        add_action( 'admin_init', array( $this, 'maybe_update_sub_orders' ) );
    }

    /**
     * Initializes the order updater.
     */
    public function init_order_updater() {
        require_once __DIR__ . '/class-mt-wc-vendors-order-updater.php';

        self::$order_updater = new MT_WC_Vendors_Order_Updater();
    }

    /**
     * Checks whether the vendor sub orders need to be updated.
     */
    public function check_sub_orders() {
        if ( get_option( 'mt_sub_orders_checked' ) ) {
            return;
        }

        if ( 0 < sizeof( $this->get_orders_needing_update() ) ) {
            MT_WC_Vendors_Admin_Notices::add_notice( 'sub_order_update' );
        }

        update_option( 'mt_sub_orders_checked', true );
    }

    /**
     * Returns the sub orders that need updating.
     *
     * @return array Array of sub order IDs.
     */
    protected function get_orders_needing_update() {
        return wc_get_orders(
            [
                'type'       => 'shop_order_vendor',
                'meta_query' => [
                    [
                        'key'     => '_sub_order_version',
                        'compare' => 'NOT EXISTS',
                    ],
                ],
                'return'     => 'ids',
                'limit'      => -1,
            ]
        );
    }

    /**
     * Triggers the sub order update when an update button is pressed in WP admin.
     */
    public function maybe_update_sub_orders() {
        if ( isset( $_GET['mt_update_sub_orders'] ) && ! self::$order_updater->is_updating() ) {
            $this->update_sub_orders();
        }

        if ( isset( $_GET['mt_force_update_orders'] ) ) {
            $this->update_sub_orders();
            wp_safe_redirect( admin_url( 'admin.php?page=wcv-settings' ) );
            exit;
        }
    }

    /**
     * Starts the sub order update in the background.
     */
    protected function update_sub_orders() {
        $logger       = wc_get_logger();
        $order_queued = false;

        foreach ( $this->get_orders_needing_update() as $order_id ) {
            $logger->info( sprintf( 'Queueing %d', $order_id ), [ 'source' => 'mt_wcv_order_updates' ] );
            self::$order_updater->push_to_queue( $order_id );
            $order_queued = true;
        }

        if ( $order_queued ) {
            self::$order_updater->save()->dispatch();
        }
    }

}

new MT_WC_Vendors_Install();
