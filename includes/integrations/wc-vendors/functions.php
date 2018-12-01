<?php

/**
 * WC Vendors integration functions.
 */

/**
 * Returns the IDs of all WC Vendors dashboard pages.
 *
 * @return array
 */
function mt_wcv_get_dashboard_page_ids() {
    return array_merge(
        [
            get_option( 'wcvendors_vendor_dashboard_page_id' ),
            get_option( 'wcvendors_shop_settings_page_id' ),
        ],
        (array) get_option( 'wcvendors_dashboard_page_id' )
    );
}

/**
 * Checks whether a page is a WC Vendors Pro dashboard page.
 *
 * @param int $page_id Optional page ID. Defaults to current page ID.
 *
 * @return bool
 */
function mt_wcv_is_dashboard_page( $page_id = 0 ) {
    if ( ! $page_id ) {
        $page_id = get_the_ID();
    }

    $dashboard_page_ids = (array) get_option( 'wcvendors_dashboard_page_id', [] );

    return in_array( $page_id, $dashboard_page_ids );
}

/**
 * Returns the IDS of the products associated with a particular shipping item.
 *
 * @param WC_Order_Item_Shipping $shipping_item
 *
 * @return array
 */
function mt_wcv_get_shipped_product_ids( $shipping_item ) {
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
