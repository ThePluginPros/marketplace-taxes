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
        add_action( 'wcvendors_settings_after_shop_description', array( $this, 'output_address_fields' ) );
        add_action( 'wcvendors_shop_settings_admin_saved', array( $this, 'save_address_fields' ) );
        add_action( 'wcvendors_shop_settings_saved', array( $this, 'save_address_fields' ) );

        if ( 'vendor' === TFM()->settings->get( 'merchant_of_record' ) ) {
            add_filter( 'wcv_store_tabs', array( $this, 'add_store_settings_tab' ) );
            add_action( 'wcv_form_submit_before_store_save_button', array( $this, 'output_store_settings_tab' ) );
            add_action( 'wcv_pro_store_settings_saved', array( $this, 'on_store_settings_saved' ) );
            add_action( 'wcv_form_input_before__wcv_store_address1', array( $this, 'output_address_anchor' ) );
            add_action( 'wp_ajax_tfm_complete_tax_setup', array( $this, 'ajax_complete_tax_setup' ) );
            add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
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
     * Outputs store address fields on the WC Vendors Free settings page.
     */
    public function output_address_fields() {
        if ( ! apply_filters( 'tfm_output_address_fields', true ) ) {
            return;
        }

        $vendor_id = get_current_user_id();

        $country  = get_user_meta( $vendor_id, '_wcv_store_country', true );
        $address1 = get_user_meta( $vendor_id, '_wcv_store_address1', true );
        $address2 = get_user_meta( $vendor_id, '_wcv_store_address2', true );
        $city     = get_user_meta( $vendor_id, '_wcv_store_city', true );
        $state    = get_user_meta( $vendor_id, '_wcv_store_state', true );
        $postcode = get_user_meta( $vendor_id, '_wcv_store_postcode', true );

        $context = is_admin() ? 'admin' : 'frontend';

        TFM_WC_Vendors_Form_Helper::country_select2(
            [
                'id'                => '_wcv_store_country',
                'title'             => __( 'Store Country' ) . '<a name="address"></a>',
                'type'              => 'text',
                'value'             => $country,
                'class'             => 'js_field-country regular-text',
                'wrapper_class'     => 'tfm-control-group',
                'custom_attributes' => [ 'required' => 'required' ],
            ],
            $context
        );

        TFM_WC_Vendors_Form_Helper::input(
            [
                'id'            => '_wcv_store_address1',
                'title'         => __( 'Store Address' ),
                'placeholder'   => __( 'Street Address' ),
                'type'          => 'text',
                'class'         => 'regular-text',
                'wrapper_class' => 'tfm-control-group',
                'value'         => $address1,
            ],
            $context
        );

        TFM_WC_Vendors_Form_Helper::input(
            [
                'id'            => '_wcv_store_address2',
                'placeholder'   => __( 'Apartment, unit, suite etc.' ),
                'type'          => 'text',
                'class'         => 'regular-text',
                'wrapper_class' => 'tfm-control-group',
                'value'         => $address2,
            ],
            $context
        );

        TFM_WC_Vendors_Form_Helper::input(
            [
                'id'            => '_wcv_store_city',
                'title'         => __( 'City / Town' ),
                'placeholder'   => __( 'City / Town' ),
                'type'          => 'text',
                'class'         => 'regular-text',
                'wrapper_class' => 'tfm-control-group',
                'value'         => $city,
            ],
            $context
        );

        TFM_WC_Vendors_Form_Helper::input(
            [
                'id'                => '_wcv_store_state',
                'title'             => __( 'State / County' ),
                'placeholder'       => __( 'State / County' ),
                'value'             => $state,
                'class'             => 'js_field-state regular-text',
                'wrapper_class'     => 'tfm-control-group',
                'custom_attributes' => [ 'required' => 'required' ],
            ],
            $context
        );

        TFM_WC_Vendors_Form_Helper::input(
            [
                'id'                => '_wcv_store_postcode',
                'title'             => __( 'Postcode / Zip' ),
                'placeholder'       => __( 'Postcode / Zip' ),
                'value'             => $postcode,
                'class'             => 'regular-text',
                'wrapper_class'     => 'tfm-control-group',
                'custom_attributes' => [ 'required' => 'required' ],
            ],
            $context
        );

        if ( 'admin' === $context ) {
            $this->enqueue_admin_assets();
        }
    }

    /**
     * Enqueues the assets for the admin dashboard page.
     */
    private function enqueue_admin_assets() {
        // Enqueue the Woo user JS to power up the country select boxes
        if ( ! wp_script_is( 'wc-users', 'enqueued' ) ) {
            TFM()->assets->enqueue(
                'script',
                'woocommerce.admin/users',
                [
                    'deps'      => [
                        'jquery',
                        'woocommerce.admin/wc-enhanced-select',
                        'woocommerce.selectWoo/selectWoo.full',
                    ],
                    'ver'       => WC_VERSION,
                    'in_footer' => true,
                    'localize'  => [
                        'wc_users_params' => [
                            'countries'              => json_encode(
                                array_merge(
                                    WC()->countries->get_allowed_country_states(),
                                    WC()->countries->get_shipping_country_states()
                                )
                            ),
                            'i18n_select_state_text' => esc_attr__( 'Select an option&hellip;', 'woocommerce' ),
                        ],
                    ],
                ]
            );
        }

        if ( ! wp_style_is( 'woocommerce_admin_styles', 'enqueued' ) ) {
            TFM()->assets->enqueue( 'style', 'woocommerce.admin' );
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
        $dashboard_pages = tfm_wcv_get_dashboard_page_ids();

        if ( ! is_page( $dashboard_pages ) ) {
            return;
        }

        TFM()->assets->enqueue(
            'style',
            'taxjar-for-marketplaces.wc-vendors',
            [
                'deps' => [ 'dashicons', 'taxjar-for-marketplaces.tax-setup' ],
            ]
        );

        if ( ! get_user_meta( get_current_user_id(), 'tax_settings_reviewed', true ) ) {
            TFM()->assets->enqueue(
                'script',
                'taxjar-for-marketplaces.wcv-tax-setup',
                [
                    'deps'     => [ 'jquery' ],
                    'localize' => [
                        'tfm_wcv_tax_setup_data' => [
                            'ajax_url' => admin_url( 'admin-ajax.php' ),
                        ],
                    ],
                ]
            );
        }
    }

}

new TFM_WC_Vendors_Dashboard();
