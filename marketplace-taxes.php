<?php

/**
 * Plugin Name:          Marketplace Taxes
 * Description:          Connect your WooCommerce based marketplace to <a href="https://thepluginpros.com/out/taxjar" target="_blank">TaxJar</a> to automate sales tax calculations, reporting, and filing.
 * Author:               The Plugin Pros
 * Author URI:           https://thepluginpros.com
 * GitHub Plugin URI:    https://github.com/ThePluginPros/marketplace-taxes
 * Version:              1.1.2
 * Text Domain:          marketplace-taxes
 * Domain Path:          /languages/
 *
 * Requires at least:    4.4.0
 * Tested up to:         5.2.0
 * WC requires at least: 3.0.0
 * WC tested up to:      3.6.0
 *
 * @category             Plugin
 * @copyright            Copyright © 2019 The Plugin Pros, LLC
 * @author               Brett Porcelli
 * @license              GPL2
 *
 * Marketplace Taxes is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by the
 * Free Software Foundation, either version 2 of the License, or any later
 * version.
 *
 * Marketplace Taxes is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU
 * General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with
 * Marketplace Taxes. If not, see http://www.gnu.org/licenses/gpl-2.0.txt.
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
        ]
    );
}

MT();
