<?php

namespace App\Services;

use App\Enums\TaskActivityType;
use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\TaskTimeLog;
use App\Models\User;
use App\Models\WorkHistory;
use App\Notifications\TaskAssigned;
use App\Notifications\TaskReadyToDeploy;
use App\Notifications\TaskRejected;
use App\Notifications\TaskSubmittedForQa;
use App\Support\Duration;
use Carbon\CarbonInterface;
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
                // The Reporter can be set explicitly (e.g. logging a task on
                // someone else's behalf) via $data['created_by']; otherwise
                // it defaults to whoever is actually performing the create.
                'created_by' => $data['created_by'] ?? $creator->id,
            ]);

            $now = now();

            $this->recordActivity($task, TaskActivityType::Created, $creator, "Task created by {$creator->name}", $now);

            $task->assignments()->create([
                'assigned_by' => $creator->id,
                'assigned_to' => $assignee->id,
                'assigned_at' => $now,
            ]);

            $this->recordActivity($task, TaskActivityType::Assigned, $creator, "Assigned to {$assignee->name}", $now);

            $task = $task->fresh();

            $this->notifyUnlessSelf($assignee, $creator, new TaskAssigned($task, $creator));

            return $task;
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

            $this->notifyUnlessSelf($to, $by, new TaskAssigned($task, $by));
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

            if ($task->qa) {
                $this->notifyUnlessSelf($task->qa, $assignee, new TaskSubmittedForQa($task, $assignee));
            }

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

    /**
     * Log (or update) one day's worked hours on a task. A person gets at most
     * one entry per day — logging the same day again updates that entry
     * rather than adding a second one, so the running total stays correct.
     */
    public function logTime(Task $task, User $user, CarbonInterface $date, float $hours, ?string $note = null): TaskTimeLog
    {
        // Not a plain updateOrCreate(): the `date` cast stores `logged_date`
        // as a full datetime string, so a raw string match condition would
        // never find the existing row and would hit the unique constraint on
        // the next insert attempt instead. whereDate() compares by calendar
        // day regardless of the stored format.
        $log = TaskTimeLog::query()
            ->where('task_id', $task->id)
            ->where('user_id', $user->id)
            ->whereDate('logged_date', $date->toDateString())
            ->first();

        $isNewEntry = $log === null;

        $log ??= new TaskTimeLog([
            'task_id' => $task->id,
            'user_id' => $user->id,
            'logged_date' => $date->toDateString(),
        ]);

        $log->fill(['hours' => $hours, 'note' => $note])->save();

        // Only the first time someone logs a given day earns a timeline
        // entry — re-submitting the same day again is a correction, not a
        // new event, so it shouldn't keep stacking "logged Xh on Y" lines.
        if ($isNewEntry) {
            $this->recordActivity($task, TaskActivityType::MetaUpdated, $user, "{$user->name} logged ".Duration::forHumans($hours)." on {$date->format('d M Y')}", now());
        }

        return $log;
    }

    public function deleteTimeLog(TaskTimeLog $timeLog, User $actor): void
    {
        $task = $timeLog->task;

        $this->recordActivity($task, TaskActivityType::MetaUpdated, $actor, "{$actor->name} removed a time entry for {$timeLog->logged_date->format('d M Y')}", now());

        $timeLog->delete();
    }

    /**
     * Correct a daily time-log entry's hours, auditing who changed it and why
     * (e.g. a Manager fixing an obviously wrong entry from Work History).
     */
    public function editTimeLog(TaskTimeLog $timeLog, float $newHours, User $editor, ?string $reason = null): void
    {
        activity()
            ->causedBy($editor)
            ->performedOn($timeLog)
            ->withProperties([
                'old_hours' => (string) $timeLog->hours,
                'new_hours' => (string) $newHours,
                'reason' => $reason,
            ])
            ->log('Time log hours edited');

        $timeLog->update(['hours' => $newHours]);

        $this->recordActivity(
            $timeLog->task,
            TaskActivityType::MetaUpdated,
            $editor,
            "{$editor->name} updated the time log for {$timeLog->logged_date->format('d M Y')} to ".Duration::forHumans($newHours),
            now()
        );
    }

    /**
     * Notify whoever needs to know about a Rejected / Ready to Deploy move.
     * Called from the Livewire components that perform these status changes
     * directly (board drag, status dropdown) rather than through a shared
     * "change status" service method, so each keeps its own existing
     * activity-logging wording — this only adds the notification side effect.
     */
    public function notifyStatusChange(Task $task, TaskStatus $to, User $actor): void
    {
        match ($to) {
            TaskStatus::Rejected => $this->notifyUnlessSelf($task->currentAssignee(), $actor, new TaskRejected($task, $actor)),
            TaskStatus::ReadyToDeploy => $this->notifyUnlessSelf($task->creator, $actor, new TaskReadyToDeploy($task, $actor)),
            default => null,
        };
    }

    /**
     * Skip notifying someone about their own action (e.g. a self-assigned
     * task, or a QA/Reviewer who is also the task's Reporter).
     */
    private function notifyUnlessSelf(?User $recipient, User $actor, object $notification): void
    {
        if ($recipient && $recipient->isNot($actor)) {
            $recipient->notify($notification);
        }
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
