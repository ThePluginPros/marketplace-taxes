<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Integration for WC Vendors.
 */
class TFM_Integration_WC_Vendors {

    /**
     * Constructor.
     *
     * Registers action hooks and filters.
     */
    public function __construct() {
        $this->includes();

        add_filter( 'tfm_form_field_callback', array( $this, 'set_field_callback' ) );
    }

    /**
     * Includes all required files.
     */
    private function includes() {
        require_once __DIR__ . '/class-tfm-wc-vendors-form-helper.php';
        require_once __DIR__ . '/class-tfm-wc-vendors-admin.php';
        require_once __DIR__ . '/class-tfm-wc-vendors-dashboard.php';
        require_once __DIR__ . '/class-tfm-wc-vendors-settings-manager.php';
    }


    /**
     * Sets the callback for displaying vendor settings form fields.
     *
     * @return callable
     */
    public function set_field_callback() {
        return array( $this, 'display_field' );
    }

    /**
     * Displays a settings field in the admin or frontend context.
     *
     * @param array $field Field definition
     * @param string $context 'admin' or 'frontend'
     */
    public function display_field( $field, $context ) {
        $callback = null;

        switch ( $field['type'] ) {
            case 'text':
            case 'checkbox':
            case 'radio':
                $callback = array( 'TFM_WC_Vendors_Form_Helper', 'input' );
                break;

            case 'select':
            case 'textarea':
            case 'custom_field':
                $callback = array( 'TFM_WC_Vendors_Form_Helper', $field['type'] );
                break;
        }

        if ( is_callable( $callback ) ) {
            $callback( $field, $context );
        }
    }

}
