<?php

namespace App\Policies;

use App\Enums\TaskStatus;
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
            || $task->currentAssignee()?->id === $user->id
            || $task->qa_id === $user->id;
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
     * Determine whether the user can move the task from its current status to
     * the given status. `TaskStatus::nextStatuses()` defines which moves exist
     * at all; this decides who may perform each specific one:
     *
     *   Todo -> InProgress         : the assignee starts work
     *   InProgress -> QaTesting    : the assignee submits their work for review
     *   Rejected -> QaTesting      : the assignee resubmits after fixing it
     *   QaTesting -> Rejected      : the QA/Reviewer sends it back
     *   QaTesting -> ReadyToDeploy : the QA/Reviewer approves it
     *   Rejected -> ReadyToDeploy  : the QA/Reviewer corrects a wrong rejection
     *   ReadyToDeploy -> Rejected  : the QA/Reviewer corrects a wrong approval
     *   ReadyToDeploy -> Done      : the Reporter closes it out
     */
    public function transitionStatus(User $user, Task $task, TaskStatus $to): bool
    {
        $isAssignee = $task->currentAssignee()?->id === $user->id;
        $isQa = $task->qa_id !== null && $task->qa_id === $user->id;
        $isReporter = $task->created_by === $user->id;

        return match (true) {
            $task->status === TaskStatus::Todo && $to === TaskStatus::InProgress => $isAssignee,
            $task->status === TaskStatus::InProgress && $to === TaskStatus::QaTesting => $isAssignee,
            $task->status === TaskStatus::Rejected && $to === TaskStatus::QaTesting => $isAssignee,
            $task->status === TaskStatus::QaTesting && $to === TaskStatus::Rejected => $isQa,
            $task->status === TaskStatus::QaTesting && $to === TaskStatus::ReadyToDeploy => $isQa,
            $task->status === TaskStatus::Rejected && $to === TaskStatus::ReadyToDeploy => $isQa,
            $task->status === TaskStatus::ReadyToDeploy && $to === TaskStatus::Rejected => $isQa,
            $task->status === TaskStatus::ReadyToDeploy && $to === TaskStatus::Done => $isReporter,
            default => false,
        };
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
