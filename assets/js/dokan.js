/* global jQuery, mt_settings_data */
jQuery(function ($) {
    var dokan_tax_settings = {
        init: function () {
            $("form#tax-form").validate({
                submitHandler: function (form) {
                    dokan_tax_settings.submit(form.getAttribute('id'));
                },
                errorElement: 'span',
                errorClass: 'error',
                errorPlacement: this.validate_error,
                rules: {
                    taxjar_api_token: {
                        required: {
                            depends: function (element) {
                                return $('#upload_transactions').is(':checked');
                            }
                        }
                    }
                },
                messages: {
                    taxjar_api_token: {
                        required: mt_settings_data.i18n_api_key_required
                    }
                }
            });

            $(document).ajaxSuccess(this.check_setup_status);

            // Maybe scroll address fields into view after page loads
            $(window)
                .on('hashchange', this.handle_hash_change)
                .load(function () {
                    $(this).trigger('hashchange');
                });
        },
        validate_error: function (error, element) {
            $(element)
                .closest('div')
                .append(error);
        },
        submit: function (form_id) {
            if (typeof tinyMCE !== 'undefined') {
                tinyMCE.triggerSave();
            }

            var self = $("form#" + form_id),
                form_data = self.serialize() + '&action=dokan_settings&form_id=' + form_id;

            self.find('.ajax_prev').append('<span class="dokan-loading"> </span>');

            $.post(dokan.ajaxurl, form_data, function (resp) {
                self.find('span.dokan-loading').remove();

                $('html,body').animate({scrollTop: 100});

                var $response = $('.dokan-ajax-response');

                if (resp.success) {
                    // Harcoded Customization for template-settings function
                    $response.html($('<div/>', {
                        'class': 'dokan-alert dokan-alert-success',
                        'html': '<p>' + resp.data.msg + '</p>',
                    }));

                    $response.append(resp.data.progress);
                } else {
                    $response.html($('<div/>', {
                        'class': 'dokan-alert dokan-alert-danger',
                        'html': resp.data
                    }));
                }
            });
        },
        check_setup_status: function (event, xhr, options, data) {
            if (typeof data.data.tax_setup_complete === 'undefined') {
                return;
            }

            if (data.data.tax_setup_complete) {
                $('#address_notice').hide();
            } else {
                // Reload to show address notice
                location.reload();
            }
        },
        handle_hash_change: function () {
            // Scroll address fields into view if hash is '#address'
            if ('#address' === location.hash) {
                $('html, body').animate({
                    scrollTop: $('#dokan_store_ppp').offset().top
                }, 500);

                // Reset hash just in case link is clicked again
                location.hash = '';
            }
        }
    };

    dokan_tax_settings.init();
});