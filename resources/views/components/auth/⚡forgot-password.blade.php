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
    <h1 class="mb-2 text-xl font-semibold text-slate-900">Forgot your password?</h1>
    <p class="mb-6 text-sm text-slate-500">Enter your email and we'll send you a password reset link.</p>

    @if ($status)
        <div class="mb-4 rounded-md bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
            {{ $status }}
        </div>
    @endif

    <form wire:submit="sendResetLink" class="space-y-4">
        <div>
            <label for="email" class="block text-sm font-medium text-slate-700">Email</label>
            <input
                wire:model="email"
                id="email"
                type="email"
                autofocus
                class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500 sm:text-sm"
            >
            @error('email')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <button
            type="submit"
            class="w-full rounded-md bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800"
        >
            Send reset link
        </button>
    </form>

    <div class="mt-6 text-center text-sm text-slate-500">
        <a href="{{ route('login') }}" wire:navigate class="hover:text-slate-900 hover:underline">Back to log in</a>
    </div>
</div>
