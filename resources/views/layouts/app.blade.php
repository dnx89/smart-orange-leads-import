<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Імпорт заявок')</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
@auth
    <header class="topbar">
        <nav>
            <a href="{{ route('imports.create') }}" @class(['active' => request()->routeIs('imports.create')])>Новий
                імпорт</a>
            <a href="{{ route('imports.index') }}" @class(['active' => request()->routeIs('imports.index', 'imports.show')])>Історія
                завантажень</a>
        </nav>
        <form method="post" action="{{ route('logout') }}" class="logout">
            @csrf
            <span>{{ auth()->user()->name }}</span>
            <button type="submit" class="link">Вийти</button>
        </form>
    </header>
@endauth
<main>
    @yield('content')
</main>
@stack('scripts')
</body>
</html>
