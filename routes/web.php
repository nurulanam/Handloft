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
    Route::livewire('/for-you', 'for-you')->name('for-you');
    Route::livewire('/starred', 'starred')->name('starred');

    Route::livewire('/users', 'users.index')->name('users.index');
    Route::livewire('/users/create', 'users.form')->name('users.create');
    Route::livewire('/users/{user}/edit', 'users.form')->name('users.edit');

    Route::livewire('/projects', 'projects.index')->name('projects.index');
    Route::livewire('/projects/create', 'projects.create')->name('projects.create');
    Route::livewire('/projects/{project}', 'projects.show')->name('projects.show');

    Route::livewire('/tasks', 'tasks.index')->name('tasks.index');
    Route::livewire('/tasks/create', 'tasks.create')->name('tasks.create');
    Route::livewire('/tasks/{task}', 'tasks.show')->name('tasks.show');

    Route::livewire('/work-history', 'work-history.index')->name('work-history.index');
    Route::livewire('/work-history/{user}', 'work-history.index')->name('work-history.show');
    Route::livewire('/notifications', 'notifications.index')->name('notifications.index');
    Route::livewire('/calendar', 'calendar.index')->name('calendar.index');

    Route::livewire('/settings', 'settings')->name('settings');

    Route::livewire('/profile', 'profile')->name('profile');
});

// Unknown URLs land here instead of 404ing before the middleware runs, so the
// session is loaded and the 404 page can show signed-in users the app shell.
Route::fallback(fn () => abort(404));
