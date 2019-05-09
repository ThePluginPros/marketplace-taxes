<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Dokan dashboard.
 *
 * Provides all functionality related to the Dokan frontend vendor dashboard.
 */
class MT_Dokan_Dashboard {

    /**
     * Constructor.
     *
     * Registers action hooks and filters.
     */
    public function __construct() {
        add_filter( 'dokan_get_dashboard_settings_nav', array( $this, 'add_tax_settings_tab' ) );
        add_filter( 'dokan_render_settings_content', array( $this, 'output_tax_settings' ) );
        add_action( 'wp_ajax_dokan_settings', array( $this, 'ajax_save_tax_settings' ), 5 );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        add_action( 'mt_dokan_after_tax_settings_form', array( $this, 'complete_tax_review_step' ) );
        add_filter( 'dokan_dashboard_settings_helper_text', array( $this, 'set_tax_settings_help_text' ), 10, 2 );
        add_filter( 'mt_form_error_callback', array( $this, 'add_error' ), 10, 2 );
        add_filter( 'dokan_ajax_settings_response', array( $this, 'filter_ajax_response' ) );
        add_action( 'dokan_dashboard_content_inside_before', array( $this, 'output_address_notice' ) );
        add_action( 'dokan_settings_content_inside_before', array( $this, 'output_address_notice' ) );
        add_filter( 'wp_dropdown_cats', array( $this, 'maybe_output_tax_category_field' ), 10, 2 );
        add_action( 'dokan_dashboard_content_after', array( $this, 'maybe_output_tax_category_popup' ) );
        add_action( 'dokan_new_product_added', array( $this, 'save_tax_category' ), 10, 2 );
        add_action( 'dokan_product_updated', array( $this, 'save_tax_category' ), 10, 2 );
    }

    /**
     * Returns the vendor settings form instance.
     *
     * @return MT_Vendor_Settings_Form
     */
    private function form() {
        static $form = null;

        if ( is_null( $form ) ) {
            $form = new MT_Vendor_Settings_Form( dokan_get_current_user_id() );
        }

        return $form;
    }

    /**
     * Adds a 'Tax' settings tab to the vendor dashboard.
     *
     * @param array $settings_menu
     *
     * @return array
     */
    public function add_tax_settings_tab( $settings_menu ) {
        $settings_menu['tax'] = [
            'title'      => __( 'Tax', 'marketplace-taxes' ),
            'icon'       => '<i class="fa fa-percent"></i>',
            'url'        => dokan_get_navigation_url( 'settings/tax' ),
            'pos'        => 70,
            'permission' => 'dokan_view_store_payment_menu',
        ];

        return $settings_menu;
    }

    /**
     * Outputs the vendor tax settings form.
     *
     * @param array $query_vars WP query vars.
     */
    public function output_tax_settings( $query_vars ) {
        $settings_page = isset( $query_vars['settings'] ) ? $query_vars['settings'] : '';

        if ( 'tax' !== $settings_page ) {
            return;
        }

        wc_get_template(
            'tax-settings-form.php',
            [
                'form' => $this->form(),
            ],
            'marketplace-taxes/dashboard/',
            MT()->path( 'includes/integrations/dokan/templates/' )
        );
    }

    /**
     * Saves the tax settings form.
     */
    public function ajax_save_tax_settings() {
        $form_id = isset( $_POST['form_id'] ) ? $_POST['form_id'] : '';

        if ( 'tax-form' !== $form_id ) {
            return;
        }

        check_ajax_referer( 'dokan_tax_settings_nonce' );

        if ( ! dokan_is_user_seller( get_current_user_id() ) ) {
            wp_send_json_error( __( 'Are you cheating?', 'marketplace-taxes' ) );
        }

        // Validation errors will be collected here
        $validate_error = new WP_Error();

        add_filter(
            'mt_form_error_callback',
            function () use ( $validate_error ) {
                return function ( $field_id, $error ) use ( $validate_error ) {
                    $validate_error->add( $field_id, $error );
                };
            }
        );

        $this->form()->save( $_POST );

        if ( ! empty( $validate_error->get_error_messages() ) ) {
            wp_send_json_error( $this->render_errors( $validate_error ) );
        }

        $response = [
            'msg' => __( 'Your information has been saved successfully', 'marketplace-taxes' ),
        ];

        wp_send_json_success( apply_filters( 'dokan_ajax_settings_response', $response ) );
    }

    /**
     * Renders validation errors as HTML.
     *
     * @param WP_Error $validate_error
     *
     * @return string
     */
    private function render_errors( $validate_error ) {
        $output = '<ul class="mt-form-errors">';

        foreach ( $validate_error->get_error_messages() as $error_message ) {
            $output .= '<li>' . $error_message . '</li>';
        }

        $output .= '</ul>';

        return $output;
    }

