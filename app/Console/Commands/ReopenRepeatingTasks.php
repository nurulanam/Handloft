<?php

namespace App\Console\Commands;

use App\Enums\TaskActivityType;
use App\Enums\TaskStatus;
use App\Models\Task;
use App\Notifications\TaskRepeated;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/** Puts finished repeating tasks back to To Do once their next run is due. Runs hourly from the scheduler. */
#[Signature('tasks:reopen-repeating')]
#[Description('Move finished repeating tasks back to To Do when due')]
class ReopenRepeatingTasks extends Command
{
    public function handle(): int
    {
        $count = 0;

        Task::query()
            ->where('status', TaskStatus::Done)
            ->whereNotNull('repeat')
            ->where('repeat_next_at', '<=', now())
            ->each(function (Task $task) use (&$count) {
                // One broken task (or a failed email) must not stop the rest, nor leave a task half-reopened.
                try {
                    DB::transaction(function () use ($task) {
                        // Shift the working window so its first day lands on the run date, keeping its length.
                        $shift = ($task->start_date ?? $task->deadline)?->diffInDays($task->repeat_next_at->startOfDay(), false) ?? 0;

                        $task->update([
                            'status' => TaskStatus::Todo,
                            'repeat_next_at' => null,
                            'repeat_count' => $task->repeat_count + 1,
                            'start_date' => $task->start_date?->addDays($shift),
                            'deadline' => $task->deadline?->addDays($shift),
                        ]);

                        $task->activities()->create([
                            'type' => TaskActivityType::StatusChanged,
                            'description' => "Repeated ({$task->repeat->value}): moved back to To Do",
                            'occurred_at' => now(),
                        ]);
                    });
                } catch (\Throwable $e) {
                    report($e);

                    return;
                }

                $count++;

                try {
                    $task->currentAssignee()?->notify(new TaskRepeated($task));
                } catch (\Throwable $e) {
                    report($e);
                }
            });

        $this->info("Reopened {$count} ".str('task')->plural($count).'.');

        return self::SUCCESS;
    }
}
