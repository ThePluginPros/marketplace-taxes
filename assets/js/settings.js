/* global jQuery, wcv_tax_localize_settings */
( function( $, data ) {
    $( function() {
        var $toggle = $( '.wcv-tax-toggle-calc-methods' ),
            $target = $( '.wcv-tax-calculation-methods' );

        $toggle.click( function( e ) {
            e.preventDefault();

            if ( $target.is( ':hidden' ) ) {
                $target.fadeIn();
                $toggle.text( data.strings.hide_details );
            } else {
                $target.hide();
                $toggle.text( data.strings.show_details );
            }
        } );
    } );
} )( jQuery, wcv_tax_localize_settings );