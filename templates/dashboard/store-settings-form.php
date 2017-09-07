<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Template for the tax settings form. You can override this template by copying
 * it to THEME_DIR/wc-vendors/dashboard/.
 *
 * @version 1.0.0
 */

?>
<div class="tabs-content hide-all" id="tax">
    <?php
        foreach ( $fields as $field_id => $field ) {
            do_action( 'wcv_taxes_before_field_' . $field_id, $field );

            if ( in_array( $field['type'], array( 'text', 'checkbox', 'radio' ) ) ) {
                $cb = array( 'WCVendors_Pro_Form_Helper', 'input' );
            } else if ( in_array( $field['type'], array( 'select', 'textarea' ) ) ) {
                $cb = array( 'WCVendors_Pro_Form_Helper', $field['type'] );
            } else {
                $cb = array( 'WCV_Taxes_Form_Helper', $field['type'] );
            }

            if ( is_callable( $cb ) ) {
                call_user_func( $cb,  $field );
            }

            do_action( 'wcv_taxes_after_field_' . $field_id, $field );
        }
    ?>
</div>