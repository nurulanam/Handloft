<?php

use App\Models\Client;
use App\Models\Project;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * The clients the team works for: add, rename and remove them, and jump to everything for one.
 * Anyone can look; managing follows the same permission as managing projects.
 */
new #[Layout('layouts.app')] #[Title('Clients')] class extends Component
{
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';

    public string $newName = '';

    public ?int $editingId = null;

    public string $editingName = '';

    public function mount(): void
    {
        Gate::authorize('viewAny', Project::class);
    }

    public function updated(string $name): void
    {
        $this->resetErrorBag($name);

        if ($name === 'search') {
            $this->resetPage();
        }
    }

    private function authorizeManage(): void
    {
        abort_unless(auth()->user()->can('manage-projects'), 403);
    }

    public function add(): void
    {
        $this->authorizeManage();

        $data = $this->validate(
            ['newName' => ['required', 'string', 'max:255']],
            ['newName.required' => 'Type the client\'s name.'],
        );

        Client::named($data['newName']);

        $this->newName = '';
        $this->dispatch('notify', message: 'Client added.', type: 'success');
    }

    public function startEdit(int $clientId): void
    {
        $this->authorizeManage();

        $client = Client::findOrFail($clientId);
        $this->editingId = $client->id;
        $this->editingName = $client->name;
        $this->resetErrorBag();
    }

    public function cancelEdit(): void
    {
        $this->editingId = null;
        $this->editingName = '';
        $this->resetErrorBag();
    }

    public function saveEdit(): void
    {
        $this->authorizeManage();

        $client = Client::findOrFail($this->editingId);

        $data = $this->validate(
            ['editingName' => ['required', 'string', 'max:255', Rule::unique('clients', 'name')->ignore($client->id)]],
            ['editingName.required' => 'A client needs a name.', 'editingName.unique' => 'There is already a client with that name.'],
        );

        $client->update(['name' => trim($data['editingName'])]);

        $this->cancelEdit();
        $this->dispatch('notify', message: 'Client renamed.', type: 'success');
    }

    /**
     * Remove a client. Their projects stay, just without a client.
     */
    public function delete(int $clientId): void
    {
        $this->authorizeManage();

        $client = Client::findOrFail($clientId);

        $client->projects()->update(['client_id' => null]);
        $client->delete();

        $this->dispatch('notify', message: 'Client removed. Their projects were kept.', type: 'success');
    }

    public function with(): array
    {
        $search = trim($this->search);

        return [
            'clients' => Client::query()
                ->withCount(['projects', 'tasks'])
                ->when($search !== '', fn (Builder $q) => $q->where('name', 'like', '%'.addcslashes($search, '\\%_').'%'))
                ->orderBy('name')
                ->paginate(15),
            'canManage' => auth()->user()->can('manage-projects'),
        ];
    }
};
?>

<div class="space-y-5">
    <div>
        <h1 class="text-xl font-semibold text-zinc-900 sm:text-2xl">Clients</h1>
        <p class="hidden text-sm text-zinc-500 sm:block">The customers you do work for. Give a project a client, then see all their tasks in one place.</p>
    </div>

    <div class="flex flex-col gap-3 rounded-xl border border-zinc-200 bg-surface p-3 sm:flex-row sm:items-start">
        <div class="relative flex-1">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-zinc-400"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
            <input wire:model.live.debounce.300ms="search" type="search" placeholder="Search clients…" class="block w-full rounded-lg border border-zinc-300 bg-zinc-50/60 py-2 pl-9 pr-3 text-sm text-zinc-900 placeholder:text-zinc-400 focus:border-brand focus:bg-surface focus:outline-none focus:ring-2 focus:ring-brand-lime/40">
        </div>

        @if ($canManage)
            <form wire:submit="add" class="sm:w-96">
                <div class="flex gap-2">
                    <input wire:model="newName" type="text" maxlength="255" placeholder="New client name" autocomplete="off" class="field-input">
                    <button type="submit" class="btn-primary shrink-0 px-4 py-2" wire:loading.attr="disabled" wire:target="add">Add</button>
                </div>
                @error('newName') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </form>
        @endif
    </div>

    @if ($clients->isEmpty())
        <div class="rounded-xl border border-dashed border-zinc-300 bg-surface px-4 py-14 text-center">
            <span class="mx-auto flex size-12 items-center justify-center rounded-full bg-zinc-100 text-zinc-400"><x-nav-icon name="clients" class="size-5" /></span>
            <p class="mt-3 text-sm font-medium text-zinc-900">{{ trim($search) !== '' ? 'No clients match that search' : 'No clients yet' }}</p>
            @if (trim($search) === '' && $canManage)
                <p class="mt-1 text-sm text-zinc-500">Add the first one above.</p>
            @endif
        </div>
    @else
        <div class="divide-y divide-zinc-100 overflow-hidden rounded-xl border border-zinc-200 bg-surface">
            @foreach ($clients as $client)
                <div wire:key="client-{{ $client->id }}" class="flex flex-wrap items-center gap-x-4 gap-y-2 px-4 py-3 sm:px-5">
                    @if ($editingId === $client->id)
                        <form wire:submit="saveEdit" class="flex min-w-0 flex-1 flex-wrap items-start gap-2">
                            <div class="min-w-48 flex-1">
                                <input wire:model="editingName" type="text" maxlength="255" autocomplete="off" autofocus wire:keydown.escape="cancelEdit" class="field-input">
                                @error('editingName') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <button type="submit" class="btn-primary px-4 py-2">Save</button>
                            <button type="button" wire:click="cancelEdit" class="btn-secondary px-4 py-2">Cancel</button>
                        </form>
                    @else
                        <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-brand/10 text-sm font-bold text-brand">{{ mb_strtoupper(mb_substr($client->name, 0, 1)) }}</span>
                        <div class="min-w-0 flex-1">
                            <a href="{{ route('tasks.index', ['view' => 'list', 'client' => $client->id]) }}" wire:navigate class="block truncate text-sm font-semibold text-zinc-900 hover:text-brand">{{ $client->name }}</a>
                            <span class="block text-xs text-zinc-500">{{ $client->projects_count }} {{ \Illuminate\Support\Str::plural('project', $client->projects_count) }} · {{ $client->tasks_count }} {{ \Illuminate\Support\Str::plural('task', $client->tasks_count) }}</span>
                        </div>

                        <div class="flex shrink-0 items-center gap-1">
                            <a href="{{ route('tasks.index', ['view' => 'list', 'client' => $client->id]) }}" wire:navigate class="rounded-lg px-2.5 py-1.5 text-xs font-semibold text-brand transition-colors hover:bg-brand/5">View tasks</a>
                            @if ($canManage)
                                <button type="button" wire:click="startEdit({{ $client->id }})" class="rounded-lg px-2.5 py-1.5 text-xs font-semibold text-zinc-600 transition-colors hover:bg-zinc-100">Rename</button>
                                <button type="button" wire:click="delete({{ $client->id }})" wire:confirm="Remove {{ $client->name }}? Their projects and tasks are kept, just without a client." class="rounded-lg px-2.5 py-1.5 text-xs font-semibold text-red-600 transition-colors hover:bg-red-50">Remove</button>
                            @endif
                        </div>
                    @endif
                </div>
            @endforeach
        </div>

        {{ $clients->links() }}
    @endif
</div>
