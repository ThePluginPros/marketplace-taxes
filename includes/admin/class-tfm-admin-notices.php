<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * TFM_Admin_Notices Class.
 *
 * Provides functions for displaying notices in WP admin.
 */
class TFM_Admin_Notices {

    /**
     * @var array Notices.
     */
    protected static $notices = [];

    /**
     * @var array Array of notices - name => callback.
     */
    protected static $core_notices = [];

    /**
     * Constructor.
     */
    public static function init() {
        self::$notices = get_option( 'tfm_admin_notices', [] );

        add_action( 'wp_loaded', array( __CLASS__, 'hide_notices' ) );
        add_action( 'shutdown', array( __CLASS__, 'store_notices' ) );

        if ( current_user_can( 'manage_woocommerce' ) ) {
            add_action( 'admin_print_styles', array( __CLASS__, 'add_notices' ) );
        }
    }

    /**
     * Store notices to DB
     */
    public static function store_notices() {
        update_option( 'tfm_admin_notices', self::get_notices() );
    }

    /**
     * Get notices
     *
     * @return array
     */
    public static function get_notices() {
        return self::$notices;
    }

    /**
     * Remove all notices.
     */
    public static function remove_all_notices() {
        self::$notices = [];
    }

    /**
     * Show a notice.
     *
     * @param string $name
     */
    public static function add_notice( $name ) {
        self::$notices = array_unique( array_merge( self::get_notices(), [ $name ] ) );
        self::store_notices();
    }

    /**
     * Remove a notice from being displayed.
     *
     * @param  string $name
     */
    public static function remove_notice( $name ) {
        self::$notices = array_diff( self::get_notices(), [ $name ] );
        delete_option( 'tfm_admin_notice_' . $name );
    }

    /**
     * See if a notice is being shown.
     *
     * @param  string $name
     *
     * @return boolean
     */
    public static function has_notice( $name ) {
        return in_array( $name, self::get_notices() );
    }

    /**
     * Hide a notice if the GET variable is set.
     */
    public static function hide_notices() {
        if ( isset( $_GET['tfm-hide-notice'] ) && isset( $_GET['_tfm_notice_nonce'] ) ) {
            if ( ! wp_verify_nonce( $_GET['_tfm_notice_nonce'], 'tfm_hide_notices_nonce' ) ) {
                wp_die( __( 'Action failed. Please refresh the page and retry.', 'taxjar-for-marketplaces' ) );
            }

            if ( ! current_user_can( 'manage_woocommerce' ) ) {
                wp_die( __( 'Cheatin&#8217; huh?', 'taxjar-for-marketplaces' ) );
            }

            $hide_notice = sanitize_text_field( $_GET['tfm-hide-notice'] );
            self::remove_notice( $hide_notice );
            do_action( 'tfm_hide_' . $hide_notice . '_notice' );
        }
    }

    /**
     * Add notices + styles if needed.
     */
    public static function add_notices() {
        $notices = self::get_notices();

        if ( empty( $notices ) ) {
            return;
        }

        foreach ( $notices as $notice ) {
            if ( ! empty( self::$core_notices[ $notice ] ) && apply_filters(
                    'tfm_show_admin_notice',
                    true,
                    $notice
                ) ) {
                if ( ! is_callable( self::$core_notices[ $notice ] ) ) {
                    self::$core_notices[ $notice ] = array( __CLASS__, self::$core_notices[ $notice ] );
                }
                add_action( 'admin_notices', self::$core_notices[ $notice ] );
            } else {
                add_action( 'admin_notices', array( __CLASS__, 'output_custom_notices' ) );
            }
        }
    }

    /**
     * Add a custom notice.
     *
     * @param string $name
     * @param string $notice_html
     */
    public static function add_custom_notice( $name, $notice_html ) {
        self::add_notice( $name );
        update_option( 'tfm_admin_notice_' . $name, wp_kses_post( $notice_html ) );
    }

    /**
     * Output any stored custom notices.
     */
    public static function output_custom_notices() {
        $notices = self::get_notices();

        if ( empty( $notices ) ) {
            return;
        }

        foreach ( $notices as $notice ) {
            if ( empty( self::$core_notices[ $notice ] ) ) {
                $notice_html = get_option( 'tfm_admin_notice_' . $notice );

                if ( $notice_html ) {
                    include( 'views/notices/html-notice-custom.php' );
                }
            }
        }
    }

}
