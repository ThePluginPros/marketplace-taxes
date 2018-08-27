<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once TFM()->path( 'includes/interface-tfm-settings-api.php' );
require_once TFM()->path( 'includes/class-tfm-download-orders.php' );

/**
 * Vendor settings form.
 *
 * Provides generic logic for displaying and saving the vendor settings form.
 */
class TFM_Vendor_Settings_Form implements TFM_Settings_API {

    /**
     * @var int ID of the vendor whose settings are being edited
     */
    protected $vendor_id;

    /**
     * @var string Admin or frontend context
     */
    protected $context;

    /**
     * @var array Settings fields
     */
    protected $form_fields = [];

    /**
     * @var TFM_Download_Orders Download orders instance
     */
    protected $download_orders;

    /**
     * Constructor.
     *
     * @param int $vendor_id ID of the vendor
     * @param string $context 'admin' or 'frontend'
     */
    public function __construct( $vendor_id = 0, $context = 'frontend' ) {
        if ( ! $vendor_id ) {
            $vendor_id = get_current_user_id();
        }

        $this->vendor_id       = $vendor_id;
        $this->context         = $context;
        $this->download_orders = new TFM_Download_Orders( $this );

        $this->init_form_fields();
    }

    /**
     * Initializes the form fields array.
     */
    private function init_form_fields() {
        $this->form_fields['nexus_addresses'] = apply_filters(
            'tfm_nexus_addresses_field',
            [
                'id'                => 'nexus_addresses',
                'type'              => 'custom_field',
                'path'              => TFM()->path( 'includes/views/html-field-address-table.php' ),
                'countries'         => $this->get_nexus_countries(),
                'context'           => $this->context,
                'title'             => __( 'Business Locations', 'taxjar-for-marketplaces' ),
                'description'       => __(
                    'Please enter all locations, including stores, warehouses, distribution facilities, etc.',
                    'taxjar-for-marketplaces'
                ),
                'sanitize_callback' => array( $this, 'validate_nexus_addresses' ),
            ]
        );

        if ( 'vendor' === TFM()->settings->get( 'merchant_of_record', 'vendor' ) ) {
            $this->form_fields['upload_transactions'] = apply_filters(
                'tfm_upload_transactions_field',
                array_merge(
                    $this->download_orders->get_form_settings_field(),
                    [
                        'id'    => 'upload_transactions',
                        'class' => 'input-toggle',
                        'title' => __( 'Use TaxJar for Reporting', 'taxjar-for-marketplaces' ),
                        'label' => __(
                            'Upload orders to <a href="https://thepluginpros.com/out/taxjar" target="_blank">TaxJar</a> for reporting',
                            'taxjar-for-marketplaces'
                        ),
                    ]
                )
            );

            $this->form_fields['taxjar_api_token'] = apply_filters(
                'tfm_taxjar_api_token_field',
                [
                    'id'                => 'taxjar_api_token',
                    'type'              => 'text',
                    'title'             => __( 'TaxJar API token', 'taxjar-for-marketplaces' ),
                    'description'       => __(
                        '<a href="https://thepluginpros.com/out/taxjar-api-token" target="_blank">Find your API token</a> | <a href="https://thepluginpros.com/out/taxjar" target="_blank">Register for TaxJar</a>',
                        'taxjar-for-marketplaces'
                    ),
                    'wrapper_class'     => 'show-if-upload_transactions-yes',
                    'sanitize_callback' => array( $this, 'validate_api_token' ),
                ]
            );
        }
    }

    /**
     * Gets the available countries for the nexus address table.
     */
    public function get_nexus_countries() {
        if ( WC()->countries->get_allowed_countries() ) {
            return WC()->countries->get_allowed_countries();
        } else {
            return WC()->countries->get_shipping_countries();
        }
    }

    /**
     * Returns the settings form description.
     *
     * @return string
     */
    public function description() {
        return apply_filters(
            'tfm_vendor_settings_form_description',
            __(
                'Please fill out the fields below to ensure that your customers are taxed correctly.',
                'taxjar-for-marketplaces'
            )
        );
    }

    /**
     * Displays the form fields.
     */
    public function fields() {
        $field_callback = apply_filters( 'tfm_form_field_callback', null );

        if ( ! is_callable( $field_callback ) ) {
            return;
        }

        TFM()->assets->enqueue( 'script', 'taxjar-for-marketplaces.input-toggle' );

        $this->init_settings();

        foreach ( $this->form_fields as $field_id => $field ) {
            if ( isset( $field['type'] ) ) {
                $field_callback( $field, $this->context );
            }
        }
    }

    /**
     * Sets the current value for all form fields.
     */
    private function init_settings() {
        foreach ( $this->form_fields as $field_id => &$field ) {
            if ( ! isset( $field['value'] ) ) {
                $field['value'] = get_user_meta( $this->vendor_id, $this->get_field_name( $field_id ), true );
            }
        }
    }

