<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

?>

    <label class="alignleft">
        <span class="title"><?php _e( 'Tax category', 'marketplace-taxes' ); ?></span>
        <span class="input-text-wrap">
            <span class="mt-selected-category"><?php esc_html_e( 'No change', 'marketplace-taxes' ); ?></span>
            <input type="hidden" name="tax_category" class="mt-category-input" value="">
            <button type="button" class="button button mt-select-category" data-is-bulk="1">
                <?php esc_html_e( 'Change', 'marketplace-taxes' ); ?>
            </button>
            <button type="button" class="button mt-reset-category">
                <?php esc_html_e( 'Reset', 'marketplace-taxes' ); ?>
            </button>
        </span>
    </label>

<?php include __DIR__ . '/html-tax-category-modal.php'; ?>