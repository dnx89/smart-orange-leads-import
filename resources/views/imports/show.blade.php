@extends('layouts.app')

@section('title', 'Завантаження #' . $import->id)

@section('content')
    <section class="card">
        <h1>Завантаження #{{ $import->id }} — {{ $import->file_name }}</h1>

        <table class="summary">
            <tr>
                <th>Хто</th>
                <td>{{ $import->user?->name }}</td>
            </tr>
            <tr>
                <th>Коли</th>
                <td>{{ $import->created_at?->format('d.m.Y H:i:s') }}</td>
            </tr>
            <tr>
                <th>Статус</th>
                <td><span class="badge {{ $import->status->value }}">{{ $import->status->label() }}</span></td>
            </tr>
            @if ($import->error)
                <tr>
                    <th>Причина</th>
                    <td class="error">{{ $import->error }}</td>
                </tr>
            @endif
            <tr>
                <th>Усього рядків</th>
                <td>{{ number_format($import->rows_total, 0, ',', ' ') }}</td>
            </tr>
            <tr>
                <th>OK</th>
                <td>{{ number_format($import->rows_clean, 0, ',', ' ') }}</td>
            </tr>
            <tr>
                <th>Попередження</th>
                <td>{{ number_format($import->rows_warning, 0, ',', ' ') }}</td>
            </tr>
            <tr>
                <th>Помилка</th>
                <td>{{ number_format($import->rows_error, 0, ',', ' ') }}</td>
            </tr>
        </table>

        <h2>Позначки</h2>
        <table class="summary" id="flag-counts"></table>

        <p><a href="{{ route('imports.export', $import) }}">Завантажити результат (CSV)</a></p>
    </section>

    <section class="card"
             id="rows"
             data-rows-url="{{ route('imports.rows', $import) }}"
             data-columns="{{ json_encode($columns, JSON_UNESCAPED_UNICODE) }}"
             data-statuses="{{ json_encode($statuses, JSON_UNESCAPED_UNICODE) }}">
        <h2>Рядки</h2>
        <p class="filters" id="row-filters"></p>

        <div class="scroll">
            <table class="grid rows">
                <thead>
                <tr id="rows-head"></tr>
                </thead>
                <tbody id="rows-body"></tbody>
            </table>
        </div>

        <p class="pager">
            <button type="button" id="rows-prev" disabled>Назад</button>
            <span id="rows-pages"></span>
            <button type="button" id="rows-next" disabled>Далі</button>
        </p>
    </section>
@endsection

@push('scripts')
    <script src="{{ asset('js/jquery.min.js') }}"></script>
    <script src="{{ asset('js/rows.js') }}"></script>
@endpush
