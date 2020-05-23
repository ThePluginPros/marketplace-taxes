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
        add_action( 'admin_init', array( $this, 'maybe_show_discontinuation_notice' ) );
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

    /**
     * Adds the plugin discontinuation notice if it hasn't been displayed already.
     */
    public function maybe_show_discontinuation_notice() {
        $doing_ajax = defined( 'DOING_AJAX' ) && DOING_AJAX;
        if ( ! get_option( 'mt_discontinuation_notice_displayed' ) && ! $doing_ajax ) {
            $notice_html = __(
                "<strong>Marketplace Taxes is being discontinued.</strong> Recent changes in TaxJar's pricing have made TaxJar cost prohibitive for our target audience, so we are ending support for Marketplace Taxes on <strong>August 21, 2020</strong>. Please read <a href='https://thepluginpros.com/2020/05/23/were-ending-support-for-marketplace-taxes-heres-what-you-can-do/' target='_blank'>our blog article</a> for more information and for advice on what you can do. We apologize for any inconvenience this may cause.",
                'marketplace-taxes'
            );
            MT_Admin_Notices::add_custom_notice( 'discontinuation-notice', $notice_html );
            update_option( 'mt_discontinuation_notice_displayed', true );
        }
    }

}

new MT_Admin();
