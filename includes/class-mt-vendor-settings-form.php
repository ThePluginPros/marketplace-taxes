<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Vendor settings form.
 *
 * Provides generic logic for displaying and saving the vendor settings form.
 */
class MT_Vendor_Settings_Form implements MT_Settings_API {

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
     * Constructor.
     *
     * @param int $vendor_id ID of the vendor
     * @param string $context 'admin' or 'frontend'
     */
    public function __construct( $vendor_id = 0, $context = 'frontend' ) {
        if ( ! $vendor_id ) {
            $vendor_id = get_current_user_id();
        }

        $this->vendor_id = $vendor_id;
        $this->context   = $context;

        $this->init_form_fields();
    }

    /**
     * Initializes the form fields array.
     */
    private function init_form_fields() {
        $this->form_fields = [
            'nexus_addresses'     => apply_filters(
                'mt_nexus_addresses_field',
                array_merge(
                    MT_Field_Business_Locations::init( $this ),
                    [
                        'id'             => 'nexus_addresses',
                        'context'        => $this->context,
                        'value_callback' => array( MT()->addresses, 'get' ),
                    ]
                )
            ),
            'upload_transactions' => apply_filters(
                'mt_upload_transactions_field',
                array_merge(
                    MT_Field_Upload_Orders::init( $this ),
                    [
                        'id'    => 'upload_transactions',
                        'class' => 'input-toggle',
                        'title' => __( 'Use TaxJar for Reporting', 'marketplace-taxes' ),
                    ]
                )
            ),
            'taxjar_api_token'    => apply_filters(
                'mt_taxjar_api_token_field',
                array_merge(
                    MT_Field_API_Token::init( $this ),
                    [
                        'id'            => 'taxjar_api_token',
                        'wrapper_class' => 'show-if-upload_transactions-yes',
                    ]
                )
            ),
        ];
    }

    /**
     * Returns the settings form description.
     *
     * @return string
     */
    public function description() {
        return apply_filters(
            'mt_vendor_settings_form_description',
            __(
                'Please fill out the fields below to ensure that your customers are taxed correctly.',
                'marketplace-taxes'
            )
        );
    }

    /**
     * Displays the form fields.
     */
    public function fields() {
        $field_callback = apply_filters( 'mt_form_field_callback', null );

        if ( ! is_callable( $field_callback ) ) {
            return;
        }

        MT()->assets->enqueue( 'script', 'marketplace-taxes.input-toggle' );

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
            if ( isset( $field['value'] ) ) {
                continue;
            }

            if ( isset( $field['value_callback'] ) ) {
                $field['value'] = $field['value_callback']( $this->vendor_id );
            } else {
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
        return 'mt_' . $field_id;
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
        $error_callback = apply_filters( 'mt_form_error_callback', null, $this->context );

        if ( is_callable( $error_callback ) ) {
            $error_callback( $field_id, $error );
        } elseif ( 'frontend' === $this->context ) {
            wc_add_notice( $error, 'error' );
        } else {
            MT()->admin->add_notice( $field_id . '-error', 'error', $error );
        }
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
        $value = get_user_meta( $this->vendor_id, $this->get_field_name( $key ), true );

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
        if ( 'merchant' === MT()->settings->get( 'merchant_of_record' ) ) {
            return false;
        }
        return 'yes' === $this->get_option( 'upload_transactions', 'no' );
    }

    /**
     * Checks whether an API token is required based on the user's settings.
     *
     * @return bool
     */
    public function is_token_required() {
        return isset( $_POST['upload_transactions'] ) && 'yes' === $_POST['upload_transactions'];
    }

    /**
     * Checks whether addresses are required based on the user's settings.
     *
     * @return bool
     */
    public function addresses_required() {
        // This form is only displayed when the vendor is the MOR, so always required
        return true;
    }

    /**
     * Gets the default addresses for the Business Locations table.
     *
     * @return int
     */
    public function get_vendor_id() {
        return $this->vendor_id;
    }

}