    /**
     * Get a field's POSTed and validated value.
     *
     * @param string $field_id Field key/ID
     * @param array $post_data
     *
     * @return mixed
     *
     * @throws Exception when field validation fails
     */
    private function get_field_value( $field_id, $post_data = [] ) {
        $field     = $this->form_fields[ $field_id ];
        $type      = $field['type'];
        $post_data = ! empty( $post_data ) ? $post_data : $_POST;
        $value     = isset( $post_data[ $field_id ] ) ? $post_data[ $field_id ] : null;

        if ( is_null( $value ) && isset( $field['default'] ) ) {
            $value = $field['default'];
        }

        if ( isset( $field['sanitize_callback'] ) && is_callable( $field['sanitize_callback'] ) ) {
            return $field['sanitize_callback']( $value );
        }

        if ( is_callable( array( $this, "validate_{$type}_field" ) ) ) {
            return $this->{"validate_{$type}_field"}( $field_id, $value );
        }

        return $value;
    }

    /**
     * Get field name from field ID.
     *
     * @param  string $field_id
     *
     * @return string
     */
    private function get_field_name( $field_id ) {
        return 'tfm_' . $field_id;
    }

    /**
     * Saves the settings form.
     *
     * If a validation error occurs, skip the field with errors and continue.
     *
     * @param array $values Form field values
     */
    public function save( $values = [] ) {
        foreach ( $this->form_fields as $field_id => $field ) {
            try {
                $value      = $this->get_field_value( $field_id, $values );
                $field_name = $this->get_field_name( $field_id );

                if ( ! is_null( $value ) ) {
                    update_user_meta( $this->vendor_id, $field_name, $value );
                } else {
                    delete_user_meta( $this->vendor_id, $field_name );
                }
            } catch ( Exception $ex ) {
                $this->handle_error( $field_id, $ex->getMessage() );
            }
        }
    }

    /**
     * Handles a form validation error.
     *
     * By default, errors are displayed as WC notices in the frontend and as
     * admin notices in WP admin.
     *
     * @param string $field_id
     * @param string $error
     */
    private function handle_error( $field_id, $error ) {
        $error_callback = apply_filters( 'tfm_form_error_callback', null, $this->context );

        if ( is_callable( $error_callback ) ) {
            $error_callback( $field_id, $error );
        } elseif ( 'frontend' === $this->context ) {
            wc_add_notice( $error, 'error' );
        } else {
            TFM()->admin->add_notice( $field_id . '-error', 'error', $error );
        }
    }

    /**
     * Validates the Nexus Addresses field.
     *
     * @param mixed $value
     *
     * @return array
     *
     * @throws Exception if validation fails
     */
    public function validate_nexus_addresses( $value ) {
        if ( ! is_array( $value ) ) {
            $value = array();
        }
        if ( empty( $value ) ) {
            throw new Exception( __( 'You must provide at least one business location.', 'taxjar-for-marketplaces' ) );
        }
        return $value;
    }

    /**
     * Validates the vendor's TaxJar API token.
     *
     * @param string $token
     *
     * @return string
     *
     * @throws Exception if validation fails
     */
    public function validate_api_token( $token ) {
        if ( isset( $_POST['upload_transactions'] ) && 'yes' === $_POST['upload_transactions'] ) {
            return TFM_Util::validate_api_token( $token );
        }

        return $token;
    }

    /**
     * Validates checkbox fields.
     *
     * @param string $field_name
     * @param mixed $value
     *
     * @return string
     */
    public function validate_checkbox_field( $field_name, $value ) {
        return 'yes' === $value ? 'yes' : 'no';
    }

    /**
     * Gets an option from the DB, using defaults if necessary to prevent undefined notices.
     *
     * @param string $key Option key.
     * @param mixed $empty_value Value when empty.
     *
     * @return string The value specified for the option or a default value for the option.
     */
    public function get_option( $key, $empty_value = null ) {
        $value = get_user_meta( $this->vendor_id, $key, true );

        if ( empty( $value ) ) {
            $value = $empty_value;
        }

        return $value;
    }

    /**
     * Gets the user's TaxJar API token.
     *
     * @return string
     */
    public function get_api_token() {
        if ( $_POST ) {
            try {
                return $this->get_field_value( 'taxjar_api_token' );
            } catch ( Exception $ex ) {
                return null;
            }
        }
        return $this->get_option( 'taxjar_api_token' );
    }

    /**
     * Checks whether sales tax reporting is enabled.
     *
     * @return bool
     */
    public function is_reporting_enabled() {
        if ( 'merchant' === TFM()->settings->get( 'merchant_of_record' ) ) {
            return false;
        }
        return 'yes' === $this->get_option( 'upload_transactions', 'no' );
    }

    /**
     * Gets the store URL to send to TaxJar.
     *
     * @return string
     */
    public function get_store_url() {
        return WCV_Vendors::get_vendor_shop_page( $this->vendor_id );
    }

}
