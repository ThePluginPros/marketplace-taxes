<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Utilities.
 *
 * A collection of helper functions used during tax calculations.
 *
 * @author  Brett Porcelli
 * @package WCV_Taxes
 * @since   0.0.1
 */
class WCV_Taxes_Util {

    /**
     * Return the given vendor's tax state. If no tax state is configured,
     * fall back to the state of the vendor's billing address, or the shop
     * base state (in that order).
     *
     * @since 0.0.1
     *
     * @param  int $vendor_id (default: 0)
     * @return string
     */
    public static function get_vendor_tax_state( $vendor_id = 0 ) {
        // TODO: UPDATE TO ALLOW MULTIPLE TAX STATES
        if ( ! $vendor_id ) {
            $vendor_id = get_current_user_id();
        }

        $state = get_user_meta( $vendor_id, 'tax_state', true );
        
        if ( ! $state ) {
            $state = get_user_meta( $vendor_id, 'billing_state', true );

            if ( ! $state ) {
                $state = WC()->countries->get_base_state();
            }
        }

        return $state;
    }

    /**
     * Return the given vendor's tax ZIP/postcode. Use the configured billing
     * postcode and shop base postcode as fallbacks.
     *
     * @since 0.0.1
     *
     * @param  int $vendor_id (default: 0)
     * @return string
     */
    public static function get_vendor_tax_zip( $vendor_id = 0 ) {
        // TODO: UPDATE TO ALLOW MULTIPLE TAX ZIPS
        if ( ! $vendor_id ) {
            $vendor_id = get_current_user_id();
        }

        $zip = get_user_meta( $vendor_id, 'tax_zip', true );

        if ( ! $zip ) {
            $zip = get_user_meta( $vendor_id, 'billing_postcode', true );

            if ( ! $zip ) {
                $zip = WC()->countries->get_base_postcode();
            }
        }

        return $zip;
    }

    /**
     * Does the given vendor collect tax?
     *
     * @since 0.0.1
     *
     * @param  int $vendor_id (default: 0)
     * @return bool
     */
    public static function does_vendor_collect_tax( $vendor_id = 0 ) {
        // TODO: APPROPRIATE/NEEDED?
        if ( ! $vendor_id ) {
            $vendor_id = get_current_user_id();
        }

        return get_user_meta( $vendor_id, 'collect_tax', true );
    }

    /**
     * Does the given state use origin sourcing or destination sourcing?
     *
     * @since 0.0.1
     *
     * @param  string $state State abbreviation.
     * @return string 'dest' or 'orig'
     */
    public static function get_state_type( $state ) {
        $origin_states = array( 'AZ', 'CA', 'IL', 'MS', 'MO', 'NM', 'OH', 'PA', 'TN', 'TX', 'UT', 'VI' );

        if ( in_array( $state, $origin_states ) ) {
            return 'orig';
        } else {
            return 'dest';
        }
    }

    /**
     * Return the customer's state.
     *
     * @since 0.0.1
     *
     * @param  int $order_id (default: 0)
     * @return string
     */
    public static function get_customer_state( $order_id = 0 ) {
        // TODO: USE DEST STATE FOR EACH SHIPPING PACKAGE
        if ( ! $order_id ) {
            $location = WC_Tax::get_tax_location();
            
            return $location[1]; // state is at index 1
        } else {
            $tax_based_on = get_option( 'woocommerce_tax_based_on' );

            switch ( $tax_based_on ) {
                case 'base':
                    $base = wc_get_base_location();
                    return $base['state'];
                case 'billing':
                    return get_post_meta( $order_id, '_billing_state', true );
                case 'shipping':
                    return get_post_meta( $order_id, '_shipping_state', true );
            }
        }
    }

    /**
     * Determine whether the current transaction is an interstate or intrastate
     * transaction for the given vendor.
     *
     * @since 0.0.1
     *
     * @param  int $vendor_id
     * @param  int $order_id (default: 0)
     * @return string 'interstate' or 'intrastate'
     */
    public static function get_transaction_type( $vendor_id, $order_id = 0 ) {
        $vendor_state   = self::get_vendor_tax_state( $vendor_uid );
        $customer_state = self::get_customer_state( $order_id );

        if ( $vendor_state != $customer_state ) {
            return 'interstate';
        } else {
            return 'intrastate';
        }
    }

    /**
     * Return the vendor ID for a given product.
     *
     * @since 0.0.1
     *
     * @param  int $product_id
     * @return int
     */
    public static function get_product_vendor( $product_id ) {
        return WCV_Vendors::get_vendor_from_product( $product_id );
    }

    /**
     * Should the given product be taxed?
     *
     * @since 0.0.1
     *
     * @param  int $product_id
     * @param  int $order_id (default: 0)
     * @return bool
     */
    public static function is_product_taxable( $product_id, $order_id = 0 ) {
        $user_id = self::get_product_vendor( $product_id );

        if ( ! self::does_vendor_collect_tax( $user_id ) ) {
            return false;
        } else if ( 'interstate' == self::get_transaction_type( $user_id, $order_id ) ) {
            return false;
        }

        return true;
    }

    /**
     * Should the shipping charges for the given vendor/order be taxed?
     *
     * @since 0.0.1
     *
     * @param  int $vendor_id
     * @param  int $order_id (default: 0)
     * @return bool
     */
    public static function is_shipping_taxable( $vendor_id, $order_id = 0 ) {
        $shipping_taxable = array( 'AR', 'CT', 'DC', 'FL', 'GA', 'HI', 'KS', 'KY', 'MI', 'MN', 'MS', 'NE', 'NJ', 'NM', 'NY', 'NC', 'ND', 'OH', 'PA', 'SC', 'SD', 'TN', 'TX', 'VT', 'WA', 'WV', 'WI', 'WY' );

        if ( ! self::does_vendor_collect_tax( $vendor_id ) ) {
            return false;
        } else if ( 'interstate' == self::get_transaction_type( $vendor_id, $order_id ) ) {
            return false;
        }

        $tax_state = self::get_vendor_tax_state( $vendor_id );

        return in_array( $tax_state, $shipping_taxable );
    }

}