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

?>
<div id="message" class="updated tfm-message">
    <a class="tfm-message-close notice-dismiss" href="<?php echo esc_url(
        wp_nonce_url( add_query_arg( 'tfm-hide-notice', $notice ), 'tfm_hide_notices_nonce', '_tfm_notice_nonce' )
    ); ?>"><?php _e( 'Dismiss', 'taxjar-for-marketplaces' ); ?></a>
    <?php echo wp_kses_post( wpautop( $notice_html ) ); ?>
</div>
