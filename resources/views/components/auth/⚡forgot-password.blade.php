<?php

use Illuminate\Support\Facades\Password;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.guest')] #[Title('Forgot password')] class extends Component
{
    public string $email = '';

    public ?string $status = null;

    public function sendResetLink(): void
    {
        $this->validate([
            'email' => ['required', 'email'],
        ]);

        $result = Password::sendResetLink(['email' => $this->email]);

        // Always show a generic success-style message so we never confirm
        // or deny whether an email address exists in the system.
        $this->status = $result === Password::RESET_LINK_SENT
            ? __('A reset link has been sent if that account exists.')
            : __('A reset link has been sent if that account exists.');

        $this->reset('email');
    }
};
?>

<div>
    <h1 class="mb-1 text-2xl font-semibold tracking-tight text-zinc-900">Forgot your password?</h1>
    <p class="mb-6 text-sm text-zinc-600">Enter your email and we'll send you a password reset link.</p>

    @if ($status)
        <div class="mb-4 rounded-xl border border-brand/20 bg-brand/5 px-4 py-3 text-sm text-brand">
            {{ $status }}
        </div>
    @endif

    <form wire:submit="sendResetLink" class="space-y-4">
        <div>
            <label for="email" class="block text-sm font-medium text-zinc-800">Email</label>
            <div class="relative mt-1.5">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="pointer-events-none absolute left-3.5 top-1/2 size-4.5 -translate-y-1/2 text-zinc-500"><rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>
                <input
                    wire:model="email"
                    id="email"
                    type="email"
                    autofocus
                    class="block w-full rounded-xl border border-zinc-300 bg-white/70 py-3 pl-10.5 pr-3.5 text-sm text-zinc-900 placeholder:text-zinc-500 transition focus:border-brand focus:bg-white focus:outline-none focus:ring-4 focus:ring-brand-lime/20 lg:py-2.5"
                >
            </div>
            @error('email')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <button
            type="submit"
            class="w-full rounded-xl bg-brand px-4 py-3.5 text-sm font-semibold text-white shadow-lg shadow-brand/25 transition hover:bg-brand/90 lg:py-3"
        >
            Send reset link
        </button>
    </form>

    <div class="mt-6 text-center text-sm text-zinc-600">
        <a href="{{ route('login') }}" wire:navigate class="hover:text-brand hover:underline">Back to log in</a>
    </div>
</div>
