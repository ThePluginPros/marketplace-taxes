<?php

/**
 * Admin View: Notice - Update
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$update_url  = esc_url( add_query_arg( 'tfm_update_sub_orders', 'true', admin_url( 'admin.php?page=wcv-settings' ) ) );
$dismiss_url = esc_url(
    wp_nonce_url(
        add_query_arg( 'tfm-hide-notice', 'sub_order_update' ),
        'tfm_hide_notices_nonce',
        '_tfm_notice_nonce'
    )
);

?>
<div id="message" class="updated tfm-message wc-connect is-dismissible">
    <p><strong><?php _e( 'Your vendor orders need to be updated.', 'taxjar-for-marketplaces' ); ?></strong></p>
    <p>
        <?php _e(
            'TaxJar for Marketplaces needs to update your database to ensure that transaction data imported into TaxJar is accurate.',
            'taxjar-for-marketplaces'
        ); ?>
    </p>
    <p class="submit">
        <a class="tfm-update-now button-primary" href="<?php echo $update_url; ?>" class="button-primary"><?php _e(
                'Run the update',
                'taxjar-for-marketplaces'
            ); ?></a> or <a class="tfm-skip-update" href="<?php echo $dismiss_url; ?>"><?php _e(
                'Dismiss',
                'taxjar-for-marketplaces'
            ); ?></a>
    </p>
</div>
<script type="text/javascript">
    jQuery('.tfm-update-now').click('click', function () {
        return window.confirm('<?php echo esc_js(
            __(
                'It is strongly recommended that you backup your database before proceeding. Are you sure you wish to run the updater now?',
                'taxjar-for-marketplaces'
            )
        ); ?>'); // jshint ignore:line
    });
    jQuery('.tfm-skip-update').on('click', function () {
        return window.confirm('<?php echo esc_js(
            __(
                'Are you sure you want to skip the database update? Your vendors will only be able to import new orders into TaxJar.',
                'taxjar-for-marketplaces'
            )
        ); ?>'); // jshint ignore:line
    });
</script>
