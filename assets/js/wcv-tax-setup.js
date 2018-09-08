jQuery(function ($) {
    $(window).load(function () {
        if (typeof window.Ink === 'undefined') {
            return;
        }

        var tabs = window.Ink.UI.Common_1.getInstance('.wcv-tabs')[0];
        var onChange = tabs._options.onChange;
        var completed = false;

        var onTabChanged = function (tabs) {
            var activeTab = tabs.activeTab();

            if ('tax' === activeTab && !completed) {
                $('#review_tax_settings_step').addClass('completed');
                $.ajax({
                    type: 'post',
                    url: tfm_wcv_tax_setup_data.ajax_url,
                    data: {
                        action: 'tfm_complete_tax_setup'
                    },
                    dataType: 'json',
                    success: function (response) {
                        if (response.success) {
                            completed = true;
                        }
                    }
                });
            }

            if (typeof onChange === 'function') {
                onChange(tabs);
            }
        };

        tabs._options.onChange = onTabChanged;

        onTabChanged(tabs);
    });
});
