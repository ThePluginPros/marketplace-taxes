<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Tax Categories class.
 *
 * Manages the list of tax categories supported by TaxJar.
 */
class MT_Tax_Categories {

    /**
     * @const The options key for the tax categories list
     */
    const OPTION_NAME = 'mt_tax_categories';

    /**
     * Constructor.
     *
     * Registers action hooks and filters.
     */
    public function __construct() {
        add_filter( 'cron_schedules', array( $this, 'add_monthly_cron_schedule' ) );
        add_action( 'marketplace_taxes_activated', array( $this, 'schedule_update' ) );
        add_action( 'marketplace_taxes_deactivated', array( $this, 'unschedule_update' ) );
        add_action( 'mt_update_categories', array( $this, 'update' ) );
        add_action( 'marketplace_taxes_options_saved', array( $this, 'on_options_saved' ) );
    }

    /**
     * Adds a monthly cron schedule to WordPress.
     *
     * @param array $schedules Existing cron schedules.
     *
     * @return array
     */
    public function add_monthly_cron_schedule( $schedules ) {
        if ( ! isset( $schedules['monthly'] ) ) {
            $schedules['monthly'] = [
                'display'  => __( 'Once monthly', 'marketplace-taxes' ),
                'interval' => 30 * DAY_IN_SECONDS,
            ];
        }
        return $schedules;
    }

    /**
     * Schedules a monthly update of the tax categories list.
     */
    public function schedule_update() {
        $this->unschedule_update();

        wp_schedule_event( time(), 'monthly', 'mt_update_categories' );
    }

    /**
     * Unschedules the monthly tax categories list update.
     */
    public function unschedule_update() {
        if ( ( $time = wp_next_scheduled( 'mt_update_categories' ) ) !== false ) {
            wp_unschedule_event( $time, 'mt_update_categories' );
        }
    }

    /**
     * Attempts to download the list of supported product categories.
     *
     * Preserves the existing list of tax categories (if any) on failure.
     */
    public function update() {
        $client = MT()->client();

        try {
            $categories = $client->categories();

            if ( ! empty( $categories ) ) {
                update_option( self::OPTION_NAME, $categories );
            }
        } catch ( TaxJar\Exception $ex ) {
            wc_get_logger()->warning( 'Failed to update tax categories: ' . $ex->getMessage() );
        }
    }

    /**
     * Runs when the plugin options are saved.
     *
     * Attempts to update the tax categories if the tax categories list is
     * empty.
     *
     * @param MT_WC_Integration $integration
     */
    public function on_options_saved( $integration ) {
        $api_token = $integration->get_option( 'api_token' );

        if ( empty( $this->get_categories() ) && ! empty( $api_token ) ) {
            $this->update();
        }
    }

    /**
     * Gets a list of all supported tax categories.
     *
     * @return array array of objects with props 'product_tax_code', 'description' and 'name'
     */
    public function get_categories() {
        $general_category = [
            'product_tax_code' => '00000',
            'description'      => __( "Products that don't fall into other categories", 'marketplace-taxes' ),
            'name'             => __( 'General', 'marketplace-taxes' ),
        ];

        $categories = (array) get_option( self::OPTION_NAME, [] );

        return array_merge( [ $general_category ], $categories );
    }

}
