<?php

namespace App\Services;

use App\Enums\TaskActivityType;
use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkHistory;
use Illuminate\Support\Facades\DB;

class TaskWorkflowService
{
    /**
     * Create a task and its initial assignment, recording both in the activity timeline.
     *
     * @param  array<string, mixed>  $data
     */
    public function createTask(array $data, User $creator, User $assignee): Task
    {
        return DB::transaction(function () use ($data, $creator, $assignee) {
            $task = Task::create([
                ...$data,
                'created_by' => $creator->id,
            ]);

            $now = now();

            $this->recordActivity($task, TaskActivityType::Created, $creator, "Task created by {$creator->name}", $now);

            $task->assignments()->create([
                'assigned_by' => $creator->id,
                'assigned_to' => $assignee->id,
                'assigned_at' => $now,
            ]);

            $this->recordActivity($task, TaskActivityType::Assigned, $creator, "Assigned to {$assignee->name}", $now);

            return $task->fresh();
        });
    }

    /**
     * Reassign a task to a new user, preserving the full assignment history.
     */
    public function reassignTask(Task $task, User $to, User $by): void
    {
        DB::transaction(function () use ($task, $to, $by) {
            $from = $task->currentAssignee();

            $task->assignments()->create([
                'assigned_by' => $by->id,
                'assigned_to' => $to->id,
                'assigned_at' => now(),
            ]);

            $description = $from
                ? "Reassigned from {$from->name} to {$to->name}"
                : "Assigned to {$to->name}";

            $this->recordActivity($task, TaskActivityType::Reassigned, $by, $description, now());
        });
    }

    /**
     * Complete a task, locking in the current assignee's hours and creating the Work History record.
     */
    public function completeTask(Task $task, User $completer, float $actualHours, ?string $note = null): WorkHistory
    {
        return DB::transaction(function () use ($task, $completer, $actualHours, $note) {
            $assignment = $task->currentAssignment;
            $now = now();

            $task->update(['status' => TaskStatus::Completed]);

            $this->recordActivity($task, TaskActivityType::Completed, $completer, "Completed by {$completer->name}", $now);

            return WorkHistory::create([
                'task_id' => $task->id,
                'user_id' => $completer->id,
                'assigned_by' => $assignment->assigned_by,
                'assigned_date' => $assignment->assigned_at->toDateString(),
                'completed_date' => $now->toDateString(),
                'completed_time' => $now,
                'actual_hours' => $actualHours,
                'status' => 'completed',
                'note' => $note,
            ]);
        });
    }

    /**
     * Correct a completed task's reported hours, auditing who changed it and why.
     */
    public function editCompletedHours(WorkHistory $workHistory, float $newHours, User $editor, ?string $reason = null): void
    {
        activity()
            ->causedBy($editor)
            ->performedOn($workHistory)
            ->withProperties([
                'old_hours' => (string) $workHistory->actual_hours,
                'new_hours' => (string) $newHours,
                'reason' => $reason,
            ])
            ->log('Completed hours edited');

        $workHistory->update(['actual_hours' => $newHours]);
    }

    private function recordActivity(Task $task, TaskActivityType $type, User $causer, string $description, \DateTimeInterface $occurredAt): void
    {
        $task->activities()->create([
            'causer_id' => $causer->id,
            'type' => $type,
            'description' => $description,
            'occurred_at' => $occurredAt,
        ]);
    }
}
