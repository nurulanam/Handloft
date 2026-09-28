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

<div>
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-zinc-900">{{ $user ? 'Edit User' : 'Create User' }}</h1>
            <p class="text-sm text-zinc-500">Admin-managed account — there is no public registration.</p>
        </div>

        <div class="flex items-center gap-3">
            <a href="{{ route('users.index') }}" wire:navigate class="text-sm font-medium text-zinc-600 hover:text-zinc-900">Cancel</a>
            <button type="submit" form="user-form" class="rounded-lg bg-brand px-4 py-2 text-sm font-semibold text-white hover:bg-brand/90" wire:loading.attr="disabled">
                {{ $user ? 'Save Changes' : 'Create User' }}
            </button>
        </div>
    </div>

    <form id="user-form" wire:submit="save">
        {{-- Profile header --}}
        <div class="rounded-lg border border-zinc-200 bg-white p-6">
            <div class="flex flex-col items-center gap-3 sm:flex-row sm:items-center">
                <div class="relative shrink-0">
                    @if ($photo)
                        <img src="{{ $photo->temporaryUrl() }}" class="size-24 rounded-full object-cover">
                    @elseif ($user?->profile_photo)
                        <img src="{{ Storage::url($user->profile_photo) }}" class="size-24 rounded-full object-cover">
                    @else
                        <div class="flex size-24 items-center justify-center rounded-full bg-brand text-2xl font-semibold text-white">
                            {{ Avatar::initials($name ?: 'New User') }}
                        </div>
                    @endif

                    <label class="absolute -bottom-1 -right-1 flex size-8 cursor-pointer items-center justify-center rounded-full border-2 border-white bg-zinc-900 text-white hover:bg-zinc-700" title="Change photo">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-4">
                            <path fill-rule="evenodd" d="M10 2a.75.75 0 01.75.75v.258a33.186 33.186 0 016.668.83.75.75 0 01-.336 1.461 31.28 31.28 0 00-1.103-.232l1.702 7.545a.75.75 0 01-.387.832A4.981 4.981 0 0115 14c-.825 0-1.606-.2-2.294-.556a.75.75 0 01-.387-.832l1.77-7.849a31.743 31.743 0 00-3.339-.254v11.505a20.01 20.01 0 013.78.501.75.75 0 11-.339 1.462A18.558 18.558 0 0010 17.5c-1.442 0-2.845.165-4.191.477a.75.75 0 01-.338-1.462 20.01 20.01 0 013.779-.501V4.509c-1.129.026-2.243.112-3.34.254l1.771 7.85a.75.75 0 01-.387.83A4.981 4.981 0 015 14a4.98 4.98 0 01-2.294-.556.75.75 0 01-.387-.832L4.02 5.067c-.37.07-.738.148-1.103.232a.75.75 0 01-.336-1.462 33.186 33.186 0 016.669-.829V2.75A.75.75 0 0110 2z" clip-rule="evenodd" />
                        </svg>
                        <input wire:model="photo" type="file" accept="image/*" class="sr-only">
                    </label>
                </div>

                <div class="text-center sm:text-left">
                    <p class="text-lg font-semibold text-zinc-900">{{ $name ?: 'New User' }}</p>
                    <p class="text-sm text-zinc-500">{{ $role ? \App\Enums\Role::from($role)->label() : 'No role selected yet' }}</p>
                    <span class="mt-1 inline-block rounded-full px-2 py-0.5 text-xs font-medium {{ $status === 'active' ? 'bg-brand/10 text-brand' : 'bg-zinc-100 text-zinc-500' }}">
                        {{ ucfirst($status) }}
                    </span>
                </div>
            </div>
            @error('photo') <p class="mt-3 text-center text-sm text-red-600 sm:text-left">{{ $message }}</p> @enderror
        </div>

        <div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-3">
            {{-- Account --}}
            <div class="space-y-4 rounded-lg border border-zinc-200 bg-white p-5 lg:col-span-2">
                <h2 class="text-sm font-semibold text-zinc-900">Account</h2>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label class="block text-sm font-medium text-zinc-700">Full Name</label>
                        <input wire:model.live.debounce.400ms="name" type="text" class="mt-1 block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand-lime/40">
                        @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-zinc-700">Email</label>
                        <input wire:model="email" type="email" class="mt-1 block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand-lime/40">
                        @error('email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-zinc-700">Phone</label>
                        <input wire:model="phone" type="text" class="mt-1 block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand-lime/40">
                        @error('phone') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div></div>

                    <div>
                        <label class="block text-sm font-medium text-zinc-700">Password {{ $user ? '(leave blank to keep current)' : '' }}</label>
                        <input wire:model="password" type="password" class="mt-1 block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand-lime/40">
                        @error('password') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-zinc-700">Confirm Password</label>
                        <input wire:model="password_confirmation" type="password" class="mt-1 block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand-lime/40">
                    </div>
                </div>
            </div>

            {{-- Details --}}
            <div class="space-y-4 rounded-lg border border-zinc-200 bg-white p-5">
                <h2 class="text-sm font-semibold text-zinc-900">Details</h2>

                <div>
                    <label class="block text-sm font-medium text-zinc-700">
                        User ID
                        @if ($autoUserId)
                            <span class="ml-1 rounded-full bg-zinc-100 px-1.5 py-0.5 text-[10px] font-normal uppercase tracking-wide text-zinc-500">Auto</span>
                        @endif
                    </label>
                    <div class="mt-1 flex items-center gap-2">
                        <input wire:model.live="user_id" type="text" class="block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 font-mono text-sm text-zinc-900 focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand-lime/40">
                        <button type="button" wire:click="regenerateUserId" title="Generate a new ID" class="flex size-9 shrink-0 items-center justify-center rounded-lg border border-zinc-300 text-zinc-500 hover:bg-zinc-50 hover:text-brand">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-4">
                                <path fill-rule="evenodd" d="M15.312 11.424a5.5 5.5 0 01-9.201 2.466l-.312-.311h2.433a.75.75 0 000-1.5H3.989a.75.75 0 00-.75.75v4.242a.75.75 0 001.5 0v-2.43l.31.31a7 7 0 0011.712-3.138.75.75 0 00-1.449-.39zm1.23-3.723a.75.75 0 00.219-.53V2.929a.75.75 0 00-1.5 0V5.36l-.31-.31A7 7 0 002.239 8.188a.75.75 0 101.448.389A5.5 5.5 0 0112.888 6.11l.311.31h-2.433a.75.75 0 000 1.5h4.243a.75.75 0 00.53-.219z" clip-rule="evenodd" />
                            </svg>
                        </button>
                    </div>
                    <p class="mt-1 text-xs text-zinc-400">{{ $autoUserId ? 'Auto-generated — regenerate or edit to set your own.' : 'Used to log in alongside email.' }}</p>
                    @error('user_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-zinc-700">Role</label>
                    <select wire:model="role" class="mt-1 block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand-lime/40">
                        <option value="">Select a role</option>
                        @foreach (Role::cases() as $roleOption)
                            <option value="{{ $roleOption->value }}">{{ $roleOption->label() }}</option>
                        @endforeach
                    </select>
                    @error('role') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-zinc-700">Department/Team</label>
                    <input wire:model="department" type="text" class="mt-1 block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand-lime/40">
                    @error('department') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-zinc-700">Joining Date</label>
                    <input wire:model="joining_date" type="date" class="mt-1 block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand-lime/40">
                    @error('joining_date') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-zinc-700">Status</label>
                    <select wire:model="status" class="mt-1 block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand-lime/40">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
            </div>
        </div>
    </form>
</div>
