<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Store Form.
 *
 * Responsible for rendering the tax settings form in the Pro Dashboard and saving
 * the vendor's tax settings.
 *
 * @author  Brett Porcelli
 * @package WCV_Taxes
 * @since   0.0.1
 */
class WCV_Taxes_Store_Form {

    /**
     * @var int Vendor ID.
     */
    private $vendor_id = 0;

    /**
     * @var array Form fields.
     */
    private $fields = array();

    /**
     * Constructor. Registers action/filter hooks & initializes fields.
     *
     * @since 0.0.1
     */
    public function __construct() {
        $this->vendor_id = get_current_user_id();
        
        add_filter( 'wcv_store_tabs', array( $this, 'register_tab' ) );
        add_action( 'wcv_form_submit_before_store_save_button', array( $this, 'display' ) );
        add_action( 'wcv_pro_store_settings_saved', array( $this, 'save' ) );
        add_filter( 'wcv_tax_sanitize_field_enabled', array( $this, 'sanitize_enabled' ) );
        add_filter( 'wcv_tax_sanitize_field_nexus_addresses', array( $this, 'sanitize_nexus_addresses' ) );
        add_action( 'plugins_loaded', array( $this, 'init_fields' ) );
    }

    /**
     * Initialize form fields.
     *
     * @since 0.0.1
     */
    public function init_fields() {
        /**
         * Core fields
         */

        // Enable/disable tax collection
        if ( ! WC_Vendors::$pv_options->get_option( 'force_tax_collection' ) ) {
            $this->add_field( 'enabled', 'input', array(
                'type'        => 'checkbox',
                'label'       => __( 'Enabled?', 'wcv-taxes' ),
                'description' => __( 'Enable tax calculations.', 'wcv-taxes' ),
                'desc_tip'    => true,
                'default'     => 'yes',
            ) );
        }

        // Nexus addresses
        $this->add_field( 'nexus_addresses', 'address_table' );

        // Calculation method
        $this->add_field( 'calc_method', 'select', array(
            'label'            => __( 'Calculation Method', 'wcv-taxes' ),
            'description'      => __( 'The method to use for tax calculations. <a href="#" class="wcv-tax-toggle-calc-methods">Show details</a>.', 'wcv-taxes' ),
            'desc_tip'         => true,
            'options'          => $this->get_calculation_methods(),
            'show_option_none' => __( 'Select one', 'wcv-taxes' ),
        ) );

        $this->add_field( 'calc_method_description', 'html', array( $this, 'output_method_description' ) );

        /**
         * Method-specific fields
         */
        do_action( 'wcv_tax_init_store_form_fields', $this );
    }

    /**
     * Get a field's value, returning the specified default if no value
     * is specified.
     *
     * @since 0.0.1
     *
     * @param  string $id
     * @param  mixed $default (default: '')
     * @return mixed
     */
    private function get_field( $id, $default = '' ) {
        $p_id = 'vt_' . $id;

        if ( isset( $_POST['store_save_button'] ) ) {
            $posted = isset( $_POST[ $p_id ] ) ? $_POST[ $p_id ] : null;
            $value  = apply_filters( 'wcv_tax_sanitize_field_' . $id, $posted );
        } else {
            $value = get_user_meta( $this->vendor_id, $p_id, true );
        }

        if ( empty( $value ) ) {
            $value = $default;
        }

        return wp_unslash( $value );
    }

    /**
     * Add a field.
     *
     * @since 0.0.1
     *
     * @param string $id
     * @param string $type (input, select, textarea, address_table, html)
     * @param array $field (default: array())
     */
    public function add_field( $id, $type, $field = array() ) {
        // Automatically set id & value for common field types
        if ( in_array( $type, array( 'input', 'select', 'textarea' ) ) ) {
            $field['id']    = 'vt_' . $id;
            $field['value'] = $this->get_field( $id, isset( $field['default'] ) ? $field['default'] : null );
        }

        // Add field
        $this->fields[ $id ] = array(
            'type'    => $type,
            'options' => $field,
        );
    }

    /**
     * Add 'Tax' tab on store settings page.
     *
     * @since 0.0.1
     *
     * @param  array $tabs
     * @return array
     */
    public function register_tab( $tabs ) {
        if ( WCV_Vendors::is_vendor( $this->vendor_id ) ) {
            $tabs[ 'tax' ] = array(
                'label' => 'Tax',
                'target' => 'tax',
                'class' => array(),
            );
        }

        return $tabs;
    }

