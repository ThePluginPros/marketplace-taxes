/* global jQuery, wcv_calc_methods_localize_script, wp, ajaxurl */
( function( $, data, wp, ajaxurl ) {
    $( function() {
        var $table                = $( '.wcv-taxes-methods' ),
            $tbody                = $( '.wcv-taxes-methods-rows' ),
            $row_template         = wp.template( 'wcv-taxes-method-row' ),
            $blank_template       = wp.template( 'wcv-taxes-method-row-blank' ),

            // Backbone model
            CalculationMethod     = Backbone.Model.extend({
                methods: [],
                setMethods: function( methods ) {
                    this.set( 'methods', methods );
                    this.trigger( 'change:methods' );
                }
            } ),

            // Backbone view
            CalculationMethodView = Backbone.View.extend({
                rowTemplate: $row_template,
                initialize: function() {
                    this.listenTo( this.model, 'change:methods', this.render );

                    $( document.body ).on( 'click', '.wcv-taxes-configure-method', { view: this }, this.onConfigureMethod );
                    
                    $( document.body ).on( 'wc_backbone_modal_response', this.onConfigureMethodSubmitted );
                },
                block: function() {
                    $( this.el ).block({
                        message: null,
                        overlayCSS: {
                            background: '#fff',
                            opacity: 0.6
                        }
                    });
                },
                unblock: function() {
                    $( this.el ).unblock();
                },
                render: function() {
                    var methods = _.indexBy( this.model.get( 'methods' ), 'id' ),
                        view    = this;

                    // Empty $tbody
                    this.$el.empty();
                    this.unblock();

                    if ( _.size( methods ) ) {
                        // Populate $tbody with the current methods
                        $.each( methods, function( id, rowData ) {
                            if ( 'yes' == rowData.enabled ) {
                                rowData.enabled_icon = '<span class="status-enabled">' + data.strings.yes + '</span>';
                            } else {
                                rowData.enabled_icon = '<span class="status-disabled">' + data.strings.no + '</span>';
                            }

                            view.$el.append( view.rowTemplate( rowData ) );
                        } );
                    } else {
                        view.$el.append( $blank_template );
                    }
                },
                initTips: function() {
                    jQuery(".sf-tips").tooltip( { 
                        animation: true,
                        html: true,
                        delay: { 
                            show: 300,
                            hide: 100 
                        } 
                    } );
                },
                onConfigureMethod: function( event ) {
                    var id      = $( this ).closest( 'tr' ).data( 'id' ),
                        view    = event.data.view,
                        model   = view.model,
                        methods = _.indexBy( model.get( 'methods' ), 'id' ),
                        method  = methods[ id ];

                    // Only load modal if supported
                    if ( ! method.settings_html ) {
                        return true;
                    }

                    event.preventDefault();

                    $( this ).WCBackboneModal( {
                        template : 'wcv-modal-calc-method-settings',
                        variable : method,
                        data     : method,
                    } );

                    view.initTips();
                },
                onConfigureMethodSubmitted: function( event, target, posted_data ) {
                    if ( 'wcv-modal-calc-method-settings' === target ) {
                        calculationMethodView.block();

                        // Save method settings via ajax call
                        $.post( ajaxurl + ( ajaxurl.indexOf( '?' ) > 0 ? '&' : '?' ) + 'action=wcv_taxes_save_calc_method', {
                            wcv_taxes_save_calc_method_nonce : data.wcv_taxes_save_calc_method_nonce,
                            id                               : posted_data.id,
                            data                             : posted_data
                        }, function( response, textStatus ) {
                            if ( 'success' === textStatus && response.success ) {
                                $table.parent().find( '#woocommerce_errors' ).remove();

                                // If there were errors, prepend the form. Otherwise, update model.
                                if ( response.data.errors.length > 0 ) {
                                    calculationMethodView.showErrors( response.data.errors );
                                } else {
                                    calculationMethodView.model.setMethods( response.data.methods );
                                }
                            } else {
                                window.alert( data.strings.save_failed );
                                calculationMethodView.unblock();
                            }
                        }, 'json' );
                    }
                },
                showErrors: function( errors ) {
                    var error_html = '<div id="woocommerce_errors" class="error notice is-dismissible">';

                    $( errors ).each( function( index, value ) {
                        error_html = error_html + '<p>' + value + '</p>';
                    } );
                    error_html = error_html + '</div>';

                    $table.before( error_html );
                },
            } ),
            calculationMethod = new CalculationMethod({
                methods: data.methods,
            } ),
            calculationMethodView = new CalculationMethodView({
                model: calculationMethod,
                el:    $tbody
            } );

        calculationMethodView.render();
    });
})( jQuery, wcv_calc_methods_localize_script, wp, ajaxurl );