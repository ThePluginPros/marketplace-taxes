<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Store Form.
 *
 * Defines methods to output the fields for the store tax settings form.
 *
 * @author  Brett Porcelli
 * @package WCV_Taxes
 * @since   0.0.1
 */
class WCV_Taxes_Store_Form {

    /**
     * @var int Vendor ID.
     */
    private static $vendor_id;

    /**
     * Initialize vendor ID.
     *
     * @since 0.0.1
     */
    public static function init() {
        self::$vendor_id = get_current_user_id();
    }

    /**
     * Output the form.
     *
     * @since 0.0.1
     */
    public static function output() {
        wp_enqueue_style( 'tax-settings', WCV_TAX_URL . '/assets/css/settings.css', array(), WCV_TAX_VERSION );

        wp_enqueue_script( 'tax-settings', WCV_TAX_URL . '/assets/js/settings.js', array( 'jquery' ), WCV_TAX_VERSION );
        
        wp_localize_script( 'tax-settings', 'wcv_tax_localize_settings', array(
            'strings' => array(
                'hide_details' => __( 'Hide details', 'wcv-taxes' ),
                'show_details' => __( 'Show details', 'wcv-taxes' ),
            ),
        ) );
        
        wc_get_template( 'tax-settings.php', array(), 'wc-vendors/dashboard/', WCV_TAX_PATH . '/templates/dashboard/' );
    }

    /**
     * Output the Enabled field.
     *
     * @since 0.0.1
     */
    public static function enabled() {
        if ( WC_Vendors::$pv_options->get_option( 'force_tax_collection' ) ) {
            return; // Always enabled if collection is forced
        }

        WCVendors_Pro_Form_Helper::input( array(
            'id'          => 'vt_enabled',
            'type'        => 'checkbox',
            'label'       => __( 'Enabled?', 'wcv-taxes' ),
            'description' => __( 'Enable tax calculations.', 'wcv-taxes' ),
            'desc_tip'    => true,
            'value'       => get_user_meta( self::$vendor_id, 'vt_enabled', true ),
        ) );
    }

    /**
     * Get enabled calculation methods.
     *
     * @since 0.0.1
     */
    private static function get_calculation_methods() {
        $methods = array();
        
        foreach ( WCV_Taxes_Calculation::get_enabled_methods() as $id => $method ) {
            $methods[ $id ] = $method->get_name(); 
        }
        
        return $methods;
    }

    /**
     * Output the description for the Calculation Method field.
     *
     * @since 0.0.1
     */
    private static function calculation_method_description() {
        $methods = WCV_Taxes_Calculation::get_enabled_methods();

        echo '<dl class="wcv-tax-calculation-methods">';

        if ( 0 === count( $methods ) ) {
            echo '<dt>No methods available.</dt>';
            echo '<dd>Please contact the store owner for assistance.</dd>';
        } else {
            foreach ( $methods as $key => $method ) {
                echo '<dt>' . $method->get_name() . '</dt>';
                echo '<dd>' . $method->get_description() . '</dd>';
            }
        }

        echo '</dl>';
    }

    /**
     * Output the Calculation Method field.
     *
     * @since 0.0.1
     */
    public static function calculation_method() {
        WCVendors_Pro_Form_Helper::select( array(
            'id'               => 'vt_calculation_method',
            'label'            => __( 'Calculation Method', 'wcv-taxes' ),
            'description'      => __( 'The method to use for tax calculations. <a href="#" class="wcv-tax-toggle-calc-methods">Show details</a>.', 'wcv-taxes' ),
            'desc_tip'         => true,
            'options'          => self::get_calculation_methods(),
            'show_option_none' => __( 'Select one', 'wcv-taxes' ),
            'value'            => get_user_meta( self::$vendor_id, 'vt_calculation_method', true ),
        ) );

        self::calculation_method_description();
    }

    /**
     * Output the options for each enabled calculation method.
     *
     * @since 0.0.1
     */
    public static function calculation_method_options() {
        $enabled = WCV_Taxes_Calculation::get_enabled_methods();

        foreach ( $enabled as $id => $method ) {
            foreach ( $method->get_options() as $option ) {
                if ( isset( $option['admin'] ) && $option['admin'] ) {
                    continue;
                }

                $field_type = $option['type'];

                if ( in_array( $field_type, array( 'text', 'checkbox', 'radio', 'hidden' ) ) ) {
                    $callback = array( 'WCVendors_Pro_Form_Helper', 'input' );
                } else {
                    $callback = array( 'WCVendors_Pro_Form_Helper', $field_type );
                }

                if ( is_callable( $callback ) ) {
                    call_user_func_array( $callback, $option );
                }
            }
        }
    }

}

WCV_Taxes_Store_Form::init();