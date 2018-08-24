<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * The main plugin class.
 *
 * @package TaxJar_For_Marketplaces
 */
final class TaxJar_For_Marketplaces extends \WordFrame\v1_1_0\Plugin {

    /**
     * @var string Current plugin version.
     */
    public $version = '0.0.1';

    /**
     * Bootstraps the plugin when all requirements are met.
     */
    public function load() {
        require __DIR__ . '/class-tfm-util.php';
        require __DIR__ . '/class-tfm-form-helper.php';
        require __DIR__ . '/class-tfm-calculation-method.php';
        require __DIR__ . '/class-tfm-calculation.php';
        require __DIR__ . '/admin/class-tfm-admin.php';
        require __DIR__ . '/class-tfm-checkout.php';
        require __DIR__ . '/class-tfm-store-form.php';
        require __DIR__ . '/class-tfm-order.php';

        // TODO:
        // 1) Create activation hook
        // 2) On activation, backup and delete all existing tax ratees
        // 3) Also, delete all tax rate transients & configure woo tax settings
        // 4) If WCV Pro < 1.4.4 installed, need to manually create shipping packages for each vendor (see TRS)
    }

    /**
     * Loads the plugin text domain.
     */
    public function load_text_domain() {
        load_plugin_textdomain( 'taxjar-for-marketplaces', false, basename( dirname( $this->file ) ) . '/languages/' );
    }

    /**
     * Returns the text to display in the notice when a required plugin is missing.
     *
     * @param array $violation
     *
     * @return string
     */
    public function get_plugin_notice( array $violation ) {
        switch ( $violation['type'] ) {
            case 'wrong_version':
                return sprintf(
                /* translators: 1: required plugin name, 2: minimum version */
                    __(
                        '<strong>%1$s needs to be updated.</strong> TaxJar for Marketplaces requires %1$s %2$s+.',
                        'taxjar-for-marketplaces'
                    ),
                    $violation['data']['name'],
                    $violation['data']['version']
                );
            case 'inactive':
            case 'not_installed':
                return sprintf(
                /* translators: 1: required plugin name */
                    __(
                        '<strong>%1$s not detected.</strong> Please install or activate %1$s to use TaxJar for Marketplaces.',
                        'taxjar-for-marketplaces'
                    ),
                    $violation['data']['name']
                );
        }
        return '';
    }

    /**
     * Returns the text to display when a PHP requirement is not met.
     *
     * @param array $violation Information about the missing requirement.
     *
     * @return string
     */
    public function get_php_notice( $violation ) {
        if ( 'extensions' === $violation['type'] ) {
            $ext_list = implode( ', ', $violation['data']['required'] );
            /* translators: 1 - list of required PHP extensions */
            return sprintf(
                __(
                    '<strong>Required PHP extensions are missing.</strong> TaxJar for Marketplaces requires %1$s.',
                    'taxjar-for-marketplaces'
                ),
                $ext_list
            );
        } elseif ( 'version' === $violation['type'] ) {
            /* translators: 1 - required php version */
            return sprintf(
                __(
                    '<strong>PHP needs to be updated.</strong> TaxJar for Marketplaces requires PHP %1$s+.',
                    'taxjar-for-marketplaces'
                ),
                $violation['data']['required']
            );
        }
        return '';
    }

}
