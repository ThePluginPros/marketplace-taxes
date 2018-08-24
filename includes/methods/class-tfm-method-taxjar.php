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
 * @package TaxJar_For_Marketplaces
 */
class TFM_Method_TaxJar extends TFM_Calculation_Method {

    /**
     * Constructor.
     *
     * @param int $vendor_id (default: 0)
     */
    public function __construct( $vendor_id = 0 ) {
        parent::__construct( $vendor_id );

        $this->id             = 'taxjar';
        $this->name           = __( 'TaxJar', 'taxjar-for-marketplaces' );
        $this->affiliate_link = '#';
        $this->cost           = sprintf( '$19.99/%s', __( 'month', 'taxjar-for-marketplaces' ) );
        $this->description    = __(
            'TaxJar is an easy-to-use tax reporting and calculation engine for small business owners and sales tax professionals. It is by far the most accurate tax calculation method available.',
            'taxjar-for-marketplaces'
        );

        $this->init_form_fields();
        $this->init_vendor_form_fields();
    }

    /**
     * Initialize form fields.
     */
    protected function init_form_fields() {
        $this->form_fields['enabled'] = [
            'name' => __( 'Enabled', 'taxjar-for-marketplaces' ),
            'id'   => 'taxjar_enabled',
            'type' => 'checkbox',
            'std'  => true,
        ];

        $this->form_fields['api_key'] = [
            'name' => __( 'API Key', 'taxjar-for-marketplaces' ),
            'id'   => 'taxjar_api_key',
            'type' => 'text',
            'tip'  => __( 'Enter your TaxJar API key.', 'taxjar-for-marketplaces' ),
            'desc' => sprintf(
                '<a href="#" target="_blank">%s</a> | <a href="#" target="_blank">%s</a>',
                __( 'Create TaxJar account', 'taxjar-for-marketplaces' ),
                __( 'Obtain API key', 'taxjar-for-marketplaces' )
            ),
        ];

        $this->form_fields['who_pays'] = [
            'name'    => __( 'Who Pays?', 'taxjar-for-marketplaces' ),
            'id'      => 'taxjar_who_pays',
            'type'    => 'select',
            'options' => [
                'marketplace' => __( 'Marketplace', 'taxjar-for-marketplaces' ),
                'vendor'      => __( 'Vendor', 'taxjar-for-marketplaces' ),
            ],
            'tip'     => __( 'Who pays for tax calculations during checkout?', 'taxjar-for-marketplaces' ),
            'std'     => 'marketplace',
        ];
    }

    /**
     * Initialize vendor form fields.
     */
    protected function init_vendor_form_fields() {
        $this->vendor_form_fields['taxjar_api_key'] = [
            'id'                => 'wcv_taxes_taxjar_api_key',
            'type'              => 'text',
            'label'             => __( 'TaxJar API Key <small>Required</small>', 'taxjar-for-marketplaces' ),
            'description'       => __(
                'You can find your API key <a href="#" target="_blank">here</a>.',
                'taxjar-for-marketplaces'
            ),
            'desc_tip'          => true,
            'wrapper_start'     => '<div class="wcv-tax-hidden show-if-calc_method-taxjar">',
            'wrapper_end'       => '</div>',
            'custom_attributes' => [
                'data-rules' => 'required',
                'data-error' => __( 'Please enter your API key.', 'taxjar-for-marketplaces' ),
            ],
        ];
    }

    /**
     * Calculate the sales tax for a given package.
     *
     * @param array $package
     *
     * @return array
     */
    public function calculate_taxes( $package ) {
        // TODO
        return $package;
    }

}
