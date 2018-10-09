<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
} ?>

<script type="text/html" id="tmpl-mt-category-row">
    <tr class="tax-category-row" data-id="{{ data.product_tax_code }}">
        <td>
            <h4>{{ data.name }} ({{ data.product_tax_code }})</h4>
            <p>{{ data.description }}</p>
        </td>
        <td width="1%">
            <button type="button" class="button button-primary mt-select-done"><?php _e(
                    'Select',
                    'marketplace-taxes'
                ); ?></button>
        </td>
    </tr>
</script>

<script type="text/html" id="tmpl-mt-category-select-modal">
    <div class="wc-backbone-modal">
        <div class="wc-backbone-modal-content mt-select-tax-category-modal-content woocommerce">
            <section class="wc-backbone-modal-main" role="main">
                <header class="wc-backbone-modal-header">
                    <h1><?php _e( 'Select tax category', 'marketplace-taxes' ); ?></h1>
                    <button class="modal-close modal-close-link dashicons dashicons-no-alt">
                        <span class="screen-reader-text"><?php _e(
                                'Close modal panel',
                                'marketplace-taxes'
                            ); ?></span>
                    </button>
                </header>
                <article>
                    <form action="" method="post">
                        <input name="search" class="mt-category-search"
                               placeholder="<?php _e( 'Start typing to search', 'marketplace-taxes' ); ?>"
                               type="text"
                               data-list=".mt-category-list">
                        <table>
                            <tbody class="mt-category-list"></tbody>
                        </table>
                        <input type="hidden" name="category" value="">
                        <input type="submit" id="btn-ok" name="btn-ok" value="Submit" style="display: none;">
                    </form>
                </article>
            </section>
        </div>
    </div>
    <div class="wc-backbone-modal-backdrop modal-close"></div>
</script>