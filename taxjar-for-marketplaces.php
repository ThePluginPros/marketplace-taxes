<?php

/**
 * Plugin Name:        TaxJar for Marketplaces
 * Description:        Make your WooCommerce marketplace more attractive with sales tax automation by <a href="https://taxjar.com" target="_blank">TaxJar</a>.
 * Author:             The Plugin Pros
 * Author URI:         https://thepluginpros.com
 *
 * Version:            1.0.0
 * Requires at least:  4.4.0
 * Tested up to:       4.9.0
 *
 * Text Domain:        taxjar-for-marketplaces
 * Domain Path:        /languages/
 *
 * @copyright          Copyright &copy; 2018 The Plugin Pros
 * @author             Brett Porcelli
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/includes/class-taxjar-for-marketplaces.php';

/**
 * Returns the single TaxJar for Marketplaces instance.
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
                    'woocommerce/woocommerce.php' => [
                        'name'    => __( 'WooCommerce', 'taxjar-for-marketplaces' ),
                        'version' => '3.0.0',
                    ],
                ],
            ],
            'updates'  => [
                'checker' => 'EDD_SL',
                'options' => [
                    'store_url' => 'https://thepluginpros.com',
                    'item_id'   => 1428,
                    'author'    => __( 'The Plugin Pros', 'taxjar-for-marketplaces' ),
                    'beta'      => false,
                ],
            ],
        ]
    );
}

TFM();
