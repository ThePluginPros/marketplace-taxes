<?php

/**
 * Essential functions for Vendor Taxes plugin
 *
 * @since 1.0
 */

if ( !defined( 'ABSPATH' ) )
	exit;

// Return a vendor's tax state, or the shop base state by default
function get_vendor_tax_state( $user_id = null ) {
	global $user_ID;

	if ( !$user_id )
		$user_id = $user_ID;

	$state = get_user_meta( $user_id, 'tax_state', true );
	
	if ( !$state && get_user_meta( $user_id, 'billing_state', true ) )
		$state = get_user_meta( $user_id, 'billing_state', true ); // Default to billing address state
	else if ( !$state )
		$state = WC()->countries->get_base_state(); // Fallback to shop base state

	return $state;
}

// Return a vendor's tax ZIP, or the shop base postcode by default
function get_vendor_tax_zip( $user_id = null ) {
	global $user_ID;

	if ( !$user_id )
		$user_id = $user_ID;

	$zip = get_user_meta( $user_id, 'tax_zip', true );

	if ( !$zip && get_user_meta( $user_id, 'billing_postcode', true ) )
		$zip = get_user_meta( $user_id, 'billing_postcode', true ); // Default to billing address ZIP
	else if ( !$zip )
		$zip = WC()->countries->get_base_postcode(); // Fallback is shop base ZIP

	return $zip;
}

// Should we collect tax for this vendor?
function does_vendor_collect_tax( $user_id = null ) {
	if ( ! $user_id ) {
		$user_id = get_current_user_id();
	}

	$collect_tax = get_user_meta( $user_id, 'collect_tax', true );

	return strlen( $collect_tax ) > 0 && (int) $collect_tax === 0 ? false : true;
}

// Determine whether a given state has origin tax sourcing or destination tax sourcing
function get_state_type( $state ) {
	$origin_states = array( 'AZ', 'CA', 'IL', 'MS', 'MO', 'NM', 'OH', 'PA', 'TN', 'TX', 'UT', 'VI' );

	if ( in_array( $state, $origin_states ) )
		return 'orig';
	else
		return 'dest';
}

// Get order tax rate that applies to vendor
// If vendor state is an origin state, we find the rate that applies to the vendor's state and ZIP
// Otherwise, we find the rate that applies to the customers' state and ZIP
function get_vendor_tax_rates( $vendor_state, $vendor_zip, $order, $shipping = false ) {
	$find_args = array(
		'country'   => 'US',
		'state'     => $vendor_state,
		'city'      => '',
		'postcode'  => $vendor_zip,
		'tax_class' => '',
	);

	if ( get_state_type( $vendor_state ) == 'dest' ) {
		$tax_based_on = get_option( 'woocommerce_tax_based_on' );

		if ( 'base' === $tax_based_on ) {
			$default  = wc_get_base_location();
			$country  = $default['country'];
			$state    = $default['state'];
			$postcode = '';
			$city     = '';
		} elseif ( 'billing' === $tax_based_on ) {
			$country  = $order->billing_country;
			$state    = $order->billing_state;
			$postcode = $order->billing_postcode;
			$city     = $order->billing_city;
		} else {
			$country  = $order->shipping_country;
			$state    = $order->shipping_state;
			$postcode = $order->shipping_postcode;
			$city     = $order->shipping_city;
		}

		$find_args['country'] = $country;
		$find_args['state'] = $state;
		$find_args['city'] = $city;
		$find_args['postcode'] = $postcode;
	}

	if ( $shipping ) 
		$rates = WC_Tax::find_shipping_rates( $find_args );
	else
		$rates = WC_Tax::find_rates( $find_args );

	if ( !$rates )
		return array();
	else 
		return $rates;
}

// Tell WooCommerce a users' items are not taxable if they do not explicitly enable taxes
function hb_is_product_taxable( $taxable, $product ) {
	$vendor = WCV_Vendors::get_vendor_from_product( $product->ID );

	if ( $vendor == -1 )
		return $taxable;

	return does_vendor_collect_tax( $vendor );
}

add_filter( 'woocommerce_product_is_taxable', 'hb_is_product_taxable', 10, 2 );

