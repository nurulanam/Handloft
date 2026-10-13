<?php

namespace App\Policies;

use App\Enums\Role;
use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\User;
use App\Support\WorkTimer;

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
     * Determine whether the user can view the model. Task::visibleTo() is the
     * query version of this rule — keep the two in step.
     */
    public function view(User $user, Task $task): bool
    {
        return $user->can('view-all-tasks')
            || $task->created_by === $user->id
            || $task->currentAssignee()?->id === $user->id
            || $task->qa_id === $user->id
            // Whoever assigned it (e.g. a team member who logged it with someone else as Reporter).
            || $task->assignments()->where('assigned_by', $user->id)->exists();
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
        // A Super Admin can move any task to any other status (an override, see
        // TaskWorkflowService::overrideStatus()); everyone else follows the workflow.
        if ($to !== $task->status && $user->hasRole(Role::SuperAdmin->value)) {
            return true;
        }

        return $this->followWorkflow($user, $task, $to);
    }

    /**
     * Whether this move is the user's own step in the normal workflow (above), as opposed to a
     * Super Admin override.
     */
    public function followWorkflow(User $user, Task $task, TaskStatus $to): bool
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
     * Whether the user can log time on the task (by hand or with the work timer). Only someone
     * actually connected to it: the assignee doing the work, the QA/Reviewer once it reaches QA
     * Testing, or the Reporter once it's Ready to Deploy. There's no permission-based override: an
     * uninvolved Manager/Admin has no reason to log hours on work they're not party to.
     */
    public function logTime(User $user, Task $task): bool
    {
        return match (true) {
            $task->isAssignedTo($user) => true,
            $task->isReviewedBy($user) => $task->status->isAtLeast(TaskStatus::QaTesting),
            $task->isReportedBy($user) => $task->status->isAtLeast(TaskStatus::ReadyToDeploy),
            default => false,
        };
    }

    /**
     * Whether the user can start the work timer on the task: they can log time on it, the timer is
     * switched on in Settings, and the task isn't finished.
     */
    public function startTimer(User $user, Task $task): bool
    {
        return WorkTimer::enabled()
            && $task->status !== TaskStatus::Done
            && $this->logTime($user, $task);
    }

    /**
     * Determine whether the user can comment on the task.
     */
    public function comment(User $user, Task $task): bool
    {
        return $this->view($user, $task);
    }
}
