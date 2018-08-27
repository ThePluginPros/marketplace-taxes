<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class TFM_Install
 *
 * Handles plugin installation and updates.
 */
class TFM_Install {

    public function __construct() {
        add_action( 'taxjar_for_marketplaces_activated', array( $this, 'maybe_install' ) );
        add_action( 'admin_init', array( $this, 'check_tax_rates' ) );
    }

    /**
     * Triggers installation the first time the plugin is activated.
     */
    public function maybe_install() {
        if ( false === get_option( 'tfm_version' ) ) {
            $this->install();
        }
    }

    /**
     * Installs the plugin.
     */
    public function install() {
        $this->configure_woocommerce();

        update_option( 'tfm_version', TFM()->version );
    }

    /**
     * Configures WooCommerce to ensure maximum compatibility.
     */
    public function configure_woocommerce() {
        update_option( 'woocommerce_calc_taxes', 'yes' );
        update_option( 'woocommerce_prices_include_tax', 'no' );
        update_option( 'woocommerce_tax_based_on', 'shipping' );
        update_option( 'woocommerce_shipping_tax_class', '' );
        update_option( 'woocommerce_tax_round_at_subtotal', false );
        update_option( 'woocommerce_tax_display_shop', 'excl' );
        update_option( 'woocommerce_tax_display_cart', 'excl' );
        update_option( 'woocommerce_tax_total_display', 'itemized' );
    }

    /**
     * Displays a warning if there are rates in the WooCommerce tax tables.
     */
    public function check_tax_rates() {
        global $wpdb;

        if ( ! get_option( 'tfm_rate_warning_dismissed' ) ) {
            $dismissed = false;

            // Handle dismissal of the warning
            if ( isset( $_GET['keep_rates'] ) ) {
                $dismissed = true;

                if ( 'no' === $_GET['keep_rates'] ) {
                    $num_deleted = $wpdb->query(
                        "DELETE FROM {$wpdb->prefix}woocommerce_tax_rates WHERE tax_rate_id > 0;"
                    );
                    $message     = sprintf( __( '%1$s tax rate(s) deleted successfully.', 'taxjar-for-marketplaces' ), $num_deleted );
                } else {
                    $message = __( 'Your existing tax rates will be kept.', 'taxjar-for-marketplaces' );
                }

                TFM()->admin->add_notice( 'warning_dismissed', 'success', $message );

                update_option( 'tfm_rate_warning_dismissed', true );
            }

            // Display warning if necessary
            if ( ! $dismissed ) {
                $num_rates = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}woocommerce_tax_rates;" );

                if ( $num_rates > 0 ) {
                    add_action( 'admin_notices', array( $this, 'tax_rates_warning' ) );
                }
            }
        }
    }

    /**
     * Outputs the tax rates warning.
     */
    public function tax_rates_warning() {
        $settings_url = add_query_arg(
            [
                'page'    => 'wc-settings',
                'tab'     => 'integration',
                'section' => 'taxjar_for_marketplaces',
            ],
            admin_url( 'admin.php' )
        );

        $message = sprintf(
            __(
                '<strong>Warning!</strong> There are tax rates in your WooCommerce tax tables. This may cause your customers to be overtaxed. Please choose to <a href="%1$s">keep</a> or <a href="%2$s">delete</a> the existing tax rates.', 'taxjar-for-marketplaces' ),
            add_query_arg( 'keep_rates', 'yes', $settings_url ),
            add_query_arg( 'keep_rates', 'no', $settings_url )
        );

        printf( '<div class="notice notice-warning"><p>%s</p></div>', $message );
    }

}

new TFM_Install();
