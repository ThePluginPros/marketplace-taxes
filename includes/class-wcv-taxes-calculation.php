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

    // TODO: HOOK wp_ajax_woocommerce_calc_line_taxes

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
        self::init_methods();

        add_action( 'woocommerce_calculate_totals', array( __CLASS__, 'calculate_tax_totals' ) );
    }

    /**
     * Initialize calculation methods.
     *
     * @since 0.0.1
     */
    protected static function init_methods() {
        require 'class-wcv-taxes-calculation-method.php';

        require 'calc-methods/class-wcv-taxes-method-taxjar.php';
        require 'calc-methods/class-wcv-taxes-method-ratesync.php';
        
        self::$methods = array(
            'taxjar'   => new WCV_Taxes_Method_TaxJar(),
            'ratesync' => new WCV_Taxes_Method_RateSync(),
        );
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