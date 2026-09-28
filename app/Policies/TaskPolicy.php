<?php

namespace App\Policies;

use App\Models\Task;
use App\Models\User;

class TaskPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Task $task): bool
    {
        return $user->can('view-all-tasks')
            || $task->created_by === $user->id
            || $task->currentAssignee()?->id === $user->id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create-task');
    }

    /**
     * Determine whether the user can reassign the task.
     */
    public function reassign(User $user, Task $task): bool
    {
        return $user->can('reassign-task');
    }

    /**
     * Determine whether the user can mark the task complete.
     */
    public function complete(User $user, Task $task): bool
    {
        return $task->currentAssignee()?->id === $user->id;
    }

    /**
     * Determine whether the user can edit task metadata (description, priority,
     * due date, QA, parent link).
     */
    public function updateMeta(User $user, Task $task): bool
    {
        return $user->can('reassign-task')
            || $task->created_by === $user->id
            || $task->currentAssignee()?->id === $user->id;
    }

    /**
     * Determine whether the user can comment on the task.
     */
    public function comment(User $user, Task $task): bool
    {
        return $this->view($user, $task);
    }
}
