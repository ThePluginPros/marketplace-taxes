/* global jQuery, wp, wcv_tax_address_table_localize, Ink.UI */
( function( $, wp, data, InkUI ) {
    $( function() {
        var $table     = $( '#wcv_taxes_nexus_addresses_table' ),
            $tbody     = $( '#wcv_taxes_nexus_addresses' ),
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
                    if ( $( '#wcv_taxes_nexus_addresses_blank_row' ).is( ':visible' ) ) {
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
                },
            } ),

            // Table view instance
            addressTable = new AddressTable( {
                el: $tbody,
            } );

        addressTable.render();
        
        // Custom validation for Business Locations field
        $( window ).load( function() {
            var formInstance = InkUI.Common_1.getInstance( '.wcv-form' )[0];
            
            if ( typeof formInstance === 'undefined' ) {
                return;
            }

            var oldCallback = formInstance._options.beforeValidation;

            formInstance._options.beforeValidation = function( arguments ) {
                var field_id  = 'locations_placeholder',
                    validator = arguments[ 'validator' ],
                    elements  = validator._formElements,
                    num_locs  = $tbody.find( 'tr:not(#wcv_taxes_nexus_addresses_blank_row)' ).length;

                // Create or reset FormElement for locations
                if ( ! ( field_id in arguments[ 'elements' ] ) ) {
                    elements[ field_id ] = [ new InkUI.FormValidator_2.FormElement(
                        document.getElementById( field_id ),
                        {
                            'form': validator,
                        }
                    ) ];
                } else {
                    elements[ field_id ][0].unforceInvalid();
                    elements[ field_id ][0].unforceValid();
                }

                // Validate locations: at least one required
                if ( num_locs < 1 ) {
                    elements[ field_id ][0].forceInvalid( data.strings.locations_error );
                } else {
                    elements[ field_id ][0].forceValid();
                }

                // Execute existing beforeValidation callback, if any
                if ( typeof oldCallback === 'function' ) {
                    oldCallback( arguments );
                }
            };
        } );
    } );

    $( window ).load( function() {
        $( document.body ).trigger( 'country_to_state_changed' );

        $( '.country_select' ).each( function() {
            $( this ).trigger( 'change' );
        } );
    } );
} )( jQuery, wp, wcv_tax_address_table_localize, Ink.UI );