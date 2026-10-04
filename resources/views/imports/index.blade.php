@extends('layouts.app')

@section('title', 'Історія завантажень')

@section('content')
    <section class="card">
        <h1>Історія завантажень</h1>

        @if ($imports->isEmpty())
            <p>Ще нічого не завантажено. <a href="{{ route('imports.create') }}">Новий імпорт</a></p>
        @else
            <table class="grid">
                <thead>
                <tr>
                    <th>#</th>
                    <th>Дата</th>
                    <th>Хто</th>
                    <th>Файл</th>
                    <th>Статус</th>
                    <th class="num">Усього</th>
                    <th class="num">OK</th>
                    <th class="num">Попередження</th>
                    <th class="num">Помилка</th>
                    <th class="num">Тривалість</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @foreach ($imports as $import)
                    <tr>
                        <td>{{ $import->id }}</td>
                        <td>{{ $import->created_at?->format('d.m.Y H:i') }}</td>
                        <td>{{ $import->user?->name }}</td>
                        <td>{{ $import->file_name }}</td>
                        <td><span class="badge {{ $import->status->value }}">{{ $import->status->label() }}</span></td>
                        <td class="num">{{ number_format($import->rows_total, 0, ',', ' ') }}</td>
                        <td class="num">{{ number_format($import->rows_clean, 0, ',', ' ') }}</td>
                        <td class="num">{{ number_format($import->rows_warning, 0, ',', ' ') }}</td>
                        <td class="num">{{ number_format($import->rows_error, 0, ',', ' ') }}</td>
                        <td class="num">
                            @if ($import->started_at && $import->finished_at)
                                {{ $import->started_at->diffInSeconds($import->finished_at) }} с
                            @endif
                        </td>
                        <td><a href="{{ route('imports.show', $import) }}">Рядки</a></td>
                    </tr>
                @endforeach
                </tbody>
            </table>

            {{ $imports->links() }}
        @endif
    </section>
@endsection
