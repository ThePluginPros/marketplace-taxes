<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Adds a bulk edit field for the product tax category.
 */
class MT_Admin_Bulk_Edit {

    /**
     * Constructor.
     *
     * Registers action hooks and filters.
     */
    public function __construct() {
        add_action( 'woocommerce_product_bulk_edit_start', array( $this, 'output_fields' ) );
        add_action( 'woocommerce_product_bulk_edit_save', array( $this, 'save_fields' ) );
    }

    /**
     * Outputs a bulk edit field for the tax category.
     */
    public function output_fields() {
        MT()->assets->enqueue( 'script', 'marketplace-taxes.category-select' );

        require_once __DIR__ . '/views/html-select-category-bulk.php';
    }

    /**
     * Handle bulk tax category updates.
     *
     * @param WC_Product $product The product being saved.
     */
    public function save_fields( $product ) {
        $category = sanitize_text_field( $_REQUEST['tax_category'] );

        if ( '' !== $category ) {
            update_post_meta( $product->get_id(), 'tax_category', $category );
        }
    }

}

new MT_Admin_Bulk_Edit();
