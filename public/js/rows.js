(function ($) {
    'use strict';

    const root = $('#rows');
    if (!root.length) {
        return;
    }

    const url = root.data('rows-url');
    const columns = root.data('columns');
    const statuses = root.data('statuses');
    const formatNumber = (n) => Number(n).toLocaleString('uk-UA');
    const state = {page: 1, status: '', flag: ''};

    function drawHead() {
        const head = $('#rows-head').empty();
        $.each(['Рядок', 'Статус', 'Позначки'].concat(columns), function (i, name) {
            head.append($('<th>').text(name));
        });
    }

    function filterLink(label, status, flag, active) {
        return $('<a>').attr('href', '#').text(label).toggleClass('active', active).on('click', function (event) {
            event.preventDefault();
            state.status = status;
            state.flag = flag;
            state.page = 1;
            load();
        });
    }

    function drawFilters() {
        const box = $('#row-filters').empty();
        box.append(filterLink('Усі', '', '', !state.status && !state.flag));
        $.each(statuses, function (i, item) {
            box.append(' ', filterLink(item.label, item.value, '', state.status === item.value));
        });
    }

    function drawFlagCounts(counts) {
        const table = $('#flag-counts').empty();
        $.each(counts, function (i, item) {
            const name = $('<th>').append(filterLink(item.label, '', item.value, state.flag === item.value));
            if (item.critical) {
                name.append(' ', $('<span>').addClass('badge error').text('критична'));
            }
            table.append($('<tr>').append(name, $('<td>').text(formatNumber(item.count))));
        });
    }

    function drawRows(rows) {
        const body = $('#rows-body').empty();
        if (rows.length === 0) {
            body.append($('<tr>').append($('<td>').attr('colspan', 3 + columns.length).text('Рядків немає.')));
            return;
        }

        $.each(rows, function (i, row) {
            const tr = $('<tr>');
            tr.append($('<td>').text(row.row_number));
            tr.append($('<td>').append($('<span>').addClass('badge ' + row.status).text(row.status_label)));
            tr.append($('<td>').text(row.problems.join('; ')));
            $.each(row.values, function (j, value) {
                tr.append($('<td>').text(value === null ? '' : value));
            });
            body.append(tr);
        });
    }

    function drawPager(meta) {
        $('#rows-pages').text('Сторінка ' + formatNumber(meta.current_page) + ' з ' + formatNumber(meta.last_page)
            + ' · рядків ' + formatNumber(meta.total));
        $('#rows-prev').prop('disabled', meta.current_page <= 1);
        $('#rows-next').prop('disabled', meta.current_page >= meta.last_page);
    }

    function load() {
        $.getJSON(url, {page: state.page, status: state.status, flag: state.flag})
            .done(function (answer) {
                drawFilters();
                drawFlagCounts(answer.flag_counts);
                drawRows(answer.data);
                drawPager(answer.meta);
            })
            .fail(function () {
                $('#rows-body').empty().append($('<tr>').append(
                    $('<td>').attr('colspan', 3 + columns.length).addClass('error').text('Не вдалося завантажити рядки.'),
                ));
            });
    }

    $('#rows-prev').on('click', function () {
        state.page -= 1;
        load();
    });

    $('#rows-next').on('click', function () {
        state.page += 1;
        load();
    });

    drawHead();
    load();
})(jQuery);