// Get the customer state for a given order
function get_customer_state( $order_id = null ) {
	if ( !$order_id ) {
		$location   = WC_Tax::get_tax_location();
		$cust_state = $location[1]; // 0 is country, 1 is state, 2 is zip, 3 is city
	} else {
		$tax_based_on = get_option( 'woocommerce_tax_based_on' );

		if ( $tax_based_on == 'base' ) { // We won't be using this setting, but let's cover it
			$default    = wc_get_base_location();
			$cust_state = $default['state'];
		} else if ( $tax_based_on == 'billing' ) {
			$cust_state = get_post_meta( $order_id, '_billing_state', true );
		} else {
			$cust_state = get_post_meta( $order_id, '_shipping_state', true ); // "Shipping" should always be selected
		}
	}

	return $cust_state;
}

// Determine the transaction type (interstate or intrastate) for a vendor
function get_transaction_type( $vendor_uid, $order_id = null ) {
	$vendor_tax_state = get_vendor_tax_state( $vendor_uid );

	$cust_state = get_customer_state( $order_id );

	if ( $cust_state != $vendor_tax_state )
		return 'interstate';
	else
		return 'intrastate';
}

// Get the user ID of the vendor for a given product
function get_product_vendor( $product_id ) {
	return WCV_Vendors::get_vendor_from_product( $product_id );
}

// Determine if a vendor product is taxable for a given transaction
function is_product_taxable( $product_id, $order_id = null ) {
	$user_id = get_product_vendor( $product_id );

	if ( !does_vendor_collect_tax( $user_id ) || get_transaction_type( $user_id, $order_id ) == 'interstate' ) {
		return false;
	} else {
		return true;
	}
}

// Determine whether or not shipping is taxable for a given vendor and transaction
function is_shipping_taxable( $user_id, $order_id = null ) {
	$shipping_taxable = array( 'AR', 'CT', 'DC', 'FL', 'GA', 'HI', 'KS', 'KY', 'MI', 'MN', 'MS', 'NE', 'NJ', 'NM', 'NY', 'NC', 'ND', 'OH', 'PA', 'SC', 'SD', 'TN', 'TX', 'VT', 'WA', 'WV', 'WI', 'WY' );

	if ( !does_vendor_collect_tax( $user_id ) || get_transaction_type( $user_id, $order_id ) == 'interstate' || !in_array( get_vendor_tax_state( $user_id ), $shipping_taxable ) ) {
		return false;
	} else {
		return true;
	}
}

