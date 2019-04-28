<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * WC Vendors order API class.
 *
 * Hooks into the MT_API_Orders controller to ensure that vendor sub order
 * uploading works.
 */
class MT_WC_Vendors_Order_API {

    /**
     * Constructor.
     */
    public function __construct() {
        add_action( 'mt_pre_get_vendor_orders', array( $this, 'filter_order_query' ) );
        add_filter( 'mt_vendor_order_clauses', array( $this, 'filter_query_clauses' ), 10, 2 );
    }

    /**
     * Filters the order query to ensure that vendors only see their own orders.
     *
     * @param WP_Query $query
     */
    public function filter_order_query( $query ) {
        $meta_query = $query->get( 'meta_query' );

        if ( ! is_array( $meta_query ) ) {
            $meta_query = [];
        }

        // Ensure that only completed sub orders are included in API responses
        if ( 'shop_order_vendor' === $query->get( 'post_type' ) ) {
            $meta_query[] = [
                'key'     => '_sub_order_version',
                'compare' => 'EXISTS',
            ];
        }

        $query->set( 'meta_query', $meta_query );

        add_filter( 'woocommerce_api_order_response', array( $this, 'filter_order_response' ), 10, 2 );
    }

    /**
     * Modifies the posts query to filter sub orders by parent order status.
     *
     * This is required because WC Vendors doesn't update the status of sub
     * orders when the parent order status is changed.
     *
     * @param array    $clauses
     * @param WP_Query $query
     *
     * @return array
     */
    public function filter_query_clauses( $clauses, $query ) {
        global $wpdb;

        $statuses = $query->get( 'post_status' );

        if ( ! empty( $statuses ) ) {
            $status_list = "'" . implode( "','", $statuses ) . "'";

            $clauses['join']  .= " INNER JOIN {$wpdb->posts} parent ON ( {$wpdb->posts}.post_parent = parent.ID )";
            $clauses['where'] .= " AND parent.post_status IN ( $status_list )";
        }

        return $clauses;
    }

    /**
     * Replaces the sub order number with the parent order number in API responses.
     *
     * @param array    $order_data Data for API response.
     * @param WC_Order $order      Order.
     *
     * @return array
     */
    public function filter_order_response( $order_data, $order ) {
        $parent = wc_get_order( $order->get_parent_id() );

        if ( $parent ) {
            $order_data['id']           = $parent->get_id();
            $order_data['order_number'] = $parent->get_order_number();
        }

        return $order_data;
    }

}

new MT_WC_Vendors_Order_API();
