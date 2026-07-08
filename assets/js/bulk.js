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

    function logTrace(postId, lines) {
        if (!lines || !lines.length) {
            return;
        }

        var $log = $('#potogh-bulk-log');

        $.each(lines, function (i, line) {
            $log.append($('<div>').text('#' + postId + ': ' + line));
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
        $('#potogh-bulk-log').empty();
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
                logTrace(postId, response.data.trace);
            }).fail(function (jqXHR) {
                var data = jqXHR.responseJSON && jqXHR.responseJSON.data ? jqXHR.responseJSON.data : null;
                var message = data && data.message ? data.message : 'errore di rete';
                failed.push(postId + ': ' + message);
                logTrace(postId, data ? data.trace : []);
            }).always(function () {
                next(index + 1);
            });
        }

        next(0);
    });
})(jQuery);
