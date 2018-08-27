<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Product controller.
 *
 * Saves the selected tax categories when a product or product variation is saved.
 */
class TFM_Product_Controller {

    /**
     * Constructor.
     *
     * Registers action hooks and filters.
     */
    public function __construct() {
        add_action( 'woocommerce_ajax_save_product_variations', array( $this, 'ajax_save_variations' ) );
        add_action( 'tfm_load_integration', array( $this, 'register_save_hooks' ) );
    }

    /**
     * Registers save_product to run on save_post_product and any other actions
     * defined by integrations.
     */
    public function register_save_hooks() {
        $actions = apply_filters( 'tfm_product_saved_actions', [ 'save_post_product' ] );

        foreach ( $actions as $action ) {
            add_action( $action, array( $this, 'save_product' ) );
        }
    }

    /**
     * Saves the selected tax categories for product variations.
     */
    public function ajax_save_variations() {
        $variable_post_id    = $_POST['variable_post_id'];
        $category_selections = $_POST['tax_category'];
        $max_loop            = max( array_keys( $_POST['variable_post_id'] ) );

        for ( $i = 0; $i <= $max_loop; $i++ ) {
            if ( isset( $variable_post_id[ $i ] ) ) {
                $variation_id = $variable_post_id[ $i ];

                if ( isset( $category_selections[ $variation_id ] ) ) {
                    update_post_meta( $variation_id, 'tax_category', $category_selections[ $variation_id ] );
                }
            }
        }
    }

    /**
     * Saves the selected tax category for simple and variable products.
     */
    public function save_product() {
        if ( ! isset( $_REQUEST['_inline_edit'] ) && ! isset( $_REQUEST['bulk_edit'] ) ) {
            $selected_categories = isset( $_REQUEST['tax_category'] ) ? $_REQUEST['tax_category'] : [];

            foreach ( $selected_categories as $product_id => $category ) {
                update_post_meta( $product_id, 'tax_category', $category );
            }
        }
    }

}

new TFM_Product_Controller();
