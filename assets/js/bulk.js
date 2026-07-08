(function ($) {
    'use strict';

    $('#potogh-select-all').on('change', function () {
        $('.potogh-post-checkbox').prop('checked', $(this).is(':checked'));
    });

    function exportOne(postId, nonce) {
        return $.post(potoghBulk.ajaxUrl, {
            action: 'potogh_bulk_export_one',
            post_id: postId,
            nonce: nonce
        });
    }

    $('#potogh-bulk-export-selected').on('click', function () {
        var $button = $(this);
        var nonce = $('#potogh_bulk_nonce').val();
        var ids = $('.potogh-post-checkbox:checked').map(function () {
            return $(this).val();
        }).get();

        if (ids.length === 0) {
            return;
        }

        $button.prop('disabled', true);
        var succeeded = 0;
        var failed = [];

        function next(index) {
            if (index >= ids.length) {
                var summary = succeeded + ' post esportati con successo.';
                if (failed.length > 0) {
                    summary += ' ' + failed.length + ' falliti: ' + failed.join('; ');
                }
                $('#potogh-bulk-summary').text(summary);
                $button.prop('disabled', false);
                return;
            }

            var postId = ids[index];

            exportOne(postId, nonce).done(function (response) {
                var $row = $('tr[data-post-id="' + postId + '"]');
                if (response.success) {
                    succeeded++;
                    $row.find('.potogh-status-cell').text(response.data.message);
                } else {
                    failed.push(postId + ': ' + response.data.message);
                }
            }).fail(function () {
                failed.push(postId + ': errore di rete');
            }).always(function () {
                next(index + 1);
            });
        }

        next(0);
    });
})(jQuery);
