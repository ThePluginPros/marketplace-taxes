<?php

/**
 * Admin View: Custom Notices
 *
 * @global string $notice
 * @global string $notice_html
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$nonce_url = wp_nonce_url( add_query_arg( 'mt-hide-notice', $notice ), 'mt_hide_notices_nonce', '_mt_notice_nonce' );

?>
<div id="message" class="updated mt-message">
    <a class="mt-message-close notice-dismiss" href="<?php echo esc_url( $nonce_url ); ?>">
        <span class="screen-reader-text"><?php _e( 'Dismiss', 'marketplace-taxes' ); ?></span>
    </a>
    <?php echo wp_kses_post( wpautop( $notice_html ) ); ?>
</div>
