<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * TaxJar calculation method.
 *
 * Uses TaxJar for tax calculation, reporting, and filing.
 *
 * @author  Brett Porcelli
 * @package WCV_Taxes
 * @since   0.0.1
 */
class WCV_Taxes_Method_TaxJar extends WCV_Taxes_Calculation_Method {

    /**
     * Constructor.
     *
     * @since 0.0.1
     *
     * @param int $vendor_id (default: 0)
     */
    public function __construct( $vendor_id = 0 ) {
        parent::__construct( $vendor_id );

        $this->id             = 'taxjar';
        $this->name           = __( 'TaxJar', 'wcv-taxes' );
        $this->affiliate_link = '#';
        $this->cost           = sprintf( '$19.99/%s', __( 'month', 'wcv-taxes' ) );
        $this->description    = __( 'TaxJar is an easy-to-use tax reporting and calculation engine for small business owners and sales tax professionals. It is by far the most accurate tax calculation method available.', 'wcv-taxes' );

        $this->init_form_fields();
        $this->init_vendor_form_fields();
    }

    /**
     * Initialize form fields.
     *
     * @since 0.0.1
     */
    protected function init_form_fields() {
        $this->form_fields['enabled'] = array(
            'name'  => __( 'Enabled', 'wcv-taxes' ),
            'id'    => 'taxjar_enabled',
            'type'  => 'checkbox',
            'std'   => true,
        );

        $this->form_fields['api_key'] = array(
            'name'  => __( 'API Key', 'wcv-taxes' ),
            'id'    => 'taxjar_api_key',
            'type'  => 'text',
            'tip'   => __( 'Enter your TaxJar API key.', 'wcv-taxes' ),
            'desc'  => sprintf( '<a href="#" target="_blank">%s</a> | <a href="#" target="_blank">%s</a>', __( 'Create TaxJar account', 'wcv-taxes' ), __( 'Obtain API key', 'wcv-taxes' ) ),
        );

        $this->form_fields['who_pays'] = array(
            'name'  => __( 'Who Pays?', 'wcv-taxes' ),
            'id'    => 'taxjar_who_pays',
            'type'  => 'select',
            'options' => array(
                'marketplace' => __( 'Marketplace', 'wcv-taxes' ),
                'vendor'      => __( 'Vendor', 'wcv-taxes' ),
            ),
            'tip'   => __( 'Who pays for tax calculations during checkout?', 'wcv-taxes' ),
            'std'   => 'marketplace',
        );
    }

    /**
     * Initialize vendor form fields.
     *
     * @since 0.0.1
     */
    protected function init_vendor_form_fields() {
        $this->vendor_form_fields['taxjar_api_key'] = array(
            'id'                => 'wcv_taxes_taxjar_api_key',
            'type'              => 'text',
            'label'             => __( 'TaxJar API Key <small>Required</small>', 'wcv-taxes' ),
            'description'       => __( 'You can find your API key <a href="#" target="_blank">here</a>.', 'wcv-taxes' ),
            'desc_tip'          => true,
            'wrapper_start'     => '<div class="wcv-tax-hidden show-if-calc_method-taxjar">',
            'wrapper_end'       => '</div>',
            'custom_attributes' => array(
                'data-rules' => 'required',
                'data-error' => __( 'Please enter your API key.', 'wcv-taxes' ),
            ),
        );
    }

    /**
     * Calculate the sales tax for a given package.
     *
     * @since 0.0.1
     *
     * @param  array $package
     * @return array
     */
    public function calculate_taxes( $package ) {
        // TODO
        return $package;
    }

}