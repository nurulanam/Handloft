<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.guest')] #[Title('Log in')] class extends Component
{
    public string $email = '';

    public string $password = '';

    public bool $remember = false;

    public function login(): void
    {
        $this->validate([
            'email' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $throttleKey = Str::lower($this->email).'|'.request()->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            $this->addError('email', "Too many login attempts. Please try again in {$seconds} seconds.");

            return;
        }

        $user = User::where('email', $this->email)->orWhere('user_id', $this->email)->first();

        if (! $user || ! Auth::attempt(['email' => $user->email, 'password' => $this->password], $this->remember)) {
            RateLimiter::hit($throttleKey, 60);

            $this->addError('email', 'These credentials do not match our records.');

            return;
        }

        if (! $user->isActive()) {
            Auth::logout();

            $this->addError('email', 'Your account is inactive. Please contact your administrator.');

            return;
        }

        RateLimiter::clear($throttleKey);

        if (request()->hasSession()) {
            request()->session()->regenerate();
        }

        $this->redirect(route('dashboard'), navigate: true);
    }
};
?>

<div>
    <h1 class="mb-6 text-xl font-semibold text-slate-900">Log in to your account</h1>

    @if (session('status'))
        <div class="mb-4 rounded-md bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
            {{ session('status') }}
        </div>
    @endif

    <form wire:submit="login" class="space-y-4">
        <div>
            <label for="email" class="block text-sm font-medium text-slate-700">User ID / Email</label>
            <input
                wire:model="email"
                id="email"
                type="text"
                autofocus
                autocomplete="username"
                class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500 sm:text-sm"
            >
            @error('email')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="password" class="block text-sm font-medium text-slate-700">Password</label>
            <input
                wire:model="password"
                id="password"
                type="password"
                autocomplete="current-password"
                class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500 sm:text-sm"
            >
            @error('password')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div class="flex items-center justify-between">
            <label class="flex items-center gap-2 text-sm text-slate-600">
                <input wire:model="remember" type="checkbox" class="rounded border-slate-300 text-slate-900 shadow-sm focus:ring-slate-500">
                Remember me
            </label>

            <a href="{{ route('password.request') }}" wire:navigate class="text-sm text-slate-600 hover:text-slate-900 hover:underline">
                Forgot password?
            </a>
        </div>

        <button
            type="submit"
            class="w-full rounded-md bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800"
            wire:loading.attr="disabled"
            wire:target="login"
        >
            Log in
        </button>
    </form>
</div>
