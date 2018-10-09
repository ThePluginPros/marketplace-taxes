jQuery(function ($) {
    $('#address_notice .notice-dismiss')
        .click(function (e) {
            e.preventDefault();

            if (confirm(mt_admin_notices.dismiss_confirmation)) {
                $.ajax({
                    type: 'post',
                    url: ajaxurl,
                    data: {
                        action: 'mt_dismiss_address_notice'
                    },
                    success: function () {
                        var $notice = $('#address_notice');

                        $notice.fadeTo(100, 0, function () {
                            $notice.slideUp(100, function () {
                                $notice.remove();
                            });
                        });
                    }
                });
            }
        })
        .off('click.wp-dismiss-notice');
});
