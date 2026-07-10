(function ($) {
    'use strict';

    var selectedIds = [];
    var selectAllMatching = false;
    var suppressChange = false;
    var stopRequested = false;
    var lastCheckedCheckbox = null;

    // Browsers restore checkbox state on reload independently of the rendered
    // markup; force a clean slate so a fresh page load never starts pre-selected.
    $('.potogh-post-checkbox, #potogh-select-all').prop('checked', false);

    function currentFilterParams() {
        var $form = $('.potogh-filters-form');

        return {
            status: $form.find('[name="status"]').val() || '',
            s: $form.find('[name="s"]').val() || '',
            category: $form.find('[name="category"]').val() || '',
            tag: $form.find('[name="tag"]').val() || '',
            m: $form.find('[name="m"]').val() || ''
        };
    }

    function totalMatching() {
        return parseInt($('.potogh-export-tab').data('total'), 10) || 0;
    }

    function updateSelectionUi() {
        var $button = $('#potogh-bulk-export-selected');
        var $count = $('#potogh-selection-count');
        var $selectAllBtn = $('#potogh-select-all-matching-btn');
        var $clearBtn = $('#potogh-clear-selection-btn');
        var count = selectedIds.length;
        var total = totalMatching();

        $button.prop('disabled', count === 0);

        if (count === 0) {
            $count.text('');
            $selectAllBtn.prop('hidden', true);
            $clearBtn.prop('hidden', true);
            return;
        }

        if (selectAllMatching) {
            $count.text(potoghBulk.selectionCount.replace('%d', count));
            $selectAllBtn.prop('hidden', true);
            $clearBtn.prop('hidden', false);
        } else {
            $count.text(count + ' ' + potoghBulk.selectedLabel);
            $clearBtn.prop('hidden', false);

            if (total > count) {
                $selectAllBtn.text(potoghBulk.selectAllMatching.replace('%d', total)).prop('hidden', false);
            } else {
                $selectAllBtn.prop('hidden', true);
            }
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
        $('#potogh-select-all').prop('checked', selectedIds.length > 0 && selectedIds.length === $('.potogh-post-checkbox').length);
        suppressChange = false;
        updateSelectionUi();
    }

    function clearSelection() {
        selectAllMatching = false;
        selectedIds = [];
        suppressChange = true;
        $('.potogh-post-checkbox').prop('checked', false);
        $('#potogh-select-all').prop('checked', false);
        suppressChange = false;
        updateSelectionUi();
    }

    $(document).on('click', '.potogh-post-checkbox', function (e) {
        if (suppressChange) {
            return;
        }

        if (e.shiftKey && lastCheckedCheckbox) {
            var checkboxes = $('.potogh-post-checkbox').toArray();
            var start = checkboxes.indexOf(lastCheckedCheckbox);
            var end = checkboxes.indexOf(this);

            if (start !== -1 && end !== -1) {
                var checked = $(this).is(':checked');
                var from = Math.min(start, end);
                var to = Math.max(start, end);

                checkboxes.slice(from, to + 1).forEach(function (checkbox) {
                    checkbox.checked = checked;
                });
            }
        }

        lastCheckedCheckbox = this;
    });

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
            clearSelection();
            return;
        }

        suppressChange = true;
        $('.potogh-post-checkbox').prop('checked', true);
        suppressChange = false;

        selectAllMatching = false;
        selectedIds = pageCheckedIds();
        updateSelectionUi();
    });

    $('#potogh-select-all-matching-btn').on('click', function () {
        var $btn = $(this);
        var $spinner = $('#potogh-select-all-spinner');

        $btn.prop('disabled', true);
        $spinner.prop('hidden', false);

        $.post(potoghBulk.ajaxUrl, $.extend({
            action: 'potogh_get_filtered_ids',
            nonce: $('.potogh-export-tab').data('nonce')
        }, currentFilterParams())).done(function (response) {
            if (response.success) {
                selectAllMatching = true;
                selectedIds = response.data.ids;
                updateSelectionUi();
            }
        }).always(function () {
            $btn.prop('disabled', false);
            $spinner.prop('hidden', true);
        });
    });

    $('#potogh-clear-selection-btn').on('click', function () {
        clearSelection();
    });

    function exportOne(postId, nonce) {
        return $.post(potoghBulk.ajaxUrl, {
            action: 'potogh_bulk_export_one',
            post_id: postId,
            nonce: nonce
        });
    }

    function statusIconClass(status) {
        switch (status) {
            case 'exported':
                return 'dashicons-yes-alt';
            case 'modified_since_export':
                return 'dashicons-warning';
            default:
                return 'dashicons-clock';
        }
    }

    function adjustStatTile(statusClass, delta) {
        var $number = $('.potogh-stat-tile.potogh-stat-' + statusClass + ' .potogh-stat-number');

        if (!$number.length) {
            return;
        }

        var current = parseInt($number.text(), 10) || 0;
        $number.text(current + delta);
    }

    function markRowExported(postId, message) {
        var $cell = $('tr[data-post-id="' + postId + '"]').find('.potogh-status-cell');
        var previousStatus = null;

        $.each(['never_exported', 'modified_since_export', 'exported'], function (i, s) {
            if ($cell.hasClass('potogh-status-' + s)) {
                previousStatus = s;
            }
        });

        $cell
            .removeClass('potogh-status-never_exported potogh-status-modified_since_export potogh-status-exported')
            .addClass('potogh-status-exported');
        $cell.find('.dashicons').attr('class', 'dashicons ' + statusIconClass('exported'));
        $cell.find('.potogh-status-text').text(message);

        if (previousStatus && previousStatus !== 'exported') {
            adjustStatTile(previousStatus, -1);
            adjustStatTile('exported', 1);
        }
    }

    function logLine(text) {
        var $log = $('#potogh-bulk-log');
        $log.append($('<div>').text(text));
        $log.scrollTop($log[0].scrollHeight);
    }

    function logTrace(postId, lines) {
        if (!lines || !lines.length) {
            return;
        }

        $.each(lines, function (i, line) {
            logLine('#' + postId + ': ' + line);
        });
    }

    function attemptExport(postId, nonce) {
        var deferred = $.Deferred();

        exportOne(postId, nonce).done(function (response) {
            deferred.resolve({
                success: !!response.success,
                message: response.data ? response.data.message : '',
                trace: response.data ? response.data.trace : [],
                retryAfter: response.data ? response.data.retry_after : null
            });
        }).fail(function (jqXHR) {
            var data = jqXHR.responseJSON && jqXHR.responseJSON.data ? jqXHR.responseJSON.data : null;
            deferred.resolve({
                success: false,
                message: data && data.message ? data.message : potoghBulk.networkError,
                trace: data ? data.trace : [],
                retryAfter: data ? data.retry_after : null
            });
        });

        return deferred.promise();
    }

    function exportWithRetry(postId, nonce, retried) {
        return attemptExport(postId, nonce).then(function (result) {
            if (!result.success && !retried && result.retryAfter) {
                var wait = Math.min(Math.max(parseInt(result.retryAfter, 10) || 0, 1), 60);
                logLine('#' + postId + ': ' + potoghBulk.rateLimitWait.replace('%d', wait));

                var deferred = $.Deferred();
                window.setTimeout(function () {
                    exportWithRetry(postId, nonce, true).then(deferred.resolve);
                }, wait * 1000);
                return deferred.promise();
            }

            return result;
        });
    }

    function setExporting(exporting) {
        $('.potogh-filters-form :input').prop('disabled', exporting);
        $('.tablenav-pages a').css('pointer-events', exporting ? 'none' : '');
        $('body').toggleClass('potogh-exporting', exporting);
        $('#potogh-bulk-footer').prop('hidden', !exporting);
        $('#potogh-bulk-stop').prop('hidden', !exporting).prop('disabled', false).text(potoghBulk.stopLabel);
    }

    function updateProgress(done, total) {
        var percent = total > 0 ? Math.round((done / total) * 100) : 0;

        $('#potogh-bulk-progress .potogh-progress-fill').css('width', percent + '%');
        $('#potogh-bulk-progress-text').text(done + '/' + total);
    }

    $('#potogh-bulk-stop').on('click', function () {
        stopRequested = true;
        $(this).prop('disabled', true).text(potoghBulk.stopping);
    });

    $('#potogh-bulk-export-selected').on('click', function () {
        var nonce = $('.potogh-export-tab').data('nonce');
        var ids = selectedIds.slice();

        if (ids.length === 0) {
            return;
        }

        stopRequested = false;
        setExporting(true);
        $('#potogh-bulk-log').empty();
        $('#potogh-bulk-summary').text('');
        updateProgress(0, ids.length);

        var succeeded = 0;
        var failed = [];

        function next(index) {
            if (stopRequested || index >= ids.length) {
                var summary = potoghBulk.summarySucceeded.replace('%d', succeeded);
                if (failed.length > 0) {
                    summary += ' ' + potoghBulk.summaryFailed.replace('%d', failed.length) + ' ' + failed.join('; ');
                }
                if (stopRequested && index < ids.length) {
                    summary += ' ' + potoghBulk.summaryStopped.replace('%d', ids.length - index);
                }
                $('#potogh-bulk-summary').text(summary);
                window.setTimeout(function () {
                    window.location.reload();
                }, 1200);
                return;
            }

            var postId = ids[index];

            exportWithRetry(postId, nonce, false).then(function (result) {
                if (result.success) {
                    succeeded++;
                    markRowExported(postId, result.message);
                } else {
                    failed.push(postId + ': ' + result.message);
                }
                logTrace(postId, result.trace);
                updateProgress(index + 1, ids.length);
                next(index + 1);
            });
        }

        next(0);
    });
})(jQuery);
