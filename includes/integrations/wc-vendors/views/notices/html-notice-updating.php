<?php

/**
 * Admin View: Notice - Updating
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$force_url = esc_url(
    add_query_arg( 'mt_force_update_orders', 'true', admin_url( 'admin.php?page=wcv-settings' ) )
);

?>
<div id="message" class="updated mt-message wc-connect">
    <p><strong><?php _e( 'Marketplace Taxes data update', 'marketplace-taxes' ); ?></strong>
        &#8211; <?php _e(
            'Your vendor sub orders are being updated in the background. ',
            'marketplace-taxes'
        ); ?> <a href="<?php echo $force_url; ?>"><?php _e(
                'Taking a while? Click here to run it now.',
                'marketplace-taxes'
            ); ?></a>
    </p>
</div>
