<?php

/**
 * Marketplace Taxes functions.
 *
 * @since 1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Returns a boolean indicating whether the current seller has completed their tax setup.
 *
 * @return bool
 */
function mt_is_seller_setup_complete() {
    $setup_complete = true;

    foreach ( mt_get_seller_setup_steps() as $setup_step ) {
        if ( ! $setup_step['complete'] ) {
            $setup_complete = false;
            break;
        }
    }

    return $setup_complete;
}

/**
 * Returns the seller tax setup steps from the current integration.
 *
 * @param string $context Admin or frontend context.
 *
 * @return array
 */
function mt_get_seller_setup_steps( $context = 'frontend' ) {
    return MT()->integration->get_vendor_setup_steps( $context );
}
