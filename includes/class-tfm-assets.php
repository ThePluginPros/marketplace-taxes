<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Assets class.
 *
 * Registers plugin stylesheets and scripts.
 */
class TFM_Assets {

    /**
     * @var array Plugin stylesheets and scripts
     */
    protected $assets = [];

    /**
     * Constructor.
     *
     * Initializes the assets array and registers our action hooks.
     */
    public function __construct() {
        add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'register_admin_assets' ) );
        add_action( 'init', array( $this, 'init_assets' ) );
    }

    /**
     * Initializes the assets array.
     */
    public function init_assets() {
        $this->assets = [
            [
                'type'    => 'script',
                'slug'    => 'taxjar-for-marketplaces.jquery.hideseek',
                'options' => [
                    'deps'    => [ 'jquery' ],
                    'version' => '0.7.1',
                ],
                'context' => 'both',
            ],
            [
                'type'    => 'script',
                'slug'    => 'taxjar-for-marketplaces.backbone-modal',
                'options' => [
                    'deps' => [ 'underscore', 'backbone', 'wp-util' ],
                ],
                'context' => 'both',
            ],
            [
                'type'    => 'script',
                'slug'    => 'taxjar-for-marketplaces.category-select',
                'options' => [
                    'deps'     => [
                        'jquery',
                        'taxjar-for-marketplaces.jquery.hideseek',
                        'taxjar-for-marketplaces.backbone-modal',
                    ],
                    'localize' => [
                        'tfm_category_select_data' => [
                            'category_list' => TFM()->categories->get_categories(),
                            'strings'       => [
                                'same_as_parent' => __( 'Same as parent', 'taxjar-for-marketplaces' ),
                                'general'        => __( 'General', 'taxjar-for-marketplaces' ),
                                'no_change'      => __( 'No change', 'taxjar-for-marketplaces' ),
                            ],
                        ],
                    ],
                ],
                'context' => 'both',
            ],
            [
                'type'    => 'script',
                'slug'    => 'taxjar-for-marketplaces.input-toggle',
                'options' => [
                    'deps' => [ 'jquery' ],
                ],
                'context' => 'both',
            ],
            [
                'type'    => 'script',
                'slug'    => 'taxjar-for-marketplaces.country-select',
                'options' => [
                    'deps'      => [ 'jquery' ],
                    'in_footer' => true,
                    'localize'  => [
                        'tfm_country_select_params' => [
                            'countries'                 => json_encode(
                                array_merge(
                                    WC()->countries->get_allowed_country_states(),
                                    WC()->countries->get_shipping_country_states()
                                )
                            ),
                            'i18n_select_state_text'    => esc_attr__(
                                'Select an option&hellip;',
                                'taxjar-for-marketplaces'
                            ),
                            'i18n_matches_1'            => _x(
                                'One result is available, press enter to select it.',
                                'enhanced select',
                                'taxjar-for-marketplaces'
                            ),
                            'i18n_matches_n'            => _x(
                                '%qty% results are available, use up and down arrow keys to navigate.',
                                'enhanced select',
                                'taxjar-for-marketplaces'
                            ),
                            'i18n_no_matches'           => _x(
                                'No matches found',
                                'enhanced select',
                                'taxjar-for-marketplaces'
                            ),
                            'i18n_ajax_error'           => _x(
                                'Loading failed',
                                'enhanced select',
                                'taxjar-for-marketplaces'
                            ),
                            'i18n_input_too_short_1'    => _x(
                                'Please enter 1 or more characters',
                                'enhanced select',
                                'taxjar-for-marketplaces'
                            ),
                            'i18n_input_too_short_n'    => _x(
                                'Please enter %qty% or more characters',
                                'enhanced select',
                                'taxjar-for-marketplaces'
                            ),
                            'i18n_input_too_long_1'     => _x(
                                'Please delete 1 character',
                                'enhanced select',
                                'taxjar-for-marketplaces'
                            ),
                            'i18n_input_too_long_n'     => _x(
                                'Please delete %qty% characters',
                                'enhanced select',
                                'taxjar-for-marketplaces'
                            ),
                            'i18n_selection_too_long_1' => _x(
                                'You can only select 1 item',
                                'enhanced select',
                                'taxjar-for-marketplaces'
                            ),
                            'i18n_selection_too_long_n' => _x(
                                'You can only select %qty% items',
                                'enhanced select',
                                'taxjar-for-marketplaces'
                            ),
                            'i18n_load_more'            => _x(
                                'Loading more results&hellip;',
                                'enhanced select',
                                'taxjar-for-marketplaces'
                            ),
                            'i18n_searching'            => _x(
                                'Searching&hellip;',
                                'enhanced select',
                                'taxjar-for-marketplaces'
                            ),
                        ],
                    ],
                ],
                'context' => 'both',
            ],
            [
                'type'    => 'style',
                'slug'    => 'taxjar-for-marketplaces.tax-setup',
                'context' => 'both',
            ],
            [
                'type'    => 'script',
                'slug'    => 'taxjar-for-marketplaces.admin-notices',
                'options' => [
                    'deps'     => [ 'jquery', 'jquery-ui-core' ],
                    'localize' => [
                        'tfm_admin_notices' => [
                            'dismiss_confirmation' => __(
                                "Are you sure you want to dismiss this notice? You won't be warned about these sellers again.",
                                'taxjar-for-marketplaces'
                            ),
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Registers assets for the frontend.
     */
    public function register_assets() {
        $this->_register_assets( 'frontend' );
    }

    /**
     * Registers assets for WP admin.
     */
    public function register_admin_assets() {
        $this->_register_assets( 'admin' );
    }

    /**
     * Helper for registering assets.
     *
     * @param string $context 'admin' or 'frontend'
     */
    private function _register_assets( $context ) {
        foreach ( $this->assets as $asset ) {
            $defaults = [
                'type'    => '',
                'slug'    => '',
                'context' => 'both',
                'options' => [],
            ];

            $asset = wp_parse_args( $asset, $defaults );

            if ( 'both' === $asset['context'] || $context === $asset['context'] ) {
                TFM()->assets->register( $asset['type'], $asset['slug'], $asset['options'] );
            }
        }
    }

}

new TFM_Assets();
