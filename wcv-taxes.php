<?php

/**
 * Plugin Name:         WC Vendors Taxes
 * Description:         The ultimate sales tax compliance solution for WC Vendors.
 * Author:              The Plugin Pros
 * Author URI:          thepluginpros.com
 *
 * Version:             0.0.1
 * Requires at least:   4.4.0
 * Tested up to:        4.8.0
 *
 * Text Domain:         wcv-taxes
 * Domain Path:         /languages/
 *
 * @category            Plugin
 * @copyright           Copyright &copy; 2017 The Plugin Pros
 * @author              The Plugin Pros, Brett Porcelli
 * @package             WCV_Taxes
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Define constants.
 */
define( 'WCV_TAX_VERSION', '0.0.1' );
define( 'WCV_TAX_MIN_WOOCOMMERCE', '2.6.0' );
define( 'WCV_TAX_MIN_WCVENDORS_PRO', '1.3.0' );
define( 'WCV_TAX_FILE', __FILE__ );
define( 'WCV_TAX_PATH', untrailingslashit( dirname( __FILE__ ) ) );
define( 'WCV_TAX_URL', untrailingslashit( plugin_dir_url( __FILE__ ) ) );

/**
 * Check for updates
 */
if ( ! class_exists( 'WC_Software_License_Client' ) ) {
    require 'includes/vendor/wc-vendors/class-wc-software-license-client.php';
}

WC_Software_License_Client::get_instance( 'https://www.wcvendors.com/', WCV_TAX_VERSION, 'wcv-taxes', __FILE__, 'WC Vendors Taxes' );

final class WCV_Taxes {

    /**
     * @var WCV_Taxes Singleton instance of this class
     */
    private static $_instance;

    /**
     * @var array Admin notices.
     */
    private $notices = array();

    /**
     * Return the singleton instance of this class.
     *
     * @since 0.0.1
     *
     * @return WCV_Taxes
     */
    public static function instance() {
        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    /**
     * Prevent cloning of the singleton instance.
     *
     * @since 0.0.1
     */
    private function __clone() {}

    /**
     * Prevent unserializing of the singleton instance.
     *
     * @since 0.0.1
     */
    private function __wakeup() {}

    /**
     * Constructor. Protected so new instances can't be created outside of
     * this class.
     *
     * @since 0.1.0
     */
    protected function __construct() {
        add_action( 'plugins_loaded', array( $this, 'init' ), 0 );
        add_action( 'admin_notices', array( $this, 'display_notices' ) );
    }

    /**
     * Initialize plugin on plugins_loaded if all requirements are met.
     *
     * @since 0.0.1
     */
    public function init() {
        load_plugin_textdomain( 'wcv-taxes', false, basename( dirname( __FILE__ ) ) . '/languages/' );

        $warning = $this->get_requirements_warning();

        if ( $warning ) { // requirements not met
            $this->add_admin_notice( 'requirements', 'error', $warning );
            return;
        }

        require 'includes/class-wcv-taxes-util.php';
        require 'includes/class-wcv-taxes-admin.php';
        require 'includes/class-wcv-taxes-checkout.php';
        require 'includes/class-wcv-taxes-dashboard.php';
        require 'includes/class-wcv-taxes-order.php';

        // TODO:
        // 1) Create activation hook
        // 2) On activation, backup and delete all existing tax ratees
        // 3) Also, delete all tax rate transients & configure woo tax settings
        // 4) If WCV Pro < 1.4.4 installed, need to manually create shipping packages for each vendor (see TRS)
    }

    /**
     * Checks the environment to determine whether the minimum requirements are
     * met. Returns a string describing the first issue found, or false if the
     * environment checks out.
     *
     * @since 0.0.1
     *
     * @return string|bool
     */
    private function get_requirements_warning() {
        if ( ! defined( 'WC_VERSION' ) ) {
            return __( 'WC Vendors Taxes requires WooCommerce to be activated.', 'wcv-taxes' );
        }

        if ( version_compare( WC_VERSION, WCV_TAX_MIN_WOOCOMMERCE, '<' ) ) {
            $message = __( 'WC Vendors Taxes - WooCommerce %1$s or later is required. You have version %2$s installed.', 'wcv-taxes' );
        
            return sprintf( $message, WCV_TAX_MIN_WOOCOMMERCE, WC_VERSION );
        }

        if ( ! defined( 'WCV_PRO_VERSION' ) ) {
            return __( 'WC Vendors Taxes requires WC Vendors Pro to be activated.', 'wcv-taxes' );
        }

        if ( version_compare( WCV_PRO_VERSION, WCV_TAX_MIN_WCVENDORS_PRO, '<' ) ) {
            $message = __( 'WC Vendors Taxes - WC Vendors Pro %1$s or later is required. You have version %2$s installed.', 'wcv-taxes' );
            
            return sprintf( $message, WCV_TAX_MIN_WCVENDORS_PRO, WCV_PRO_VERSION );
        }

        return false;
    }

    /**
     * Add an admin notice.
     *
     * @since 0.0.1
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
     *
     * @since 0.0.1
     */
    public function display_notices() {
        foreach ( $this->notices as $slug => $notice ) {
            printf( '<div class="notice notice-%1$s"><p>%2$s</p></div>', $notice['type'], $notice['content'] );
        }
    }

}

/**
 * Return an instance of WCV_Taxes.
 *
 * @return WCV_Taxes
 */
function WCV_Tax() {
    return WCV_Taxes::instance();
}

WCV_Tax();