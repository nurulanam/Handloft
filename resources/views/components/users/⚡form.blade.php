<?php

use App\Enums\Role;
use App\Models\User;
use App\Support\Avatar;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts.app')] #[Title('User')] class extends Component
{
    use WithFileUploads;

    public ?User $user = null;

    public string $name = '';

    public string $user_id = '';

    public string $email = '';

    public string $phone = '';

    public string $password = '';

    public string $password_confirmation = '';

    public string $role = '';

    public string $department = '';

    public string $joining_date = '';

    public string $status = 'active';

    public $photo = null;

    /**
     * Whether the User ID field still tracks the auto-generated suggestion.
     * Flips to false the moment the admin types into it directly.
     */
    public bool $autoUserId = true;

    /**
     * Prefix for the auto-generated User ID (e.g. "AM-9546").
     */
    private const USER_ID_PREFIX = 'AM-';

    public function mount(?User $user = null): void
    {
        Gate::authorize($user ? 'update' : 'create', $user ?? User::class);

        if ($user) {
            $this->user = $user;
            $this->name = $user->name;
            $this->user_id = (string) $user->user_id;
            $this->email = $user->email;
            $this->phone = (string) $user->phone;
            $this->department = (string) $user->department;
            $this->joining_date = $user->joining_date?->toDateString() ?? '';
            $this->status = $user->status;
            $this->role = $user->getRoleNames()->first() ?? '';
            $this->autoUserId = false;

            return;
        }

        $this->user_id = $this->generateUniqueUserId();
    }

    /**
     * Clear a field's validation error as soon as the user changes it,
     * instead of leaving a stale error message on screen until re-submit.
     */
    public function updated(string $name): void
    {
        $this->resetErrorBag($name);
    }

    public function updatedUserId(): void
    {
        $this->autoUserId = false;
    }

    public function regenerateUserId(): void
    {
        $this->user_id = $this->generateUniqueUserId();
        $this->autoUserId = true;
        $this->resetErrorBag('user_id');
    }

    private function generateUniqueUserId(): string
    {
        do {
            $candidate = self::USER_ID_PREFIX.random_int(1000, 9999);
        } while (User::where('user_id', $candidate)->when($this->user, fn ($q) => $q->whereKeyNot($this->user->id))->exists());

        return $candidate;
    }

    public function save(): void
    {
        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'user_id' => ['required', 'string', 'max:50', Rule::unique('users', 'user_id')->ignore($this->user?->id)],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->user?->id)],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => [$this->user ? 'nullable' : 'required', 'string', 'min:8', 'confirmed'],
            'role' => ['required', Rule::in(array_column(Role::cases(), 'value'))],
            'department' => ['nullable', 'string', 'max:255'],
            'joining_date' => ['nullable', 'date'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'photo' => ['nullable', 'image', 'max:2048'],
        ], [
            'name.required' => 'Enter their full name.',
            'role.required' => 'Choose what they can do.',
            'password.required' => 'Set a password they can sign in with.',
            'password.confirmed' => 'The two passwords don\'t match.',
            'photo.max' => 'The photo can be up to 2 MB.',
        ]);

        $data['joining_date'] = $data['joining_date'] ?: null;
        $data['phone'] = $data['phone'] ?: null;
        $data['department'] = $data['department'] ?: null;

        if ($this->photo) {
            $data['profile_photo'] = $this->photo->store('profile-photos', 'public');
        }

        if (empty($data['password'])) {
            unset($data['password']);
        }

        $role = $data['role'];
        unset($data['role']);

        if ($this->user) {
            $this->user->update($data);
        } else {
            $this->user = User::create($data);
        }

        $this->user->syncRoles([$role]);

        $this->redirect(route('users.index'), navigate: true);
    }
};
?>

@php
    $editing = (bool) $user;
    $action = $editing ? 'Save changes' : 'Add member';
    $roles = [
        Role::SuperAdmin->value => ['Full access, including settings and the team.', '<path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/><path d="m9 12 2 2 4-4"/>'],
        Role::Manager->value => ['Runs projects, assigns work and sees reports.', '<path d="M16 20V4a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/><rect width="20" height="14" x="2" y="6" rx="2"/>'],
        Role::TeamMember->value => ['Works on assigned tasks and logs time.', '<path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>'],
    ];
    $photoUrl = $photo ? $photo->temporaryUrl() : ($user?->profile_photo ? Storage::url($user->profile_photo) : null);
@endphp

