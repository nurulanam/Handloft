<?php

use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.guest')] #[Title('Reset password')] class extends Component
{
    public string $token = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function mount(string $token): void
    {
        $this->token = $token;
        $this->email = request()->query('email', '');
    }

    public function resetPassword(): void
    {
        $this->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $status = Password::reset(
            [
                'email' => $this->email,
                'password' => $this->password,
                'password_confirmation' => $this->password_confirmation,
                'token' => $this->token,
            ],
            function ($user) {
                $user->forceFill([
                    'password' => Hash::make($this->password),
                ])->setRememberToken(Str::random(60));

                $user->save();

                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            $this->addError('email', __($status));

            return;
        }

        session()->flash('status', __('Your password has been reset. You can now log in.'));

        $this->redirect(route('login'), navigate: true);
    }
};
?>

<div>
    <h1 class="mb-1 text-2xl font-semibold tracking-tight text-zinc-900">Reset your password</h1>
    <p class="mb-6 text-sm text-zinc-600">Choose a new password for your account.</p>

    <form wire:submit="resetPassword" class="space-y-4">
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

        <div>
            <label for="password" class="block text-sm font-medium text-zinc-800">New password</label>
            <div class="relative mt-1.5">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="pointer-events-none absolute left-3.5 top-1/2 size-4.5 -translate-y-1/2 text-zinc-500"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                <input
                    wire:model="password"
                    id="password"
                    type="password"
                    class="block w-full rounded-xl border border-zinc-300 bg-white/70 py-3 pl-10.5 pr-3.5 text-sm text-zinc-900 placeholder:text-zinc-500 transition focus:border-brand focus:bg-white focus:outline-none focus:ring-4 focus:ring-brand-lime/20 lg:py-2.5"
                >
            </div>
            @error('password')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="password_confirmation" class="block text-sm font-medium text-zinc-800">Confirm new password</label>
            <div class="relative mt-1.5">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="pointer-events-none absolute left-3.5 top-1/2 size-4.5 -translate-y-1/2 text-zinc-500"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                <input
                    wire:model="password_confirmation"
                    id="password_confirmation"
                    type="password"
                    class="block w-full rounded-xl border border-zinc-300 bg-white/70 py-3 pl-10.5 pr-3.5 text-sm text-zinc-900 placeholder:text-zinc-500 transition focus:border-brand focus:bg-white focus:outline-none focus:ring-4 focus:ring-brand-lime/20 lg:py-2.5"
                >
            </div>
        </div>

        <button
            type="submit"
            class="w-full rounded-xl bg-brand px-4 py-3.5 text-sm font-semibold text-white shadow-lg shadow-brand/25 transition hover:bg-brand/90 lg:py-3"
        >
            Reset password
        </button>
    </form>
</div>
