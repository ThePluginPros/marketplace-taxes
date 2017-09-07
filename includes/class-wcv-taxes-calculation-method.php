<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Calculation Method.
 *
 * Base class extended by tax calculation methods.
 *
 * @author  Brett Porcelli
 * @package WCV_Taxes
 * @since   0.0.1
 */
abstract class WCV_Taxes_Calculation_Method {

    /**
     * @var string Method ID.
     */
    protected $id = '';

    /**
     * @var int Vendor ID.
     */
    protected $vendor_id = 0;

    /**
     * @var string Name.
     */
    protected $name = '';

    /**
     * @var string Affiliate link.
     */
    protected $affiliate_link = '#';

    /**
     * @var string Cost, e.g. '$19.99/month'
     */
    protected $cost = '';

    /**
     * @var string Description.
     */
    protected $description = '';

    /**
     * @var array Form fields.
     */
    protected $form_fields = array();

    /**
     * @var array Vendor specific form fields.
     */
    protected $vendor_form_fields = array();

    /**
     * @var array Settings.
     */
    protected $settings = array();

    /**
     * @var array Vendor specific settings.
     */
    protected $vendor_settings = array();

    /**
     * Constructor.
     *
     * @since 0.0.1
     *
     * @param int $vendor_id (default: 0)
     */
    public function __construct( $vendor_id = 0 ) {
        if ( $vendor_id ) {
            $this->vendor_id = $vendor_id;
        } else {
            $this->vendor_id = get_current_user_id();
        }
    }
    
    /**
     * Getter for ID.
     *
     * @since 0.0.1
     *
     * @return string
     */
    public function get_id() {
        return $this->id;
    }

    /**
     * Getter for Vendor ID.
     *
     * @since 0.0.1
     *
     * @return int
     */
    public function get_vendor_id() {
        return $this->vendor_id;
    }

    /**
     * Getter for name.
     *
     * @since 0.0.1
     *
     * @return string
     */
    public function get_name() {
        return $this->name;
    }

    /**
     * Getter for affiliate link.
     *
     * @since 0.0.1
     *
     * @return string
     */
    public function get_affiliate_link() {
        return $this->affiliate_link;
    }

    /**
     * Getter for cost.
     *
     * @since 0.0.1
     *
     * @return string
     */
    public function get_cost() {
        return $this->cost;
    }

    /**
     * Is the method enabled?
     *
     * @since 0.0.1
     *
     * @return bool
     */
    public function is_enabled() {
        return $this->get_option( 'enabled' );
    }

    /**
     * Getter for description.
     *
     * @since 0.0.1
     *
     * @return string
     */
    public function get_description() {
        return $this->description;
    }

    /**
     * Getter for form fields.
     *
     * @since 0.0.1
     *
     * @return array
     */
    public function get_form_fields() {
        return $this->form_fields;
    }

    /**
     * Getter for vendor form fields.
     *
     * @since 0.0.1
     *
     * @return array
     */
    public function get_vendor_form_fields() {
        return $this->vendor_form_fields;
    }

    /**
     * Initialize settings.
     *
     * @since 0.0.1
     */
    protected function init_settings() {
        $settings = array();

        foreach ( $this->get_form_fields() as $key => $field ) {
            $name             = $this->id . '_' . $key;
            $default          = isset( $field['std'] ) ? $field['std'] : false;
            $settings[ $key ] = WC_Vendors::$pv_options->get_option( $name, $default );
        }

        $this->settings = $settings;
    }

    /**
     * Initialize vendor settings.
     *
     * @since 0.0.1
     */
    protected function init_vendor_settings() {
        foreach ( $this->get_vendor_form_fields() as $key => $field ) {
            $id    = 'wcv_taxes_' . $key;
            $value = get_user_meta( $this->vendor_id, $id, true );

            if ( empty( $value ) && isset( $field['default'] ) ) {
                $value = $field['default'];    
            }

            $this->vendor_settings[ $key ] = $value;
        }
    }

    /**
     * Get a sitewide option, returning the default value if necessary.
     *
     * @since 0.0.1
     *
     * @param  string $option_name
     * @param  mixed $empty_value (default: '')
     * @return mixed
     */
    public function get_sitewide_option( $option_name, $empty_value = '' ) {
        $value = '';

        if ( empty( $this->settings ) ) {
            $this->init_settings();
        }

        if ( array_key_exists( $option_name, $this->settings ) ) {
            $value = $this->settings[ $option_name ];
        }

        return empty( $value ) ? $empty_value : $value;
    }

    /**
     * Get a vendor option, returning the default value if necessary.
     *
     * @since 0.0.1
     *
     * @param  string $option_name
     * @param  mixed $empty_value (default: '')
     * @return mixed
     */
    public function get_vendor_option( $option_name, $empty_value = '' ) {
        $value = '';

        if ( empty( $this->vendor_settings ) ) {
            $this->init_vendor_settings();
        }

        if ( array_key_exists( $option_name, $this->vendor_settings ) ) {
            $value = $this->vendor_settings[ $option_name ];
        }

        return empty( $value ) ? $empty_value : $value;
    }

    /**
     * Get an option, returning the default value if necessary. Prioritize
     * vendor settings over sitewide settings if applicable.
     *
     * @since 0.0.1
     *
     * @param  string $option_name
     * @param  mixed $empty_value (default: '')
     * @return mixed
     */
    public function get_option( $option_name, $empty_value = '' ) {
        if ( $this->vendor_id && array_key_exists( $option_name, $this->vendor_form_fields ) ) {
            return $this->get_vendor_option( $option_name, $empty_value );
        }

        return $this->get_sitewide_option( $option_name, $empty_value );
    }

    /**
     * Get admin settings HTML.
     *
     * @since 0.0.1
     *
     * @return string
     */
    public function get_admin_settings_html() {
        $settings_api = isset( WC_Vendors::$pv_options ) ? WC_Vendors::$pv_options : null;

        if ( is_null( $settings_api ) ) {
            return '';
        }

        ob_start();

        foreach ( $this->get_form_fields() as $field ) {
            $settings_api->settings_options_format( $field );
        }

        return '<table class="form-table">' . ob_get_clean() . '</table>';
    }

    /**
     * Initialize form fields.
     *
     * Set the form fields to display on the settings page.
     *
     * @since 0.0.1
     */
    protected function init_form_fields() { }

    /**
     * Calculate the sales tax for a given package.
     *
     * @since 0.0.1
     *
     * @param  array $package
     * @return array
     */
    abstract public function calculate_taxes( $package );

}