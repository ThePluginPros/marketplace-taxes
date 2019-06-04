<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * WC Vendors Dashboard class.
 *
 * Provides all functionality related to the WC Vendors Pro dashboard.
 */
class MT_WC_Vendors_Dashboard {

    /**
     * Constructor.
     *
     * Registers action hooks and filters.
     */
    public function __construct() {
        add_action( 'wcv_after_product_details', array( $this, 'display_category_field' ) );
        add_action( 'wcv_product_variation_before_tax_class', array( $this, 'display_category_field' ), 10, 2 );
        add_filter( 'wcvendors_pro_product_variation_path', array( $this, 'set_variation_template_path' ) );
        add_filter( 'mt_product_saved_actions', array( $this, 'register_save_action' ) );
        add_action( 'wcv_save_product_variation', array( $this, 'set_variation_post_id' ), 10, 2 );
        add_action( 'wcvendors_shop_settings_admin_saved', array( $this, 'save_address_fields' ) );
        add_action( 'wcvendors_shop_settings_saved', array( $this, 'save_address_fields' ) );
        add_filter( 'pre_option_wcvendors_hide_settings_store_address', 'mt_return_yes' );

        if ( is_admin() ) {
            add_action( 'wcvendors_settings_after_shop_description', array( $this, 'output_address_fields' ) );
        } else {
            add_action( 'template_redirect', array( $this, 'address_fields_hook' ) );
        }

        if ( 'vendor' === MT()->settings->get( 'merchant_of_record' ) ) {
            add_filter( 'wcv_store_tabs', array( $this, 'add_store_settings_tab' ) );
            add_action( 'wcv_form_submit_before_store_save_button', array( $this, 'output_store_settings_tab' ) );
            add_action( 'wcv_pro_store_settings_saved', array( $this, 'on_store_settings_saved' ) );
            add_action( 'wcv_form_input_before__wcv_store_address1', array( $this, 'output_address_anchor' ) );
            add_action( 'wp_ajax_mt_complete_tax_setup', array( $this, 'ajax_complete_tax_setup' ) );
            add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        }
    }

