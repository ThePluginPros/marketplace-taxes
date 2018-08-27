<?php

/**
 * Tax category select box template.
 *
 * @global string $selected_category
 * @global bool $is_variation
 * @global int $product_id
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( $is_variation ) {
    $class = 'form-row form-field form-row-full';
} else {
    $class = 'form-field';
} ?>

    <p class="<?php echo $class; ?> tax_category">
        <label for="tax_category[<?php echo $product_id; ?>]">
            <?php _e( 'Tax category', 'taxjar-for-marketplaces' ); ?>
        </label>

        <?php if ( $is_variation ): ?><br><?php endif; ?>

        <span class="tfm-selected-category"><?php esc_html_e(
                'General',
                'taxjar-for-marketplaces'
            ); ?></span>

        <button type="button" class="button tfm-select-category" data-is-variation="<?php echo (int) $is_variation; ?>">
            <?php esc_html_e(
                'Select',
                'taxjar-for-marketplaces'
            ); ?>
        </button>

        <input type="hidden" name="tax_category[<?php echo $product_id; ?>]" class="tfm-category-input"
               value="<?php echo $selected_category; ?>">
    </p>

<?php include __DIR__ . '/html-tax-category-modal.php'; ?>