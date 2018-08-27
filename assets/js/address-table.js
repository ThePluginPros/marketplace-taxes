/* global jQuery, wp, wcv_tax_address_table_localize, Ink.UI */
( function( $, wp, data ) {
    $( function() {
        var $tbody     = $( '#nexus_addresses' ),
            $row_empty = wp.template( 'vt-nexus-addresses-empty' ),
            $row       = wp.template( 'vt-nexus-address' ),

            // Backbone view
            AddressTable = Backbone.View.extend( {
                rowTemplate: $row,
                initialize: function() {
                    $( document.body ).on( 'click', '.vt-add-nexus-address', { view: this }, this.onAddNewRow );
                    $( document.body ).on( 'click', '.vt-remove-nexus-address', { view: this }, this.onDeleteRow );
                },
                render: function() {
                    var addresses = data['addresses'],
                        view      = this;

                    view.$el.empty();

                    if ( _.size( addresses ) ) {
                        // Populate $tbody with the current addresses
                        $.each( addresses, function( id, rowData ) {
                            rowData['id'] = id;

                            view.renderRow( rowData );
                        } );
                    } else {
                        view.$el.append( $row_empty );
                    }
                },
                renderRow: function( rowData ) {
                    var view = this,
                        row  = view.rowTemplate( rowData );

                    view.$el.append( row );
                    view.initRow( rowData );
                },
                initRow: function( rowData ) {
                    var view    = this,
                        $tr     = view.$el.find( 'tr[data-id="' + rowData['id'] + '"]' ),
                        country = rowData['country'];

                    // Select country
                    if ( '' !== country ) {
                        $tr.find( '.country_select option[value="' + country + '"]' ).prop( 'selected', true );
                    }

                    // Initialize select2
                    $( document.body ).trigger( 'country_to_state_changed' );
                },
                onAddNewRow: function( event ) {
                    var view = event.data.view;

                    event.preventDefault();

                    // If this is the first row, remove the blank row
                    if ( $( '#nexus_addresses_blank_row' ).is( ':visible' ) ) {
                        view.$el.empty();
                    }

                    // Add new row
                    var row_id = view.$el.find( 'tr' ).length;

                    view.renderRow( {
                        id: row_id,
                        address_1: '',
                        address_2: '',
                        country: '',
                        city: '',
                        state: '',
                        postcode: ''
                    } );
                },
                onDeleteRow: function( event ) {
                    var view = event.data.view,
                        row  = $( this ).closest('tr');

                    event.preventDefault();
                    
                    row.remove();

                    if ( $tbody.find( 'tr' ).length === 0 ) {
                        view.$el.append( $row_empty );
                    }
                }
            } ),

            // Table view instance
            addressTable = new AddressTable( {
                el: $tbody
            } );

        addressTable.render();
    } );

    $( window ).load( function() {
        $( document.body ).trigger( 'country_to_state_changed' );

        $( '.country_select' ).each( function() {
            $( this ).trigger( 'change' );
        } );
    } );
} )( jQuery, wp, wcv_tax_address_table_localize );