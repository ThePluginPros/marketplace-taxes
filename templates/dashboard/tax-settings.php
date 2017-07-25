<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Tax settings template. You can override this template by copying it to
 * THEME_DIR/wc-vendors/dashboard/.
 *
 * @version 1.0.0
 */
?>

<div class="tabs-content hide-all" id="tax">
    <?php WCV_Taxes_Store_Form::enabled(); ?>
	<?php WCV_Taxes_Store_Form::calculation_method(); ?>
    <?php WCV_Taxes_Store_Form::calculation_method_options(); ?>
</div>