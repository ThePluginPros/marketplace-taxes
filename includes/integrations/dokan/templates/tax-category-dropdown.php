<?php

/**
 * Dokan Tax Category dropdown template.
 *
 * You can override this by copying it to THEME_DIR/marketplaces-taxes/dokan/.
 *
 * IMPORTANT: DO NOT close div.dokan-form-group. It will be closed for you.
 *
 * @since 1.1.0
 *
 * @version 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$is_variation = isset( $is_variation ) ? $is_variation : false;
$selected     = isset( $selected ) ? $selected : '';
$tooltip      = esc_attr__(
    'For products that are exempt from tax in some jurisdictions or taxed at reduced rates.',
    'marketplace-taxes'
);

?>
<div class="dokan-form-group">
    <label class="dokan-w12 dokan-control-label">
        <?php _e( 'Tax Category', 'marketplace-taxes' ); ?>

        <i class="fa fa-question-circle tips" title="<?php echo $tooltip; ?>" aria-hidden="true"></i>
    </label>

    <div class="dokan-12 tax-category-wrapper">
        <span class="mt-selected-category"><?php esc_html_e( 'Loading...', 'marketplace-taxes' ); ?></span>

        <button type="button" class="dokan-btn mt-select-category"
                data-is-variation="<?php echo (int) $is_variation; ?>"><?php esc_html_e(
                'Change',
                'marketplace-taxes'
            ); ?></button>
        <button type="button" class="dokan-btn dokan-btn-danger mt-reset-category"><?php esc_html_e(
                'Reset',
                'marketplace-taxes'
            ); ?></button>

        <input type="hidden" name="tax_category" class="mt-category-input" value="<?php echo $selected; ?>">
    </div>
