<?php

/**
 * Admin View: Notice - Updated
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

?>
<div id="message" class="updated tfm-message wc-connect tfm-message-success">
    <a class="tfm-message-close notice-dismiss" href="<?php echo esc_url(
        wp_nonce_url(
            add_query_arg( 'tfm-hide-notice', 'sub_order_update', remove_query_arg( 'tfm_update_sub_orders' ) ),
            'tfm_hide_notices_nonce',
            '_tfm_notice_nonce'
        )
    ); ?>"><?php _e( 'Dismiss', 'taxjar-for-marketplaces' ); ?></a>
    <p>
        <?php _e(
            'TaxJar for Marketplaces data update complete. The updated orders will be imported into TaxJar within 24 hours.',
            'taxjar-for-marketplaces'
        ); ?>
    </p>
</div>
