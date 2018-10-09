<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once __DIR__ . '/integrations/wc-vendors/class-mt-integration-wc-vendors.php';

/**
 * Integrations class.
 *
 * Loads the appropriate integration based on which marketplace plugin is
 * active.
 */
class MT_Integrations {

    /**
     * @var MT_Integration The loaded integration, if any.
     */
    protected static $integration = null;

    /**
     * Loads an appropriate integration based on the detected marketplace plugin.
     *
     * @return MT_Integration
     */
    public static function load() {
        if ( ! is_null( self::$integration ) ) {
            return self::$integration;
        }

        $integrations = apply_filters(
            'mt_integrations',
            [
                'wc-vendors/class-wc-vendors.php' => [
                    'class'       => 'MT_Integration_WC_Vendors',
                    'min_version' => '1.9.14',
                ],
            ]
        );

        $plugins = get_plugins();

        foreach ( $integrations as $plugin_slug => $integration ) {
            if ( array_key_exists( $plugin_slug, $plugins ) ) {
                $plugin_info = $plugins[ $plugin_slug ];

                if ( ! is_plugin_active( $plugin_slug ) ) {
                    self::add_error(
                        sprintf(
                            __(
                                '<strong>%1$s not detected.</strong> Please install or active %1$s to use Marketplace Taxes.',
                                'marketplace-taxes'
                            ),
                            $plugin_info['Name']
                        )
                    );
                } elseif ( isset( $integration['min_version'] ) && version_compare(
                        $plugin_info['Version'],
                        $integration['min_version'],
                        '<'
                    ) ) {
                    self::add_error(
                        sprintf(
                            __(
                                '<strong>%1$s needs to be updated.</strong> Marketplace Taxes requires %1$s version %2$s or greater.',
                                'marketplace-taxes'
                            ),
                            $plugin_info['Name'],
                            $integration['min_version']
                        )
                    );
                } else {
                    self::$integration = new $integration['class']();
                    break;
                }
            }
        }

        // Load default integration if need be
        if ( is_null( self::$integration ) ) {
            self::$integration = new MT_Integration();
            self::add_error(
                __(
                    '<strong>TaxJar for Marketplace is inactive</strong>. No compatible marketplace plugin detected.',
                    'marketplace-taxes'
                )
            );
        }

        do_action( 'mt_load_integration', self::$integration );

        return self::$integration;
    }

    /**
     * Shows an integration error message.
     *
     * @param string $error
     */
    private static function add_error( $error ) {
        MT()->admin->add_notice( 'integration-error', 'error', $error );
    }

}
