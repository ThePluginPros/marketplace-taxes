<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * WC Vendors Dashboard class.
 *
 * Provides all functionality related to the WC Vendors Pro dashboard.
 */
class TFM_WC_Vendors_Dashboard {

    /**
     * Constructor.
     *
     * Registers action hooks and filters.
     */
    public function __construct() {
        add_filter( 'wcv_product_tax_status', array( $this, 'hide_form_field' ) );
        add_filter( 'wcv_product_tax_class', array( $this, 'hide_form_field' ) );
        add_action( 'wcv_product_options_tax', array( $this, 'display_category_field' ) );
        add_action( 'wcv_product_variation_before_tax_class', array( $this, 'display_category_field' ) );
        add_filter( 'wcvendors_pro_product_variation_path', array( $this, 'set_variation_template_path' ) );
        add_filter(
            'pre_option_wcvendors_hide_product_variations_tax_class',
            array( $this, 'hide_variation_tax_class' )
        );
        add_filter( 'tfm_product_saved_actions', array( $this, 'register_save_action' ) );

        if ( 'vendor' === TFM()->settings->get( 'merchant_of_record' ) ) {
            add_filter( 'wcv_store_tabs', array( $this, 'add_store_settings_tab' ) );
            add_action( 'wcv_form_submit_before_store_save_button', array( $this, 'output_store_settings_tab' ) );
            add_action( 'wcv_pro_store_settings_saved', array( $this, 'on_store_settings_saved' ) );
        }
    }

    /**
     * Adds a 'Taxes' tab to the store settings page.
     *
     * @param array $tabs
     *
     * @return array
     */
    public function add_store_settings_tab( $tabs ) {
        if ( ! isset( $tabs['taxes'] ) ) {
            $tabs['tax'] = [
                'label'  => __( 'Taxes', 'taxjar-for-marketplaces' ),
                'target' => 'tax',
                'class'  => [],
            ];
        }
        return $tabs;
    }

    /**
     * Outputs the 'Taxes' tab.
     */
    public function output_store_settings_tab() {
        TFM()->assets->enqueue(
            'style',
            'taxjar-for-marketplaces.wc-vendors',
            [
                'deps' => [ 'dashicons' ],
            ]
        );

        wc_get_template(
            'store-settings-form.php',
            [
                'form' => $this->form(),
            ],
            'wc-vendors/dashboard/',
            TFM()->path( 'includes/integrations/wc-vendors/templates/' )
        );
    }

    /**
     * Saves the vendor settings form.
     */
    public function on_store_settings_saved() {
        $this->form()->save( $_POST );
    }

    /**
     * Returns the single settings form instance.
     *
     * @return TFM_Vendor_Settings_Form
     */
    private function form() {
        static $instance = null;

        if ( is_null( $instance ) ) {
            $instance = new TFM_Vendor_Settings_Form();
        }

        return $instance;
    }

    /**
     * Hides a WC Vendors form field by wrapping it in a hidden div.
     *
     * @param array $field
     *
     * @return array
     */
    public function hide_form_field( $field ) {
        $field['wrapper_start'] = '<div style="display: none;">';
        $field['wrapper_end']   = '</div>';

        return $field;
    }

    /**
     * Displays a 'Tax category' field on the Edit Product screen.
     *
     * @param int $variation_id
     */
    public function display_category_field( $variation_id = null ) {
        $is_variation = ! empty( $variation_id );

        if ( $is_variation ) {
            $product_id = $variation_id;
        } else {
            $product_id = get_query_var( 'object_id' );
        }

        $selected_category = get_post_meta( $product_id, 'tax_category', true );

        TFM()->assets->enqueue( 'style', 'taxjar-for-marketplaces.category-select' );
        TFM()->assets->enqueue( 'script', 'taxjar-for-marketplaces.category-select' );

        require __DIR__ . '/views/html-field-tax-category.php';
    }

    /**
     * Sets the path to the WC Vendors product variation template.
     *
     * @param string $path
     *
     * @return string
     */
    public function set_variation_template_path( $path ) {
        if ( 'forms/partials/wcvendors-pro-product-variation.php' === $path ) {
            // Default template in use - override it
            $path = __DIR__ . '/views/partials/wcvendors-pro-product-variation.php';
        } else {
            // Custom template in use - use it and log an error
            wc_get_logger()->error(
                __(
                    "A custom product variation template is in use. Tax category assignment won't work for variations.",
                    'taxjar-for-marketplaces'
                )
            );
        }
        return $path;
    }

    /**
     * Hides the tax class field for product variations by forcing the value
     * of the option `wcvendors_hide_product_variations_tax_class` to 'yes'.
     *
     * @return string
     */
    public function hide_variation_tax_class() {
        return 'yes';
    }

    /**
     * Registers `wcv_save_product` as a product save action.
     *
     * This ensures that the selected tax categories are saved by the product
     * controller.
     *
     * @param array $actions
     *
     * @return array
     */
    public function register_save_action( $actions ) {
        $actions[] = 'wcv_save_product';
        return $actions;
    }

}

new TFM_WC_Vendors_Dashboard();
