<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Checkout.
 *
 * Handles tax calculations during checkout.
 *
 * @author  Brett Porcelli
 * @package TaxJar_For_Marketplaces
 */
class TFM_Checkout {

    /**
     * Constructor. Registers action/filter hooks.
     */
    public function __construct() {
        add_action( 'woocommerce_new_order', array( $this, 'save_vendor_taxes_new' ) );
        add_action( 'woocommerce_resume_order', array( $this, 'save_vendor_taxes_resume' ) );
        add_filter( 'woocommerce_rate_label', array( $this, 'get_tax_label' ) );
        add_action( 'woocommerce_calculate_totals', array( $this, 'calculate_taxes' ), 99 );
        add_filter( 'woocommerce_product_is_taxable', array( $this, 'is_product_taxable' ), 10, 2 );
    }

    /**
     * Store vendor taxes array when a new order is added. 
     *
     * @param int $order_id
     */
    public function save_vendor_taxes_new( $order_id ) {
        if ( isset( WC()->session->vendor_taxes ) ) {
            update_post_meta( $order_id, '_vendor_taxes', WC()->session->vendor_taxes );
        }
    }

    /**
     * Store vendor taxes array when an existing order is resumed.
     *
     * @param  int $order_id
     */
    public function save_vendor_taxes_resume( $order_id ) {
        WC()->cart->calculate_totals(); // TODO: NECESSARY?

        if ( isset( WC()->session->vendor_taxes ) ) {
            update_post_meta( $order_id, '_vendor_taxes', WC()->session->vendor_taxes );
        }
    }

    /**
     * Hide percentages in tax row labels.
     *
     * @param  string $label
     * @return string
     */
    public function get_tax_label( $label ) {
        $pos = strpos( $label, '(' );
        if ( $pos !== false ) {
            return substr( $label, 0, $pos - 1 );
        }
        return $label;
    }

    /**
     * Calculate the sales tax for the current cart, applying appropriate rules
     * for each vendor.
     *
     * @param WC_Cart $cart
     */
    public function calculate_taxes( $cart ) {
        // Undo WooCommerce's work
        WC()->cart->remove_taxes();

        // Reset vendor taxes array
        WC()->session->vendor_taxes = array();

        $order_taxes = $shipping_taxes = $vendor_taxes = array();

        $cart_contents = WC()->cart->cart_contents;

        // Calculate tax for each item, vendor-wise
        foreach ( $cart_contents as $item_key => &$item ) {
            if ( ! TFM_Util::is_product_taxable( $item['product_id'] ) )
                continue;
            
            $vendor_user = TFM_Util::get_product_vendor( $item['product_id'] );
            $tax_state   = TFM_Util::get_vendor_tax_state( $vendor_user );
            $tax_zip     = TFM_Util::get_vendor_tax_zip( $vendor_user );

            if ( TFM_Util::get_state_type( $tax_state ) == 'orig' ) {
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

            $rates = WC_Tax::find_rates( $location );

            if ( $rates ) {
                $item['line_subtotal_tax'] = 0;
                $item['line_tax']          = 0;

                $sub_taxes = WC_Tax::calc_tax( $item['line_subtotal'], $rates );

                foreach ( $sub_taxes as $rate_id => $amount ) {
                    $item['line_subtotal_tax'] += $amount;
                }

                $taxes = WC_Tax::calc_tax( $item['line_total'], $rates );

                foreach ( $taxes as $rate_id => $amount ) {
                    $item['line_tax'] += $amount;
                    
                    if ( !isset( $order_taxes[ $rate_id ] ) )
                        $order_taxes[ $rate_id ] = 0;

                    $order_taxes[ $rate_id ] += $amount;

                    if ( !isset( $vendor_taxes[ $vendor_user ] ) )
                        $vendor_taxes[ $vendor_user ] = array( 'cart' => array(), 'shipping' => array() );

                    if ( !isset( $vendor_taxes[ $vendor_user ]['cart'][ $rate_id ] ) )
                        $vendor_taxes[ $vendor_user ]['cart'][ $rate_id ] = 0;

                    $vendor_taxes[ $vendor_user ]['cart'][ $rate_id ] += $amount;
                }

                $item['line_tax_data'] = array( 'total' => $taxes, 'subtotal' => $sub_taxes );
            }
        }

        WC()->cart->cart_contents = $cart_contents;

        // Calculate shipping tax, vendor-wise
        $vendor_shipping_costs = WC()->session->vendor_shipping_totals;

        if ( is_array( $vendor_shipping_costs ) ) {
            foreach ( $vendor_shipping_costs as $user_id => $cost ) {

                if ( $user_id !== false ) {
                    if ( ! TFM_Util::is_shipping_taxable( $user_id ) )
                        continue;

                    $tax_state = TFM_Util::get_vendor_tax_state( $user_id );
                    $tax_zip   = TFM_Util::get_vendor_tax_zip( $user_id );

                    if ( TFM_Util::get_state_type( $tax_state ) == 'orig' ) {
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
                        $items = 0;

                        foreach ( WC()->cart->cart_contents as $item_key => $data ) {
                            $vendor_user = TFM_Util::get_product_vendor( $data['product_id'] );

                            if ( $vendor_user == $user_id )
                                $items++;
                        }

                        $taxes = WC_Tax::calc_shipping_tax( $cost * $items, $rates );

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
        }

        // Update cart tax totals (@see WC_Cart::calculate_totals)
        if ( WC()->cart->round_at_subtotal ) {
            WC()->cart->tax_total          = WC_Tax::get_tax_total( $order_taxes );
            WC()->cart->shipping_tax_total = WC_Tax::get_tax_total( $shipping_taxes );
            WC()->cart->taxes              = array_map( array( 'WC_Tax', 'round' ), $order_taxes );
            WC()->cart->shipping_taxes     = array_map( array( 'WC_Tax', 'round' ), $shipping_taxes );

            foreach ( $vendor_taxes as $vendor_id => &$taxes ) {
                $taxes['cart'] = array_map( array( 'WC_Tax', 'round' ), $taxes['cart'] );
                $taxes['shipping'] = array_map( array( 'WC_Tax', 'round' ), $taxes['shipping'] );
            }
        } else {
            WC()->cart->tax_total          = array_sum( $order_taxes );
            WC()->cart->shipping_tax_total = array_sum( $shipping_taxes );
            WC()->cart->taxes              = $order_taxes;
            WC()->cart->shipping_taxes     = $shipping_taxes;
        }

        // Update vendor taxes array (used for reporting later)
        WC()->session->vendor_taxes = $vendor_taxes;
    }

    /**
     * Tell WooCommerce a vendor's items aren't taxable if they have not
     * explicitly enabled taxes.
     *
     * @param  bool $taxable
     * @param  WC_Product $product
     * @return bool
     */
    public function is_product_taxable( $taxable, $product ) {
        $vendor = WCV_Vendors::get_vendor_from_product( $product->ID );

        if ( -1 === $vendor ) {
            return $taxable;
        }

        return TFM_Util::does_vendor_collect_tax( $vendor );
    }

}

new TFM_Checkout();
