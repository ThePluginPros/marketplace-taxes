<?php

/**
 * Tax settings form template.
 *
 * You can override this template by copying this file to THEME_DIR/marketplace-taxes/dashboard/.
 *
 * @global MT_Vendor_Settings_Form $form
 *
 * @version 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

?>

<?php do_action( 'mt_dokan_before_tax_settings_form' ); ?>

    <form method="post" id="tax-form" class="dokan-form-horizontal" novalidate="novalidate">

        <?php wp_nonce_field( 'dokan_tax_settings_nonce' ); ?>

        <?php $form->fields(); ?>

        <div class="dokan-form-group">
            <div class="dokan-w12 ajax_prev dokan-text-left">
                <input type="submit" name="dokan_update_tax_settings" class="dokan-btn dokan-btn-danger dokan-btn-theme"
                       value="<?php esc_attr_e( 'Update Settings', 'marketplace-taxes' ); ?>">
            </div>
        </div>

        <?php do_action( 'mt_dokan_after_tax_settings' ); ?>

    </form>

<?php do_action( 'mt_dokan_after_tax_settings_form' ); ?>