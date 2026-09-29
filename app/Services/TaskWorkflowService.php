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
                'status' => TaskStatus::Todo,
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
     * Submit a task for QA testing, logging the assignee's hours worked so far.
     * Used both for the initial InProgress -> QaTesting move and for a
     * Rejected -> QaTesting resubmission, which simply updates the same
     * Work History record rather than creating a second one.
     */
    public function submitForQa(Task $task, User $assignee, float $actualHours, ?string $note = null): WorkHistory
    {
        return DB::transaction(function () use ($task, $assignee, $actualHours, $note) {
            $assignment = $task->currentAssignment;
            $now = now();

            $task->update(['status' => TaskStatus::QaTesting]);

            $this->recordActivity($task, TaskActivityType::StatusChanged, $assignee, "Submitted for QA testing by {$assignee->name}", $now);

            return WorkHistory::updateOrCreate(
                ['task_id' => $task->id],
                [
                    'user_id' => $assignee->id,
                    'assigned_by' => $assignment->assigned_by,
                    'assigned_date' => $assignment->assigned_at->toDateString(),
                    'completed_date' => $now->toDateString(),
                    'completed_time' => $now,
                    'actual_hours' => $actualHours,
                    'status' => 'completed',
                    'note' => $note,
                ]
            );
        });
    }

    /**
     * Close out a task once the Reporter confirms it's done. The Work History
     * record was already created/updated when it was submitted for QA testing.
     */
    public function markDone(Task $task, User $reporter): void
    {
        DB::transaction(function () use ($task, $reporter) {
            $now = now();

            $task->update(['status' => TaskStatus::Done]);

            $this->recordActivity($task, TaskActivityType::Completed, $reporter, "Marked done by {$reporter->name}", $now);
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
