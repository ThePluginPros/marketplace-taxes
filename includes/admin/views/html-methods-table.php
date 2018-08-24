<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * HTML for Calculation Methods table.
 */

?>
</p> <!-- opened by settings framework -->

<table class="widefat striped wcv-taxes-table wcv-taxes-methods">
    <thead>
        <tr>
            <th><?php _e( 'Name', 'taxjar-for-marketplaces' ); ?></th>
            <th><?php _e( 'Pricing', 'taxjar-for-marketplaces' ); ?></th>
            <th><?php _e( 'Description', 'taxjar-for-marketplaces' ); ?></th>
            <th><?php _e( 'Enabled', 'taxjar-for-marketplaces' ); ?></th>
            <th><!-- Options --></th>
        </tr>
    </thead>
    <tbody class="wcv-taxes-methods-rows">
        <!-- Methods here -->
    </tbody>
</table>

<script type="text/html" id="tmpl-wcv-taxes-method-row">
    <tr data-id="{{ data.id }}">
        <td class="wcv-taxes-method-name">
            <a href="{{ data.affiliate_link }}">{{ data.name }}</a>
        </td>
        <td>{{{ data.cost }}}</td>
        <td>{{{ data.description }}}</td>
        <td>{{{ data.enabled_icon }}}</td>
        <td>
            <button class="button wcv-taxes-configure-method" type="button"><?php _e( 'Configure', 'taxjar-for-marketplaces' ); ?></button>
        </td>
    </tr>
</script>

<script type="text/html" id="tmpl-wcv-taxes-method-row-blank">
    <tr>
        <td colspan="5">
            <p><?php esc_html_e( 'No methods available.', 'taxjar-for-marketplaces' ); ?></p>
        </td>
    </tr>
</script>

<script type="text/template" id="tmpl-wcv-modal-calc-method-settings">
    <div class="wc-backbone-modal wc-backbone-modal-calc-method-settings">
        <div class="wc-backbone-modal-content">
            <section class="wc-backbone-modal-main" role="main">
                <header class="wc-backbone-modal-header">
                    <h1><?php
                        /* translators: %s: calculation method name */
                        printf(
                            esc_html__( '%s Settings', 'woocommerce', 'taxjar-for-marketplaces' ),
                            '{{{ data.name }}}'
                        );
                    ?></h1>
                    <button class="modal-close modal-close-link dashicons dashicons-no-alt">
                        <span class="screen-reader-text"><?php _e( 'Close modal panel', 'woocommerce', 'taxjar-for-marketplaces' ); ?></span>
                    </button>
                </header>
                <article class="wc-modal-shipping-method-settings">
                    <form action="" method="post">
                        {{{ data.settings_html }}}
                        <input type="hidden" name="method_id" value="{{{ data.id }}}" />
                    </form>
                </article>
                <footer>
                    <div class="inner">
                        <button id="btn-ok" class="button button-primary button-large"><?php _e( 'Save changes', 'woocommerce', 'taxjar-for-marketplaces' ); ?></button>
                    </div>
                </footer>
            </section>
        </div>
    </div>
    <div class="wc-backbone-modal-backdrop modal-close"></div>
</script>

<p> <!-- closed by settings framework -->