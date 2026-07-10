(function ($) {
    'use strict';

    $(document).on('click', '#potogh-test-connection', function () {
        var $button = $(this);
        var $result = $('#potogh-test-connection-result');

        $button.prop('disabled', true);
        $result.removeClass('potogh-test-success potogh-test-error').text(potoghSettings.testing);

        $.post(potoghSettings.ajaxUrl, {
            action: 'potogh_test_connection',
            nonce: $('#potogh_test_connection_nonce').val(),
            token: $('#potogh_token').val(),
            owner_repo: $('#potogh_owner_repo').val(),
            branch: $('#potogh_branch').val()
        }).done(function (response) {
            var message = response.data && response.data.message ? response.data.message : '';
            $result.text(message).addClass(response.success ? 'potogh-test-success' : 'potogh-test-error');
        }).fail(function (jqXHR) {
            var data = jqXHR.responseJSON && jqXHR.responseJSON.data ? jqXHR.responseJSON.data : null;
            var message = data && data.message ? data.message : potoghSettings.networkError;
            $result.text(message).addClass('potogh-test-error');
        }).always(function () {
            $button.prop('disabled', false);
        });
    });
})(jQuery);
