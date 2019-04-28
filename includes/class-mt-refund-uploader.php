<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Refund uploader.
 *
 * Uploads full and partial refunds to TaxJar in the background.
 *
 * Required because TaxJar doesn't support importing partial refunds from
 * WooCommerce :(
 */
class MT_Refund_Uploader extends WP_Background_Process {

    /**
     * @const int The number of refunds per upload batch.
     */
    const BATCH_SIZE = 50;

    /**
     * @var string The current merchant of record.
     */
    protected $merchant_of_record;

    /**
     * Constructor.
     *
     * Initializes the background process.
     */
    public function __construct() {
        // Use unique prefix so each blog gets its own queue
        $this->prefix = 'wp_' . get_current_blog_id();
        $this->action = 'mt_refund_uploader';

        add_action( 'marketplace_taxes_activated', array( $this, 'enable_or_disable' ) );
        add_action( 'marketplace_taxes_deactivated', array( $this, 'disable' ) );
        add_action( 'marketplace_taxes_options_saved', array( $this, 'handle_settings_changes' ) );
        add_action( 'mt_update_refund_queue', array( $this, 'update_queue' ) );
        add_filter( 'woocommerce_order_data_store_cpt_get_orders_query', array( $this, 'filter_orders_query' ), 10, 2 );
        add_action( 'init', array( $this, 'set_merchant_of_record' ) );

        parent::__construct();
    }

    /**
     * Sets the current merchant of record.
     *
     * This MUST run on `init` or later else the value will be incorrect.
     */
    public function set_merchant_of_record() {
        $this->merchant_of_record = MT()->settings->get( 'merchant_of_record' );
    }

    /**
     * Cancels the running process (if any) and repopulates the upload queue
     * when the merchant of record is changed.
     *
     * @param MT_WC_Integration $integration
     */
    public function handle_settings_changes( $integration ) {
        $old_merchant_of_record = $this->merchant_of_record;
        $new_merchant_of_record = $integration->get_option( 'merchant_of_record' );

        if ( $old_merchant_of_record !== $new_merchant_of_record ) {
            $this->merchant_of_record = $new_merchant_of_record;

            if ( $this->is_running() ) {
                wc_get_logger()->info(
                    sprintf(
                        'Canceling running refund upload: %s - %s',
                        $old_merchant_of_record,
                        $new_merchant_of_record
                    ),
                    [ 'source' => 'mt_refund_uploader' ]
                );
                $this->cancel_process();
            }
        }

        $this->enable_or_disable();
    }

    /**
     * Enables or disables the uploader based on the current plugin settings.
     *
     * This runs on activation and when settings are changed.
     */
    public function enable_or_disable() {
        if ( $this->should_enable() ) {
            $this->enable();
        } else {
            $this->disable();
        }
    }

    /**
     * Checks whether the uploader should be enabled.
     *
     * @return bool
     */
    protected function should_enable() {
        if ( 'marketplace' === $this->merchant_of_record ) {
            $settings = MT()->settings;

            return 'yes' === $settings->get( 'upload_transactions' ) && ! empty( $settings->get( 'api_token' ) );
        }

        return true;
    }

    /**
     * Enables the uploader on plugin activation.
     */
    public function enable() {
        if ( ! $this->should_enable() ) {
            return;
        }

        if ( ! $this->is_enabled() ) {
            wp_schedule_event( time(), 'daily', 'mt_update_refund_queue' );
        }
    }

    /**
     * Disables the uploader on plugin deactivation.
     */
    public function disable() {
        $timestamp = wp_next_scheduled( 'mt_update_refund_queue' );

        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, 'mt_update_refund_queue' );
        }

        if ( $this->is_running() ) {
            $this->cancel_process();
        }
    }

    /**
     * Updates the refund queue.
     */
    public function update_queue() {
        $logger = wc_get_logger();

        if ( ! $this->needs_update() ) {
            $logger->info( 'Skipping update.', [ 'source' => 'mt_refund_uploader' ] );

            return;
        }

        // Get the refunds to upload (at most self::BATCH_SIZE at a time)
        $refunds = wc_get_orders(
            [
                'type'       => 'shop_order_refund',
                'limit'      => self::BATCH_SIZE,
                'for_taxjar' => true,
            ]
        );

        // Use the marketplace API token by default - this will be changed in
        // the loop as needed
        $api_token = MT()->settings->get( 'api_token' );

        foreach ( $refunds as $refund ) {
            if ( $this->should_upload( $refund ) ) {
                $logger->info(
                    sprintf( 'Queueing #%d', $refund->get_id() ),
                    [ 'source' => 'mt_refund_uploader' ]
                );
                $refund->update_meta_data( '_mt_upload_status', 'queued' );

                if ( 'vendor' === $this->merchant_of_record ) {
                    $api_token = get_user_meta(
                        $refund->get_meta( '_vendor_id', true ),
                        'mt_taxjar_api_token',
                        true
                    );
                }

                $this->push_to_queue( [ 'api_token' => $api_token, 'refund_id' => $refund->get_id() ] );
            } else {
                $logger->info(
                    sprintf( 'Skipping refund #%d.', $refund->get_id() ),
                    [ 'source' => 'mt_refund_uploader' ]
                );
                $refund->update_meta_data( '_mt_upload_status', 'skipped' );
            }

            $refund->save();
        }

        if ( ! empty( $this->data ) ) {
            $this->save()->dispatch();
        }
    }

    /**
     * Checks whether the queue needs to be updated.
     *
     * @return bool
     */
    protected function needs_update() {
        if ( ! $this->is_enabled() ) {
            return false;
        }

        if ( 'marketplace' === $this->merchant_of_record ) {
            return ! empty( MT()->settings->get( 'api_token' ) );
        }

        return 0 < sizeof( $this->get_enabled_vendors() );
    }

    /**
     * Builds a TaxJar refund transaction given a WC refund object.
     *
     * @param WC_Order_Refund $refund
     *
     * @return array|WP_Error Refund transaction or WP_Error instance on error.
     */
    protected function build_refund_transaction( $refund ) {
        $date_created = $refund->get_date_created();

        if ( is_null( $date_created ) ) {
            return new WP_Error( 'no_date', 'Refund date is not set.' );
        }

        $from_address = $this->get_from_address( $refund );
        $to_address   = $this->get_to_address( $refund );

        if ( ! $this->is_valid_address( $from_address, 'from' ) ) {
            return new WP_Error( 'invalid_from_address', 'From address is invalid.' );
        }

        if ( ! $this->is_valid_address( $to_address, 'to' ) ) {
            return new WP_Error( 'invalid_to_address', 'To address is invalid.' );
        }

        $line_items = [];

        $total_ex_tax   = 0;
        $total_tax      = 0;
        $total_shipping = 0;

        foreach ( $refund->get_items( [ 'line_item', 'fee' ] ) as $item ) {
            $total_ex_tax += (float) $item->get_total();
            $total_tax    += (float) $item->get_total_tax();

            $line_item = [
                'id'          => $item->get_id(),
                'quantity'    => abs( $item->get_quantity() ),
                'description' => trim( $item->get_name() ),
                'unit_price'  => $refund->get_item_total( $item, false, true ),
                'sales_tax'   => abs( $item->get_total_tax() ),
            ];

            if ( 'line_item' === $item->get_type() && ( $product = $item->get_product() ) ) {
                $line_item['product_identifier'] = $product->get_id();

                if ( ( $tax_code = MT_Util::get_product_tax_code( $product->get_id() ) ) ) {
                    $line_item['product_tax_code'] = $tax_code;
                }
            }

            $line_items[] = $line_item;
        }

        foreach ( $refund->get_shipping_methods() as $shipping_method ) {
            $total_shipping += (float) $shipping_method->get_total();
            $total_ex_tax   += (float) $shipping_method->get_total();
            $total_tax      += (float) $shipping_method->get_total_tax();
        }

        return [
            'transaction_id'           => $refund->get_id(),
            'transaction_reference_id' => $refund->get_parent_id(),
            'transaction_date'         => $date_created->format( 'c' ),
            'from_country'             => $from_address['country'],
            'from_zip'                 => $from_address['postcode'],
            'from_state'               => $from_address['state'],
            'from_city'                => $from_address['city'],
            'from_street'              => $from_address['address'],
            'to_country'               => $to_address['country'],
            'to_zip'                   => $to_address['postcode'],
            'to_state'                 => $to_address['state'],
            'to_city'                  => $to_address['city'],
            'to_street'                => $to_address['address'],
            'amount'                   => abs( $total_ex_tax ),
            'shipping'                 => abs( $total_shipping ),
            'sales_tax'                => abs( $total_tax ),
            'line_items'               => $line_items,
        ];
    }

    /**
     * Gets the 'Shipped From' address for a refund.
     *
     * @param WC_Order_Refund $refund
     *
     * @return array
     */
    protected function get_from_address( $refund ) {
        $vendor_id = $refund->get_meta( '_vendor_id', true );

        if ( $vendor_id ) {
            $address = MT()->integration->get_vendor_from_address( $vendor_id );
        } else {
            $address = [
                'country'  => WC()->countries->get_base_country(),
                'postcode' => WC()->countries->get_base_postcode(),
                'city'     => WC()->countries->get_base_state(),
                'state'    => WC()->countries->get_base_state(),
                'address'  => WC()->countries->get_base_address(),
            ];
        }

        $address = array_map( 'trim', $address );

        return apply_filters( 'mt_refund_from_address', $address, $refund );
    }

    /**
     * Gets the 'Shipped To' address for a refund.
     *
     * @param WC_Order_Refund $refund
     *
     * @return array
     */
    protected function get_to_address( $refund ) {
        $order = wc_get_order( $refund->get_parent_id() );

        if ( $order ) {
            $address = [
                'country'  => trim( $order->get_shipping_country() ),
                'postcode' => trim( $order->get_shipping_postcode() ),
                'city'     => trim( $order->get_shipping_city() ),
                'state'    => trim( $order->get_shipping_state() ),
                'address'  => trim( $order->get_shipping_address_1() ),
            ];
        } else {
            $address = [
                'country'  => '',
                'postcode' => '',
                'city'     => '',
                'state'    => '',
                'address'  => '',
            ];
        }

        return apply_filters( 'mt_refund_to_address', $address, $refund );
    }

    /**
     * Checks whether a 'Shipped From' or 'Shipped To' address is valid.
     *
     * @param array  $address
     * @param string $type 'from' or 'to'
     *
     * @return bool
     */
    protected function is_valid_address( $address, $type ) {
        $required = [ 'country', 'postcode', 'state' ];

        if ( 'from' === $type ) {
            $required = array_merge( $required, [ 'city', 'address' ] );
        }

        return 0 === sizeof( array_diff( $required, array_keys( array_filter( array_map( 'trim', $address ) ) ) ) );
    }

    /**
     * Increments the upload attempts for the passed refund.
     *
     * The caller must save() the refund for the new value to be persisted.
     *
     * @param WC_Order_Refund $refund
     */
    protected function increment_upload_attempts( $refund ) {
        $current_attempts = $refund->get_meta( '_mt_upload_attempts', true );

        if ( ! is_numeric( $current_attempts ) ) {
            $current_attempts = 0;
        }

        $refund->update_meta_data( '_mt_upload_attempts', $current_attempts + 1 );
        $refund->update_meta_data( '_mt_last_attempt', time() );
    }

    /**
     * Checks whether a refund should be uploaded.
     *
     * @param WC_Order_Refund $refund
     *
     * @return bool
     */
    protected function should_upload( $refund ) {
        if ( 'vendor' === $this->merchant_of_record ) {
            $min_date = get_user_meta( $refund->get_meta( '_vendor_id', true ), 'mt_reports_start_date', true );
        } else {
            $min_date = get_option( 'mt_reports_start_date' );
        }

        if ( ! $min_date ) {
            $min_date = date( 'Y-m-d H:i:s', strtotime( 'first day of 2 months ago 00:00' ) );
        }

        return apply_filters( 'mt_upload_refund', $refund->get_date_created() >= $min_date, $refund );
    }

    /**
     * Returns a list of vendors who have enabled transaction uploading.
     *
     * @return int[] Array of vendor IDs.
     */
    protected function get_enabled_vendors() {
        global $wpdb;

        $query = <<<SQL
SELECT 
  m1.user_id 
FROM 
  {$wpdb->usermeta} m1, 
  {$wpdb->usermeta} m2 
WHERE 
  m1.user_id = m2.user_id 
  AND m1.meta_key = 'mt_upload_transactions' 
  AND m1.meta_value = 'yes' 
  AND m2.meta_key = 'mt_taxjar_api_token' 
  AND m2.meta_value IS NOT NULL 
  AND m2.meta_value <> '';
SQL;

        return $wpdb->get_col( $query );
    }

    /**
     * Dispatch
     *
     * @access public
     * @return void
     */
    public function dispatch() {
        $result = parent::dispatch();

        if ( is_wp_error( $result ) ) {
            wc_get_logger()->error(
                sprintf( 'Failed to dispatch refund uploader: %s', $result->get_error_message() ),
                [ 'source' => 'mt_refund_uploader' ]
            );
        }
    }

    /**
     * Task
     *
     * Override this method to perform any actions required on each
     * queue item. Return the modified item for further processing
     * in the next pass through. Or, return false to remove the
     * item from the queue.
     *
     * @param array $item Array with keys 'api_token' and 'refund_id'
     *
     * @return mixed
     */
    protected function task( $item ) {
        $logger = wc_get_logger();

        if ( ! isset( $item['api_token'], $item['refund_id'] ) ) {
            $logger->error(
                'Skipping invalid refund: ' . print_r( $item, true ),
                [ 'source' => 'mt_refund_uploader' ]
            );

            return false;
        }

        $refund = wc_get_order( $item['refund_id'] );

        if ( ! $refund ) {
            $logger->error(
                sprintf( 'Skipping refund #%s: refund no longer exists.', $item['refund_id'] ),
                [ 'source' => 'mt_refund_uploader' ]
            );

            return false;
        }

        $this->increment_upload_attempts( $refund );

        $success = false;

        try {
            $transaction = $this->build_refund_transaction( $refund );

            if ( is_wp_error( $transaction ) ) {
                throw new Exception( $transaction->get_error_message() );
            }

            TaxJar\Client::withApiKey( $item['api_token'] )->createRefund( $transaction );

            $logger->info(
                sprintf( 'Successfully uploaded #%d.', $refund->get_id() ),
                [ 'source' => 'mt_refund_uploader' ]
            );

            $success = true;
        } catch ( Exception $ex ) {
            $attempts = $refund->get_meta( '_mt_upload_attempts' );

            $logger->info(
                sprintf( 'Skipping #%d - %s. Attempts: %d.', $refund->get_id(), $ex->getMessage(), $attempts ),
                [ 'source' => 'mt_refund_uploader' ]
            );
        }

        $refund->update_meta_data( '_mt_upload_status', $success ? 'succeeded' : 'failed' );
        $refund->save();

        return false;
    }

    /**
     * Complete.
     *
     * Override if applicable, but ensure that the below actions are
     * performed, or, call parent::complete().
     */
    protected function complete() {
        parent::complete();

        // Call update_queue again just in case another page of refunds needs to be imported
        if ( $this->is_enabled() ) {
            $this->update_queue();
        }
    }

    /**
     * Handles the `for_taxjar` order query variable.
     *
     * @param array $query      Args for WP_Query.
     * @param array $query_vars Query vars from WC_Order_Query.
     *
     * @return array Modified $query
     */
    public function filter_orders_query( $query, $query_vars ) {
        if ( 'shop_order_refund' !== $query_vars['type'] ) {
            return $query;
        }

        if ( empty( $query_vars['for_taxjar'] ) ) {
            return $query;
        }

        $meta_query = [
            'relation' => 'OR',
            [
                'key'     => '_mt_upload_status',
                'compare' => 'NOT EXISTS',
            ],
            [
                [
                    'key'   => '_mt_upload_status',
                    'value' => 'failed',
                ],
                [
                    'key'     => '_mt_upload_attempts',
                    'value'   => $this->get_max_attempts(),
                    'compare' => '<',
                    'type'    => 'NUMERIC',
                ],
                [
                    'key'     => '_mt_last_attempt',
                    'value'   => time() - DAY_IN_SECONDS,
                    'compare' => '<=',
                    'type'    => 'NUMERIC',
                ],
            ],
        ];

        if ( 'vendor' === $this->merchant_of_record ) {
            // Only grab the refunds for enabled vendors
            $meta_query = [
                [ $meta_query ],
                [
                    'key'     => '_vendor_id',
                    'value'   => $this->get_enabled_vendors(),
                    'compare' => 'IN',
                ],
            ];
        }

        $query['meta_query'] = array_merge( $query['meta_query'], $meta_query );

        return apply_filters( 'mt_refund_uploader_orders_query', $query, $query_vars );
    }

    /**
     * Checks whether the uploader is running.
     *
     * @return bool
     */
    public function is_running() {
        return false === $this->is_queue_empty();
    }

    /**
     * Checks whether the refund uploader is enabled.
     *
     * @return bool
     */
    public function is_enabled() {
        return false !== wp_next_scheduled( 'mt_update_refund_queue' );
    }

    /**
     * Returns the maximum number of upload attempts.
     *
     * @return int
     */
    protected function get_max_attempts() {
        return apply_filters( 'mt_max_upload_attempts', 3 );
    }

}