    /**
     * Adds an appropriate hook to output the store address fields.
     */
    public function address_fields_hook() {
        if ( mt_wcv_is_dashboard_page() ) {
            add_action( 'wcv_form_input_after__wcv_store_phone', array( $this, 'output_address_fields' ) );
        } else {
            add_action( 'wcvendors_settings_after_shop_description', array( $this, 'output_address_fields' ) );
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
                'label'  => __( 'Taxes', 'marketplace-taxes' ),
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
        wc_get_template(
            'store-settings-form.php',
            [
                'form' => $this->form(),
            ],
            'wc-vendors/dashboard/',
            MT()->path( 'includes/integrations/wc-vendors/templates/' )
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
     * @return MT_Vendor_Settings_Form
     */
    private function form() {
        static $instance = null;

        if ( is_null( $instance ) ) {
            $instance = new MT_Vendor_Settings_Form();
        }

        return $instance;
    }

    /**
     * Displays a 'Tax category' field on the Edit Product screen.
     *
     * @param int $variation_id
     * @param int $loop
     */
    public function display_category_field( $variation_id = null, $loop = null ) {
        $is_variation = ! is_null( $loop );

        if ( $is_variation ) {
            $field_name = "variation_tax_category[$loop]";
            $product_id = $variation_id;
        } else {
            $field_name = 'tax_category';
            $product_id = get_query_var( 'object_id' );
        }

        $selected_category = get_post_meta( $product_id, 'tax_category', true );

        MT()->assets->enqueue( 'style', 'marketplace-taxes.category-select' );
        MT()->assets->enqueue( 'script', 'marketplace-taxes.category-select' );

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
                    'marketplace-taxes'
                )
            );
        }

        return $path;
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

    /**
     * Sets the post ID for each product variation so that variation tax
     * categories are saved correctly.
     *
     * @param int $variation_id
     * @param int $loop
     */
    public function set_variation_post_id( $variation_id, $loop ) {
        if ( isset( $_REQUEST['variation_tax_category'] ) ) {
            $_REQUEST['variation_tax_category'][ $variation_id ] = $_REQUEST['variation_tax_category'][ $loop ];

            unset( $_REQUEST['variation_tax_category'][ $loop ] );
        }
    }

    /**
     * Outputs an anchor before the 'Store Address' setting so we can link to it.
     */
    public function output_address_anchor() {
        // This should only be output on the frontend
        if ( ! is_admin() ) {
            echo '<a name="address"></a>';
        }
    }

    /**
     * Completes the tax setup process via AJAX.
     */
    public function ajax_complete_tax_setup() {
        update_user_meta( get_current_user_id(), 'tax_settings_reviewed', true );

        wp_send_json_success();
    }

    /**
     * Outputs store address fields on the vendor store settings page.
     */
    public function output_address_fields() {
        $vendor_id = get_current_user_id();

        $country  = get_user_meta( $vendor_id, '_wcv_store_country', true );
        $address1 = get_user_meta( $vendor_id, '_wcv_store_address1', true );
        $address2 = get_user_meta( $vendor_id, '_wcv_store_address2', true );
        $city     = get_user_meta( $vendor_id, '_wcv_store_city', true );
        $state    = get_user_meta( $vendor_id, '_wcv_store_state', true );
        $postcode = get_user_meta( $vendor_id, '_wcv_store_postcode', true );

        $context = is_admin() ? 'admin' : 'frontend';

        if ( mt_wcv_is_dashboard_page() ) {
            $wrapper_class = '';
        } else {
            $wrapper_class = 'mt-control-group';
        }

        MT_WC_Vendors_Form_Helper::country_select2(
            [
                'id'                => '_wcv_store_country',
                'title'             => __( 'Store Country', 'marketplace-taxes' ) . '<a name="address"></a>',
                'type'              => 'text',
                'value'             => $country,
                'class'             => 'regular-text',
                'wrapper_class'     => $wrapper_class,
                'custom_attributes' => [
                    'required'         => 'required',
                    'data-state_input' => '#_wcv_store_state',
                ],
            ],
            $context
        );

        MT_WC_Vendors_Form_Helper::input(
            [
                'id'            => '_wcv_store_address1',
                'title'         => __( 'Store Address', 'marketplace-taxes' ),
                'placeholder'   => __( 'Street Address', 'marketplace-taxes' ),
                'type'          => 'text',
                'class'         => 'regular-text',
                'wrapper_class' => $wrapper_class,
                'value'         => $address1,
            ],
            $context
        );

        MT_WC_Vendors_Form_Helper::input(
            [
                'id'            => '_wcv_store_address2',
                'placeholder'   => __( 'Apartment, unit, suite etc.', 'marketplace-taxes' ),
                'type'          => 'text',
                'class'         => 'regular-text',
                'wrapper_class' => $wrapper_class,
                'value'         => $address2,
            ],
            $context
        );

        MT_WC_Vendors_Form_Helper::input(
            [
                'id'            => '_wcv_store_city',
                'title'         => __( 'City / Town', 'marketplace-taxes' ),
                'placeholder'   => __( 'City / Town', 'marketplace-taxes' ),
                'type'          => 'text',
                'class'         => 'regular-text',
                'wrapper_class' => $wrapper_class,
                'value'         => $city,
            ],
            $context
        );

        MT_WC_Vendors_Form_Helper::input(
            [
                'id'                => '_wcv_store_state',
                'title'             => __( 'State / County', 'marketplace-taxes' ),
                'placeholder'       => __( 'State / County', 'marketplace-taxes' ),
                'value'             => $state,
                'class'             => 'regular-text',
                'wrapper_class'     => $wrapper_class,
                'custom_attributes' => [ 'required' => 'required' ],
            ],
            $context
        );

        MT_WC_Vendors_Form_Helper::input(
            [
                'id'                => '_wcv_store_postcode',
                'title'             => __( 'Postcode / Zip', 'marketplace-taxes' ),
                'placeholder'       => __( 'Postcode / Zip', 'marketplace-taxes' ),
                'value'             => $postcode,
                'class'             => 'regular-text',
                'wrapper_class'     => $wrapper_class,
                'custom_attributes' => [ 'required' => 'required' ],
            ],
            $context
        );

        MT()->assets->enqueue( 'script', 'marketplace-taxes.country-select' );

        if ( 'admin' === $context ) {
            add_action( 'admin_footer', array( $this, 'fix_country_select_issue' ) );
        }
    }

    /**
     * Saves the store address fields.
     *
     * @param int $user_id ID of vendor whose settings are being saved.
     */
    public function save_address_fields( $user_id ) {
        if ( isset( $_POST['_wcv_store_country'] ) ) {
            update_user_meta( $user_id, '_wcv_store_country', trim( $_POST['_wcv_store_country'] ) );
        }
        if ( isset( $_POST['_wcv_store_address1'] ) ) {
            update_user_meta( $user_id, '_wcv_store_address1', trim( $_POST['_wcv_store_address1'] ) );
        }
        if ( isset( $_POST['_wcv_store_address2'] ) ) {
            update_user_meta( $user_id, '_wcv_store_address2', trim( $_POST['_wcv_store_address2'] ) );
        }
        if ( isset( $_POST['_wcv_store_city'] ) ) {
            update_user_meta( $user_id, '_wcv_store_city', trim( $_POST['_wcv_store_city'] ) );
        }
        if ( isset( $_POST['_wcv_store_state'] ) ) {
            update_user_meta( $user_id, '_wcv_store_state', trim( $_POST['_wcv_store_state'] ) );
        }
        if ( isset( $_POST['_wcv_store_postcode'] ) ) {
            update_user_meta( $user_id, '_wcv_store_postcode', trim( $_POST['_wcv_store_postcode'] ) );
        }
    }

    /**
     * Enqueues the scripts and styles required for dashboard pages.
     */
    public function enqueue_assets() {
        $dashboard_pages = mt_wcv_get_dashboard_page_ids();

        if ( ! is_page( $dashboard_pages ) ) {
            return;
        }

        MT()->assets->enqueue(
            'style',
            'marketplace-taxes.wc-vendors',
            [
                'deps' => [ 'dashicons', 'marketplace-taxes.tax-setup' ],
            ]
        );

        $tax_settings_reviewed = get_user_meta( get_current_user_id(), 'tax_settings_reviewed', true );

        if ( 'settings' === get_query_var( 'object' ) && ! $tax_settings_reviewed ) {
            MT()->assets->enqueue(
                'script',
                'marketplace-taxes.wcv-tax-setup',
                [
                    'deps'     => [ 'jquery' ],
                    'localize' => [
                        'mt_wcv_tax_setup_data' => [
                            'ajax_url' => admin_url( 'admin-ajax.php' ),
                        ],
                    ],
                ]
            );
        }
    }

    /**
     * Temporary fix for https://github.com/wcvendors/wcvendors/issues/556.
     *
     * This can be removed once we drop support for versions of WC Vendors
     * where this issue has not been fixed.
     */
    public function fix_country_select_issue() {
        ?>
        <script>
            (function () {
                if (!window.jQuery) {
                    return;
                }

                var $ = jQuery;

                $(document).on('country_to_state_changed', function () {
                    var $form = $('table.form-table > form');
                    var $input = $('#_wcv_store_state');

                    if ($form.length && $input.length) {
                        $form.attr('id', 'vendor-settings-form');
                        $input.attr('form', 'vendor-settings-form');
                    }
                });
            })();
        </script>
        <?php
    }

}

new MT_WC_Vendors_Dashboard();