// Recalculate taxes during checkout, applying appropriate rules for each vendor
// TODO: HOW CAN WE HANDLE FEES?
function checkout_recalculate_taxes() {
	// Undo WooCommerce's work
	WC()->cart->remove_taxes();

	// Reset vendor taxes array
	WC()->session->vendor_taxes = array();

	$order_taxes = $shipping_taxes = $vendor_taxes = array();

	$cart_contents = WC()->cart->cart_contents;

	// Calculate tax for each item, vendor-wise
	foreach ( $cart_contents as $item_key => &$item ) {
		if ( !is_product_taxable( $item['product_id'] ) ) 
			continue;
		
		$vendor_user = get_product_vendor( $item['product_id'] );
		$tax_state   = get_vendor_tax_state( $vendor_user );
		$tax_zip     = get_vendor_tax_zip( $vendor_user );

		if ( get_state_type( $tax_state ) == 'orig' ) {
			// Calculate tax based on vendor location
			$location = array(
				'country'  => 'US',
				'state'    => $tax_state,
				'city'     => '',
				'postcode' => $tax_zip,
			);
		} else {
			$location = WC_Tax::get_tax_location();

			list( $country, $state, $postcode, $city ) = $location;
			
			$location = array(
				'country'  => $country,
				'state'    => $state,
				'postcode' => $postcode,
				'city'     => $city,
			);
		}

		$rates = WC_Tax::find_rates( $location );

		if ( $rates ) {
			$item['line_subtotal_tax'] = 0;
			$item['line_tax']          = 0;

			$sub_taxes = WC_Tax::calc_tax( $item['line_subtotal'], $rates );

			foreach ( $sub_taxes as $rate_id => $amount ) {
				$item['line_subtotal_tax'] += $amount;
			}

			$taxes = WC_Tax::calc_tax( $item['line_total'], $rates );

			foreach ( $taxes as $rate_id => $amount ) {
				$item['line_tax'] += $amount;
				
				if ( !isset( $order_taxes[ $rate_id ] ) )
					$order_taxes[ $rate_id ] = 0;

				$order_taxes[ $rate_id ] += $amount;

				if ( !isset( $vendor_taxes[ $vendor_user ] ) )
					$vendor_taxes[ $vendor_user ] = array( 'cart' => array(), 'shipping' => array() );

				if ( !isset( $vendor_taxes[ $vendor_user ]['cart'][ $rate_id ] ) )
					$vendor_taxes[ $vendor_user ]['cart'][ $rate_id ] = 0;

				$vendor_taxes[ $vendor_user ]['cart'][ $rate_id ] += $amount;
			}

			$item['line_tax_data'] = array( 'total' => $taxes, 'subtotal' => $sub_taxes );
		}
	}

	WC()->cart->cart_contents = $cart_contents;

	// Calculate shipping tax, vendor-wise
	$vendor_shipping_costs = WC()->session->vendor_shipping_totals;

	if ( is_array( $vendor_shipping_costs ) ) {
		foreach ( $vendor_shipping_costs as $user_id => $cost ) {

			if ( $user_id !== false ) {
				if ( !is_shipping_taxable( $user_id ) )
					continue;

				$tax_state = get_vendor_tax_state( $user_id );
				$tax_zip   = get_vendor_tax_zip( $user_id );

				if ( get_state_type( $tax_state ) == 'orig' ) {
					// Calculate tax based on vendor location
					$location = array(
						'country'  => 'US',
						'state'    => $tax_state,
						'city'     => '',
						'postcode' => $tax_zip,
					);
				} else {
					$location = WC_Tax::get_tax_location();

					list( $country, $state, $postcode, $city ) = $location;
				
					$location = array(
						'country'  => $country,
						'state'    => $state,
						'postcode' => $postcode,
						'city'     => $city,
					);
				}

				$rates = WC_Tax::find_shipping_rates( $location );

				if ( $rates ) {
					$items = 0;

					foreach ( WC()->cart->cart_contents as $item_key => $data ) {
						$vendor_user = get_product_vendor( $data['product_id'] );

						if ( $vendor_user == $user_id )
							$items++;
					}

					$taxes = WC_Tax::calc_shipping_tax( $cost * $items, $rates );

					foreach ( $taxes as $rate_id => $amount ) {
						if ( !isset( $shipping_taxes[ $rate_id ] ) )
							$shipping_taxes[ $rate_id ] = 0;

						$shipping_taxes[ $rate_id ] += $amount;

						if ( !isset( $vendor_taxes[ $user_id ] ) )
							$vendor_taxes[ $user_id ] = array( 'cart' => array(), 'shipping' => array() );

						if ( !isset( $vendor_taxes[ $user_id ]['shipping'][ $rate_id ] ) )
							$vendor_taxes[ $user_id ]['shipping'][ $rate_id ] = 0;

						$vendor_taxes[ $user_id ]['shipping'][ $rate_id ] += $amount;
					}
				}
			}
		}
	}

	// Update cart tax totals (@see WC_Cart::calculate_totals)
	if ( WC()->cart->round_at_subtotal ) {
		WC()->cart->tax_total          = WC_Tax::get_tax_total( $order_taxes );
		WC()->cart->shipping_tax_total = WC_Tax::get_tax_total( $shipping_taxes );
		WC()->cart->taxes              = array_map( array( 'WC_Tax', 'round' ), $order_taxes );
		WC()->cart->shipping_taxes     = array_map( array( 'WC_Tax', 'round' ), $shipping_taxes );

		foreach ( $vendor_taxes as $vendor_id => &$taxes ) {
			$taxes['cart'] = array_map( array( 'WC_Tax', 'round' ), $taxes['cart'] );
			$taxes['shipping'] = array_map( array( 'WC_Tax', 'round' ), $taxes['shipping'] );
		}
	} else {
		WC()->cart->tax_total          = array_sum( $order_taxes );
		WC()->cart->shipping_tax_total = array_sum( $shipping_taxes );
		WC()->cart->taxes              = $order_taxes;
		WC()->cart->shipping_taxes     = $shipping_taxes;
	}

	// Update vendor taxes array (used for reporting later)
	WC()->session->vendor_taxes = $vendor_taxes;
}

