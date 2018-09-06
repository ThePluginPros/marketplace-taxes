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

        var country = $(this).val(),
            $statebox = $(this).parent().next(),
            state_input = $statebox.find('.shipping_state'),
            input_name = state_input.attr('name'),
            value = state_input.val(),
            placeholder = state_input.attr('placeholder') || '';

        if (states[country]) {

            var options = '',
                state = states[country];

            for (var index in state) {
                if (state.hasOwnProperty(index)) {
                    options = options + '<option value="' + index + '">' + state[index] + '</option>';
                }
            }

            state_select = $('<select name="' + input_name + '" class="tfm_state_select shipping_state" placeholder="' + placeholder + '"><option value="">' + tfm_country_select_params.i18n_select_state_text + '</option>' + options + '</select>');
            state_input.remove();
            $statebox.append(state_select);
            state_select.val(value).change();

            $(document.body).trigger('country_to_state_changed', [country, $wrapper]);

        } else {

            if (state_input.is('select')) {

                state_select = $('<input type="text" class="shipping_state" name="' + input_name + '" placeholder="' + placeholder + '" />');
                state_input.remove();
                $statebox.append(state_select);

                $(document.body).trigger('country_to_state_changed', [country, $wrapper]);

            }
        }

        $(document.body).trigger('country_to_state_changing', [country, $wrapper]);

    });

    $('.tfm_country_to_state').change();

});
