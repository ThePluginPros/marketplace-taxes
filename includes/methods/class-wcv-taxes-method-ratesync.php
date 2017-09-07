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
     *
     * @param int $vendor_id (default: 0)
     */
    public function __construct( $vendor_id = 0 ) {
        parent::__construct( $vendor_id );

        $this->id             = 'ratesync';
        $this->name           = __( 'RateSync', 'wcv-taxes' );
        $this->affiliate_link = '#';
        $this->cost           = 'FREE';
        $this->description    = __( 'RateSync uses a combination of custom tax rules and <a href="http://taxrates.com" target="_blank">free sales tax tables</a> to perform tax calculations. Its accuracy is limited, although it may be useful in some cases.', 'wcv-taxes' );

        $this->init_form_fields();
    }

    /**
     * Initialize form fields.
     *
     * @since 0.0.1
     */
    protected function init_form_fields() {
        $this->form_fields['enabled'] = array(
            'name'  => __( 'Enabled', 'wcv-taxes' ),
            'id'    => 'ratesync_enabled',
            'type'  => 'checkbox',
            'std'   => false,
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