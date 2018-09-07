<?php

use WCV_Settings\v1_0_2\Settings_API;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Settings manager.
 *
 * Manages tax related WC Vendors settings while the plugin is active.
 */
class TFM_WC_Vendors_Settings_Manager {

    /**
     * Constructor.
     *
     * Registers action hooks and filters.
     */
    public function __construct() {
        add_action( 'admin_print_footer_scripts', array( $this, 'disable_managed_fields' ) );
        add_action( 'taxjar_for_marketplaces_options_saved', array( $this, 'set_give_taxes' ) );
        add_filter( 'get_user_metadata', array( $this, 'set_give_tax_override' ), 10, 4 );

        Settings_API::on_saved( array( $this, 'set_give_taxes' ) );
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
                            'This field is disabled while TaxJar for Marketplaces is active.',
                            'taxjar-for-marketplaces'
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
        $merchant_of_record = TFM()->settings->get( 'merchant_of_record', 'vendor' );

        Settings_API::set( 'wcvendors_vendor_give_taxes', wc_bool_to_string( 'vendor' === $merchant_of_record ) );
    }

    /**
     * Sets the vendor level 'Give Tax' override based on the selected M.O.R.
     *
     * @param mixed $value
     * @param int $user_id
     * @param string $meta_key
     *
     * @return mixed
     */
    public function set_give_tax_override( $value, $user_id, $meta_key ) {
        if ( 'wcv_give_vendor_tax' !== $meta_key ) {
            return $value;
        }

        $give_vendor_tax = 'vendor' === TFM()->settings->get( 'merchant_of_record' );

        return apply_filters( 'tfm_wcv_give_vendor_tax', $give_vendor_tax, $user_id );
    }

}

new TFM_WC_Vendors_Settings_Manager();
