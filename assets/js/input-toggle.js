/** global jQuery */
jQuery( function( $ ) {
    $('.input-toggle').on('change', function() {
        var id  = $(this).attr('id'),
            val = $(this).val();

        if ($(this).is('[type="checkbox"]') && ! $(this).is(':checked')) {
            val = null;
        }

        $('[class*="show-if-' + id + '"]')
            .closest('tr, p, div')
            .hide();
        $('[class*="show-if-' + id + '-' + val + '"]')
            .closest('tr, p, div')
            .show();

        $('[class*="hide-if-' + id + '"]')
            .closest('tr, p, div')
            .show();
        $('[class*="hide-if-' + id + '-' + val + '"]')
            .closest('tr, p, div')
            .hide();
    });

    function init() {
        $('.input-toggle').each(function() {
            $(this).trigger('change');
        });
    }

    init();
} );