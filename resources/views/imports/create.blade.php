@extends('layouts.app')

@section('title', 'Новий імпорт')

@section('content')
    <section class="card">
        <h1>Імпорт заявок</h1>
        <p>CSV-файл у UTF-8, розділювач — кома, перший рядок — заголовок з колонками в такому порядку:</p>
        <p class="columns">@foreach ($columns as $column)
                <code>{{ $column }}</code>
            @endforeach</p>
        <p class="muted">Кожен рядок файлу зберігається з позначками знайдених проблем. Невірний телефон робить рядок
            помилкою, решта проблем — попередження.</p>

        <form id="import-form"
              data-store-url="{{ route('imports.store') }}"
              data-max-bytes="{{ $maxBytes }}">
            <input type="file" id="import-file" accept=".csv,text/csv" required>
            <button type="submit" id="import-submit">Завантажити</button>
        </form>

        <div id="import-progress" hidden>
            <div class="phase">
                <span>Передача файлу</span>
                <span id="upload-text"></span>
            </div>
            <div class="phase">
                <span>Обробка рядків</span>
                <progress id="process-bar" max="1" value="0"></progress>
                <span id="process-text"></span>
            </div>
        </div>

        <p id="import-error" class="error" hidden></p>

        <div id="import-summary" hidden>
            <h2>Готово</h2>
            <table class="summary">
                <tr>
                    <th>Усього рядків</th>
                    <td id="sum-total"></td>
                </tr>
                <tr>
                    <th>OK</th>
                    <td id="sum-clean"></td>
                </tr>
                <tr>
                    <th>Попередження</th>
                    <td id="sum-warning"></td>
                </tr>
                <tr>
                    <th>Помилка</th>
                    <td id="sum-error"></td>
                </tr>
            </table>
            <p><a id="sum-link" href="#">Переглянути рядки</a> · <a id="sum-export" href="#">Завантажити результат
                    (CSV)</a></p>
        </div>
    </section>
@endsection

@push('scripts')
    <script src="{{ asset('js/import.js') }}"></script>
@endpush
