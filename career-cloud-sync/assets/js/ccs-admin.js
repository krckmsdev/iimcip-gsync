/**
 * Career Cloud Sync — Admin UI
 * Enqueued only on the plugin's own page (see CCS_Admin_Menu::enqueue_assets).
 * Expects `ccsAdmin = { ajaxUrl, nonce }` from wp_localize_script.
 */
jQuery(document).ready(function ($) {
    'use strict';

    $(function () {
        initTabs();
        initTestConnection();
        initMappingRows();
        initLogFilter();
    });

    function initTabs() {
        var $tabs   = $('.ccs-tab');
        var $panels = $('.ccs-tab-panel');

        if (!$tabs.length) {
            return;
        }

        function activate(tab) {
            $tabs.removeClass('is-active').filter('[data-tab="' + tab + '"]').addClass('is-active');
            $panels.removeClass('is-active').filter('[data-panel="' + tab + '"]').addClass('is-active');
            if (history.replaceState) {
                history.replaceState(null, '', '#' + tab);
            }
        }

        $tabs.on('click', function (e) {
            e.preventDefault();
            var $this = $(this);

            if ($this.data('locked')) {
                alert('Connect Google in Settings first — the Folder Mapping tab unlocks once the connection test passes.');
                return;
            }

            activate($this.data('tab'));
        });

        var startTab = (location.hash || '').replace('#', '');
        if (startTab && $tabs.filter('[data-tab="' + startTab + '"]').not('[data-locked]').length) {
            activate(startTab);
        }
    }

    function initTestConnection() {
        var $btn = $('#ccs-test-connection');
        if (!$btn.length) {
            return;
        }

        $btn.on('click', function () {
            var $spinner = $('#ccs-test-spinner');

            $btn.prop('disabled', true);
            $spinner.addClass('is-active');

            $.post(ccsAdmin.ajaxUrl, {
                action: 'ccs_test_connection',
                nonce: ccsAdmin.nonce
            }).done(function (response) {
                var data = response.data || {};

                $('#ccs-status-pill')
                    .removeClass('ccs-pill-green ccs-pill-red ccs-pill-gray')
                    .addClass(response.success ? 'ccs-pill-green' : 'ccs-pill-red');
                $('#ccs-status-text').text(response.success ? 'Connected' : 'Connection Failed');
                $('#ccs-status-time-inline').text(data.verified || '');

                // Unlock Folder Mapping immediately on success, no reload needed.
                if (response.success) {
                    $('.ccs-tab[data-tab="mapping"]').removeClass('is-disabled').removeAttr('data-locked title');
                 } else {
                    $('.ccs-tab[data-tab="mapping"]')
                        .addClass('is-disabled')
                        .attr('data-locked', '1')
                        .attr('title', 'Connect Google in Settings first');
                }

                if (!response.success) {
                    alert(data.message || 'Connection failed.');
                }
            }).fail(function () {
                alert('Request failed — check your network and try again.');
            }).always(function () {
                $btn.prop('disabled', false);
                $spinner.removeClass('is-active');
            });
        });
    }

   function initMappingRows() {
        var $table = $('#ccs-mapping-table');
        if (!$table.length) {
            return;
        }

        var nextIndex = parseInt($table.data('next-index'), 10) || 0;

        $('#ccs-add-row').on('click', function () {
            var $newRow = $('#ccs-template-row').clone(true);
            $newRow.removeAttr('id');

            $newRow.find('select, input').each(function () {
                var name = $(this).attr('name');
                if (name) {
                    $(this).attr('name', name.replace('__INDEX__', nextIndex));
                }
            });

            $('#ccs-mapping-rows').append($newRow);
            nextIndex++;
        });

        $(document).on('click', '.ccs-remove-row', function () {
            var $rows = $('#ccs-mapping-rows .ccs-mapping-row');
            if ($rows.length > 1) {
                $(this).closest('tr').remove();
            } else {
                var $row = $(this).closest('tr');
                $row.find('select').val('');
                $row.find('input[type=text]').val('');
            }
        });

        $table.closest('form').on('submit', function (e) {
            var seen = {};
            var duplicate = null;

            $('#ccs-mapping-rows .ccs-mapping-row select.ccs-job-select').each(function () {
                var val = $(this).val();
                if (!val) {
                    return;
                }
                if (seen[val]) {
                    duplicate = $(this).find('option:selected').text();
                } else {
                    seen[val] = true;
                }
            });

            if (duplicate) {
                e.preventDefault();
                alert('"' + duplicate + '" is mapped more than once. Each job can only have one row.');
            }
        });
    }

        function initLogFilter() {
            var $filter = $('#ccs-log-filter');
            if (!$filter.length) {
                return;
            }

            var pageSize    = 25;
            var currentPage = 1;

            function getMatches() {
                var source = $filter.val();
                var search = $('#ccs-log-search').val().toLowerCase();

                return $('#ccs-log-table tbody tr.ccs-log-row').filter(function () {
                    var $row = $(this);
                    var matchesSource = !source || $row.data('source') === source;
                    var matchesSearch = !search || $row.text().toLowerCase().indexOf(search) !== -1;
                    return matchesSource && matchesSearch;
                });
            }

            function render() {
                var $all     = $('#ccs-log-table tbody tr.ccs-log-row');
                var $matches = getMatches(); // rows already come newest-first from PHP

                $all.hide();

                var totalPages = Math.max(1, Math.ceil($matches.length / pageSize));
                if (currentPage > totalPages) {
                    currentPage = totalPages;
                }

                var start = (currentPage - 1) * pageSize;
                $matches.slice(start, start + pageSize).show();

                $('#ccs-log-page-info').text('Page ' + currentPage + ' of ' + totalPages);
                $('#ccs-log-prev').prop('disabled', currentPage <= 1);
                $('#ccs-log-next').prop('disabled', currentPage >= totalPages);
            }

            $filter.on('input change', function () { currentPage = 1; render(); });
            $('#ccs-log-search').on('input change', function () { currentPage = 1; render(); });
            $('#ccs-log-prev').on('click', function () { if (currentPage > 1) { currentPage--; render(); } });
            $('#ccs-log-next').on('click', function () { currentPage++; render(); });

            render();
        }
});
