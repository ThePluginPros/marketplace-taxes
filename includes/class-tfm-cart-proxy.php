<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Cart Proxy.
 *
 * Provides a simple, backward-compatible interface to the WC Cart object.
 *
 * @author Brett Porcelli
 */
class TFM_Cart_Proxy {

    /**
     * @var WC_Cart The cart object being wrapped
     */
    protected $cart = null;

    /**
     * @var bool Should the WooCommerce 3.2+ API be used?
     */
    protected $use_new_api = false;

    /**
     * @var string Tax rate ID for this cart
     */
    protected $tax_rate_id = '';

    /**
     * Constructor.
     *
     * @param WC_Cart $cart Cart to wrap
     * @param string $tax_rate_id Tax rate ID for cart
     */
    public function __construct( $cart, $tax_rate_id ) {
        $this->cart        = $cart;
        $this->use_new_api = version_compare( WC_VERSION, '3.2', '>=' );
        $this->tax_rate_id = $tax_rate_id;
    }

    /**
     * Get cart taxes.
     *
     * @return array of cart taxes.
     */
    public function get_cart_taxes() {
        if ( $this->use_new_api ) {
            return wc_array_merge_recursive_numeric(
                $this->cart->get_cart_contents_taxes(),
                $this->cart->get_fee_taxes()
            );
        } else {
            return $this->cart->taxes;
        }
    }

    /**
     * Get shipping taxes.
     *
     * @return array of shipping taxes.
     */
    public function get_shipping_taxes() {
        if ( $this->use_new_api ) {
            return $this->cart->get_shipping_taxes();
        } else {
            return $this->cart->shipping_taxes;
        }
    }

    /**
     * Set cart tax amount.
     *
     * @param string $value Value to set.
     */
    public function set_cart_tax( $value ) {
        if ( $this->use_new_api ) {
            $this->cart->set_cart_contents_tax( $value );
        } else {
            $this->cart->tax_total = wc_round_tax_total( $value );
        }
    }

    /**
     * Set shipping tax.
     *
     * @param string $value Value to set.
     */
    public function set_shipping_tax( $value ) {
        if ( $this->use_new_api ) {
            $this->cart->set_shipping_tax( $value );
        } else {
            $this->cart->shipping_tax_total = wc_round_tax_total( $value );
        }
    }

    /**
     * Set the tax for a particular cart item.
     *
     * @param mixed $key cart item key.
     * @param float $tax sales tax for cart item.
     */
    public function set_cart_item_tax( $key, $tax ) {
        if ( $this->use_new_api ) {
            $cart_contents = $this->cart->get_cart_contents();
        } else {
            $cart_contents = $this->cart->cart_contents;
        }

        $tax_data = $cart_contents[ $key ]['line_tax_data'];

        $tax_data['subtotal'][ $this->tax_rate_id ] = $tax;
        $tax_data['total'][ $this->tax_rate_id ]    = $tax;

        $cart_contents[ $key ]['line_tax_data']     = $tax_data;
        $cart_contents[ $key ]['line_subtotal_tax'] = array_sum( $tax_data['subtotal'] );
        $cart_contents[ $key ]['line_tax']          = array_sum( $tax_data['total'] );

        if ( $this->use_new_api ) {
            $this->cart->set_cart_contents( $cart_contents );
        } else {
            $this->cart->cart_contents = $cart_contents;
        }
    }

    /**
     * Set the tax for a particular fee.
     *
     * @param mixed $id fee ID.
     * @param float $tax sales tax for fee.
     */
    public function set_fee_item_tax( $id, $tax ) {
        if ( $this->use_new_api ) {
            $fees = $this->cart->fees_api()->get_fees();
        } else {
            $fees = $this->cart->fees;
        }

        $fees[ $id ]->tax_data[ $this->tax_rate_id ] = $tax;
        $fees[ $id ]->tax                            = array_sum( $fees[ $id ]->tax_data );

        if ( $this->use_new_api ) {
            $this->cart->fees_api()->set_fees( $fees );
        } else {
            $this->cart->fees = $fees;
        }
    }

    /**
     * Set the tax for a shipping package.
     *
     * @param mixed $key package key.
     * @param float $tax sales tax for package.
     */
    public function set_package_tax( $key, $tax ) {
        $packages = WC()->shipping()->get_packages();

        if ( ! isset( $packages[ $key ] ) ) {
            return;
        }

        $chosen_methods = WC()->session->get( 'chosen_shipping_methods', [] );

        if ( ! isset( $chosen_methods[ $key ] ) ) {
            return;
        }

        $use_new_api = version_compare( WC_VERSION, '3.2', '>=' );
        $method      = $chosen_methods[ $key ];

        if ( isset( $packages[ $key ]['rates'][ $method ] ) ) {
            $rate = $packages[ $key ]['rates'][ $method ];

            if ( $use_new_api ) {
                $taxes = $rate->get_taxes();
            } else {
                $taxes = $rate->taxes;
            }

            $taxes[ $this->tax_rate_id ] = $tax;

            if ( $use_new_api ) {
                $rate->set_taxes( $taxes );
            } else {
                $rate->taxes = $taxes;
            }
        }

        WC()->shipping()->packages = $packages;
    }

    /**
     * Set the tax amount for a given tax rate.
     *
     * @param string $tax_rate_id ID of the tax rate to set taxes for.
     * @param float $amount
     */
    public function set_tax_amount( $tax_rate_id, $amount ) {
        if ( $this->use_new_api ) {
            $taxes                 = $this->cart->get_cart_contents_taxes();
            $taxes[ $tax_rate_id ] = $amount;
            $this->cart->set_cart_contents_taxes( $taxes );
        } else {
            $this->cart->taxes[ $tax_rate_id ] = $amount;
        }
    }

    /**
     * Set the shipping tax amount for a given tax rate.
     *
     * @param string $tax_rate_id ID of the tax rate to set shipping taxes for.
     * @param float $amount
     */
    public function set_shipping_tax_amount( $tax_rate_id, $amount ) {
        if ( $this->use_new_api ) {
            $taxes                 = $this->cart->get_shipping_taxes();
            $taxes[ $tax_rate_id ] = $amount;
            $this->cart->set_shipping_taxes( $taxes );
        } else {
            $this->cart->shipping_taxes[ $tax_rate_id ] = $amount;
        }
    }

    /**
     * Update tax totals based on tax arrays.
     */
    public function update_tax_totals() {
        $this->set_cart_tax( WC_Tax::get_tax_total( $this->get_cart_taxes() ) );
        $this->set_shipping_tax( WC_Tax::get_tax_total( $this->get_shipping_taxes() ) );
    }

    /**
     * Forward calls to inaccessible methods to the underlying cart object.
     *
     * @param string $name name of method being called.
     * @param array $args parameters of method.
     *
     * @return mixed
     */
    public function __call( $name, $args ) {
        return call_user_func_array( array( $this->cart, $name ), $args );
    }

    /**
     * Forward read requests for inaccessible properties to the underlying cart object.
     *
     * @param string $name name of property being read.
     *
     * @return mixed
     */
    public function __get( $name ) {
        return $this->cart->$name;
    }

    /**
     * Forward write requests for inaccessible properties to the underlying cart object.
     *
     * @param string $name name of property being written to.
     * @param mixed $value value being written.
     */
    public function __set( $name, $value ) {
        $this->cart->$name = $value;
    }

}
