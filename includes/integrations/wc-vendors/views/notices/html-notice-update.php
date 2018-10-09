<?php

/**
 * Admin View: Notice - Update
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$update_url  = esc_url( add_query_arg( 'mt_update_sub_orders', 'true', admin_url( 'admin.php?page=wcv-settings' ) ) );
$dismiss_url = esc_url(
    wp_nonce_url(
        add_query_arg( 'mt-hide-notice', 'sub_order_update' ),
        'mt_hide_notices_nonce',
        '_mt_notice_nonce'
    )
);

?>
<div id="message" class="updated mt-message wc-connect is-dismissible">
    <p><strong><?php _e( 'Your vendor orders need to be updated.', 'marketplace-taxes' ); ?></strong></p>
    <p>
        <?php _e(
            'Marketplace Taxes needs to update your database to ensure that transaction data imported into TaxJar is accurate.',
            'marketplace-taxes'
        ); ?>
    </p>
    <p class="submit">
        <a class="mt-update-now button-primary" href="<?php echo $update_url; ?>" class="button-primary"><?php _e(
                'Run the update',
                'marketplace-taxes'
            ); ?></a> or <a class="mt-skip-update" href="<?php echo $dismiss_url; ?>"><?php _e(
                'Dismiss',
                'marketplace-taxes'
            ); ?></a>
    </p>
</div>
<script type="text/javascript">
    jQuery('.mt-update-now').click('click', function () {
        return window.confirm('<?php echo esc_js(
            __(
                'It is strongly recommended that you backup your database before proceeding. Are you sure you wish to run the updater now?',
                'marketplace-taxes'
            )
        ); ?>'); // jshint ignore:line
    });
    jQuery('.mt-skip-update').on('click', function () {
        return window.confirm('<?php echo esc_js(
            __(
                'Are you sure you want to skip the database update? Your vendors will only be able to import new orders into TaxJar.',
                'marketplace-taxes'
            )
        ); ?>'); // jshint ignore:line
    });
</script>
