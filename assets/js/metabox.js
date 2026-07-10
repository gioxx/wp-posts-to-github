(function ($) {
    'use strict';

    function renderTrace($trace, lines) {
        $trace.empty();

        if (!lines || !lines.length) {
            return;
        }

        $.each(lines, function (i, line) {
            $trace.append($('<li>').text(line));
        });
    }

    function setStatusExported($status, message) {
        $status
            .removeClass('potogh-status-never_exported potogh-status-modified_since_export potogh-status-exported')
            .addClass('potogh-status-exported')
            .find('.potogh-status-text').text(message);
        $status.find('.dashicons').attr('class', 'dashicons dashicons-yes-alt');
    }

    $(document).on('click', '.potogh-export-button', function () {
        var $button = $(this);
        var postId = $button.data('post-id');
        var $wrapper = $button.closest('.postbox').length ? $button.closest('.postbox') : $button.parent();
        var $status = $wrapper.find('.potogh-status');
        var $message = $wrapper.find('.potogh-export-message');
        var $trace = $wrapper.find('.potogh-export-trace');
        var nonce = $wrapper.find('#potogh_export_nonce').val();

        $button.prop('disabled', true).addClass('potogh-loading');
        $message.text('');
        renderTrace($trace, []);

        $.post(potoghMetabox.ajaxUrl, {
            action: 'potogh_export_post',
            post_id: postId,
            nonce: nonce
        }).done(function (response) {
            if (response.success) {
                setStatusExported($status, response.data.message);
                $message.text('');
            } else {
                $message.text(response.data.message);
            }
            renderTrace($trace, response.data.trace);
        }).fail(function (jqXHR) {
            var data = jqXHR.responseJSON && jqXHR.responseJSON.data ? jqXHR.responseJSON.data : null;
            var message = data && data.message ? data.message : potoghMetabox.networkError;
            $message.text(message);
            renderTrace($trace, data ? data.trace : []);
        }).always(function () {
            $button.prop('disabled', false).removeClass('potogh-loading');
        });
    });
})(jQuery);
