<?php

/**
 * Address notice template.
 *
 * You can override this template by copying it to YOUR_THEME/marketplace-taxes/address-notice.php.
 *
 * @global array $setup_steps
 *
 * @since 1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

?>
<strong><?php _e( 'Tax setup incomplete.', 'marketplace-taxes' ); ?></strong>
<p><?php _e(
        'Please complete the following steps to ensure your customers are taxed correctly:',
        'marketplace-taxes'
    ); ?></p>
<ol id="tax_setup_steps">
    <?php foreach ( $setup_steps as $id => $step ): ?>
        <li id="<?php echo esc_attr( $id ); ?>_step"
            class="<?php echo $step['complete'] ? 'completed' : ''; ?>">
            <a href="<?php echo esc_attr( $step['url'] ); ?>"><?php echo wp_kses_post(
                    $step['label']
                ); ?></a>
        </li>
    <?php endforeach; ?>
</ol>
