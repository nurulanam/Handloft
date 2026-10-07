<?php

use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * The signed-in user's own profile. Personal details, photo and password are
 * self-service; role, status, department, User ID and joining date are shown
 * read-only, since only an admin (via Team) may change those.
 */
new #[Layout('layouts.app')] #[Title('My profile')] class extends Component
{
    use WithFileUploads;

    public string $name = '';

    public string $email = '';

    public string $phone = '';

    public $photo = null;

    public string $current_password = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function mount(): void
    {
        $user = auth()->user();

        $this->name = $user->name;
        $this->email = $user->email;
        $this->phone = (string) $user->phone;
    }

    public function updating(string $name): void
    {
        $this->resetErrorBag($name);
    }

    public function saveDetails(): void
    {
        $user = auth()->user();

        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:30'],
        ]);

        $user->update([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?: null,
        ]);

        $this->dispatch('notify', message: 'Your profile was updated.', type: 'success');
    }

    /**
     * A photo picked from the camera button on the avatar is saved straight
     * away (replacing, and deleting, the previous one).
     */
    public function updatedPhoto(): void
    {
        $this->validate(['photo' => ['required', 'image', 'max:2048']]);

        $user = auth()->user();
        $this->deleteStoredPhoto($user->profile_photo);
        $user->update(['profile_photo' => $this->photo->store('profile-photos', 'public')]);
        $this->photo = null;

        $this->dispatch('notify', message: 'Profile photo updated.', type: 'success');
    }

    public function savePassword(): void
    {
        $this->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', 'min:8', 'confirmed', 'different:current_password'],
        ], [
            'current_password.current_password' => 'That isn\'t your current password.',
            'password.different' => 'Choose a password different from your current one.',
        ]);

        auth()->user()->update(['password' => $this->password]);

        $this->reset('current_password', 'password', 'password_confirmation');

        $this->dispatch('notify', message: 'Your password was changed.', type: 'success');
    }

    private function deleteStoredPhoto(?string $path): void
    {
        if ($path && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }

    public function with(): array
    {
        $user = auth()->user();

        return [
            'user' => $user,
            'roleName' => \Illuminate\Support\Str::headline($user->getRoleNames()->first() ?? 'No role'),
            'photoUrl' => $user->profile_photo ? Storage::url($user->profile_photo) : null,
        ];
    }
};
?>

@php
    $input = 'block w-full rounded-lg border border-zinc-300 bg-surface px-3 py-2 text-sm text-zinc-900 placeholder:text-zinc-400 focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand-lime/40';
    $label = 'block text-sm font-medium text-zinc-700';
@endphp

