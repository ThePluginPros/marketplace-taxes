<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once __DIR__ . '/admin/class-tfm-wc-integration.php';

/**
 * Class TFM_Settings
 *
 * Instantiates our WC integration and provides a convenience method for getting
 * plugin options.
 */
class TFM_Settings {

    /**
     * @var TFM_WC_Integration Integration instance
     */
    private $integration;

    /**
     * Constructor.
     *
     * Instantiates the integration and loads it into WooCommerce.
     */
    public function __construct() {
        add_filter( 'woocommerce_integrations', array( $this, 'load_integration' ) );
        add_action( 'init', array( $this, 'get_integration_instance' ), 0 );
    }

    /**
     * Loads the integration into WooCommerce.
     *
     * @param array $integrations
     *
     * @return array
     */
    public function load_integration( $integrations ) {
        $integrations['taxjar_for_marketplaces'] = 'TFM_WC_Integration';

        return $integrations;
    }

    /**
     * Retrieves the integration instance from WooCommerce.
     */
    public function get_integration_instance() {
        $this->integration = WC()->integrations->integrations['taxjar_for_marketplaces'];
    }

    /**
     * Gets a plugin option.
     *
     * @param string $option_name
     * @param mixed $default Default value (default: null)
     *
     * @return mixed
     */
    public function get( $option_name, $default = null ) {
        if ( isset( $this->integration ) ) {
            return $this->integration->get_option( $option_name, $default );
        }
        return null;
    }

}
