<?php

/**
 * Template for the store tax settings form.
 *
 * You can override this template by copying it to THEME_DIR/marketplace-taxes/dashboard/.
 *
 * @global MT_Vendor_Settings_Form $form Form instance
 *
 * @version 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

?>
<div class="tabs-content hide-all" id="tax">
    <p><?php echo $form->description(); ?></p>

    <?php $form->fields(); ?>
</div>