<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Tax Calculation class.
 *
 * Manages available tax calculation methods and performs tax calculations.
 *
 * @author  Brett Porcelli
 * @package WCV_Taxes
 * @since   0.0.1
 */
class WCV_Taxes_Calculation {

    /**
     * @var array Calculation methods.
     */
    protected static $methods = array();

    /**
     * Constructor.
     * 
     * @since 0.0.1
     */
    public function __construct() {
        self::load_methods();

        add_action( 'woocommerce_calculate_totals', array( __CLASS__, 'calculate_tax_totals' ) );
    }

    /**
     * Load calculation methods.
     *
     * @since 0.0.1
     */
    public static function load_methods() {
        if ( ! class_exists( 'WCV_Taxes_Method_TaxJar' ) ) {
            require 'methods/class-wcv-taxes-method-taxjar.php';
        }
        if ( ! class_exists( 'WCV_Taxes_Method_RateSync' ) ) {
            require 'methods/class-wcv-taxes-method-ratesync.php';
        }
        
        self::$methods = array(
            'taxjar'   => new WCV_Taxes_Method_TaxJar(),
            'ratesync' => new WCV_Taxes_Method_RateSync(),
        );
    }

    /**
     * Get a calculation method by ID.
     *
     * @since 0.0.1
     *
     * @param  string $method_id
     * @return WCV_Taxes_Calculation_Method | NULL
     */
    public static function get_method( $method_id ) {
        if ( array_key_exists( $method_id, self::$methods ) ) {
            return self::$methods[ $method_id ];
        }
        return NULL;
    }

    /**
     * Get calculation methods.
     *
     * @since 0.0.1
     *
     * @return array
     */
    public static function get_methods() {
        return self::$methods;
    }

    /**
     * Get calculation methods formatted for display on settings page.
     *
     * @since 0.0.1
     *
     * @return array
     */
    public static function get_methods_formatted() {
        $methods = array();

        foreach ( self::get_methods() as $id => $method ) {
            $methods[ $id ] = array(
                'id'             => $id,
                'name'           => $method->get_name(),
                'affiliate_link' => $method->get_affiliate_link(),
                'cost'           => $method->get_cost(),
                'description'    => $method->get_description(),
                'enabled'        => $method->is_enabled() ? 'yes' : 'no',
                'settings_html'  => $method->get_admin_settings_html(),
            );
        }

        return $methods;
    }

    /**
     * Get enabled calculation methods.
     *
     * @since 0.0.1
     *
     * @return array
     */
    public static function get_enabled_methods() {
        $enabled = array();

        foreach ( self::$methods as $id => $method ) {
            if ( $method->is_enabled() ) {
                $enabled[ $id ] = $method;
            }
        }

        return $enabled;
    }

    /**
     * Calculate the tax totals for the given cart.
     *
     * @since 0.0.1
     *
     * @param WC_Cart $cart
     */
    public function calculate_tax_totals( $cart ) {
        // TODO: calculate tax totals, using correct method for each vendor
    }

}

new WCV_Taxes_Calculation();