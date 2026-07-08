(function ($) {
    'use strict';

    $(document).on('click', '.potogh-export-button', function () {
        var $button = $(this);
        var postId = $button.data('post-id');
        var $wrapper = $button.closest('.postbox').length ? $button.closest('.postbox') : $button.parent();
        var $message = $wrapper.find('.potogh-export-message');
        var nonce = $wrapper.find('#potogh_export_nonce').val();

        $button.prop('disabled', true);
        $message.text('');

        $.post(potoghMetabox.ajaxUrl, {
            action: 'potogh_export_post',
            post_id: postId,
            nonce: nonce
        }).done(function (response) {
            if (response.success) {
                $wrapper.find('.potogh-status').text(response.data.message);
                $message.text('');
            } else {
                $message.text(response.data.message);
            }
        }).fail(function () {
            $message.text('Errore di rete durante l\'esportazione.');
        }).always(function () {
            $button.prop('disabled', false);
        });
    });
})(jQuery);