add_action( 'woocommerce_calculate_totals', 'checkout_recalculate_taxes', 99 );

// Recalculate taxes on "Edit Order" screen, applying appropriate rules for each vendor
// @see WC_AJAX::calc_line_taxes for original
function order_recalculate_taxes() {
	global $wpdb;

	check_ajax_referer( 'calc-totals', 'security' );

	$order_id       = absint( $_POST['order_id'] );
	$items          = array();
	$country        = strtoupper( esc_attr( $_POST['country'] ) );
	$state          = strtoupper( esc_attr( $_POST['state'] ) );
	$postcode       = strtoupper( esc_attr( $_POST['postcode'] ) );
	$city           = sanitize_title( esc_attr( $_POST['city'] ) );
	$order          = wc_get_order( $order_id );
	$taxes          = array();
	$shipping_taxes = array();
	$vendor_taxes   = array();

	// Parse the jQuery serialized items
	parse_str( $_POST['items'], $items );

	// Prevent undefined warnings
	if ( ! isset( $items['line_tax'] ) ) {
		$items['line_tax'] = array();
	}
	if ( ! isset( $items['line_subtotal_tax'] ) ) {
		$items['line_subtotal_tax'] = array();
	}
	$items['order_taxes'] = array();

	// Action
	$items = apply_filters( 'woocommerce_ajax_calc_line_taxes', $items, $order_id, $country, $_POST );

	// Get items and fees taxes
	if ( isset( $items['order_item_id'] ) ) {
		$line_total = $line_subtotal = $order_item_tax_class = array();

		foreach ( $items['order_item_id'] as $item_id ) {
			$item_id                          = absint( $item_id );
			$line_total[ $item_id ]           = isset( $items['line_total'][ $item_id ] ) ? wc_format_decimal( $items['line_total'][ $item_id ] ) : 0;
			$line_subtotal[ $item_id ]        = isset( $items['line_subtotal'][ $item_id ] ) ? wc_format_decimal( $items['line_subtotal'][ $item_id ] ) : $line_total[ $item_id ];
			$order_item_tax_class[ $item_id ] = isset( $items['order_item_tax_class'][ $item_id ] ) ? sanitize_text_field( $items['order_item_tax_class'][ $item_id ] ) : '';
			$product_id                       = $order->get_item_meta( $item_id, '_product_id', true );

			// Don't perform a tax calculation if product is not taxable for this transaction
			if ( !is_product_taxable( $product_id, $order_id ) ) 
				continue;

			// Get product details
			if ( get_post_type( $product_id ) == 'product' ) {
				$_product        = wc_get_product( $product_id );
				$item_tax_status = $_product->get_tax_status();
			} else {
				$item_tax_status = 'taxable';
			}

			if ( '0' !== $order_item_tax_class[ $item_id ] && 'taxable' === $item_tax_status ) {
				$vendor_user = get_product_vendor( $product_id );
				$tax_state   = get_vendor_tax_state( $vendor_user );
				$tax_zip     = get_vendor_tax_zip( $vendor_user );

				if ( get_state_type( $tax_state ) == 'orig' ) {
					// Calculate tax based on vendor location
					$location = array(
						'country'  => 'US',
						'state'    => $tax_state,
						'city'     => '',
						'postcode' => $tax_zip,
					);
				} else {
					$location = WC_Tax::get_tax_location();

					list( $country, $state, $postcode, $city ) = $location;
					
					$location = array(
						'country'  => $country,
						'state'    => $state,
						'postcode' => $postcode,
						'city'     => $city,
					);
				}

				$tax_rates = WC_Tax::find_rates( $location );

				$line_taxes          = WC_Tax::calc_tax( $line_total[ $item_id ], $tax_rates );
				$line_subtotal_taxes = WC_Tax::calc_tax( $line_subtotal[ $item_id ], $tax_rates );

				// Set the new line_tax
				foreach ( $line_taxes as $_tax_id => $_tax_value ) {
					$items['line_tax'][ $item_id ][ $_tax_id ] = $_tax_value;
					
					if ( !isset( $vendor_taxes[ $vendor_user ] ) )
						$vendor_taxes[ $vendor_user ] = array( 'cart' => array(), 'shipping' => array() );

					if ( !isset( $vendor_taxes[ $vendor_user ]['cart'][ $_tax_id ] ) )
						$vendor_taxes[ $vendor_user ]['cart'][ $_tax_id ] = 0;

					$vendor_taxes[ $vendor_user ]['cart'][ $_tax_id ] += $_tax_value;
				}

				// Set the new line_subtotal_tax
				foreach ( $line_subtotal_taxes as $_tax_id => $_tax_value ) {
					$items['line_subtotal_tax'][ $item_id ][ $_tax_id ] = $_tax_value;
				}

				// Sum the item taxes
				foreach ( array_keys( $taxes + $line_taxes ) as $key ) {
					$taxes[ $key ] = ( isset( $line_taxes[ $key ] ) ? $line_taxes[ $key ] : 0 ) + ( isset( $taxes[ $key ] ) ? $taxes[ $key ] : 0 );
				}
			}
		}
	}

	// Get shipping taxes
	$vendor_shipping_costs = $order->vendor_shipping_totals;

	$shipping_tax   = 0;
	$shipping_total = 0;

	foreach ( $vendor_shipping_costs as $user_id => $cost ) {

		$shipping_total += $cost;

		if ( $user_id !== false ) {
			if ( !is_shipping_taxable( $user_id ) )
				continue;

			$tax_state = get_vendor_tax_state( $user_id );
			$tax_zip   = get_vendor_tax_zip( $user_id );

			if ( get_state_type( $tax_state ) == 'orig' ) {
				// Calculate tax based on vendor location
				$location = array(
					'country'  => 'US',
					'state'    => $tax_state,
					'city'     => '',
					'postcode' => $tax_zip,
				);
			} else {
				$location = WC_Tax::get_tax_location();

				list( $country, $state, $postcode, $city ) = $location;
			
				$location = array(
					'country'  => $country,
					'state'    => $state,
					'postcode' => $postcode,
					'city'     => $city,
				);
			}

			$rates = WC_Tax::find_shipping_rates( $location );

			if ( $rates ) {
				$taxes = WC_Tax::calc_shipping_tax( $cost, $rates );

				foreach ( $taxes as $rate_id => $amount ) {
					if ( !isset( $shipping_taxes[ $rate_id ] ) )
						$shipping_taxes[ $rate_id ] = 0;

					$shipping_taxes[ $rate_id ] += $amount;

					if ( !isset( $vendor_taxes[ $user_id ] ) )
						$vendor_taxes[ $user_id ] = array( 'cart' => array(), 'shipping' => array() );

					if ( !isset( $vendor_taxes[ $user_id ]['shipping'][ $rate_id ] ) )
						$vendor_taxes[ $user_id ]['shipping'][ $rate_id ] = 0;

					$vendor_taxes[ $user_id ]['shipping'][ $rate_id ] += $amount;
				}
			}
		}
	}

	// We assume a single shipping method for each order
	if ( isset( $items['shipping_method_id'] ) ) {
		$shipping_cost = $shipping_taxes = array();

		foreach ( $items['shipping_method_id'] as $item_id ) {
			$item_id                   = absint( $item_id );
			$shipping_cost[ $item_id ] = isset( $items['shipping_cost'][ $item_id ] ) ? wc_format_decimal( $items['shipping_cost'][ $item_id ] ) : 0;
	
			// Set the new shipping_taxes
			foreach ( $shipping_taxes as $_tax_id => $_tax_value ) {
				$items['shipping_taxes'][ $item_id ][ $_tax_id ] = $_tax_value;
			}
		}
	}

	// Remove old tax rows
	$order->remove_order_items( 'tax' );

	// Add tax rows
	foreach ( array_keys( $taxes + $shipping_taxes ) as $tax_rate_id ) {
		$order->add_tax( $tax_rate_id, isset( $taxes[ $tax_rate_id ] ) ? $taxes[ $tax_rate_id ] : 0, isset( $shipping_taxes[ $tax_rate_id ] ) ? $shipping_taxes[ $tax_rate_id ] : 0 );
	}

	// Create the new order_taxes
	foreach ( $order->get_taxes() as $tax_id => $tax_item ) {
		$items['order_taxes'][ $tax_id ] = absint( $tax_item['rate_id'] );
	}

	// Save order items
	wc_save_order_items( $order_id, $items );

	// Update vendor taxes array
	if ( get_option( 'woocommerce_tax_round_at_subtotal' ) == 'yes' ) {
		foreach ( $vendor_taxes as $vendor_id => &$taxes ) {
			$taxes['cart'] = array_map( array( 'WC_Tax', 'round' ), $taxes['cart'] );
			$taxes['shipping'] = array_map( array( 'WC_Tax', 'round' ), $taxes['shipping'] );
		}
	}

	update_post_meta( $order_id, '_vendor_taxes', $vendor_taxes );

	// Return HTML items
	$order = wc_get_order( $order_id );
	$data  = get_post_meta( $order_id );
	include( ABSPATH .'/'. PLUGINDIR .'/woocommerce/includes/admin/meta-boxes/views/html-order-items.php' );

	die();
}

