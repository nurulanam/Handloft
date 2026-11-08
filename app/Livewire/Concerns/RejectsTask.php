<?php

namespace App\Livewire\Concerns;

use App\Enums\TaskStatus;
use App\Models\Task;
use App\Services\TaskWorkflowService;
use Illuminate\Support\Facades\Gate;

/**
 * The "Reject task" window, shared by the board and the task page: the QA / Reviewer must type why
 * the work is being sent back, and that reason goes to the assignee.
 */
trait RejectsTask
{
    public string $rejection_reason = '';

    protected function resetRejection(): void
    {
        $this->rejection_reason = '';
        $this->resetErrorBag('rejection_reason');
    }

    protected function rejectTaskWithReason(Task $task, TaskWorkflowService $workflow): bool
    {
        Gate::authorize('transitionStatus', [$task, TaskStatus::Rejected]);

        $data = $this->validate(
            ['rejection_reason' => ['required', 'string', 'max:5000']],
            ['rejection_reason.required' => 'Tell the assignee why this is being rejected.'],
        );

        $workflow->rejectTask($task, auth()->user(), trim($data['rejection_reason']));
        $this->resetRejection();

        return true;
    }
}
