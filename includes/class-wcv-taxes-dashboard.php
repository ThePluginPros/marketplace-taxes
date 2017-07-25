<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Dashboard.
 *
 * Responsible for rendering the settings form in the Pro Dashboard and saving
 * the vendor's tax settings.
 *
 * @author  Brett Porcelli
 * @package WCV_Taxes
 * @since   0.0.1
 */
class WCV_Taxes_Dashboard {

    /**
     * Constructor. Registers action/filter hooks.
     *
     * @since 0.0.1
     */
    public function __construct() {
        add_filter( 'wcv_store_tabs', array( $this, 'add_tax_tab' ) );
        add_action( 'wcv_form_submit_before_store_save_button', array( $this, 'output_tax_tab' ) );
        add_action( 'wcv_pro_store_settings_saved', array( $this, 'save_tax_settings' ) );
    }

    /**
     * Add 'Tax' tab on store settings page.
     *
     * @since 0.0.1
     *
     * @param  array $tabs
     * @return array
     */
    public function add_tax_tab( $tabs ) {
        $user_id = get_current_user_id();

        if ( WCV_Vendors::is_vendor( $user_id ) ) // PREVENT TAB FROM BEING DISPLAYED DURING SIGN UP
        {
            $tabs[ 'tax' ] = array(
                'label' => 'Tax',
                'target' => 'tax',
                'class' => array(),
            );
        }

        return $tabs;
    }

    /**
     * Output the content for the tax settings tab.
     *
     * @since 0.0.1
     */
    public function output_tax_tab() {
        require 'forms/class-wcv-taxes-store-form.php';
        
        WCV_Taxes_Store_Form::output();
    }

    /**
     * Save the vendor's tax settings when the store form is saved.
     *
     * @since 0.0.1
     *
     * @param int $vendor_id
     */
    public function save_tax_settings( $vendor_id ) {
        if ( WCV_Vendors::is_vendor( $vendor_id ) ) // DON'T SAVE SETTINGS WHEN SIGN UP FORM SUBMITTED
        {
            $collect_tax = (int) isset( $_POST[ 'collect_tax' ] );
            update_user_meta( $vendor_id, 'collect_tax', $collect_tax );
        }
    }

}

new WCV_Taxes_Dashboard();