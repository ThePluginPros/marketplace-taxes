<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Product data meta box.
 *
 * Adds a tax category select box to the WC product data meta box.
 */
class MT_Product_Data_Meta_Box {

    /**
     * Constructor.
     *
     * Registers action hooks and filters.
     */
    public function __construct() {
        add_action( 'woocommerce_product_options_tax', array( $this, 'tax_category_field' ) );
        add_action( 'woocommerce_product_after_variable_attributes', array( $this, 'tax_category_field' ), 10, 3 );
    }

    /**
     * Outputs the Tax category field.
     *
     * @param int   $loop
     * @param array $variation_data
     * @param array $variation
     */
    public function tax_category_field( $loop = null, $variation_data = null, $variation = null ) {
        global $post;

        $is_variation = ! empty( $variation );

        if ( $is_variation ) {
            $product_id = $variation->ID;
        } else {
            $product_id = $post->ID;
        }

        $selected_category = get_post_meta( $product_id, 'tax_category', true );

        MT()->assets->enqueue( 'script', 'marketplace-taxes.category-select' );

        require __DIR__ . '/views/html-select-category.php';
    }

}

new MT_Product_Data_Meta_Box();
