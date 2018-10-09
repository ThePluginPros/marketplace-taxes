<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Admin.
 *
 * Responsible for rendering and controlling the admin settings UI.
 *
 * @author  Brett Porcelli
 * @package Marketplace_Taxes
 */
class MT_Admin {

    /**
     * @var array Admin notices.
     */
    private $notices = array();

    /**
     * Constructor. Registers action/filter hooks.
     */
    public function __construct() {
        add_filter( 'plugin_action_links_' . plugin_basename( MT()->file ), array( $this, 'add_settings_link' ) );
        add_action( 'all_admin_notices', array( $this, 'display_notices' ) );
        add_action( 'admin_init', array( $this, 'init' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_stylesheet' ) );
    }

    /**
     * Add plugin action for accessing settings page.
     *
     * @param array $actions Existing actions.
     *
     * @return array
     */
    public function add_settings_link( $actions ) {
        $settings_url = add_query_arg(
            [
                'page'    => 'wc-settings',
                'tab'     => 'integration',
                'section' => 'marketplace_taxes',
            ],
            admin_url( 'admin.php' )
        );

        return array_merge(
            [
                'settings' => sprintf(
                    "<a href='%s'>%s</a>",
                    $settings_url,
                    __( 'Settings', 'marketplace-taxes' )
                ),
            ],
            $actions
        );
    }

    /**
     * Add an admin notice.
     *
     * @param string $slug
     * @param string $type 'error' or 'success'
     * @param string $content
     */
    public function add_notice( $slug, $type, $content ) {
        $this->notices[ $slug ] = array(
            'type'    => $type,
            'content' => $content,
        );
    }

    /**
     * Display admin notices.
     */
    public function display_notices() {
        foreach ( $this->notices as $slug => $notice ) {
            printf( '<div class="notice notice-%1$s"><p>%2$s</p></div>', $notice['type'], $notice['content'] );
        }
    }

    /**
     * Initializes admin class instances.
     */
    public function init() {
        require_once __DIR__ . '/class-mt-product-data-meta-box.php';
        require_once __DIR__ . '/class-mt-admin-bulk-edit.php';
    }

    /**
     * Enqueues the admin stylesheet.
     */
    public function enqueue_stylesheet() {
        MT()->assets->enqueue( 'style', 'marketplace-taxes.admin' );
        MT()->assets->enqueue( 'style', 'marketplace-taxes.tax-setup' );
    }

}

new MT_Admin();