<div>
    <x-form.header
        :back="route('users.index')"
        back-label="Team"
        :title="$editing ? 'Edit '.$user->name : 'Add a team member'"
        :subtitle="$editing ? 'Update their details, sign-in or what they can do.' : 'Set up their profile and sign-in. They can change their photo and password later.'"
    >
        <x-slot:actions>
            <a href="{{ route('users.index') }}" wire:navigate class="btn-secondary">Cancel</a>
            <x-form.submit form="user-form">{{ $action }}</x-form.submit>
        </x-slot:actions>
    </x-form.header>

    <form id="user-form" wire:submit="save">
        <div class="grid grid-cols-1 gap-4 lg:grid-cols-3 lg:gap-6">
            {{-- Live preview + photo: first on phones, a sticky side card from lg up. --}}
            <div class="lg:order-2 lg:sticky lg:top-20 lg:self-start">
                <x-form.card>
                    <div class="flex flex-col items-center text-center">
                        <div class="relative">
                            @if ($photoUrl)
                                <img src="{{ $photoUrl }}" alt="" class="size-24 rounded-full object-cover ring-4 ring-surface shadow-md">
                            @else
                                <div class="flex size-24 items-center justify-center rounded-full bg-brand text-3xl font-semibold text-white ring-4 ring-surface shadow-md">
                                    {{ Avatar::initials($name ?: 'New Member') }}
                                </div>
                            @endif
                            <label class="absolute -bottom-0.5 -right-0.5 flex size-9 cursor-pointer items-center justify-center rounded-full bg-ink-900 text-white ring-4 ring-surface transition hover:bg-brand" title="Upload a photo">
                                <svg wire:loading.remove wire:target="photo" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-4"><path d="M13.997 4a2 2 0 0 1 1.76 1.05l.486.9A2 2 0 0 0 18.003 7H20a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V9a2 2 0 0 1 2-2h1.997a2 2 0 0 0 1.759-1.048l.489-.904A2 2 0 0 1 10.004 4z"/><circle cx="12" cy="13" r="3"/></svg>
                                <svg wire:loading wire:target="photo" class="size-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-opacity=".3" stroke-width="3"/><path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round"/></svg>
                                <input wire:model="photo" type="file" accept="image/*" class="sr-only">
                            </label>
                        </div>

                        <p class="mt-4 max-w-full truncate text-lg font-semibold text-zinc-900">{{ $name ?: 'New member' }}</p>
                        <p class="max-w-full truncate text-sm text-zinc-500">{{ $email ?: 'No email yet' }}</p>

                        <div class="mt-3 flex flex-wrap items-center justify-center gap-1.5">
                            <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium {{ $role ? 'bg-brand/10 text-brand' : 'bg-zinc-100 text-zinc-500' }}">
                                {{ $role ? Role::from($role)->label() : 'No role yet' }}
                            </span>
                            <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium {{ $status === 'active' ? 'bg-emerald-50 text-emerald-700' : 'bg-zinc-100 text-zinc-500' }}">
                                <span class="size-1.5 rounded-full {{ $status === 'active' ? 'bg-emerald-500' : 'bg-zinc-400' }}"></span>
                                {{ $status === 'active' ? 'Active' : 'Inactive' }}
                            </span>
                        </div>

                        @error('photo')
                            <p class="mt-3 text-xs font-medium text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <dl class="mt-5 divide-y divide-zinc-100 border-t border-zinc-100 text-sm">
                        <div class="flex items-center justify-between gap-3 py-2.5">
                            <dt class="text-zinc-500">User ID</dt>
                            <dd class="truncate font-mono text-zinc-900">{{ $user_id ?: '—' }}</dd>
                        </div>
                        <div class="flex items-center justify-between gap-3 py-2.5">
                            <dt class="text-zinc-500">Department</dt>
                            <dd class="truncate text-zinc-900">{{ $department ?: '—' }}</dd>
                        </div>
                        <div class="flex items-center justify-between gap-3 pt-2.5">
                            <dt class="text-zinc-500">Joined</dt>
                            <dd class="text-zinc-900">{{ $joining_date ? \Illuminate\Support\Carbon::parse($joining_date)->format('d M Y') : '—' }}</dd>
                        </div>
                    </dl>
                </x-form.card>
            </div>

            <div class="space-y-4 lg:order-1 lg:col-span-2 lg:space-y-6">
                <x-form.card title="Profile" description="Who they are and how to reach them." icon='<circle cx="12" cy="8" r="5"/><path d="M20 21a8 8 0 0 0-16 0"/>'>
                    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                        <x-form.field label="Full name" for="user-name" error="name" required class="sm:col-span-2">
                            <input id="user-name" wire:model.live.debounce.400ms="name" type="text" placeholder="e.g. Karim Hasan" autocomplete="off" @class(['field-input', 'field-input-error' => $errors->has('name')])>
                        </x-form.field>

                        <x-form.field label="Email" for="user-email" error="email" required>
                            <input id="user-email" wire:model.live.debounce.400ms="email" type="email" placeholder="name@company.com" autocomplete="off" @class(['field-input', 'field-input-error' => $errors->has('email')])>
                        </x-form.field>

                        <x-form.field label="Phone" for="user-phone" error="phone" optional>
                            <input id="user-phone" wire:model="phone" type="tel" placeholder="+880 1XXX-XXXXXX" class="field-input">
                        </x-form.field>

                        <x-form.field label="Department / team" for="user-department" error="department" optional>
                            <input id="user-department" wire:model.live.debounce.400ms="department" type="text" placeholder="e.g. Design" class="field-input">
                        </x-form.field>

                        <x-form.field label="Joining date" error="joining_date" optional>
                            <x-form.date model="joining_date" :value="$joining_date" placeholder="Not set" />
                        </x-form.field>
                    </div>
                </x-form.card>

                <x-form.card title="Sign-in" description="They can sign in with their user ID or email." icon='<rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>'>
                    <x-form.field label="User ID" for="user-id" error="user_id" required :hint="$autoUserId ? 'Generated for you. Edit it, or roll a new one.' : 'Used to sign in, alongside their email.'">
                        <x-slot:badge>
                            @if ($autoUserId)
                                <span class="rounded-full bg-brand/10 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-brand">Auto</span>
                            @endif
                        </x-slot:badge>
                        <div class="flex gap-2">
                            <input id="user-id" wire:model.live="user_id" type="text" @class(['field-input font-mono', 'field-input-error' => $errors->has('user_id')])>
                            <button type="button" wire:click="regenerateUserId" title="Generate a new ID" class="btn-secondary shrink-0 px-3">
                                <svg wire:loading.class="animate-spin" wire:target="regenerateUserId" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-4"><path fill-rule="evenodd" d="M15.312 11.424a5.5 5.5 0 0 1-9.201 2.466l-.312-.311h2.433a.75.75 0 0 0 0-1.5H3.989a.75.75 0 0 0-.75.75v4.242a.75.75 0 0 0 1.5 0v-2.43l.31.31a7 7 0 0 0 11.712-3.138.75.75 0 0 0-1.449-.39Zm1.23-3.723a.75.75 0 0 0 .219-.53V2.929a.75.75 0 0 0-1.5 0V5.36l-.31-.31A7 7 0 0 0 3.239 8.188a.75.75 0 1 0 1.448.389A5.5 5.5 0 0 1 13.89 6.11l.311.31h-2.432a.75.75 0 0 0 0 1.5h4.243a.75.75 0 0 0 .53-.219Z" clip-rule="evenodd" /></svg>
                            </button>
                        </div>
                    </x-form.field>

                    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2" x-data="{ show: false }">
                        <x-form.field label="{{ $editing ? 'New password' : 'Password' }}" for="user-password" error="password" :required="! $editing" :optional="$editing" :hint="$editing ? 'Leave blank to keep their current password.' : 'At least 8 characters.'">
                            <div class="relative">
                                <input id="user-password" wire:model="password" :type="show ? 'text' : 'password'" type="password" autocomplete="new-password" @class(['field-input pr-10', 'field-input-error' => $errors->has('password')])>
                                <button type="button" @click="show = ! show" class="absolute inset-y-0 right-0 flex w-10 items-center justify-center text-zinc-400 hover:text-zinc-700" :title="show ? 'Hide passwords' : 'Show passwords'">
                                    <svg x-show="! show" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-4"><path d="M10 12.5a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5Z" /><path fill-rule="evenodd" d="M.664 10.59a1.651 1.651 0 0 1 0-1.186A10.004 10.004 0 0 1 10 3c4.257 0 7.893 2.66 9.336 6.41.147.381.146.804 0 1.186A10.004 10.004 0 0 1 10 17c-4.257 0-7.893-2.66-9.336-6.41ZM14 10a4 4 0 1 1-8 0 4 4 0 0 1 8 0Z" clip-rule="evenodd" /></svg>
                                    <svg x-show="show" x-cloak xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-4"><path fill-rule="evenodd" d="M3.28 2.22a.75.75 0 0 0-1.06 1.06l14.5 14.5a.75.75 0 1 0 1.06-1.06l-1.745-1.745a10.029 10.029 0 0 0 3.3-4.38 1.651 1.651 0 0 0 0-1.185A10.004 10.004 0 0 0 9.999 3a9.956 9.956 0 0 0-4.744 1.194L3.28 2.22ZM7.752 6.69l1.092 1.092a2.5 2.5 0 0 1 3.374 3.373l1.091 1.092a4 4 0 0 0-5.557-5.557Z" clip-rule="evenodd" /><path d="m10.748 13.93 2.523 2.523a9.987 9.987 0 0 1-3.27.547c-4.258 0-7.894-2.66-9.337-6.41a1.651 1.651 0 0 1 0-1.186A10.007 10.007 0 0 1 2.839 6.02L6.07 9.252a4 4 0 0 0 4.678 4.678Z" /></svg>
                                </button>
                            </div>
                        </x-form.field>

                        <x-form.field label="Confirm password" for="user-password-confirmation">
                            <input id="user-password-confirmation" wire:model="password_confirmation" :type="show ? 'text' : 'password'" type="password" autocomplete="new-password" class="field-input">
                        </x-form.field>
                    </div>
                </x-form.card>

                <x-form.card title="Role & access" description="What they can see and do in {{ config('app.name') }}." icon='<path d="M2.586 17.414A2 2 0 0 0 2 18.828V21a1 1 0 0 0 1 1h3a1 1 0 0 0 1-1v-1a1 1 0 0 1 1-1h1a1 1 0 0 0 1-1v-1a1 1 0 0 1 1-1h.172a2 2 0 0 0 1.414-.586l.814-.814a6.5 6.5 0 1 0-4-4z"/><circle cx="16.5" cy="7.5" r=".5" fill="currentColor"/>'>
                    <x-form.field label="Role" error="role" required>
                        <div class="grid grid-cols-1 gap-2 sm:grid-cols-3" role="radiogroup" aria-label="Role">
                            @foreach (Role::cases() as $roleOption)
                                <label class="group cursor-pointer">
                                    <input type="radio" wire:model.live="role" value="{{ $roleOption->value }}" class="peer sr-only">
                                    <span @class([
                                        'flex h-full gap-3 rounded-xl border-2 p-3 transition sm:flex-col sm:gap-2',
                                        'border-zinc-200 hover:border-zinc-300 peer-checked:border-brand peer-checked:bg-brand/5 peer-focus-visible:ring-4 peer-focus-visible:ring-brand/10',
                                        'border-red-200' => $errors->has('role'),
                                    ])>
                                        <span class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-zinc-100 text-zinc-500 transition group-has-[:checked]:bg-brand group-has-[:checked]:text-white">
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-4">{!! $roles[$roleOption->value][1] !!}</svg>
                                        </span>
                                        <span class="min-w-0">
                                            <span class="block text-sm font-semibold text-zinc-900">{{ $roleOption->label() }}</span>
                                            <span class="mt-0.5 block text-xs text-zinc-500">{{ $roles[$roleOption->value][0] }}</span>
                                        </span>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    </x-form.field>

                    <label class="flex cursor-pointer items-center justify-between gap-4 rounded-xl bg-zinc-50 px-4 py-3">
                        <span class="min-w-0">
                            <span class="block text-sm font-medium text-zinc-900">Account active</span>
                            <span class="block text-xs text-zinc-500">Inactive members can't sign in, but their work and history stay.</span>
                        </span>
                        <span class="relative inline-flex shrink-0">
                            <input type="checkbox" class="peer sr-only" @checked($status === 'active') @change="$wire.set('status', $event.target.checked ? 'active' : 'inactive')">
                            <span class="h-7 w-12 rounded-full bg-zinc-300 transition-colors peer-checked:bg-brand peer-focus-visible:ring-4 peer-focus-visible:ring-brand/20"></span>
                            <span class="absolute left-1 top-1 size-5 rounded-full bg-white shadow-sm transition-transform peer-checked:translate-x-5"></span>
                        </span>
                    </label>
                </x-form.card>
            </div>
        </div>

        <x-form.actions :cancel="route('users.index')">{{ $action }}</x-form.actions>
    </form>
</div>
