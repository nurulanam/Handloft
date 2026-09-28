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
    <h1 class="mb-2 text-xl font-semibold text-zinc-900">Forgot your password?</h1>
    <p class="mb-6 text-sm text-zinc-500">Enter your email and we'll send you a password reset link.</p>

    @if ($status)
        <div class="mb-4 rounded-lg border border-brand/20 bg-brand/5 px-4 py-3 text-sm text-brand">
            {{ $status }}
        </div>
    @endif

    <form wire:submit="sendResetLink" class="space-y-4">
        <div>
            <label for="email" class="block text-sm font-medium text-zinc-700">Email</label>
            <input
                wire:model="email"
                id="email"
                type="email"
                autofocus
                class="mt-1 block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 placeholder:text-zinc-400 focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand-lime/40"
            >
            @error('email')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <button
            type="submit"
            class="w-full rounded-lg bg-brand px-4 py-2 text-sm font-semibold text-white hover:bg-brand/90"
        >
            Send reset link
        </button>
    </form>

    <div class="mt-6 text-center text-sm text-zinc-500">
        <a href="{{ route('login') }}" wire:navigate class="hover:text-brand hover:underline">Back to log in</a>
    </div>
</div>
