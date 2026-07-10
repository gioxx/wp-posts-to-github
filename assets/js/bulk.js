(function ($) {
    'use strict';

    var selectedIds = [];
    var selectAllMatching = false;
    var suppressChange = false;

    function currentFilterParams() {
        var $form = $('.potogh-filters');

        return {
            status: $form.find('[name="status"]').val() || '',
            s: $form.find('[name="s"]').val() || '',
            category: $form.find('[name="category"]').val() || '',
            tag: $form.find('[name="tag"]').val() || '',
            m: $form.find('[name="m"]').val() || ''
        };
    }

    function updateSelectionUi() {
        var $button = $('#potogh-bulk-export-selected');
        var $count = $('#potogh-selection-count');
        var count = selectedIds.length;

        $button.prop('disabled', count === 0);

        if (count === 0) {
            $count.text('');
        } else if (selectAllMatching) {
            $count.text(potoghBulk.selectionCount.replace('%d', count));
        } else {
            $count.text(count + ' ' + potoghBulk.selectedLabel);
        }
    }

    function pageCheckedIds() {
        return $('.potogh-post-checkbox:checked').map(function () {
            return parseInt($(this).val(), 10);
        }).get();
    }

    function resetToManualSelection() {
        selectAllMatching = false;
        selectedIds = pageCheckedIds();
        suppressChange = true;
        $('#potogh-select-all').prop('checked', false);
        suppressChange = false;
        updateSelectionUi();
    }

    $(document).on('change', '.potogh-post-checkbox', function () {
        if (suppressChange) {
            return;
        }
        resetToManualSelection();
    });

    $('#potogh-select-all').on('change', function () {
        if (suppressChange) {
            return;
        }

        var checked = $(this).is(':checked');

        if (!checked) {
            selectAllMatching = false;
            selectedIds = [];
            suppressChange = true;
            $('.potogh-post-checkbox').prop('checked', false);
            suppressChange = false;
            updateSelectionUi();
            return;
        }

        suppressChange = true;
        $('.potogh-post-checkbox').prop('checked', true);
        suppressChange = false;

        $.post(potoghBulk.ajaxUrl, $.extend({
            action: 'potogh_get_filtered_ids',
            nonce: $('.potogh-export-tab').data('nonce')
        }, currentFilterParams())).done(function (response) {
            if (response.success) {
                selectAllMatching = true;
                selectedIds = response.data.ids;
                updateSelectionUi();
            }
        });
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

    function setExporting(exporting) {
        $('.potogh-filters :input').prop('disabled', exporting);
        $('.tablenav-pages a').css('pointer-events', exporting ? 'none' : '');
        $('body').toggleClass('potogh-exporting', exporting);
    }

    function updateProgress(done, total) {
        var $progress = $('#potogh-bulk-progress');
        var percent = total > 0 ? Math.round((done / total) * 100) : 0;

        $progress.prop('hidden', false);
        $progress.find('.potogh-progress-fill').css('width', percent + '%');
        $('#potogh-bulk-progress-text').text(done + '/' + total);
    }

    $('#potogh-bulk-export-selected').on('click', function () {
        var nonce = $('.potogh-export-tab').data('nonce');
        var ids = selectedIds.slice();

        if (ids.length === 0) {
            return;
        }

        setExporting(true);
        $('#potogh-bulk-log').empty();
        $('#potogh-bulk-summary').text('');
        updateProgress(0, ids.length);

        var succeeded = 0;
        var failed = [];

        function next(index) {
            if (index >= ids.length) {
                var summary = potoghBulk.summarySucceeded.replace('%d', succeeded);
                if (failed.length > 0) {
                    summary += ' ' + potoghBulk.summaryFailed.replace('%d', failed.length) + ' ' + failed.join('; ');
                }
                $('#potogh-bulk-summary').text(summary);
                setExporting(false);
                return;
            }

            var postId = ids[index];

            exportOne(postId, nonce).done(function (response) {
                var $row = $('tr[data-post-id="' + postId + '"]');
                if (response.success) {
                    succeeded++;
                    $row.find('.potogh-status-text').text(response.data.message);
                } else {
                    failed.push(postId + ': ' + response.data.message);
                }
                logTrace(postId, response.data.trace);
            }).fail(function (jqXHR) {
                var data = jqXHR.responseJSON && jqXHR.responseJSON.data ? jqXHR.responseJSON.data : null;
                var message = data && data.message ? data.message : potoghBulk.networkError;
                failed.push(postId + ': ' + message);
                logTrace(postId, data ? data.trace : []);
            }).always(function () {
                updateProgress(index + 1, ids.length);
                next(index + 1);
            });
        }

        next(0);
    });
})(jQuery);