    /**
     * Display the 'Tax' tab.
     *
     * @since 0.0.1
     */
    public function display() {
        // Enqueue styles
        wp_enqueue_style( 'wcv-tax-settings', WCV_TAX_URL . '/assets/css/settings.css', array(), WCV_TAX_VERSION );

        // Enqueue scripts
        wp_enqueue_script( 'wcv-tax-calc-methods', WCV_TAX_URL . '/assets/js/calc-methods.js', array( 'jquery' ), WCV_TAX_VERSION );
        
        wp_localize_script( 'wcv-tax-calc-methods', 'wcv_tax_calc_methods_localize', array(
            'strings' => array(
                'show_details' => __( 'Show details', 'wcv-taxes' ),
                'hide_details' => __( 'Hide details', 'wcv-taxes' ),
            ),
        ) );

        // Output form
        echo '<div class="tabs-content hide-all" id="tax">';

        do_action( 'wcv_tax_before_store_form', $this );

        foreach ( $this->fields as $field ) {
            if ( in_array( $field['type'], array( 'input', 'select', 'textarea' ) ) ) {
                $cb = array( 'WCVendors_Pro_Form_Helper', $field['type'] );
            } else {
                $cb = array( $this, 'output_' . $field['type'] );
            }

            if ( is_callable( $cb ) ) {
                call_user_func( $cb,  $field['options'] );
            }
        }

        do_action( 'wcv_tax_after_store_form', $this );
        
        echo '</div>';
    }

    /**
     * Sanitize the 'enabled' seting.
     *
     * @since 0.0.1
     *
     * @param  mixed $value
     * @return string 'yes' or 'no'
     */
    public function sanitize_enabled( $value ) {
        if ( ! is_string( $value ) ) {
            return 'no';
        }
        return $value;
    }

    /**
     * Sanitize the 'nexus addresses' setting.
     *
     * @since 0.0.1
     *
     * @param  mixed $value
     * @return array
     */
    public function sanitize_nexus_addresses( $value ) {
        if ( ! is_array( $value ) ) {
            return array();
        }
        return $value;
    }

    /**
     * Save the vendor's tax settings when the store form is saved.
     *
     * @since 0.0.1
     *
     * @param int $vendor_id
     */
    public function save( $vendor_id ) {
        if ( ! WCV_Vendors::is_vendor( $vendor_id ) ) {
            return; // Not an approved vendor
        }

        do_action( 'wcv_tax_before_save_store_form', $vendor_id );

        foreach ( $this->fields as $key => $field ) {
            $p_key = 'vt_' . $key;

            // Get POSTed value
            $posted_value = isset( $_POST[ $p_key ] ) ? $_POST[ $p_key ] : null;

            // Sanitize
            $value = apply_filters( 'wcv_tax_sanitize_field_' . $key, $posted_value );

            // Save
            if ( ! is_null( $value ) ) {
                update_user_meta( $this->vendor_id, $p_key, $value );
            } else {
                delete_user_meta( $this->vendor_id, $p_key );
            }
        }

        do_action( 'wcv_tax_after_save_store_form', $vendor_id );
    }

    /**
     * Get enabled calculation methods.
     *
     * @since 0.0.1
     */
    private function get_calculation_methods() {
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
    private function output_method_description() {
        $methods = WCV_Taxes_Calculation::get_enabled_methods();

        // Method list
        echo '<dl class="wcv-tax-calculation-methods">';

        if ( 0 === count( $methods ) ) {
            printf( '<dt>%s</dt>', __( 'No methods available.', 'wcv-taxes' ) );
            printf( '<dd>%s</dd>', __( 'Please contact the store owner for assistance.', 'wcv-taxes' ) );
        } else {
            foreach ( $methods as $key => $method ) {
                echo '<dt>' . $method->get_name() . '</dt>';
                echo '<dd>' . $method->get_description() . '</dd>';
            }
        }

        echo '</dl>';
    }

    /**
     * Output custom HTML.
     *
     * @since 0.0.1
     *
     * @param callable $cb Callback to generate HTML.
     */
    private function output_html( $cb ) {
        if ( is_callable( $cb ) ) {
            call_user_func( $cb );
        }
    }

    /**
     * Output address table.
     *
     * @since 0.0.1
     *
     * @param array $options (unused)
     */
    private function output_address_table( $options ) {
        wp_enqueue_script( 'wcv-tax-address-table', WCV_TAX_URL . '/assets/js/address-table.js', array( 'jquery', 'wp-util', 'underscore', 'backbone', 'wcv-country-select' ), WCV_TAX_VERSION );
        wp_localize_script( 'wcv-tax-address-table', 'wcv_tax_address_table_localize', array(
            'addresses' => $this->get_field( 'nexus_addresses', array() ),
        ) );
    
        wc_get_template( 'field-address-table.php', array(
            'countries' => ( WC()->countries->get_allowed_countries() ) ? WC()->countries->get_allowed_countries() : WC()->countries->get_shipping_countries(),
        ), 'wc-vendors/dashboard/', WCV_TAX_PATH . '/templates/dashboard/' );
    }

}

new WCV_Taxes_Store_Form();