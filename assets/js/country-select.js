/*
 * A modified version of the WC Vendors Pro country select script that doesn't
 * use select2.
 *
 * global tfm_country_select_params
 */
jQuery(function ($) {

    // tfm_country_select_params is required to continue, ensure the object exists
    if (typeof tfm_country_select_params === 'undefined') {
        return false;
    }

    /* State/Country select boxes */
    var states_json = tfm_country_select_params.countries.replace(/&quot;/g, '"'),
        states = $.parseJSON(states_json);

    $(document.body).on('change', 'select.tfm_country_to_state', function () {
        // Grab wrapping element to target only stateboxes in same 'group'
        var $wrapper = $(this).closest('.wcv_shipping_rates');

        var state_input,
            state_input_selector = $(this).data('state_input');

        if (state_input_selector) {
            state_input = $(state_input_selector);
        } else {
            state_input = $(this).closest('tr, fieldset, form').find('.shipping_state');
        }

        var country = $(this).val(),
            input_name = state_input.attr('name'),
            input_id = state_input.attr('id'),
            value = state_input.val(),
            placeholder = state_input.attr('placeholder') || '',
            classes = state_input.attr('class') || '';

        if (-1 === classes.indexOf('shipping_state')) {
            classes += ' shipping_state';
        }

        if (states[country]) {

            var options = '',
                state = states[country];

            for (var index in state) {
                if (state.hasOwnProperty(index)) {
                    options = options + '<option value="' + index + '">' + state[index] + '</option>';
                }
            }

            if (-1 === classes.indexOf('tfm_state_select')) {
                classes += ' tfm_state_select';
            }

            state_select = $('<select name="' + input_name + '" id="' + input_id + '" class="' + classes.trim() + '"><option value="">' + tfm_country_select_params.i18n_select_state_text + '</option>' + options + '</select>');
            state_input.replaceWith(state_select);
            state_select.val(value).change();

            $(document.body).trigger('country_to_state_changed', [country, $wrapper]);

        } else {

            if (state_input.is('select')) {

                state_select = $('<input type="text" class="' + classes.trim() + '" name="' + input_name + '" id="' + input_id + '" placeholder="' + placeholder + '" />');
                state_input.replaceWith(state_select);

                $(document.body).trigger('country_to_state_changed', [country, $wrapper]);

            }
        }

        $(document.body).trigger('country_to_state_changing', [country, $wrapper]);

    });

    $('.tfm_country_to_state').change();

});
