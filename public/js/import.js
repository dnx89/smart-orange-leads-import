(function () {
    'use strict';

    const form = document.getElementById('import-form');
    if (!form) {
        return;
    }

    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    const maxBytes = Number(form.dataset.maxBytes);
    const fileInput = document.getElementById('import-file');
    const submit = document.getElementById('import-submit');
    const el = (id) => document.getElementById(id);
    const formatNumber = (n) => Number(n).toLocaleString('uk-UA');

    async function request(url, body) {
        const headers = {'X-CSRF-TOKEN': csrf, 'Accept': 'application/json'};
        if (!(body instanceof FormData)) {
            headers['Content-Type'] = 'application/json';
            body = JSON.stringify(body);
        }

        let response;
        try {
            response = await fetch(url, {method: 'POST', headers, body});
        } catch (e) {
            throw new Error('Немає зв\'язку з сервером.');
        }

        if (response.status === 413) {
            throw new Error('Сервер не прийняв файл такого розміру.');
        }

        const text = await response.text();
        let data;
        try {
            data = text ? JSON.parse(text) : {};
        } catch (e) {
            throw new Error('Сервер відповів не тим, що очікувалося — найімовірніше, файл більший за дозволений на сервері.');
        }

        if (!response.ok) {
            throw new Error(firstMessage(data) || ('Помилка ' + response.status));
        }

        return data;
    }

    function firstMessage(data) {
        if (data.errors) {
            const first = Object.values(data.errors)[0];
            return Array.isArray(first) ? first[0] : first;
        }
        return data.error || data.message || '';
    }

    async function upload(file) {
        if (file.size > maxBytes) {
            throw new Error('Файл завеликий: максимум ' + Math.round(maxBytes / 1024 / 1024) + ' МБ.');
        }

        const body = new FormData();
        body.append('file', file, file.name);

        el('upload-text').textContent = Math.round(file.size / 1024) + ' КБ, триває…';
        const created = await request(form.dataset.storeUrl, body);
        if (!created.id) {
            throw new Error('Сервер не повернув номер імпорту.');
        }
        el('upload-text').textContent = Math.round(file.size / 1024) + ' КБ, готово';

        return form.dataset.storeUrl + '/' + created.id;
    }

    async function process(base) {
        for (; ;) {
            const state = await request(base + '/process', {});

            el('process-bar').value = state.progress;
            el('process-text').textContent = formatNumber(state.rows_total) + ' рядків';

            if (state.status === 'failed') {
                throw new Error(state.error || 'Імпорт зупинено.');
            }
            if (state.finished) {
                return state;
            }
        }
    }

    function showSummary(base, state) {
        el('sum-total').textContent = formatNumber(state.rows_total);
        el('sum-clean').textContent = formatNumber(state.rows_clean);
        el('sum-warning').textContent = formatNumber(state.rows_warning);
        el('sum-error').textContent = formatNumber(state.rows_error);
        el('sum-link').href = base;
        el('sum-export').href = base + '/export';
        el('import-summary').hidden = false;
    }

    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        const file = fileInput.files[0];
        if (!file) {
            return;
        }

        submit.disabled = true;
        fileInput.disabled = true;
        el('import-error').hidden = true;
        el('import-summary').hidden = true;
        el('import-progress').hidden = false;
        el('process-bar').value = 0;
        el('upload-text').textContent = '';
        el('process-text').textContent = '';

        try {
            const base = await upload(file);
            const state = await process(base);
            showSummary(base, state);
        } catch (e) {
            el('import-error').textContent = e.message;
            el('import-error').hidden = false;
        } finally {
            submit.disabled = false;
            fileInput.disabled = false;
        }
    });
})();
