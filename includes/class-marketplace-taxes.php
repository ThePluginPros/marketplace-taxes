<?php

use WordFrame\v1_1_3\Plugin;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * The main plugin class.
 *
 * @package Marketplace_Taxes
 */
final class Marketplace_Taxes extends Plugin {

    /**
     * @var string Current plugin version.
     */
    public $version = '1.1.0';

    /**
     * @var MT_Settings Settings instance.
     */
    public $settings;

    /**
     * @var MT_Admin Admin instance.
     */
    public $admin;

    /**
     * @var MT_Tax_Categories Categories instance.
     */
    public $categories;

    /**
     * @var MT_Addresses Addresses instance.
     */
    public $addresses;

    /**
     * @var MT_Integration The active marketplace plugin integration.
     */
    public $integration;

    /**
     * @var MT_Refund_Uploader Refund uploader instance.
     */
    protected $refund_uploader;

    /**
     * Bootstraps the plugin when all requirements are met.
     */
    public function load() {
        parent::load();

        $this->includes();

        $this->settings        = new MT_Settings();
        $this->categories      = new MT_Tax_Categories();
        $this->admin           = new MT_Admin();
        $this->addresses       = new MT_Addresses();
        $this->refund_uploader = new MT_Refund_Uploader();

        add_action( 'init', array( $this, 'trigger_activation' ) );
        add_action( 'init', array( $this, 'load_integration' ) );
    }

    /**
     * Includes all required files.
     */
    private function includes() {
        require 'functions.php';
        require 'interface-mt-settings-api.php';
        require 'fields/class-mt-field-upload-orders.php';
        require 'fields/class-mt-field-api-token.php';
        require 'fields/class-mt-field-business-locations.php';
        require 'class-mt-settings.php';
        require 'class-mt-install.php';
        require 'class-mt-assets.php';
        require 'class-mt-tax-categories.php';
        require 'class-mt-util.php';
        require 'class-mt-vendor-settings-form.php';
        require 'admin/class-mt-admin.php';
        require 'class-mt-integration.php';
        require 'class-mt-integrations.php';
        require 'class-mt-product-controller.php';
        require 'class-mt-calculator.php';
        require 'class-mt-addresses.php';
        require 'class-mt-vendors.php';
        require 'class-mt-api.php';
        require 'class-mt-refund-uploader.php';
        require 'class-mt-refund-manager.php';
    }

    /**
     * Runs when the plugin is activated.
     *
     * Sets the mt_activate flag so that activation logic will run on the
     * next page load.
     */
    public function activate() {
        update_option( 'mt_activate', true );
    }

    /**
     * Fires the marketplace_taxes_activated hook when the mt_activate
     * flag is set.
     */
    public function trigger_activation() {
        if ( get_option( 'mt_activate' ) ) {
            delete_option( 'mt_activate' );

            do_action( 'marketplace_taxes_activated', $this );
        }
    }

    /**
     * Fires the marketplace_taxes_deactivated hook on plugin deactivation.
     */
    public function deactivate() {
        do_action( 'marketplace_taxes_deactivated' );
    }

    /**
     * Loads the plugin text domain.
     */
    public function load_text_domain() {
        load_plugin_textdomain( 'marketplace-taxes', false, basename( dirname( $this->file ) ) . '/languages/' );
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
                        '<strong>%1$s needs to be updated.</strong> Marketplace Taxes requires %1$s %2$s+.',
                        'marketplace-taxes'
                    ),
                    $violation['data']['name'],
                    $violation['data']['version']
                );
            case 'inactive':
            case 'not_installed':
                return sprintf(
                /* translators: 1: required plugin name */
                    __(
                        '<strong>%1$s not detected.</strong> Please install or activate %1$s to use Marketplace Taxes.',
                        'marketplace-taxes'
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
                    '<strong>Required PHP extensions are missing.</strong> Marketplace Taxes requires %1$s.',
                    'marketplace-taxes'
                ),
                $ext_list
            );
        } elseif ( 'version' === $violation['type'] ) {
            /* translators: 1 - required php version */
            return sprintf(
                __(
                    '<strong>PHP needs to be updated.</strong> Marketplace Taxes requires PHP %1$s+.',
                    'marketplace-taxes'
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

        $client = TaxJar\Client::withApiKey( $token );

        if ( $this->is_sandbox_enabled() ) {
            $client->setApiConfig( 'api_url', TaxJar\TaxJar::SANDBOX_API_URL );
        }

        return $client;
    }

    /**
     * Checks whether the TaxJar API sandbox is enabled.
     *
     * @return bool True if sandbox is enabled, otherwise false.
     */
    public function is_sandbox_enabled() {
        return apply_filters( 'mt_sandbox_enabled', defined( 'MT_SANDBOX_ENABLED' ) && MT_SANDBOX_ENABLED );
    }

    /**
     * Loads an appropriate integration based on the detected marketplace plugin.
     */
    public function load_integration() {
        $this->integration = MT_Integrations::load();
    }

}
