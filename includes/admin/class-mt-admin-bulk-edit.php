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
        add_action( 'woocommerce_product_bulk_edit_start', array( $this, 'filter_wc_tax_enabled' ), 1000 );
        add_action( 'woocommerce_product_bulk_edit_end', array( $this, 'remove_wc_tax_enabled_filter' ), 1 );
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

    /**
     * Adds a filter hook to prevent the Tax class and Tax status dropdowns from being displayed in the bulk editor.
     */
    public function filter_wc_tax_enabled() {
        add_filter( 'wc_tax_enabled', '__return_false' );
    }

    /**
     * Removes the filter added in `filter_wc_tax_enabled()` just after the bulk editor is rendered.
     *
     * @see MT_Admin_Bulk_Edit::filter_wc_tax_enabled()
     */
    public function remove_wc_tax_enabled_filter() {
        remove_filter( 'wc_tax_enabled', '__return_false' );
    }

}

new MT_Admin_Bulk_Edit();
