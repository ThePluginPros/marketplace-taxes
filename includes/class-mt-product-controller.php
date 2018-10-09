<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Product controller.
 *
 * Saves the selected tax categories when a product or product variation is saved.
 */
class MT_Product_Controller {

    /**
     * Constructor.
     *
     * Registers action hooks and filters.
     */
    public function __construct() {
        add_action( 'mt_load_integration', array( $this, 'register_save_hooks' ) );
    }

    /**
     * Adds action hooks for save_tax_categories.
     */
    public function register_save_hooks() {
        $actions = [ 'woocommerce_ajax_save_product_variations' ];

        if ( is_admin() ) {
            $actions[] = 'save_post_product';
        }

        $actions = apply_filters( 'mt_product_saved_actions', $actions );

        foreach ( $actions as $action ) {
            add_action( $action, array( $this, 'save_tax_categories' ) );
        }
    }

    /**
     * Saves the selected tax category for a product and its variations (if any).
     *
     * @param int $product_id
     */
    public function save_tax_categories( $product_id ) {
        if ( isset( $_REQUEST['_inline_edit'] ) || isset( $_REQUEST['bulk_edit'] ) ) {
            return;
        }

        if ( isset( $_REQUEST['tax_category'] ) ) {
            update_post_meta( $product_id, 'tax_category', $_REQUEST['tax_category'] );
        }

        if ( isset( $_REQUEST['variation_tax_category'] ) ) {
            foreach ( $_REQUEST['variation_tax_category'] as $product_id => $tax_category ) {
                update_post_meta( $product_id, 'tax_category', $tax_category );
            }
        }
    }

}

new MT_Product_Controller();
