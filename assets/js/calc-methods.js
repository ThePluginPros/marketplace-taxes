/**
 * JS to toggle visibility of calculation method description.
 */

/* global jQuery, wcv_tax_calc_methods_localize */
( function( $, data ) {
    var $toggle      = $( '.wcv-tax-toggle-calc-methods' ),
        $description = $( '.wcv-tax-calculation-methods' );

    $toggle.click( function( e ) {
        e.preventDefault();

        if ( $description.is( ':hidden' ) ) {
            $description.fadeIn();
            $toggle.text( data.strings.hide_details );
        } else {
            $description.hide();
            $toggle.text( data.strings.show_details );
        }
    } );
} )( jQuery, wcv_tax_calc_methods_localize );