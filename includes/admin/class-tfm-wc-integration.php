<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once TFM()->path( 'includes/interface-tfm-settings-api.php' );
require_once TFM()->path( 'includes/class-tfm-download-orders.php' );

/**
 * WooCommerce integration for TaxJar for Marketplaces.
 *
 * Adds a marketplace settings page under WooCommerce > Settings > Integration >
 * TaxJar for Marketplace.
 */
class TFM_WC_Integration extends WC_Integration implements TFM_Settings_API {

    /**
     * @var TFM_Download_Orders Download orders instance.
     */
    protected $download_orders;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->id                 = 'taxjar_for_marketplaces';
        $this->method_title       = __( 'TaxJar for Marketplaces', 'taxjar-for-marketplaces' );
        $this->method_description = $this->get_method_description();
        $this->download_orders    = new TFM_Download_Orders( $this );

        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
        add_action( 'woocommerce_update_options_integration_' . $this->id, array( $this, 'process_admin_options' ) );

        $this->init_form_fields();
    }

    /**
     * Enqueues the scripts for the admin options screen.
     */
    public function enqueue_scripts() {
        $screen = get_current_screen();
        $tab    = isset( $_GET['tab'] ) ? $_GET['tab'] : '';

        if ( 'woocommerce_page_wc-settings' === $screen->id && 'integration' === $tab ) {
            TFM()->assets->enqueue( 'script', 'taxjar-for-marketplaces.input-toggle' );
        }
    }

    /**
     * Returns the description displayed on the settings page.
     *
     * @return string
     */
    public function get_method_description() {
        $paragraphs = [
            __( 'Use this page to configure sales tax automation for your marketplace.', 'taxjar-for-marketplaces' ),
            __(
                'Need help? Check out the <a href="https://thepluginpros.com/documentation/taxjar">documentation</a>',
                'taxjar-for-marketplaces'
            ),
        ];
        return '<p>' . implode( '</p><p>', $paragraphs ) . '</p>';
    }

    /**
     * Initializes the settings form fields.
     */
    public function init_form_fields() {
        $this->form_fields = [
            'enabled'             => [
                'title'   => __( 'Enable', 'taxjar-for-marketplaces' ),
                'type'    => 'checkbox',
                'label'   => __( 'Enable automated tax calculations', 'taxjar-for-marketplaces' ),
                'default' => 'yes',
            ],
            'api_token'           => [
                'title'             => __( 'TaxJar API token', 'taxjar-for-marketplaces' ),
                'type'              => 'text',
                'desc_tip'          => __(
                    'Your API token will be used to calculate the correct tax rate at checkout.',
                    'taxjar-for-marketplaces'
                ),
                'description'       => __(
                    '<a href="https://thepluginpros.com/out/taxjar-api-token" target="_blank">Find your API token</a> | <a href="https://thepluginpros.com/out/taxjar" target="_blank">Register for TaxJar</a>',
                    'taxjar-for-marketplaces'
                ),
                'custom_attributes' => [
                    'required' => 'required',
                ],
                'sanitize_callback' => array( 'TFM_Util', 'validate_api_token' ),
            ],
            'merchant_of_record'  => [
                'title'       => __( 'Seller of record', 'taxjar-for-marketplaces' ),
                'type'        => 'select',
                'class'       => 'input-toggle',
                'options'     => [
                    'vendor'      => __( 'Vendor', 'taxjar-for-marketplaces' ),
                    'marketplace' => __( 'Marketplace', 'taxjar-for-marketplaces' ),
                ],
                'default'     => 'vendor',
                'description' => __(
                    'The seller of record is responsible for collecting and remitting sales tax for each sale. The tax collected at checkout will be given to the seller of record.',
                    'taxjar-for-marketplaces'
                ),
                'desc_tip'    => true,
            ],
            'upload_transactions' => $this->download_orders->get_form_settings_field(),
        ];
    }

    /**
     * Outputs the admin options screen with any validation errors.
     */
    public function admin_options() {
        parent::display_errors();

        parent::admin_options();
    }

    /**
     * Saves the options entered by the user.
     */
    public function process_admin_options() {
        parent::process_admin_options();

        do_action( 'taxjar_for_marketplaces_options_saved', $this );
    }

    /**
     * Gets the user's TaxJar API token.
     *
     * @return string
     */
    public function get_api_token() {
        if ( $_POST ) {
            return $this->get_field_value( 'api_token', $this->form_fields['api_token'] );
        }
        return $this->get_option( 'api_token' );
    }

    /**
     * Checks whether sales tax reporting is enabled.
     *
     * @return bool
     */
    public function is_reporting_enabled() {
        if ( 'vendor' === $this->get_option( 'merchant_of_record' ) ) {
            return false;
        }
        return 'yes' === $this->get_option( 'upload_transactions' );
    }

    /**
     * Gets the store URL to send to TaxJar.
     *
     * @return string
     */
    public function get_store_url() {
        return home_url();
    }

}
