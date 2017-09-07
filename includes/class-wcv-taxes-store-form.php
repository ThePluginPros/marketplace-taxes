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
     * Get description for Calculation Method field.
     *
     * @since 0.0.1
     *
     * @return string
     */
    private function get_calc_method_description() {
        $html = '';

        foreach ( WCV_Taxes_Calculation::get_enabled_methods() as $method ) {
            $html .= sprintf( '<span class="wcv-tax-hidden show-if-calc_method-%s">%s</span>', $method->get_id(), $method->get_description() );
        }

        return $html;
    }

    /**
     * Get options for Calculation Method field.
     *
     * @since 0.0.1
     */
    private function get_calc_method_options() {
        $methods = array();
        
        foreach ( WCV_Taxes_Calculation::get_enabled_methods() as $id => $method ) {
            $methods[ $id ] = $method->get_name(); 
        }
        
        return $methods;
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
            $this->fields['enabled'] = array(
                'id'          => 'wcv_taxes_enabled',
                'type'        => 'checkbox',
                'label'       => __( 'Enabled?', 'wcv-taxes' ),
                'description' => __( 'Enable tax calculations.', 'wcv-taxes' ),
                'desc_tip'    => true,
                'default'     => 'yes',
            );
        }

        // Nexus addresses
        $this->fields['nexus_addresses'] = array( 
            'type' => 'address_table'
        );

        // Calculation method
        $this->fields['calc_method'] = array(
            'id'                => 'wcv_taxes_calc_method',
            'type'              => 'select',
            'label'             => __( 'Calculation Method <small>Required</small>', 'wcv-taxes' ),
            'description'       => $this->get_calc_method_description(),
            'desc_tip'          => true,
            'options'           => $this->get_calc_method_options(),
            'custom_attributes' => array(
                'data-rules' => 'required',
                'data-error' => __( 'Please select a calculation method.', 'wcv-taxes' ),
            ),
        );

        /**
         * Method-specific fields
         */
        foreach ( WCV_Taxes_Calculation::get_enabled_methods() as $method ) {
            $method_fields = $method->get_vendor_form_fields();

            foreach ( $method_fields as $field_id => &$field ) {
                $field['value'] = $method->get_vendor_option( $field_id );
            }

            $this->fields = array_merge( $this->fields, $method_fields );
        }
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
     * Get field name from field ID.
     *
     * @since 0.0.1
     *
     * @param  string $field_id
     * @return string
     */
    private function get_field_name( $field_id ) {
        return 'wcv_taxes_' . $field_id;
    }

    /**
     * Get a field's value, returning the specified default if necessary.
     *
     * @since 0.0.1
     *
     * @param  string $field_id
     * @param  mixed $default (default: '')
     * @return mixed
     */
    private function get_field_value( $field_id, $default = '' ) {
        $name = $this->get_field_name( $field_id );

        if ( isset( $_POST['store_save_button'] ) ) {
            $posted = isset( $_POST[ $name ] ) ? $_POST[ $name ] : null;
            $value  = apply_filters( 'wcv_tax_sanitize_field_' . $field_id, $posted );
        } else {
            $value = get_user_meta( $this->vendor_id, $name, true );
        }

        if ( empty( $value ) ) {
            $value = $default;
        }

        return wp_unslash( $value );
    }

    /**
     * Display the 'Tax' tab.
     *
     * @since 0.0.1
     */
    public function display() {
        // Enqueue scripts & styles
        wp_enqueue_style( 'wcv-tax-settings', WCV_TAX_URL . '/assets/css/settings.css', array(), WCV_TAX_VERSION );

        wp_enqueue_script( 'wcv-tax-settings', WCV_TAX_URL . '/assets/js/settings.js', array( 'jquery' ), WCV_TAX_VERSION );

        // Prepare fields for output
        foreach ( $this->fields as $field_id => &$field ) {
            if ( ! isset( $field['value'] ) ) {
                $field['value'] = $this->get_field_value( $field_id );
            }
        }

        // Output form
        wc_get_template( 'store-settings-form.php', array(
            'fields' => $this->fields,
        ), 'wc-vendors/dashboard/', WCV_TAX_PATH . '/templates/dashboard/' );
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

        foreach ( $this->fields as $field_id => $field ) {
            // Get sanitized value
            $value = $this->get_field_value( $field_id, null );

            // Save
            $meta_name = $this->get_field_name( $field_id );
            
            if ( ! is_null( $value ) ) {
                update_user_meta( $this->vendor_id, $meta_name, $value );
            } else {
                delete_user_meta( $this->vendor_id, $meta_name );
            }
        }
    }

}

new WCV_Taxes_Store_Form();