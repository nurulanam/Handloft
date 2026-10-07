<?php

namespace App\Livewire\Concerns;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Support\Picker;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Renderless;

/**
 * Server-side search for <x-form.picker source="…">: rather than sending every person, project or
 * task to the browser (there can be thousands), the picker asks for a short list of matches as the
 * user types. Tasks are limited to the ones the user is allowed to see.
 */
trait SearchesPickerOptions
{
    /**
     * @return list<array{value: string, label: string, hint: ?string}>
     */
    #[Renderless]
    public function pickerOptions(string $source, string $query = '', ?int $except = null): array
    {
        $query = trim(mb_substr($query, 0, 100));
        $like = '%'.addcslashes($query, '\\%_').'%';

        return match ($source) {
            'people' => User::query()
                ->when($query !== '', fn (Builder $q) => $q->where(fn (Builder $w) => $w
                    ->where('name', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('department', 'like', $like)))
                ->orderBy('name')
                ->limit(Picker::LIMIT)
                ->get(['id', 'name', 'department'])
                ->map(fn (User $user) => ['value' => (string) $user->id, 'label' => $user->name, 'hint' => $user->department])
                ->all(),

            'projects' => Project::query()
                ->when($query !== '', fn (Builder $q) => $q->where('name', 'like', $like))
                ->orderBy('name')
                ->limit(Picker::LIMIT)
                ->get(['id', 'name', 'status'])
                ->map(fn (Project $project) => ['value' => (string) $project->id, 'label' => $project->name, 'hint' => $project->status->label()])
                ->all(),

            'tasks' => Task::query()
                ->visibleTo(auth()->user())
                ->when($except, fn (Builder $q) => $q->whereKeyNot($except))
                ->when($query !== '', function (Builder $q) use ($query, $like) {
                    // "HL-42", "42" or words from the title.
                    $number = preg_match('/^(?:'.preg_quote(Task::KEY_PREFIX, '/').'-)?(\d+)$/i', $query, $m) ? (int) $m[1] : null;
                    $q->where(fn (Builder $w) => $w->where('title', 'like', $like)->when($number, fn (Builder $w) => $w->orWhereKey($number)));
                })
                ->latest('id')
                ->limit(Picker::LIMIT)
                ->get(['id', 'title', 'status'])
                ->map(fn (Task $task) => ['value' => (string) $task->id, 'label' => $task->task_key.' '.$task->title, 'hint' => $task->status->label()])
                ->all(),

            default => [],
        };
    }
}
