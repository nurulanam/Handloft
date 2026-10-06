<?php

namespace App\Livewire\Concerns;

use App\Models\Task;
use Illuminate\Support\Str;

/**
 * Stars or unstars a task for the signed-in user and confirms it with a toast,
 * so every star button in the app behaves (and reads) the same way.
 */
trait TogglesStars
{
    protected function toggleStarFor(Task $task): bool
    {
        $starred = auth()->user()->starredTasks()->toggle($task->id)['attached'] !== [];

        // The toast's own title says "Added to / Removed from Starred"; the message names the task.
        $this->dispatch('notify', message: Str::limit($task->title, 60), type: $starred ? 'star' : 'unstar');

        return $starred;
    }
}
