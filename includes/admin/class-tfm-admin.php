<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Admin.
 *
 * Responsible for rendering and controlling the admin settings UI.
 *
 * @author  Brett Porcelli
 * @package TaxJar_For_Marketplaces
 */
class TFM_Admin {

    /**
     * @var array Admin notices.
     */
    private $notices = array();

    /**
     * Constructor. Registers action/filter hooks.
     */
    public function __construct() {
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_styles' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
        add_filter( 'plugin_action_links_' . plugin_basename( TFM()->file ), array( $this, 'add_settings_link' ) );
        add_filter( 'wc_prd_vendor_options', array( $this, 'add_tax_tab' ) );
        add_action( 'wp_ajax_wcv_taxes_save_calc_method', array( $this, 'save_calculation_method' ) );
        add_action( 'admin_notices', array( $this, 'display_notices' ) );
    }

    /**
     * Register & enqueue admin styles.
     */
    public function enqueue_styles() {
        TFM()->assets->register(
            'style',
            'taxjar-for-marketplaces.admin',
            [
                'deps' => [ 'woocommerce_admin_styles' ],
            ]
        );

        if ( $this->is_tax_tab() ) {
            TFM()->assets->enqueue( 'style', 'taxjar-for-marketplaces.admin' );
        }
    }

    /**
     * Register & enqueue admin scripts.
     */
    public function enqueue_scripts() {
        if ( $this->is_tax_tab() ) {
            TFM()->assets->enqueue(
                'script',
                'taxjar-for-marketplaces.calc-methods-table',
                [
                    'deps'     => [
                        'jquery',
                        'jquery-blockui',
                        'wp-util',
                        'underscore',
                        'backbone',
                        'wc-backbone-modal',
                    ],
                    'localize' => [
                        'wcv_calc_methods_localize_script' => [
                            'wcv_taxes_save_calc_method_nonce' => wp_create_nonce( 'wcv_taxes_save_calc_method' ),
                            'methods'                          => TFM_Calculation::get_methods_formatted(),
                            'strings'                          => array(
                                'yes'         => __( 'Yes', 'taxjar-for-marketplaces' ),
                                'no'          => __( 'No', 'taxjar-for-marketplaces' ),
                                'save_failed' => __(
                                    'Failed to save changes. Please try again.',
                                    'taxjar-for-marketplaces'
                                ),
                            ),
                        ],
                    ],
                ]
            );
        }
    }

    /**
     * Add plugin action for accessing settings page.
     *
     * @param  array $actions Existing actions.
     *
     * @return array
     */
    public function add_settings_link( $actions ) {
        $settings_uri = admin_url( 'admin.php?page=wc_prd_vendor&tab=tax' );

        return array_merge(
            array(
                'settings' => sprintf(
                    "<a href='%s'>%s</a>",
                    $settings_uri,
                    __( 'Settings', 'taxjar-for-marketplaces' )
                ),
            ),
            $actions
        );
    }

    /**
     * Hide/disable core tax options that we want to control.
     *
     * GENERAL TAB
     * - Taxes (will be controlled by Seller of Record)
     *
     * PRODUCTS TAB
     * - Miscellaneous > Taxes (no longer applicable)
     *
     * PRODUCT FORM TAB
     * - General > Tax (no longer applicable)
     * - Variations > Tax Class (no longer applicable)
     *
     * @param  array $options
     *
     * @return array
     */
    private function remove_core_options( $options ) {
        foreach ( $options as $key => $option ) {
            if ( ! isset( $option['id'] ) ) {
                continue;
            }

            if ( 'give_tax' === $option['id'] ) {
                unset( $options[ $key ] );
            } else {
                if ( 'hide_product_misc' === $option['id'] ) {
                    unset( $options[ $key ]['options']['taxes'] );
                } else {
                    if ( 'hide_product_general' === $option['id'] ) {
                        unset( $options[ $key ]['options']['tax'] );
                    } else {
                        if ( 'hide_product_variations' === $option['id'] ) {
                            unset( $options[ $key ]['options']['tax_class'] );
                        }
                    }
                }
            }
        }

        return $options;
    }

    /**
     * Get the HTML for the Calculation Methods table.
     *
     * @return string
     */
    public function get_methods_table_html() {
        ob_start();

        require 'views/html-methods-table.php';

        return ob_get_clean();
    }

