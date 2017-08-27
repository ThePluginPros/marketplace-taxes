<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * RateSync calculation method.
 *
 * Uses custom tax calculation rules and the latest tax tables from TaxRates.com
 * to calculate sales tax.
 *
 * @author  Brett Porcelli
 * @package WCV_Taxes
 * @since   0.0.1
 */
class WCV_Taxes_Method_RateSync extends WCV_Taxes_Calculation_Method {

    /**
     * Constructor.
     *
     * @since 0.0.1
     */
    public function __construct() {
        $this->id                = 'ratesync';
        $this->name              = __( 'RateSync', 'wcv-taxes' );
        $this->description       = __( 'Use <a href="http://taxrates.com" target="_blank">free sales tax tables</a> to perform tax calculations.', 'wcv-taxes' );
        $this->admin_description = __( '<a href="https://wcratesync.com" target="_blank">RateSync</a> extends the WooCommerce tax system to support multi-nexus tax collection. It uses a combination of custom tax rules and rates from TaxRates.com to perform tax calculations. RateSync is free to use, although it is far less accurate than TaxJar.', 'wcv-taxes' );
        $this->options           = array(
            array(
                'name'  => __( 'Enabled', 'wcv-taxes' ),
                'id'    => 'ratesync_enabled',
                'type'  => 'checkbox',
                'desc'  => __( 'Allow vendors to use RateSync for tax calculations', 'wcv-tax' ),
                'std'   => false,
                'admin' => true,
            ),
        );

        // TODO: IF ENABLED, REGISTER USER SETTINGS
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