<?php

/**
 * Tax category select box template.
 *
 * @global string $selected_category
 * @global bool   $is_variation
 * @global int    $product_id
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( $is_variation ) {
    $field_name = "variation_tax_category[$product_id]";
    $class      = 'form-row form-field form-row-full variation-category';
} else {
    $field_name = 'tax_category';
    $class      = 'form-field';
}

?>

    <p class="<?php echo $class; ?> tax-category">
        <label for="<?php echo $field_name; ?>">
            <?php
            _e( 'Tax category', 'marketplace-taxes' );

            echo wc_help_tip(
                __(
                    'Used for products that are either exempt from tax in some jurisdictions or are taxed at reduced rates. ',
                    'marketplace-taxes'
                )
            );
            ?>
        </label>

        <span class="mt-selected-category">
            <?php esc_html_e( 'None', 'marketplace-taxes' ); ?>
        </span>

        <button type="button" class="button mt-select-category" data-is-variation="<?php echo (int) $is_variation; ?>">
            <?php esc_html_e( 'Change', 'marketplace-taxes' ); ?>
        </button>

        <button type="button" class="button mt-reset-category">
            <?php esc_html_e( 'Reset', 'marketplace-taxes' ); ?>
        </button>

        <input type="hidden" name="<?php echo $field_name; ?>" class="mt-category-input"
               value="<?php echo $selected_category; ?>">
    </p>

<?php include __DIR__ . '/html-tax-category-modal.php'; ?>