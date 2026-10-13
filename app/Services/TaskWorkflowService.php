<?php

namespace App\Services;

use App\Enums\TaskActivityType;
use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\TaskTimeLog;
use App\Models\TaskTimer;
use App\Models\User;
use App\Models\WorkHistory;
use App\Notifications\TaskAssigned;
use App\Notifications\TaskReadyToDeploy;
use App\Notifications\TaskRejected;
use App\Notifications\TaskSubmittedForQa;
use App\Support\DeferredNotification;
use App\Support\Duration;
use App\Support\WorkTimer;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
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
        $this->stopTimersOn($task);

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
     * Add a time entry. Every log is its own entry, so a person can log the same task several times a
     * day (e.g. a morning and an afternoon session) and the day's time is the sum of them.
     */
    public function logTime(Task $task, User $user, CarbonInterface $date, float $hours, ?string $note = null): TaskTimeLog
    {
        $log = TaskTimeLog::create([
            'task_id' => $task->id,
            'user_id' => $user->id,
            'logged_date' => $date->toDateString(),
            'hours' => $hours,
            'note' => $note,
        ]);

        $this->recordActivity($task, TaskActivityType::MetaUpdated, $user, "{$user->name} logged ".Duration::forHumans($hours)." on {$date->format('d M Y')}", now());

        return $log;
    }

    /**
     * Hours a person has logged on a task for one day, across all of that day's entries (optionally
     * leaving one entry out, when it's the one being corrected).
     */
    public function hoursLoggedOn(Task $task, User $user, CarbonInterface $date, ?int $exceptLogId = null): float
    {
        return (float) TaskTimeLog::query()
            ->where('task_id', $task->id)
            ->where('user_id', $user->id)
            ->whereDate('logged_date', $date->toDateString())
            ->when($exceptLogId, fn ($q) => $q->whereKeyNot($exceptLogId))
            ->sum('hours');
    }

    /**
     * Add one work-timer session as its own entry. A day never goes past 24 hours
     * for one person on one task; returns the hours actually added.
     */
    public function addTime(Task $task, User $user, CarbonInterface $date, float $hours, ?string $note = null): float
    {
        $added = round(max(0, min($hours, 24 - $this->hoursLoggedOn($task, $user, $date))), 2);

        if ($added <= 0) {
            return 0.0;
        }

        TaskTimeLog::create([
            'task_id' => $task->id,
            'user_id' => $user->id,
            'logged_date' => $date->toDateString(),
            'hours' => $added,
            'note' => $note,
        ]);

        return $added;
    }

    /**
     * Mark a task as being worked on right now. A person has one timer at a time, so a timer running
     * on another task is stopped (and logged) first. When the assignee starts work on a To Do task,
     * it moves to In Progress, as dragging it there would.
     */
    public function startTimer(Task $task, User $user): TaskTimer
    {
        $running = $user->runningTimer()->first();

        if ($running?->task_id === $task->id) {
            return $running;
        }

        if ($running) {
            $this->stopTimer($running);
        }

        return DB::transaction(function () use ($task, $user) {
            $timer = TaskTimer::create(['task_id' => $task->id, 'user_id' => $user->id, 'started_at' => now()]);

            if ($task->status === TaskStatus::Todo && $task->isAssignedTo($user)) {
                $task->update(['status' => TaskStatus::InProgress]);
                $this->recordActivity($task, TaskActivityType::StatusChanged, $user, "{$user->name} started work, moved this to In Progress", now());
            }

            return $timer;
        });
    }

    /**
     * Stop a work timer and log the time it ran, split across calendar days when it ran past midnight.
     * A timer is never counted beyond Settings → Work schedule's limit (a forgotten timer), and under a
     * minute isn't logged. Returns the hours logged; 0 when someone else already stopped it.
     */
    public function stopTimer(TaskTimer $timer, bool $automatic = false): float
    {
        return DB::transaction(function () use ($timer, $automatic) {
            // Whoever deletes the row logs the time, so a double click or the scheduled clean-up
            // racing a manual stop can't log the same session twice.
            if (TaskTimer::query()->whereKey($timer->id)->delete() === 0) {
                return 0.0;
            }

            $task = $timer->task;
            $user = $timer->user;
            $start = Carbon::parse($timer->started_at);
            $end = now();
            $maxHours = WorkTimer::maxHours();
            $capped = $maxHours !== null && $start->copy()->addHours($maxHours)->lessThan($end);

            if ($capped) {
                $end = $start->copy()->addHours($maxHours);
            }

            $logged = 0.0;
            $cursor = $start->copy();

            while ($cursor->lessThan($end)) {
                $segmentEnd = $cursor->copy()->addDay()->startOfDay()->min($end);
                $minutes = intdiv((int) $cursor->diffInSeconds($segmentEnd, true), 60);

                if ($minutes > 0) {
                    $logged += $this->addTime($task, $user, $cursor, round($minutes / 60, 2), 'Work timer');
                }

                $cursor = $segmentEnd;
            }

            if ($logged > 0) {
                $suffix = match (true) {
                    $capped => " (stopped automatically after {$maxHours}h)",
                    $automatic => ' (stopped automatically)',
                    default => '',
                };

                $this->recordActivity($task, TaskActivityType::MetaUpdated, $user, "{$user->name} logged ".Duration::forHumans($logged)." with the work timer{$suffix}", now());
            }

            return $logged;
        });
    }

    /**
     * Stop every timer running on a task (it's finished, so nobody is working on it any more).
     */
    public function stopTimersOn(Task $task): void
    {
        $task->timers()->get()->each(fn (TaskTimer $timer) => $this->stopTimer($timer, automatic: true));
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
     * A Super Admin moving a task straight to any status, outside the normal workflow (e.g. from Done
     * back to In Progress, or past QA when there's no reviewer). It's recorded on the timeline as an
     * override, and the people the target status concerns are notified as usual.
     */
    public function overrideStatus(Task $task, TaskStatus $to, User $admin): void
    {
        $from = $task->status;

        if ($from === $to) {
            return;
        }

        if ($to === TaskStatus::Done) {
            $this->stopTimersOn($task);
        }

        DB::transaction(function () use ($task, $from, $to, $admin) {
            $task->update(['status' => $to]);

            $this->recordActivity(
                $task,
                $to === TaskStatus::Done ? TaskActivityType::Completed : TaskActivityType::StatusChanged,
                $admin,
                "{$admin->name} moved this from {$from->label()} to {$to->label()} (Super Admin)",
                now(),
            );
        });

        if ($to === TaskStatus::QaTesting && $task->qa) {
            $this->notifyUnlessSelf($task->qa, $admin, new TaskSubmittedForQa($task, $admin));
        }

        $this->notifyStatusChange($task, $to, $admin);
    }

    /**
     * Skip notifying someone about their own action (e.g. a self-assigned
     * task, or a QA/Reviewer who is also the task's Reporter).
     */
    private function notifyUnlessSelf(?User $recipient, User $actor, object $notification): void
    {
        if ($recipient && $recipient->isNot($actor)) {
            DeferredNotification::send($recipient, $notification);
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
