<?php

/**
 * Template for the Tax Category select box.
 *
 * @global string $selected_category
 * @global bool $is_variation
 * @global string $field_name
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

?>

    <div class="control-group">
        <label for="<?php echo $field_name; ?>">
            <?php _e( 'Tax Category', 'taxjar-for-marketplaces' ); ?>
        </label>

        <div class="control">
            <span class="tfm-selected-category"><?php esc_html_e( 'Loading...', 'taxjar-for-marketplaces' ); ?></span>

            <button type="button" class="button tfm-select-category"
                    data-is-variation="<?php echo (int) $is_variation; ?>"><?php esc_html_e(
                    'Change',
                    'taxjar-for-marketplaces'
                ); ?></button>

            <button type="button" class="button tfm-reset-category"><?php esc_html_e(
                    'Reset',
                    'taxjar-for-marketplaces'
                ); ?></button>

            <input type="hidden" name="<?php echo $field_name; ?>" class="tfm-category-input"
                   value="<?php echo $selected_category; ?>">
        </div>

        <p class="tip">
            <?php _e(
                'Used for products that are either exempt from tax in some jurisdictions or are taxed at reduced rates.',
                'taxjar-for-marketplaces'
            ); ?>
        </p>
    </div>

<?php include __DIR__ . '/../../../admin/views/html-tax-category-modal.php'; ?>