add_action( 'wp_ajax_woocommerce_calc_line_taxes', 'order_recalculate_taxes', 1 );

// Store vendor taxes array when a new order is added
function store_vendor_taxes_array( $order_id ) {
	if ( isset( WC()->session->vendor_taxes ) ) {
		update_post_meta( $order_id, '_vendor_taxes', WC()->session->vendor_taxes );
	}
}

add_action( 'woocommerce_new_order', 'store_vendor_taxes_array', 10, 1 );

// Recalculate taxes when an order is resumed
function resume_order_calculate_taxes( $order_id ) {
	WC()->cart->calculate_totals();

	if ( isset( WC()->session->vendor_taxes ) ) {
		update_post_meta( $order_id, '_vendor_taxes', WC()->session->vendor_taxes );
	}
}

add_action( 'woocommerce_resume_order', 'resume_order_calculate_taxes', 10, 1 );

// Hide percentage in sales tax label
function get_tax_label( $label ) {
	$pos = strpos( $label, '(' );
	if ( $pos !== false ) {
		return substr( $label, 0, $pos - 1 );
	}
	return $label;
}

add_filter( 'woocommerce_rate_label', 'get_tax_label', 10, 1 );

// ADD "TAX" TAB TO STORE SETTINGS PAGE
function wcv_add_tax_settings_tab( $tabs ) {
	$user_id = get_current_user_id();

	if ( WCV_Vendors::is_vendor( $user_id ) ) // PREVENT TAB FROM BEING DISPLAYED DURING SIGN UP
	{
		$tabs[ 'tax' ] = array(
			'label' => 'Tax',
			'target' => 'tax',
			'class' => array(),
		);
	}

	return $tabs;
}

add_filter( 'wcv_store_tabs', 'wcv_add_tax_settings_tab' );

// OUTPUT TAX TAB CONTENT
function output_tax_tab_content() {
	require_once trailingslashit( VENDOR_TAXES_PATH ) . 'templates/tax-tab.php';
}

add_action( 'wcv_custom_after_settings_tabs', 'output_tax_tab_content' );

// SAVE VENDOR TAX SETTINGS
function wcv_save_tax_settings( $vendor_id ) {
	if ( WCV_Vendors::is_vendor( $vendor_id ) ) // DON'T SAVE SETTINGS WHEN SIGN UP FORM SUBMITTED
	{
		$collect_tax = (int) isset( $_POST[ 'collect_tax' ] );
		update_user_meta( $vendor_id, 'collect_tax', $collect_tax );
	}
}

add_action( 'wcv_pro_store_settings_saved', 'wcv_save_tax_settings' );