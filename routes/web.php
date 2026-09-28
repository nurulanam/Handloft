<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::middleware('guest')->group(function () {
    Route::livewire('/login', 'auth.login')->name('login');
    Route::livewire('/forgot-password', 'auth.forgot-password')->name('password.request');
    Route::livewire('/reset-password/{token}', 'auth.reset-password')->name('password.reset');
});

Route::post('/logout', function () {
    Auth::logout();

    request()->session()->invalidate();
    request()->session()->regenerateToken();

    return redirect()->route('login');
})->middleware('auth')->name('logout');

Route::middleware('auth')->group(function () {
    Route::livewire('/dashboard', 'dashboard')->name('dashboard');
});
