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
     */
    public function __construct() {
        $this->id                = 'taxjar';
        $this->name              = __( 'TaxJar', 'wcv-taxes' );
        $this->description       = __( 'Use <a href="#" target="_blank">TaxJar</a> for tax calculation, reporting, and filing.', 'wcv-taxes' );
        $this->admin_description = __( '<a href="#" target="_blank">TaxJar</a> is an easy-to-use tax reporting and calculation engine for small business owners and sales tax professionals. Pricing starts from only $19.99 per month for up to 1000 transactions. You may choose to cover the cost of tax calculations, or require vendors to pay for their own.', 'wcv-taxes' );
        $this->options           = array(
            array(
                'name'  => __( 'Enabled', 'wcv-taxes' ),
                'id'    => 'taxjar_enabled',
                'type'  => 'checkbox',
                'desc'  => __( 'Allow vendors to use TaxJar for tax calculations', 'wcv-tax' ),
                'std'   => true,
                'admin' => true,
            ),
            array(
                'name'  => __( 'API Key', 'wcv-taxes' ),
                'id'    => 'taxjar_api_key',
                'type'  => 'text',
                'tip'   => __( 'Enter your TaxJar API key.', 'wcv-taxes' ),
                'desc'  => sprintf( '<a href="#" target="_blank">%s</a> | <a href="#" target="_blank">%s</a>', __( 'Create TaxJar account', 'wcv-taxes' ), __( 'Obtain API key', 'wcv-taxes' ) ),
                'admin' => true,
            ),
            array(
                'name'  => __( 'Who Pays?', 'wcv-taxes' ),
                'id'    => 'taxjar_who_pays',
                'type'  => 'select',
                'options' => array(
                    'marketplace' => __( 'Marketplace', 'wcv-taxes' ),
                    'vendor'      => __( 'Vendor', 'wcv-taxes' ),
                ),
                'tip'   => __( 'Who pays for tax calculations during checkout?', 'wcv-taxes' ),
                'std'   => 'marketplace',
                'admin' => true,
            ),
        );

        parent::__construct();
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