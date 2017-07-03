<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Order.
 *
 * Handles the recalculation of order taxes.
 *
 * @author  Brett Porcelli
 * @package WCV_Taxes
 * @since   0.0.1
 */
class WCV_Taxes_Order {

    /**
     * Constructor. Registers action/filter hooks.
     *
     * @since 0.0.1
     */
    public function __construct() {
        add_action( 'wp_ajax_woocommerce_calc_line_taxes', array( $this, 'calc_line_taxes' ), 1 );
    }

    /**
     * Recalculate the sales tax for an order, applying appropriate rules for
     * each vendor.
     *
     * @since 0.0.1
     */
    public function order_recalculate_taxes() {
        // TODO: UPDATE FOR WC 3.0.X COMPATIBILITY
        global $wpdb;

        check_ajax_referer( 'calc-totals', 'security' );

        $order_id       = absint( $_POST['order_id'] );
        $items          = array();
        $country        = strtoupper( esc_attr( $_POST['country'] ) );
        $state          = strtoupper( esc_attr( $_POST['state'] ) );
        $postcode       = strtoupper( esc_attr( $_POST['postcode'] ) );
        $city           = sanitize_title( esc_attr( $_POST['city'] ) );
        $order          = wc_get_order( $order_id );
        $taxes          = array();
        $shipping_taxes = array();
        $vendor_taxes   = array();

        // Parse the jQuery serialized items
        parse_str( $_POST['items'], $items );

        // Prevent undefined warnings
        if ( ! isset( $items['line_tax'] ) ) {
            $items['line_tax'] = array();
        }
        if ( ! isset( $items['line_subtotal_tax'] ) ) {
            $items['line_subtotal_tax'] = array();
        }
        $items['order_taxes'] = array();

        // Action
        $items = apply_filters( 'woocommerce_ajax_calc_line_taxes', $items, $order_id, $country, $_POST );

        // Get items and fees taxes
        if ( isset( $items['order_item_id'] ) ) {
            $line_total = $line_subtotal = $order_item_tax_class = array();

            foreach ( $items['order_item_id'] as $item_id ) {
                $item_id                          = absint( $item_id );
                $line_total[ $item_id ]           = isset( $items['line_total'][ $item_id ] ) ? wc_format_decimal( $items['line_total'][ $item_id ] ) : 0;
                $line_subtotal[ $item_id ]        = isset( $items['line_subtotal'][ $item_id ] ) ? wc_format_decimal( $items['line_subtotal'][ $item_id ] ) : $line_total[ $item_id ];
                $order_item_tax_class[ $item_id ] = isset( $items['order_item_tax_class'][ $item_id ] ) ? sanitize_text_field( $items['order_item_tax_class'][ $item_id ] ) : '';
                $product_id                       = $order->get_item_meta( $item_id, '_product_id', true );

                // Don't perform a tax calculation if product is not taxable for this transaction
                if ( ! WCV_Taxes_Util::is_product_taxable( $product_id, $order_id ) ) 
                    continue;

                // Get product details
                if ( get_post_type( $product_id ) == 'product' ) {
                    $_product        = wc_get_product( $product_id );
                    $item_tax_status = $_product->get_tax_status();
                } else {
                    $item_tax_status = 'taxable';
                }

                if ( '0' !== $order_item_tax_class[ $item_id ] && 'taxable' === $item_tax_status ) {
                    $vendor_user = WCV_Taxes_Util::get_product_vendor( $product_id );
                    $tax_state   = WCV_Taxes_Util::get_vendor_tax_state( $vendor_user );
                    $tax_zip     = WCV_Taxes_Util::get_vendor_tax_zip( $vendor_user );

                    if ( WCV_Taxes_Util::get_state_type( $tax_state ) == 'orig' ) {
                        // Calculate tax based on vendor location
                        $location = array(
                            'country'  => 'US',
                            'state'    => $tax_state,
                            'city'     => '',
                            'postcode' => $tax_zip,
                        );
                    } else {
                        $location = WC_Tax::get_tax_location();

                        list( $country, $state, $postcode, $city ) = $location;
                        
                        $location = array(
                            'country'  => $country,
                            'state'    => $state,
                            'postcode' => $postcode,
                            'city'     => $city,
                        );
                    }

                    $tax_rates = WC_Tax::find_rates( $location );

                    $line_taxes          = WC_Tax::calc_tax( $line_total[ $item_id ], $tax_rates );
                    $line_subtotal_taxes = WC_Tax::calc_tax( $line_subtotal[ $item_id ], $tax_rates );

                    // Set the new line_tax
                    foreach ( $line_taxes as $_tax_id => $_tax_value ) {
                        $items['line_tax'][ $item_id ][ $_tax_id ] = $_tax_value;
                        
                        if ( !isset( $vendor_taxes[ $vendor_user ] ) )
                            $vendor_taxes[ $vendor_user ] = array( 'cart' => array(), 'shipping' => array() );

                        if ( !isset( $vendor_taxes[ $vendor_user ]['cart'][ $_tax_id ] ) )
                            $vendor_taxes[ $vendor_user ]['cart'][ $_tax_id ] = 0;

                        $vendor_taxes[ $vendor_user ]['cart'][ $_tax_id ] += $_tax_value;
                    }

                    // Set the new line_subtotal_tax
                    foreach ( $line_subtotal_taxes as $_tax_id => $_tax_value ) {
                        $items['line_subtotal_tax'][ $item_id ][ $_tax_id ] = $_tax_value;
                    }

                    // Sum the item taxes
                    foreach ( array_keys( $taxes + $line_taxes ) as $key ) {
                        $taxes[ $key ] = ( isset( $line_taxes[ $key ] ) ? $line_taxes[ $key ] : 0 ) + ( isset( $taxes[ $key ] ) ? $taxes[ $key ] : 0 );
                    }
                }
            }
        }

        // Get shipping taxes
        $vendor_shipping_costs = $order->vendor_shipping_totals;

        $shipping_tax   = 0;
        $shipping_total = 0;

        foreach ( $vendor_shipping_costs as $user_id => $cost ) {

            $shipping_total += $cost;

            if ( $user_id !== false ) {
                if ( ! WCV_Taxes_Util::is_shipping_taxable( $user_id ) )
                    continue;

                $tax_state = WCV_Taxes_Util::get_vendor_tax_state( $user_id );
                $tax_zip   = WCV_Taxes_Util::get_vendor_tax_zip( $user_id );

                if ( WCV_Taxes_Util::get_state_type( $tax_state ) == 'orig' ) {
                    // Calculate tax based on vendor location
                    $location = array(
                        'country'  => 'US',
                        'state'    => $tax_state,
                        'city'     => '',
                        'postcode' => $tax_zip,
                    );
                } else {
                    $location = WC_Tax::get_tax_location();

                    list( $country, $state, $postcode, $city ) = $location;
                
                    $location = array(
                        'country'  => $country,
                        'state'    => $state,
                        'postcode' => $postcode,
                        'city'     => $city,
                    );
                }

                $rates = WC_Tax::find_shipping_rates( $location );

                if ( $rates ) {
                    $taxes = WC_Tax::calc_shipping_tax( $cost, $rates );

                    foreach ( $taxes as $rate_id => $amount ) {
                        if ( !isset( $shipping_taxes[ $rate_id ] ) )
                            $shipping_taxes[ $rate_id ] = 0;

                        $shipping_taxes[ $rate_id ] += $amount;

                        if ( !isset( $vendor_taxes[ $user_id ] ) )
                            $vendor_taxes[ $user_id ] = array( 'cart' => array(), 'shipping' => array() );

                        if ( !isset( $vendor_taxes[ $user_id ]['shipping'][ $rate_id ] ) )
                            $vendor_taxes[ $user_id ]['shipping'][ $rate_id ] = 0;

                        $vendor_taxes[ $user_id ]['shipping'][ $rate_id ] += $amount;
                    }
                }
            }
        }

        // We assume a single shipping method for each order
        if ( isset( $items['shipping_method_id'] ) ) {
            $shipping_cost = $shipping_taxes = array();

            foreach ( $items['shipping_method_id'] as $item_id ) {
                $item_id                   = absint( $item_id );
                $shipping_cost[ $item_id ] = isset( $items['shipping_cost'][ $item_id ] ) ? wc_format_decimal( $items['shipping_cost'][ $item_id ] ) : 0;
        
                // Set the new shipping_taxes
                foreach ( $shipping_taxes as $_tax_id => $_tax_value ) {
                    $items['shipping_taxes'][ $item_id ][ $_tax_id ] = $_tax_value;
                }
            }
        }

        // Remove old tax rows
        $order->remove_order_items( 'tax' );

        // Add tax rows
        foreach ( array_keys( $taxes + $shipping_taxes ) as $tax_rate_id ) {
            $order->add_tax( $tax_rate_id, isset( $taxes[ $tax_rate_id ] ) ? $taxes[ $tax_rate_id ] : 0, isset( $shipping_taxes[ $tax_rate_id ] ) ? $shipping_taxes[ $tax_rate_id ] : 0 );
        }

        // Create the new order_taxes
        foreach ( $order->get_taxes() as $tax_id => $tax_item ) {
            $items['order_taxes'][ $tax_id ] = absint( $tax_item['rate_id'] );
        }

        // Save order items
        wc_save_order_items( $order_id, $items );

        // Update vendor taxes array
        if ( get_option( 'woocommerce_tax_round_at_subtotal' ) == 'yes' ) {
            foreach ( $vendor_taxes as $vendor_id => &$taxes ) {
                $taxes['cart'] = array_map( array( 'WC_Tax', 'round' ), $taxes['cart'] );
                $taxes['shipping'] = array_map( array( 'WC_Tax', 'round' ), $taxes['shipping'] );
            }
        }

        update_post_meta( $order_id, '_vendor_taxes', $vendor_taxes );

        // Return HTML items
        $order = wc_get_order( $order_id );
        $data  = get_post_meta( $order_id );
        include( ABSPATH .'/'. PLUGINDIR .'/woocommerce/includes/admin/meta-boxes/views/html-order-items.php' );

        die();
    }

}

new WCV_Taxes_Order();