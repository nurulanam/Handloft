<?php

use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
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
        }
    }

    /**
     * Clear a field's validation error as soon as the user changes it,
     * instead of leaving a stale error message on screen until re-submit.
     */
    public function updated(string $name): void
    {
        $this->resetErrorBag($name);
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

<div class="max-w-2xl space-y-6">
    <div>
        <h1 class="text-2xl font-semibold text-zinc-900">{{ $user ? 'Edit User' : 'Create User' }}</h1>
        <p class="text-sm text-zinc-500">Admin-managed account — there is no public registration.</p>
    </div>

    <form wire:submit="save" class="space-y-4 rounded-lg border border-zinc-200 bg-white p-6">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
                <label class="block text-sm font-medium text-zinc-700">Full Name</label>
                <input wire:model="name" type="text" class="mt-1 block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand-lime/40">
                @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-zinc-700">User ID</label>
                <input wire:model="user_id" type="text" class="mt-1 block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand-lime/40">
                @error('user_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
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

            <div>
                <label class="block text-sm font-medium text-zinc-700">Password {{ $user ? '(leave blank to keep current)' : '' }}</label>
                <input wire:model="password" type="password" class="mt-1 block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand-lime/40">
                @error('password') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-zinc-700">Confirm Password</label>
                <input wire:model="password_confirmation" type="password" class="mt-1 block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand-lime/40">
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

            <div class="sm:col-span-2">
                <label class="block text-sm font-medium text-zinc-700">Profile Photo</label>
                <input wire:model="photo" type="file" accept="image/*" class="mt-1 block w-full text-sm text-zinc-600">
                @error('photo') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="flex items-center justify-end gap-3 pt-2">
            <a href="{{ route('users.index') }}" wire:navigate class="text-sm font-medium text-zinc-600 hover:text-zinc-900">Cancel</a>
            <button type="submit" class="rounded-lg bg-brand px-4 py-2 text-sm font-semibold text-white hover:bg-brand/90" wire:loading.attr="disabled">
                {{ $user ? 'Save Changes' : 'Create User' }}
            </button>
        </div>
    </form>
</div>
