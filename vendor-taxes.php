<?php

/**
 * Plugin Name: Vendor Taxes
 * Author: Brett Porcelli
 * Version: 1.1
 * Description: Enhances the WC Vendors tax system by using a single tax table for all vendors.
 */

if ( !defined( 'ABSPATH' ) )
	exit; // Exit if accessed directly

define( 'VENDOR_TAXES_URI', plugin_dir_url( __FILE__ ) );
define( 'VENDOR_TAXES_PATH', plugin_dir_path( __FILE__ ) );

function maybe_enable_plugin() {
	if ( class_exists( 'WooCommerce' ) && class_exists( 'WCV_Vendors' ) ) {
		require 'includes/essential-functions.php';
	}
}

add_action( 'plugins_loaded', 'maybe_enable_plugin', 25 );

// Remove rates with class "vendor-VENDORID" from database on activation
function activate_vendor_taxes() {
	global $wpdb;
	$wpdb->query( "DELETE FROM {$wpdb->prefix}woocommerce_tax_rates WHERE tax_rate_class LIKE 'vendor-%'" );
}

register_activation_hook( __FILE__, 'activate_vendor_taxes' );
