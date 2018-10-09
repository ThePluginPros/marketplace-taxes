<?php

/**
 * Admin View: Notice - Updated
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

?>
<div id="message" class="updated mt-message wc-connect mt-message-success">
    <a class="mt-message-close notice-dismiss" href="<?php echo esc_url(
        wp_nonce_url(
            add_query_arg( 'mt-hide-notice', 'sub_order_update', remove_query_arg( 'mt_update_sub_orders' ) ),
            'mt_hide_notices_nonce',
            '_mt_notice_nonce'
        )
    ); ?>"><?php _e( 'Dismiss', 'marketplace-taxes' ); ?></a>
    <p>
        <?php _e(
            'Marketplace Taxes data update complete. The updated orders will be imported into TaxJar within 24 hours.',
            'marketplace-taxes'
        ); ?>
    </p>
</div>
