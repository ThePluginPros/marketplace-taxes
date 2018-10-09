<?php

/**
 * Plugin Name:          Marketplace Taxes
 * Description:          Make your WooCommerce marketplace more attractive with sales tax automation by <a href="https://thepluginpros.com/out/taxjar" target="_blank">TaxJar</a>.
 * Author:               The Plugin Pros
 * Author URI:           https://thepluginpros.com
 * Version:              1.0.0
 * Text Domain:          marketplace-taxes
 * Domain Path:          /languages/
 *
 * Requires at least:    4.4.0
 * Tested up to:         4.9.0
 * WC requires at least: 3.0.0
 * WC tested up to:      3.4.5
 *
 * @copyright            Copyright &copy; 2018 The Plugin Pros
 * @author               Brett Porcelli
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/includes/class-marketplace-taxes.php';

/**
 * Returns the single Marketplace Taxes instance.
 *
 * @return Marketplace_Taxes
 */
function MT() {
    return Marketplace_Taxes::init(
        __FILE__,
        [
            'requires' => [
                'php'     => '5.6',
                'plugins' => [
                    'woocommerce/woocommerce.php' => [
                        'name'    => __( 'WooCommerce', 'marketplace-taxes' ),
                        'version' => '3.0.0',
                    ],
                ],
            ],
            'updates'  => [
                'checker' => 'EDD_SL',
                'options' => [
                    'store_url' => 'https://thepluginpros.com',
                    'item_id'   => 1428,
                    'author'    => __( 'The Plugin Pros', 'marketplace-taxes' ),
                    'beta'      => false,
                ],
            ],
        ]
    );
}

MT();
