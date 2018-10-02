<?php

/**
 * Admin View: Notice - Updating
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$force_url = esc_url(
    add_query_arg( 'tfm_force_update_orders', 'true', admin_url( 'admin.php?page=wcv-settings' ) )
);

?>
<div id="message" class="updated tfm-message wc-connect">
    <p><strong><?php _e( 'TaxJar for Marketplaces data update', 'taxjar-for-marketplaces' ); ?></strong>
        &#8211; <?php _e(
            'Your vendor sub orders are being updated in the background. ',
            'taxjar-for-marketplaces'
        ); ?> <a href="<?php echo $force_url; ?>"><?php _e(
                'Taking a while? Click here to run it now.',
                'taxjar-for-marketplaces'
            ); ?></a>
    </p>
</div>
