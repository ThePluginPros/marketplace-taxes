<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once __DIR__ . '/integrations/wc-vendors/class-tfm-integration-wc-vendors.php';

/**
 * Integrations class.
 *
 * Loads the appropriate integration based on which marketplace plugin is
 * active.
 */
class TFM_Integrations {

    /**
     * Constructor.
     *
     * Registers action hooks.
     */
    public function __construct() {
        add_action( 'init', array( $this, 'load_integration' ) );
    }

    /**
     * Loads one of the available integrations based on which marketplace
     * plugin is active.
     */
    public function load_integration() {
        $integrations = apply_filters(
            'tfm_integrations',
            [
                'wc-vendors/class-wc-vendors.php' => [
                    'class'       => 'TFM_Integration_WC_Vendors',
                    'min_version' => '1.9.14',
                ],
            ]
        );

        $plugins = get_plugins();

        foreach ( $integrations as $plugin_slug => $integration ) {
            if ( array_key_exists( $plugin_slug, $plugins ) ) {
                $plugin_info = $plugins[ $plugin_slug ];

                if ( ! is_plugin_active( $plugin_slug ) ) {
                    $error = sprintf(
                        __(
                            '<strong>%1$s not detected.</strong> Please install or active %1$s to use TaxJar for Marketplaces.',
                            'taxjar-for-marketplaces'
                        ),
                        $plugin_info['Name']
                    );
                } elseif ( isset( $integration['min_version'] ) && $plugin_info['Version'] < $integration['min_version'] ) {
                    $error = sprintf(
                        __(
                            '<strong>%1$s needs to be updated.</strong> TaxJar for Marketplaces requires %1$s version %2$s or greater.',
                            'taxjar-for-marketplaces'
                        ),
                        $plugin_info['Name'],
                        $integration['min_version']
                    );
                }

                if ( isset( $error ) ) {
                    TFM()->admin->add_notice( 'integration-error', 'error', $error );
                } else {
                    do_action( 'tfm_load_integration', new $integration['class']() );
                }
                return;
            }
        }

        TFM()->admin->add_notice(
            'no-compatible-plugin',
            'error',
            __(
                '<strong>TaxJar for Marketplace is inactive</strong>. No compatible marketplace plugin detected.',
                'taxjar-for-marketplaces'
            )
        );
    }

}

new TFM_Integrations();
