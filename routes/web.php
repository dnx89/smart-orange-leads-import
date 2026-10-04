<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\LoginController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('login', [LoginController::class, 'create'])->name('login');
    Route::post('login', [LoginController::class, 'store'])->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::post('logout', [LoginController::class, 'destroy'])->name('logout');

    Route::redirect('/', '/imports');

    // Placeholders until Manual 05.
    Route::view('imports', 'imports.placeholder')->name('imports.index');
    Route::view('imports/create', 'imports.placeholder')->name('imports.create');
});
