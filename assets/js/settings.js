/* global jQuery */
( function( $ ) {

    /**
     * Toggle visibility of method-specific fields based on selected method
     */
    function toggle_method_fields( old_val ) {
        if ( ! old_val ) {
            old_val = null;
        }

        var new_val = $( '#wcv_taxes_calc_method' ).val();

        if ( old_val ) {
            $( '.show-if-calc_method-' + old_val ).hide();
            $( '.hide-if-calc_method-' + old_val ).show();
        }
        if ( new_val ) {
            $( '.hide-if-calc_method-' + new_val ).hide();
            $( '.show-if-calc_method-' + new_val ).show();
        }
    }

    $( '#wcv_taxes_calc_method' ).change( function( e ) {
        toggle_method_fields( e['removed']['id'] );
    } );

    $( function() {
        toggle_method_fields();
    } );

} )( jQuery );