<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class TFM_Download_Orders
 *
 * Handles TaxJar order downloads.
 *
 * Based on the WC_Taxjar_Order_Downloads class from TaxJar for WooCommerce.
 */
class TFM_Download_Orders {

    const API_URI = 'https://api.taxjar.com/v2/';

    /**
     * @var TFM_Settings_API WC integration or vendor settings form
     */
    protected $integration;

    /**
     * @var bool Is order downloading enabled?
     */
    protected $taxjar_download = false;

    public function __construct( $integration ) {
        $this->integration     = $integration;
        $this->taxjar_download = $this->integration->is_reporting_enabled();
    }

    /**
     * Validate the option to enable TaxJar order downloads and link or unlink shop
     *
     * @param string $value
     *
     * @return string
     *
     * @throws Exception If linking or unlinking the user's TaxJar account fails
     */
    public function validate_upload_transactions_field( $value ) {
        $previous_value = $this->integration->get_option( 'upload_transactions' );

        if ( 'yes' !== $value ) {
            $value = 'no';
        } else {
            $value = 'yes';
        }

        if ( ( $value != $previous_value ) ) {
            if ( 'yes' == $value ) {
                // Enable the WooCommerce API for downloads if it is not enabled
                update_option( 'woocommerce_api_enabled', 'yes' );

                // Get/generate the WooCommerce API information and link this store to TaxJar
                $keys    = $this->get_or_create_woocommerce_api_keys();
                $success = false;

                if ( $keys ) {
                    $consumer_key    = $keys['consumer_key'];
                    $consumer_secret = $keys['consumer_secret'];
                    $store_url       = $this->integration->get_store_url();
                    $success         = $this->link_provider( $consumer_key, $consumer_secret, $store_url );
                }

                if ( ! $success ) {
                    $this->taxjar_download = false;

                    throw new Exception(
                        __(
                            "There was an error linking this store to your TaxJar account. Please contact support@thepluginpros.com", 'taxjar-for-marketplaces' )
                    );
                }
            } else {
                $success = $this->unlink_provider( $this->integration->get_store_url() );

                if ( ! $success ) {
                    throw new Exception(
                        __(
                            "There was an error unlinking this store from your TaxJar account. Please contact support@thepluginpros.com", 'taxjar-for-marketplaces' )
                    );
                }
            }
        }

        return $value;
    }

    /**
     * Called by the integration to show on the TaxJar settings page
     *
     * @return array
     */
    public function get_form_settings_field() {
        $field = [
            'title'             => __( 'Sales tax reporting', 'taxjar-for-marketplaces' ),
            'label'             => __( 'Upload orders to TaxJar for reporting', 'taxjar-for-marketplaces' ),
            'type'              => 'checkbox',
            'default'           => 'no',
            'class'             => 'show-if-woocommerce_taxjar_for_marketplaces_merchant_of_record-marketplace',
            'sanitize_callback' => array( $this, 'validate_upload_transactions_field' ),
        ];

        if ( $this->taxjar_download && ! $this->existing_api_key() ) {
            $field['description'] = __(
                "<span style='color: #ff0000;'>There was an error retrieving your keys. Please disable and re-enable reporting.</span>", 'taxjar-for-marketplaces' );
        }

        return $field;
    }

    /**
     * Connect this store to the user's Taxjar account
     *
     * @param string $consumer_key
     * @param string $consumer_secret
     * @param string $store_url
     *
     * @return boolean
     */
    private function link_provider( $consumer_key, $consumer_secret, $store_url ) {
        $url         = self::API_URI . 'plugins/woo/register';
        $body_string = sprintf(
            'consumer_key=%s&consumer_secret=%s&store_url=%s',
            $consumer_key,
            $consumer_secret,
            $store_url
        );

        $response = wp_remote_post(
            $url,
            array(
                'timeout' => 60,
                'headers' => array(
                    'Authorization' => 'Token token="' . $this->integration->get_api_token() . '"',
                    'Content-Type'  => 'application/x-www-form-urlencoded',
                ),
                'body'    => $body_string,
            )
        );

        // Fail loudly if we get an error from wp_remote_post
        if ( is_wp_error( $response ) ) {
            return false;
        } elseif ( 201 != $response['response']['code'] ) {
            wc_get_logger()->error( "[TaxJar] Received (" . $response['response']['code'] . "): " . $response['body'] );
            return false;
        }

        return true;
    }

    /**
     * Disconnect this store from the user's Taxjar account
     *
     * @param string $store_url
     *
     * @return boolean
     */
    public function unlink_provider( $store_url ) {
        $url         = self::API_URI . 'plugins/woo/deregister';
        $body_string = sprintf( 'store_url=%s', $store_url );

        $response = wp_remote_request(
            $url,
            array(
                'timeout' => 60,
                'headers' => array(
                    'Authorization' => 'Token token="' . $this->integration->get_api_token() . '"',
                    'Content-Type'  => 'application/x-www-form-urlencoded',
                ),
                'body'    => $body_string,
                'method'  => 'DELETE',
            )
        );

        if ( is_wp_error( $response ) ) {
            return false;
        } elseif ( 200 !== $response['response']['code'] ) {
            wc_get_logger()->error( "[TaxJar] Received (" . $response['response']['code'] . "): " . $response['body'] );
            return false;
        }

        return true;
    }

    /**
     * Check if there is an existing WooCommerce 2.4 API Key
     *
     * @return boolean
     */
    private function existing_api_key() {
        global $wpdb;

        $user_id = get_current_user_id();
        $sql     = "SELECT count(key_id)
			FROM {$wpdb->prefix}woocommerce_api_keys
			WHERE description LIKE '%taxjar%'
			AND user_id = {$user_id};";

        return ( $wpdb->get_var( $sql ) > 0 );
    }

    /**
     * Direct copy of how API keys are generated via AJAX in WooCommerce
     *
     * @param int $user_id
     *
     * @return array
     */
    private function generate_v2_api_keys( $user_id ) {
        global $wpdb;

        $consumer_key    = 'ck_' . wc_rand_hash();
        $consumer_secret = 'cs_' . wc_rand_hash();

        $data = array(
            'user_id'         => $user_id,
            'description'     => 'TaxJar',
            'permissions'     => 'read',
            'consumer_key'    => wc_api_hash( $consumer_key ),
            'consumer_secret' => $consumer_secret,
            'truncated_key'   => substr( $consumer_key, -7 ),
        );

        $wpdb->insert(
            $wpdb->prefix . 'woocommerce_api_keys',
            $data,
            array(
                '%d',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
            )
        );

        return array(
            'consumer_key'    => $consumer_key,
            'consumer_secret' => $consumer_secret,
        );
    }

    /**
     * Compares WooCommerce version and returns the appropriate API key
     *
     * @return array
     */
    private function get_or_create_woocommerce_api_keys() {
        $this->delete_wc_taxjar_keys();

        return $this->generate_v2_api_keys( get_current_user_id() );
    }

    /**
     * Deletes any existing TaxJar WooCommerce API keys
     *
     * @return void
     */
    private function delete_wc_taxjar_keys() {
        global $wpdb;

        $user_id = get_current_user_id();

        $key_ids = $wpdb->get_results(
            "SELECT key_id
			FROM {$wpdb->prefix}woocommerce_api_keys
			WHERE description LIKE '%taxjar%'
			AND user_id = {$user_id};"
        );

        foreach ( $key_ids as $row ) {
            $wpdb->delete( $wpdb->prefix . 'woocommerce_api_keys', array( 'key_id' => $row->key_id ), array( '%d' ) );
        }
    }

}