    /**
     * Sets the `tax_settings_reviewed` flag after a vendor sees the tax
     * settings form.
     */
    public function complete_tax_review_step() {
        update_user_meta( dokan_get_current_user_id(), 'tax_settings_reviewed', true );
    }

    /**
     * Sets the help text for the tax settings page.
     *
     * @param string $help_text Help text.
     * @param string $page      Settings page ID.
     *
     * @return string
     */
    public function set_tax_settings_help_text( $help_text, $page ) {
        if ( 'tax' === $page ) {
            return $this->form()->description();
        }

        return $help_text;
    }

    /**
     * Enqueues the scripts and styles for the dashboard.
     */
    public function enqueue_assets() {
        if ( ! dokan_is_seller_dashboard() ) {
            return;
        }

        MT()->assets->enqueue(
            'style',
            'marketplace-taxes.dokan',
            [
                'deps' => [ 'marketplace-taxes.tax-setup' ],
            ]
        );

        MT()->assets->enqueue(
            'script',
            'marketplace-taxes.dokan',
            [
                'deps'      => [
                    'jquery',
                    'dokan-form-validate',
                ],
                'in_footer' => true,
                'localize'  => [
                    'mt_settings_data' => [
                        'i18n_api_key_required' => __( 'A valid API key is required.', 'marketplace-taxes' ),
                    ],
                ],
                'ver'       => '1.0.1',
            ]
        );
    }

    /**
     * Adds a `tax_setup_complete` flag to the settings AJAX response.
     *
     * @param array $response
     *
     * @return array
     */
    public function filter_ajax_response( $response ) {
        $response['tax_setup_complete'] = mt_is_seller_setup_complete();

        return $response;
    }

    /**
     * Outputs a notice in the seller dashboard when tax setup is required.
     */
    public function output_address_notice() {
        if ( mt_is_seller_setup_complete() ) {
            return;
        }

        $setup_steps = mt_get_seller_setup_steps();

        echo '<div id="address_notice" class="dokan-alert dokan-alert-danger">';

        wc_get_template(
            'address-notice.php',
            compact( 'setup_steps' ),
            'marketplace-taxes/',
            MT()->path( 'templates/' )
        );

        echo '</div>';
    }

    /**
     * Outputs the Tax Category dropdown under the Product Tags dropdown.
     *
     * @param string $output Output from `wp_dropdown_categories`
     * @param array  $args   Arguments passed to `wp_dropdown_categories`
     *
     * @return string
     * @todo Ask Dokan devs to add a suitable action
     *
     */
    public function maybe_output_tax_category_field( $output, $args ) {
        global $post;

        if ( ! dokan_is_seller_dashboard() && ! $this->is_edit_product_page() ) {
            return $output;
        }

        if ( 'product_tag' !== $args['taxonomy'] ) {
            return $output;
        }

        $product_id = 0;

        if ( ! empty( $_GET['product_id'] ) ) {
            $product_id = $_GET['product_id'];
        } elseif ( 'product' === $post->post_type ) {
            $product_id = $post->ID;
        }

        // Close .dokan-form-group
        $output .= '</div>';

        // Append HTML markup for Tax Category select box
        $output .= wc_get_template_html(
            'tax-category-dropdown.php',
            [
                'selected'     => get_post_meta( $product_id, 'tax_category', true ),
                'is_variation' => false,
            ],
            'marketplace-taxes/dokan/',
            MT()->path( 'includes/integrations/dokan/templates/' )
        );

        return $output;
    }

    /**
     * Checks whether the current page is the Dokan Edit Product screen.
     *
     * @return bool
     */
    private function is_edit_product_page() {
        return get_query_var( 'edit' ) && is_singular( 'product' );
    }

    /**
     * Outputs the markup for the Tax Category popup on the product list and
     * product detail pages.
     */
    public function maybe_output_tax_category_popup() {
        global $wp;

        $is_list         = isset( $wp->query_vars['products'] );
        $is_add_product  = isset( $wp->query_vars['new-product'] );
        $is_edit_product = $this->is_edit_product_page();

        if ( ! $is_list && ! $is_add_product && ! $is_edit_product ) {
            return;
        }

        MT()->assets->enqueue( 'style', 'marketplace-taxes.category-select' );
        MT()->assets->enqueue( 'script', 'marketplace-taxes.category-select' );

        require MT()->path() . '/includes/admin/views/html-tax-category-modal.php';
    }

    /**
     * Saves the Tax Category for a newly created product.
     *
     * @param int   $product_id
     * @param array $post_data
     */
    public function save_tax_category( $product_id, $post_data ) {
        $tax_category = '';

        if ( isset( $post_data['tax_category'] ) ) {
            $tax_category = $post_data['tax_category'];
        } elseif ( isset( $_POST['tax_category'] ) ) {
            $tax_category = $_POST['tax_category'];
        }

        update_post_meta( $product_id, 'tax_category', $tax_category );
    }

}

new MT_Dokan_Dashboard();
