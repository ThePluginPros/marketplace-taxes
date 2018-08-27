<?php

/**
 * Admin tax settings page template. Not overridable.
 *
 * @global TFM_Vendor_Settings_Form $form
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

?>
<div class="wrap">
    <h2><?php _e( 'Tax Settings', 'taxjar-for-marketplaces' ); ?></h2>

    <p><?php echo $form->description(); ?></p>

    <form action="" method="POST">
        <table class="form-table">
            <?php $form->fields(); ?>

            <tr>
                <th class="full-width-field" colspan="2">
                    <input type="hidden" name="tfm_settings_save" value="true">
                    <?php wp_nonce_field( 'save-tax-settings' ); ?>
                    <?php submit_button( null, 'primary', 'submit', false ); ?>
                </th>
            </tr>
        </table>
    </form>
</div>
