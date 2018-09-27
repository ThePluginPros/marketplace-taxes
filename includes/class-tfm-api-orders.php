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

            add_filter( 'pre_get_posts', array( $this, 'filter_order_query' ) );
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
    public function filter_order_query( $query ) {
        $post_type = $query->get( 'post_type' );

        if ( ! in_array( $post_type, [ 'shop_order', 'shop_order_refund' ] ) ) {
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
        }

        return parent::get_orders( $fields, $filter, $status, $page );
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

}