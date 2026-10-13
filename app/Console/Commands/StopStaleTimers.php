<?php

namespace App\Console\Commands;

use App\Models\TaskTimer;
use App\Services\TaskWorkflowService;
use App\Support\WorkTimer;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Stops work timers left running past Settings → Work schedule's limit (someone forgot to stop it),
 * logging the time up to the limit. Runs every five minutes from the scheduler.
 */
#[Signature('timers:stop-stale')]
#[Description('Stop work timers that have run longer than the limit in Settings')]
class StopStaleTimers extends Command
{
    public function handle(TaskWorkflowService $workflow): int
    {
        $maxHours = WorkTimer::maxHours();

        if ($maxHours === null) {
            return self::SUCCESS;
        }

        $stopped = 0;

        TaskTimer::query()
            ->where('started_at', '<=', now()->subHours($maxHours))
            ->with(['task', 'user'])
            ->each(function (TaskTimer $timer) use ($workflow, &$stopped) {
                $workflow->stopTimer($timer, automatic: true);
                $stopped++;
            });

        $this->info("Stopped {$stopped} ".str('timer')->plural($stopped).'.');

        return self::SUCCESS;
    }
}
