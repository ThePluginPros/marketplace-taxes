<?php

use WCV_Settings\v1_0_5\Legacy_Settings_API;
use WCV_Settings\v1_0_5\Settings_API;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Settings manager.
 *
 * Manages tax related WC Vendors settings while the plugin is active.
 */
class MT_WC_Vendors_Settings_Manager {

    /**
     * Constructor.
     *
     * Registers action hooks and filters.
     */
    public function __construct() {
        add_action( 'admin_print_footer_scripts', array( $this, 'disable_managed_fields' ) );
        add_action( 'marketplace_taxes_options_saved', array( $this, 'set_give_taxes' ) );
        add_filter( 'get_user_metadata', array( $this, 'set_give_tax_override' ), 10, 4 );

        Settings_API::on_saved( array( $this, 'set_give_taxes' ) );

        $this->hide_tax_fields();

        if ( version_compare( WCV_VERSION, '2.0.0', '<' ) ) {
            add_filter( 'option_wc_prd_vendor_options', array( $this, 'hide_legacy_tax_fields' ) );

            // Must reload current options so filter is applied
            Legacy_Settings_API::reload_settings();
        }
    }

    /**
     * Hides the Tax Class and Tax Status fields in WC Vendors 2.0.0+.
     */
    public function hide_tax_fields() {
        $hide_options = [
            'wcvendors_hide_product_general_tax',
            'wcvendors_hide_product_variations_tax_class',
        ];

        foreach ( $hide_options as $option_name ) {
            add_filter( "pre_option_{$option_name}", array( $this, 'hide_form_field' ) );
        }
    }

    /**
     * Hides the Tax Class and Tax Status fields in WC Vendors < 2.0.0.
     *
     * @param array $options
     *
     * @return array
     */
    public function hide_legacy_tax_fields( $options ) {
        if ( ! is_array( $options ) ) {
            $options = [];
        }

        $options_to_hide = [
            'hide_product_misc'       => [ 'taxes' ],
            'hide_product_general'    => [ 'tax' ],
            'hide_product_variations' => [ 'tax_class' ],
        ];

        foreach ( $options_to_hide as $parent_option => $to_hide ) {
            if ( isset( $options[ $parent_option ] ) ) {
                $options[ $parent_option ] = maybe_unserialize( $options[ $parent_option ] );

                foreach ( $to_hide as $field ) {
                    $options[ $parent_option ][ $field ] = 1;
                }
            }
        }

        return $options;
    }

    /**
     * Disables managed settings fields with JavaScript.
     */
    public function disable_managed_fields() {
        $screen_id = get_current_screen()->id;

        if ( in_array( $screen_id, $this->get_settings_screen_ids() ) ) {
            $disabled_fields = [
                '#wcvendors_vendor_give_taxes',
                '#wcvendors_hide_product_general_tax',
                '#wcvendors_required_product_general_tax',
                '#wcvendors_hide_product_variations_tax_class',
                '#give_tax',
                '#hide_product_misc_taxes',
                '#hide_product_general_tax',
                '#hide_product_variations_tax_class',
                '#wcv_give_vendor_tax',
            ];
            $selector        = implode( ', ', $disabled_fields );
            ?>
            <script>
                jQuery(function ($) {
                    $('<?php echo $selector; ?>')
                        .attr('title', '<?php _e(
                            'This field is disabled while Marketplace Taxes is active.',
                            'marketplace-taxes'
                        ); ?>')
                        .attr('disabled', 'disabled');
                });
            </script>
            <?php
        }
    }

    /**
     * Gets the IDs of all WC Vendors settings screens.
     *
     * @return array
     */
    private function get_settings_screen_ids() {
        return [
            'wc-vendors_page_wcv-settings',
            'woocommerce_page_wc_prd_vendor',
            'user-edit',
        ];
    }

    /**
     * Sets the value of the 'Give Taxes' setting based on the selected M.O.R.
     */
    public function set_give_taxes() {
        $merchant_of_record = MT()->settings->get( 'merchant_of_record', 'vendor' );

        if ( version_compare( WCV_VERSION, '2.0.0', '<' ) ) {
            $cb_value = intval( 'vendor' === $merchant_of_record );
        } else {
            $cb_value = wc_bool_to_string( 'vendor' === $merchant_of_record );
        }

        Settings_API::set( 'wcvendors_vendor_give_taxes', $cb_value );
    }

    /**
     * Sets the vendor level 'Give Tax' override based on the selected M.O.R.
     *
     * @param mixed  $value
     * @param int    $user_id
     * @param string $meta_key
     *
     * @return mixed
     */
    public function set_give_tax_override( $value, $user_id, $meta_key ) {
        if ( 'wcv_give_vendor_tax' !== $meta_key ) {
            return $value;
        }

        $give_vendor_tax = 'vendor' === MT()->settings->get( 'merchant_of_record' );

        return apply_filters( 'mt_wcv_give_vendor_tax', $give_vendor_tax, $user_id );
    }

    /**
     * Hides a form field by forcing the value of the `wcvendors_hide_{FIELD}`
     * option to 'yes'.
     *
     * @return string
     */
    public function hide_form_field() {
        return 'yes';
    }

}

new MT_WC_Vendors_Settings_Manager();