<div class="space-y-4 sm:space-y-6">
    <div>
        <h1 class="text-xl font-semibold text-zinc-900 sm:text-2xl">My profile</h1>
        <p class="hidden text-sm text-zinc-500 sm:block">Your personal details and sign-in settings.</p>
    </div>

    {{-- Identity card. The photo is changed right here: the camera button on the avatar picks one, which opens the
         cropper (position + zoom) before it's uploaded and saved. --}}
    <div x-data="photoCropper" class="relative overflow-hidden rounded-2xl border border-zinc-200 bg-surface">
        <div class="h-20 bg-brand sm:h-24" style="background-image: radial-gradient(circle at 85% 20%, rgb(191 239 30 / 0.35), transparent 45%), radial-gradient(circle, rgb(255 255 255 / 0.12) 1px, transparent 1px); background-size: auto, 18px 18px;"></div>
        <div class="flex flex-col gap-3 px-4 pb-5 sm:flex-row sm:items-start sm:gap-5 sm:px-6">
            <div class="relative -mt-10 shrink-0 self-start sm:-mt-12">
                <x-user-avatar :user="$user" class="size-20 rounded-2xl text-2xl shadow-lg ring-4 ring-surface sm:size-24" />
                <span wire:loading.flex wire:target="photo" class="absolute inset-0 hidden items-center justify-center rounded-2xl bg-zinc-900/50 ring-4 ring-surface">
                    <svg class="size-6 animate-spin text-white" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-opacity=".3" stroke-width="3"/><path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round"/></svg>
                </span>
                <label class="absolute -bottom-1.5 -right-1.5 flex size-9 cursor-pointer items-center justify-center rounded-full bg-surface text-zinc-700 shadow-md ring-1 ring-zinc-900/10 transition hover:bg-brand hover:text-white" title="{{ $photoUrl ? 'Change photo' : 'Upload photo' }}">
                    <span class="sr-only">{{ $photoUrl ? 'Change photo' : 'Upload photo' }}</span>
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-4"><path d="M14.5 4h-5L7 7H4a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-3l-2.5-3z"/><circle cx="12" cy="13" r="3"/></svg>
                    <input type="file" accept="image/*" @change="pick($event)" class="sr-only">
                </label>
            </div>
            <div class="min-w-0 flex-1 sm:pt-4">
                <div class="flex flex-wrap items-center gap-2">
                    <h2 class="truncate text-lg font-semibold text-zinc-900">{{ $user->name }}</h2>
                    <span class="rounded-full bg-brand/10 px-2.5 py-0.5 text-xs font-semibold text-brand">{{ $roleName }}</span>
                </div>
                <p class="truncate text-sm text-zinc-500">{{ $user->email }}</p>
                @error('photo') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                <p x-show="error" x-cloak x-text="error" class="mt-1 text-xs text-red-600"></p>
            </div>
            <p class="text-xs text-zinc-400 sm:pt-5">
                {{ $user->joining_date ? 'Joined '.$user->joining_date->format('d M Y') : 'Member since '.$user->created_at->format('M Y') }}
            </p>
        </div>

        {{-- Cropper: a frosted bottom sheet on phones, a centred glass dialog from sm up. --}}
        <template x-teleport="body">
            <div x-show="open" x-cloak @keydown.escape.window="open && ! saving && close()" class="relative z-65" role="dialog" aria-modal="true" aria-label="Crop profile photo">
                <div x-show="open" x-transition.opacity.duration.300ms @click="! saving && close()" class="fixed inset-0 bg-zinc-900/40 backdrop-blur-sm"></div>
                <div class="pointer-events-none fixed inset-x-0 bottom-0 sm:inset-0 sm:flex sm:items-center sm:justify-center sm:p-4">
                    <div
                        x-show="open"
                        x-transition:enter="transition duration-300 ease-[cubic-bezier(0.32,0.72,0,1)]"
                        x-transition:enter-start="translate-y-full sm:translate-y-4 sm:scale-95 sm:opacity-0"
                        x-transition:enter-end="translate-y-0 sm:scale-100 sm:opacity-100"
                        x-transition:leave="transition duration-250 ease-in"
                        x-transition:leave-start="translate-y-0 sm:scale-100 sm:opacity-100"
                        x-transition:leave-end="translate-y-full sm:translate-y-4 sm:scale-95 sm:opacity-0"
                        class="pointer-events-auto w-full rounded-t-[2rem] border-t border-white/60 bg-surface/85 px-5 pb-[max(1.25rem,env(safe-area-inset-bottom))] pt-3 shadow-2xl shadow-zinc-900/25 backdrop-blur-xl backdrop-saturate-150 sm:max-w-sm sm:rounded-3xl sm:border sm:p-6"
                    >
                        <div class="flex justify-center pb-2 sm:hidden"><span class="h-1.5 w-10 rounded-full bg-zinc-900/20"></span></div>
                        <h2 class="text-lg font-semibold text-zinc-900">Crop your photo</h2>
                        <p class="text-sm text-zinc-500">Drag to position · <span class="hidden sm:inline">scroll or use the slider to zoom</span><span class="sm:hidden">pinch or slide to zoom</span></p>

                        <div
                            x-ref="frame"
                            @pointerdown.prevent="down($event)"
                            @pointermove="move($event)"
                            @pointerup="up($event)"
                            @pointercancel="up($event)"
                            @wheel.prevent="wheel($event)"
                            class="relative mx-auto mt-4 aspect-square w-full max-w-72 touch-none select-none overflow-hidden rounded-2xl bg-ink-900"
                            :class="dragging ? 'cursor-grabbing' : 'cursor-grab'"
                        >
                            <template x-if="img">
                                <img
                                    :src="src"
                                    alt=""
                                    draggable="false"
                                    class="pointer-events-none absolute left-0 top-0 max-w-none origin-top-left"
                                    :style="`width: ${img.naturalWidth * scale}px; height: ${img.naturalHeight * scale}px; transform: translate(${x}px, ${y}px)`"
                                >
                            </template>
                            {{-- How it looks in round avatars, plus a rule-of-thirds grid while dragging. --}}
                            <div class="pointer-events-none absolute inset-0 rounded-full shadow-[0_0_0_999px_rgb(0_0_0/0.28)] ring-2 ring-white/80"></div>
                            <div x-show="dragging" x-transition.opacity class="pointer-events-none absolute inset-0" style="background-image: linear-gradient(to right, rgb(255 255 255 / 0.35) 1px, transparent 1px), linear-gradient(to bottom, rgb(255 255 255 / 0.35) 1px, transparent 1px); background-size: 33.333% 33.333%; background-position: -1px -1px;"></div>
                        </div>

                        <div class="mx-auto mt-4 flex max-w-72 items-center gap-3">
                            <button type="button" @click="setZoom(zoom - 0.25)" class="rounded-full p-1.5 text-zinc-500 hover:bg-zinc-900/5 hover:text-zinc-800" title="Zoom out">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" class="size-4"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3M8 11h6"/></svg>
                            </button>
                            <input type="range" min="1" max="4" step="0.01" :value="zoom" @input="setZoom(parseFloat($event.target.value))" class="range-modern flex-1" :style="`--fill: ${(zoom - 1) / 3 * 100}%`" aria-label="Zoom">
                            <button type="button" @click="setZoom(zoom + 0.25)" class="rounded-full p-1.5 text-zinc-500 hover:bg-zinc-900/5 hover:text-zinc-800" title="Zoom in">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" class="size-4"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3M8 11h6M11 8v6"/></svg>
                            </button>
                        </div>
                        <div class="mt-1 text-center">
                            <button type="button" @click="fit()" class="text-xs font-medium text-zinc-500 hover:text-brand hover:underline">Reset</button>
                        </div>

                        <div class="mt-4 flex flex-col gap-2.5 sm:flex-row-reverse">
                            <button type="button" @click="save()" :disabled="saving" class="flex flex-1 items-center justify-center rounded-xl bg-brand px-4 py-3 text-sm font-semibold text-white shadow-sm hover:bg-brand/90 disabled:opacity-70 sm:py-2.5">
                                <span x-text="saving ? 'Saving…' : 'Save photo'">Save photo</span>
                            </button>
                            <button type="button" @click="close()" :disabled="saving" class="flex-1 rounded-xl bg-zinc-900/5 px-4 py-3 text-sm font-semibold text-zinc-700 hover:bg-zinc-900/10 disabled:opacity-50 sm:py-2.5">Cancel</button>
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </div>

    <div class="grid gap-4 sm:gap-6 lg:grid-cols-3 lg:items-start">
        <div class="space-y-4 sm:space-y-6 lg:col-span-2">
            {{-- Personal details --}}
            <form wire:submit="saveDetails" class="overflow-hidden rounded-xl border border-zinc-200 bg-surface">
                <div class="border-b border-zinc-100 px-4 py-4 sm:px-6">
                    <h2 class="text-base font-semibold text-zinc-900">Personal details</h2>
                    <p class="text-sm text-zinc-500">How you appear to your team.</p>
                </div>

                <div class="px-4 py-5 sm:px-6">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <label for="name" class="{{ $label }}">Full name</label>
                            <input wire:model="name" id="name" type="text" autocomplete="name" class="mt-1 {{ $input }}">
                            @error('name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="email" class="{{ $label }}">Email</label>
                            <input wire:model="email" id="email" type="email" autocomplete="email" class="mt-1 {{ $input }}">
                            @error('email') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="phone" class="{{ $label }}">Phone <span class="font-normal text-zinc-400">(optional)</span></label>
                            <input wire:model="phone" id="phone" type="tel" autocomplete="tel" class="mt-1 {{ $input }}">
                            @error('phone') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </div>

                <div class="flex justify-end border-t border-zinc-100 bg-zinc-50/70 px-4 py-3 sm:px-6">
                    <button type="submit" class="w-full rounded-lg bg-brand px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-brand/90 disabled:opacity-60 sm:w-auto sm:py-2" wire:loading.attr="disabled" wire:target="saveDetails">Save changes</button>
                </div>
            </form>

            {{-- Password --}}
            <form wire:submit="savePassword" x-data="{ reveal: false }" class="overflow-hidden rounded-xl border border-zinc-200 bg-surface">
                <div class="flex items-start justify-between gap-4 border-b border-zinc-100 px-4 py-4 sm:px-6">
                    <div>
                        <h2 class="text-base font-semibold text-zinc-900">Change password</h2>
                        <p class="text-sm text-zinc-500">At least 8 characters.</p>
                    </div>
                    <button type="button" @click="reveal = ! reveal" class="shrink-0 rounded-lg px-2 py-1 text-xs font-medium text-zinc-500 hover:bg-zinc-100 hover:text-zinc-800" x-text="reveal ? 'Hide' : 'Show'">Show</button>
                </div>

                <div class="grid gap-4 px-4 py-5 sm:grid-cols-2 sm:px-6">
                    <div class="sm:col-span-2">
                        <label for="current_password" class="{{ $label }}">Current password</label>
                        <input wire:model="current_password" id="current_password" :type="reveal ? 'text' : 'password'" type="password" autocomplete="current-password" class="mt-1 {{ $input }}">
                        @error('current_password') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="password" class="{{ $label }}">New password</label>
                        <input wire:model="password" id="password" :type="reveal ? 'text' : 'password'" type="password" autocomplete="new-password" class="mt-1 {{ $input }}">
                        @error('password') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="password_confirmation" class="{{ $label }}">Confirm new password</label>
                        <input wire:model="password_confirmation" id="password_confirmation" :type="reveal ? 'text' : 'password'" type="password" autocomplete="new-password" class="mt-1 {{ $input }}">
                    </div>
                </div>

                <div class="flex justify-end border-t border-zinc-100 bg-zinc-50/70 px-4 py-3 sm:px-6">
                    <button type="submit" class="w-full rounded-lg bg-brand px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-brand/90 disabled:opacity-60 sm:w-auto sm:py-2" wire:loading.attr="disabled" wire:target="savePassword">Update password</button>
                </div>
            </form>
        </div>

        {{-- Read-only work details --}}
        <div class="overflow-hidden rounded-xl border border-zinc-200 bg-surface lg:sticky lg:top-20">
            <div class="border-b border-zinc-100 px-4 py-4 sm:px-6">
                <h2 class="text-base font-semibold text-zinc-900">Work details</h2>
                <p class="flex items-center gap-1.5 text-sm text-zinc-500">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-3.5"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                    Managed by an admin
                </p>
            </div>
            <dl class="divide-y divide-zinc-100 px-4 sm:px-6">
                @foreach ([
                    'User ID' => $user->user_id ?: '—',
                    'Role' => $roleName,
                    'Department' => $user->department ?: '—',
                    'Joining date' => $user->joining_date?->format('d M Y') ?? '—',
                ] as $term => $value)
                    <div class="flex items-center justify-between gap-4 py-3">
                        <dt class="text-sm text-zinc-500">{{ $term }}</dt>
                        <dd class="truncate text-sm font-medium text-zinc-900">{{ $value }}</dd>
                    </div>
                @endforeach
                <div class="flex items-center justify-between gap-4 py-3">
                    <dt class="text-sm text-zinc-500">Status</dt>
                    <dd>
                        <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $user->isActive() ? 'bg-brand/10 text-brand' : 'bg-zinc-100 text-zinc-500' }}">
                            <span class="size-1.5 rounded-full {{ $user->isActive() ? 'bg-brand' : 'bg-zinc-400' }}"></span>
                            {{ ucfirst($user->status) }}
                        </span>
                    </dd>
                </div>
            </dl>
        </div>
    </div>
</div>
