<?php

use App\Models\AppSetting;
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

        // Flash a one-time signal the dashboard's layout picks up on its very
        // next page load, to show the branded loading overlay over the real
        // dashboard content instead of on this login page (see
        // layouts/app.blade.php) — only if the setting is currently enabled.
        $settings = AppSetting::current();

        if ($settings->show_loading_screen) {
            session()->flash('just_logged_in', true);
            session()->flash('loading_screen_seconds', $settings->loading_screen_seconds);
        }

        $this->redirect(route('dashboard'), navigate: true);
    }
};
?>

<div>
    <h1 class="mb-1 text-2xl font-semibold tracking-tight text-zinc-900">Welcome back</h1>
    <p class="mb-6 text-sm text-zinc-500">Log in to your account to continue.</p>

    @if (session('status'))
        <div class="mb-4 rounded-xl border border-brand/20 bg-brand/5 px-4 py-3 text-sm text-brand">
            {{ session('status') }}
        </div>
    @endif

    <form wire:submit="login" class="space-y-4">
        <div>
            <label for="email" class="block text-sm font-medium text-zinc-700">User ID / Email</label>
            <input
                wire:model="email"
                id="email"
                type="text"
                autofocus
                autocomplete="username"
                class="mt-1.5 block w-full rounded-xl border border-zinc-300 bg-white px-3.5 py-2.5 text-sm text-zinc-900 placeholder:text-zinc-400 transition-shadow focus:border-brand focus:outline-none focus:ring-4 focus:ring-brand-lime/20"
            >
            @error('email')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="password" class="block text-sm font-medium text-zinc-700">Password</label>
            <input
                wire:model="password"
                id="password"
                type="password"
                autocomplete="current-password"
                class="mt-1.5 block w-full rounded-xl border border-zinc-300 bg-white px-3.5 py-2.5 text-sm text-zinc-900 placeholder:text-zinc-400 transition-shadow focus:border-brand focus:outline-none focus:ring-4 focus:ring-brand-lime/20"
            >
            @error('password')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div class="flex items-center justify-between pt-1">
            <label class="flex items-center gap-2 text-sm text-zinc-600">
                <input wire:model="remember" type="checkbox" class="rounded border-zinc-300 text-brand focus:ring-brand-lime/40">
                Remember me
            </label>

            <a href="{{ route('password.request') }}" wire:navigate class="text-sm text-zinc-600 hover:text-brand hover:underline">
                Forgot password?
            </a>
        </div>

        <button
            type="submit"
            class="w-full rounded-xl bg-brand px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-brand/90 disabled:opacity-60"
            wire:loading.attr="disabled"
            wire:target="login"
        >
            <span wire:loading.remove wire:target="login">Log in</span>
            <span wire:loading wire:target="login">Logging in…</span>
        </button>
    </form>
</div>
