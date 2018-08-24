<?php

/**
 * Plugin Name:         TaxJar for Marketplaces
 * Description:         Make your WooCommerce marketplace more attractive with sales tax automation by <a href="https://taxjar.com">TaxJar</a>.
 * Author:              The Plugin Pros
 * Author URI:          https://thepluginpros.com
 *
 * Version:             0.0.1
 * Requires at least:   4.4.0
 * Tested up to:        4.9.0
 *
 * Text Domain:         taxjar-for-marketplaces
 * Domain Path:         /languages/
 *
 * @copyright           Copyright &copy; 2018 The Plugin Pros
 * @author              Brett Porcelli
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Returns the single TaxJar for Marketplaces instance.
 *
 * @todo configure updater
 *
 * @return TaxJar_For_Marketplaces
 */
function TFM() {
    return TaxJar_For_Marketplaces::init(
        __FILE__,
        [
            'requires' => [
                'php'     => '5.6',
                'plugins' => [
                    'woocommerce/woocommerce.php'     => [
                        'name'        => __( 'WooCommerce', 'taxjar-for-marketplaces' ),
                        'min_version' => '3.0.0',
                    ],
                    'wc-vendors/class-wc-vendors.php' => [
                        'name'        => __( 'WC Vendors Marketplace', 'taxjar-for-marketplaces' ),
                        'min_version' => '1.9.14',
                    ],
                ],
            ],
        ]
    );
}
