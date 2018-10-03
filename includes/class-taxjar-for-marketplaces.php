<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * The main plugin class.
 *
 * @package TaxJar_For_Marketplaces
 */
final class TaxJar_For_Marketplaces extends \WordFrame\v1_1_0\Plugin {

    /**
     * @var string Current plugin version.
     */
    public $version = '1.0.0';

    /**
     * @var TFM_Settings Settings instance.
     */
    public $settings;

    /**
     * @var TFM_Admin Admin instance.
     */
    public $admin;

    /**
     * @var TFM_Tax_Categories Categories instance.
     */
    public $categories;

    /**
     * @var TFM_Addresses Addresses instance.
     */
    public $addresses;

    /**
     * @var TFM_Integration The active marketplace plugin integration.
     */
    public $integration;

    /**
     * @var TFM_Refund_Uploader Refund uploader instance.
     */
    protected $refund_uploader;

    /**
     * Bootstraps the plugin when all requirements are met.
     */
    public function load() {
        parent::load();

        $this->includes();

        $this->settings        = new TFM_Settings();
        $this->categories      = new TFM_Tax_Categories();
        $this->admin           = new TFM_Admin();
        $this->addresses       = new TFM_Addresses();
        $this->refund_uploader = new TFM_Refund_Uploader();

        add_action( 'init', array( $this, 'trigger_activation' ) );
        add_action( 'init', array( $this, 'load_integration' ) );
    }

    /**
     * Includes all required files.
     */
    private function includes() {
        require 'interface-tfm-settings-api.php';
        require 'fields/class-tfm-field-upload-orders.php';
        require 'fields/class-tfm-field-api-token.php';
        require 'fields/class-tfm-field-business-locations.php';
        require 'class-tfm-settings.php';
        require 'class-tfm-install.php';
        require 'class-tfm-assets.php';
        require 'class-tfm-tax-categories.php';
        require 'class-tfm-util.php';
        require 'class-tfm-vendor-settings-form.php';
        require 'admin/class-tfm-admin.php';
        require 'class-tfm-integration.php';
        require 'class-tfm-integrations.php';
        require 'class-tfm-product-controller.php';
        require 'class-tfm-calculator.php';
        require 'class-tfm-addresses.php';
        require 'class-tfm-vendors.php';
        require 'class-tfm-api.php';
        require 'class-tfm-refund-uploader.php';
    }

    /**
     * Runs when the plugin is activated.
     *
     * Sets the tfm_activate flag so that activation logic will run on the
     * next page load.
     */
    public function activate() {
        update_option( 'tfm_activate', true );
    }

    /**
     * Fires the taxjar_for_marketplaces_activated hook when the tfm_activate
     * flag is set.
     */
    public function trigger_activation() {
        if ( get_option( 'tfm_activate' ) ) {
            delete_option( 'tfm_activate' );

            do_action( 'taxjar_for_marketplaces_activated', $this );
        }
    }

    /**
     * Fires the taxjar_for_marketplaces_deactivated hook on plugin deactivation.
     */
    public function deactivate() {
        do_action( 'taxjar_for_marketplaces_deactivated' );
    }

    /**
     * Loads the plugin text domain.
     */
    public function load_text_domain() {
        load_plugin_textdomain( 'taxjar-for-marketplaces', false, basename( dirname( $this->file ) ) . '/languages/' );
    }

    /**
     * Returns the text to display in the notice when a required plugin is missing.
     *
     * @param array $violation
     *
     * @return string
     */
    public function get_plugin_notice( array $violation ) {
        switch ( $violation['type'] ) {
            case 'wrong_version':
                return sprintf(
                /* translators: 1: required plugin name, 2: minimum version */
                    __(
                        '<strong>%1$s needs to be updated.</strong> TaxJar for Marketplaces requires %1$s %2$s+.',
                        'taxjar-for-marketplaces'
                    ),
                    $violation['data']['name'],
                    $violation['data']['version']
                );
            case 'inactive':
            case 'not_installed':
                return sprintf(
                /* translators: 1: required plugin name */
                    __(
                        '<strong>%1$s not detected.</strong> Please install or activate %1$s to use TaxJar for Marketplaces.',
                        'taxjar-for-marketplaces'
                    ),
                    $violation['data']['name']
                );
        }
        return '';
    }

    /**
     * Returns the text to display when a PHP requirement is not met.
     *
     * @param array $violation Information about the missing requirement.
     *
     * @return string
     */
    public function get_php_notice( $violation ) {
        if ( 'extensions' === $violation['type'] ) {
            $ext_list = implode( ', ', $violation['data']['required'] );
            /* translators: 1 - list of required PHP extensions */
            return sprintf(
                __(
                    '<strong>Required PHP extensions are missing.</strong> TaxJar for Marketplaces requires %1$s.',
                    'taxjar-for-marketplaces'
                ),
                $ext_list
            );
        } elseif ( 'version' === $violation['type'] ) {
            /* translators: 1 - required php version */
            return sprintf(
                __(
                    '<strong>PHP needs to be updated.</strong> TaxJar for Marketplaces requires PHP %1$s+.',
                    'taxjar-for-marketplaces'
                ),
                $violation['data']['required']
            );
        }
        return '';
    }

    /**
     * Returns an instance of the TaxJar client.
     *
     * @param string $token API token - marketplace token used if unspecified.
     *
     * @return TaxJar\Client
     */
    public function client( $token = '' ) {
        if ( empty( $token ) ) {
            $token = $this->settings->get( 'api_token' );
        }
        return TaxJar\Client::withApiKey( $token );
    }

    /**
     * Loads an appropriate integration based on the detected marketplace plugin.
     */
    public function load_integration() {
        $this->integration = TFM_Integrations::load();
    }

}
