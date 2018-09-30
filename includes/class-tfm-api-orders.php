<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once WC()->plugin_path() . '/includes/api/legacy/v2/class-wc-api-orders.php';

/**
 * WC REST API Orders Controller.
 *
 * Custom implementation of the WC_API_Orders class that returns shop orders
 * or vendor shop orders based on the authenticated user's role.
 */
class TFM_API_Orders extends WC_API_Orders {

    /**
     * @var bool Is the authenticated user a vendor?
     */
    protected $is_user_vendor = false;

    /**
     * Setup class
     *
     * @since 2.1
     *
     * @param WC_API_Server $server
     */
    public function __construct( $server ) {
        add_filter( 'woocommerce_api_check_authentication', array( $this, 'check_user_role' ) );

        parent::__construct( $server );
    }

    /**
     * Checks the user role of the authenticated user.
     *
     * @param WP_User $user
     *
     * @return WP_User
     */
    public function check_user_role( $user ) {
        $this->is_user_vendor = WCV_Vendors::is_vendor( $user->ID );

        // Operate on vendor sub orders if the current user is a vendor
        if ( $this->is_user_vendor ) {
            $this->post_type = 'shop_order_vendor';

            add_action( 'pre_get_posts', array( $this, 'filter_order_query' ) );
        }

        return $user;
    }

    /**
     * Gets the consumer key provided in the request.
     *
     * @return string
     */
    protected function get_consumer_key() {
        $params = WC()->api->server->params['GET'];

        if ( ! empty( $_SERVER['PHP_AUTH_USER'] ) ) {
            return $_SERVER['PHP_AUTH_USER'];
        } elseif ( ! empty( $params['consumer_key'] ) ) {
            return $params['consumer_key'];
        } elseif ( ! empty( $params['oauth_consumer_key'] ) ) {
            return $params['oauth_consumer_key'];
        }

        return '';
    }

    /**
     * Checks whether the current API request is coming from TaxJar.
     *
     * @return bool
     */
    protected function is_taxjar_request() {
        global $wpdb;

        $description = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT description FROM {$wpdb->prefix}woocommerce_api_keys WHERE consumer_key = %s",
                wc_api_hash( $this->get_consumer_key() )
            )
        );

        return 'TaxJar' === $description;
    }

    /**
     * Filters the order query to ensure that vendors only see their own orders.
     *
     * @param WP_Query $query
     */
    public function filter_order_query( &$query ) {
        $post_type = $query->get( 'post_type' );

        if ( ! in_array( $post_type, [ 'shop_order_vendor', 'shop_order_refund' ] ) ) {
            return;
        }

        $meta_query = $query->get( 'meta_query' );

        if ( ! is_array( $meta_query ) ) {
            $meta_query = [];
        }

        $meta_query[] = [
            'key'   => '_vendor_id',
            'value' => get_current_user_id(),
        ];

        $query->set( 'meta_query', $meta_query );
    }

    /**
     * Get all orders
     *
     * @since 2.1
     *
     * @param string $fields
     * @param array $filter
     * @param string $status
     * @param int $page
     *
     * @return array
     */
    public function get_orders( $fields = null, $filter = array(), $status = null, $page = 1 ) {
        if ( $this->is_taxjar_request() ) {
            $merchant_of_record = TFM()->settings->get( 'merchant_of_record', 'vendor' );

            // Force empty response if the current user is not the Merchant of Record
            if ( $this->is_user_vendor && 'vendor' !== $merchant_of_record ) {
                add_filter( 'woocommerce_api_query_args', array( $this, 'force_empty_response' ) );
            } elseif ( ! $this->is_user_vendor && 'vendor' === $merchant_of_record ) {
                add_filter( 'woocommerce_api_query_args', array( $this, 'force_empty_response' ) );
            }

            // Ensure that partially refunded orders are included in TaxJar reports
            add_filter( 'posts_clauses', array( $this, 'fix_refunds_query' ), 10, 2 );
        }

        return parent::get_orders( $fields, $filter, $status, $page );
    }

    /**
     * Get the order for the given ID
     *
     * Updates vendor sub orders on the fly if they are missing information
     * required for TaxJar Reporting to work.
     *
     * @since 2.1
     *
     * @param int $id the order ID
     * @param array $fields
     * @param array $filter
     *
     * @return array|WP_Error
     */
    public function get_order( $id, $fields = null, $filter = array() ) {
        $order_data = parent::get_order( $id, $fields, $filter );

        if ( 'shop_order_vendor' !== $this->post_type ) {
            return $order_data;
        }

        if ( ! empty( $order_data['customer_ip'] ) ) {
            return $order_data;
        }

        // The vendor order is missing one or more inherited properties. Save
        // the parent so the inherited properties are set.
        $parent = wc_get_order( get_post_field( 'post_parent', $id ) );
        $parent->save();

        return parent::get_order( $id, $fields, $filter );
    }

    /**
     * Forces an empty response by setting post__in.
     *
     * @param array $args
     *
     * @return array $args
     */
    public function force_empty_response( $args ) {
        $args['post__in'] = [ 0 ];

        return $args;
    }

    /**
     * Modifies the posts query used to find refunds so that partially refunded
     * orders are included in TaxJar reports.
     *
     * This is hooked only for TaxJar API requests.
     *
     * @param array $clauses
     * @param WP_Query $query
     *
     * @return array
     */
    public function fix_refunds_query( $clauses, $query ) {
        global $wpdb;

        $post_type = $query->get( 'post_type' );

        if ( ! in_array( $post_type, [ 'shop_order', 'shop_order_vendor' ] ) ) {
            return $clauses;
        }

        if ( ! in_array( 'wc-refunded', $query->get( 'post_status' ) ) ) {
            return $clauses;
        }

        // Return all completed OR refunded orders with associated refunds
        $clauses['where'] = str_replace(
            "post_status = 'wc-refunded'",
            "post_status IN ( 'wc-refunded', 'wc-completed' )",
            $clauses['where']
        );

        $clauses['join'] .= " JOIN {$wpdb->posts} `refund` ON `refund`.`post_parent` = {$wpdb->posts}.`ID` AND `refund`.`post_type` = 'shop_order_refund' AND `refund`.`post_status` != 'trash'";

        return $clauses;
    }

}
