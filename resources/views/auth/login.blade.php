@extends('layouts.app')

@section('title', 'Вхід')

@section('content')
    <form method="post" action="{{ route('login.store') }}" class="card narrow">
        @csrf
        <h1>Вхід</h1>

        <label for="email">Email</label>
        <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus>

        <label for="password">Пароль</label>
        <input id="password" type="password" name="password" required>

        <label class="inline"><input type="checkbox" name="remember" value="1"> Запам'ятати мене</label>

        @error('email')
        <p class="error">{{ $message }}</p>
        @enderror

        <button type="submit">Увійти</button>
    </form>
@endsection
