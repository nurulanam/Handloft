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
    <h1 class="text-2xl font-semibold tracking-tight text-zinc-900">Welcome back</h1>
    <p class="mt-1 text-sm text-zinc-600">Log in to your account to continue.</p>

    @if (session('status'))
        <div class="mt-5 rounded-xl border border-brand/20 bg-brand/5 px-4 py-3 text-sm text-brand">
            {{ session('status') }}
        </div>
    @endif

    <form wire:submit="login" class="mt-6 space-y-4" x-data="{ reveal: false }">
        <div>
            <label for="email" class="block text-sm font-medium text-zinc-800">User ID / Email</label>
            <div class="relative mt-1.5">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="pointer-events-none absolute left-3.5 top-1/2 size-4.5 -translate-y-1/2 text-zinc-500"><circle cx="12" cy="8" r="5"/><path d="M20 21a8 8 0 0 0-16 0"/></svg>
                <input
                    wire:model="email"
                    id="email"
                    type="text"
                    autofocus
                    autocomplete="username"
                    placeholder="you@company.com"
                    class="block w-full rounded-xl border border-zinc-300 bg-white/70 py-3 pl-10.5 pr-3.5 text-sm text-zinc-900 placeholder:text-zinc-500 transition focus:border-brand focus:bg-white focus:outline-none focus:ring-4 focus:ring-brand-lime/20 lg:py-2.5"
                >
            </div>
            @error('email')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="password" class="block text-sm font-medium text-zinc-800">Password</label>
            <div class="relative mt-1.5">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="pointer-events-none absolute left-3.5 top-1/2 size-4.5 -translate-y-1/2 text-zinc-500"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                <input
                    wire:model="password"
                    id="password"
                    :type="reveal ? 'text' : 'password'"
                    type="password"
                    autocomplete="current-password"
                    placeholder="••••••••"
                    class="block w-full rounded-xl border border-zinc-300 bg-white/70 py-3 pl-10.5 pr-11 text-sm text-zinc-900 placeholder:text-zinc-500 transition focus:border-brand focus:bg-white focus:outline-none focus:ring-4 focus:ring-brand-lime/20 lg:py-2.5"
                >
                <button type="button" @click="reveal = ! reveal" class="absolute inset-y-0 right-0 flex items-center px-3.5 text-zinc-500 hover:text-zinc-700" :title="reveal ? 'Hide password' : 'Show password'">
                    <svg x-show="! reveal" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-4.5"><path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0"/><circle cx="12" cy="12" r="3"/></svg>
                    <svg x-show="reveal" x-cloak xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-4.5"><path d="M10.733 5.076a10.744 10.744 0 0 1 11.205 6.575 1 1 0 0 1 0 .696 10.747 10.747 0 0 1-1.444 2.49"/><path d="M14.084 14.158a3 3 0 0 1-4.242-4.242"/><path d="M17.479 17.499a10.75 10.75 0 0 1-15.417-5.151 1 1 0 0 1 0-.696 10.75 10.75 0 0 1 4.446-5.143"/><path d="m2 2 20 20"/></svg>
                </button>
            </div>
            @error('password')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div class="flex items-center justify-between pt-1">
            <label class="flex items-center gap-2 text-sm text-zinc-700">
                <input wire:model="remember" type="checkbox" class="size-4 rounded border-zinc-300 accent-brand">
                Remember me
            </label>

            <a href="{{ route('password.request') }}" wire:navigate class="text-sm font-medium text-brand hover:underline">
                Forgot password?
            </a>
        </div>

        <button
            type="submit"
            class="group flex w-full items-center justify-center gap-2 rounded-xl bg-brand px-4 py-3.5 text-sm font-semibold text-white shadow-lg shadow-brand/25 transition hover:bg-brand/90 disabled:opacity-60 lg:py-3"
            wire:loading.attr="disabled"
            wire:target="login"
        >
            <span wire:loading.remove wire:target="login" class="flex items-center gap-2">
                Log in
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-4 transition-transform group-hover:translate-x-0.5"><path fill-rule="evenodd" d="M3 10a.75.75 0 01.75-.75h10.638L10.23 5.29a.75.75 0 111.04-1.08l5.5 5.25a.75.75 0 010 1.08l-5.5 5.25a.75.75 0 11-1.04-1.08l4.158-3.96H3.75A.75.75 0 013 10z" clip-rule="evenodd" /></svg>
            </span>
            <span wire:loading wire:target="login">Logging in…</span>
        </button>
    </form>

    <p class="mt-8 text-center text-xs text-zinc-600 lg:hidden">&copy; {{ date('Y') }} {{ config('app.name') }}</p>
</div>