    /**
     * Add 'Tax' tab under WooCommerce > WC Vendors.
     *
     * @param  array $options Existing options.
     *
     * @return array
     */
    public function add_tax_tab( $options ) {
        // Bail if we aren't on the WC Vendors settings page
        if ( isset( $_REQUEST['page'] ) && 'wc_prd_vendor' !== $_REQUEST['page'] ) {
            return $options;
        }

        // Add 'Tax' tab and options
        $tax_options = array(
            array(
                'name' => __( 'Tax', 'taxjar-for-marketplaces' ),
                'type' => 'heading',
            ),
            array(
                'name' => __( 'General settings', 'taxjar-for-marketplaces' ),
                'type' => 'title',
                'desc' => __( 'Configure your WC Vendors Taxes installation.', 'taxjar-for-marketplaces' ),
            ),
            array(
                'name' => __( 'Force Tax Collection', 'taxjar-for-marketplaces' ),
                'id'   => 'force_tax_collection',
                'type' => 'checkbox',
                'desc' => __( 'Force vendors to collect tax', 'taxjar-for-marketplaces' ),
                'std'  => true,
            ),
            array(
                'name'    => __( 'Merchant of Record', 'taxjar-for-marketplaces' ),
                'id'      => 'merchant_of_record',
                'type'    => 'select',
                'options' => array(
                    'vendor'      => __( 'Vendor', 'taxjar-for-marketplaces' ),
                    'marketplace' => __( 'Marketplace', 'taxjar-for-marketplaces' ),
                ),
                'std'     => 'vendor',
                'tip'     => __(
                    'The merchant of record is responsible for collecting and remitting sales tax for each sale. The sales tax collected will be given to the merchant of record.',
                    'taxjar-for-marketplaces'
                ),
            ),
            array(
                'name' => __( 'Calculation methods', 'taxjar-for-marketplaces' ),
                'type' => 'title',
                'desc' => __(
                        'Configure available tax calculation methods.',
                        'taxjar-for-marketplaces'
                    ) . $this->get_methods_table_html(),
            ),
        );

        return array_merge( $this->remove_core_options( $options ), $tax_options );
    }

    /**
     * Is the tax tab being displayed?
     *
     * @return bool
     */
    protected function is_tax_tab() {
        if ( ! isset( $_GET['page'], $_GET['tab'] ) ) {
            return false;
        }
        return 'wc_prd_vendor' == $_GET['page'] && 'tax' == $_GET['tab'];
    }

    /**
     * Save WC Vendors options.
     *
     * @param array $options
     */
    private function save_vendor_options( $options ) {
        global $wc_vendors;

        $settings_api = WC_Vendors::$pv_options;

        // Remove validation callback so we can save unregistered fields
        remove_filter( 'sanitize_option_wc_prd_vendor_options', array( $settings_api, 'validate_options' ) );

        update_option( 'wc_prd_vendor_options', $options );

        add_filter( 'sanitize_option_wc_prd_vendor_options', array( $settings_api, 'validate_options' ) );

        // Reload settings so correct values are used in generated HTML
        WC_Vendors::$pv_options->current_options = get_option( 'wc_prd_vendor_options' );
    }

    /**
     * Save a calculation method via AJAX.
     */
    public function save_calculation_method() {
        // Perform security checks
        check_ajax_referer( 'wcv_taxes_save_calc_method', 'wcv_taxes_save_calc_method_nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( -1 );
        }

        $posted    = $_POST['data'];
        $method_id = $posted['method_id'];
        $method    = TFM_Calculation::get_method( $method_id );

        if ( is_null( $method ) ) {
            wp_die( -1 );
        }

        // Update settings with POSTed values
        $settings = WC_Vendors::$pv_options->get_current_options();

        foreach ( $method->get_form_fields() as $id => $field ) {
            $id   = $method_id . '_' . $id;
            $name = sprintf( 'wc_prd_vendor_options[%s', $id );

            // Get field value
            if ( 'checkbox' == $field['type'] && ! isset( $posted[ $name ] ) ) {
                $settings[ $id ] = 0;
            } else {
                $settings[ $id ] = $posted[ $name ];
            }

            // Sanitize
            if ( has_filter( 'geczy_sanitize_' . $field['type'] ) ) {
                $settings[ $id ] = apply_filters( 'geczy_sanitize_' . $field['type'], $settings[ $id ], $field );
            }
        }

        $this->save_vendor_options( $settings );

        // Send back updated methods
        TFM_Calculation::load_methods();

        wp_send_json_success(
            array(
                'errors'  => array(),
                'methods' => TFM_Calculation::get_methods_formatted(),
            )
        );
    }

    /**
     * Add an admin notice.
     *
     * @param string $slug
     * @param string $type 'error' or 'success'
     * @param string $content
     */
    public function add_admin_notice( $slug, $type, $content ) {
        $this->notices[ $slug ] = array(
            'type'    => $type,
            'content' => $content,
        );
    }

    /**
     * Display admin notices.
     */
    public function display_notices() {
        foreach ( $this->notices as $slug => $notice ) {
            printf( '<div class="notice notice-%1$s"><p>%2$s</p></div>', $notice['type'], $notice['content'] );
        }
    }

}

new TFM_Admin();