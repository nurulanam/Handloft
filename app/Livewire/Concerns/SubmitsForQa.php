<?php

namespace App\Livewire\Concerns;

use App\Enums\TaskStatus;
use App\Models\Task;
use App\Services\TaskWorkflowService;
use App\Support\Duration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

/**
 * The "Submit for QA Testing" window, shared by the board and the task page. When the person
 * submitting hasn't logged any time on the task yet, the window asks for it right there (see
 * components/tasks/qa-time-log); a work timer they have running on the task is stopped and logged.
 * The using component owns `$submission_note`.
 */
trait SubmitsForQa
{
    public string $qa_log_date = '';

    public string $qa_log_hours = '0';

    public string $qa_log_minutes = '0';

    /**
     * Whether the window should offer to log time: the submitter has none on this task yet and no
     * timer running on it.
     */
    protected function needsQaTimeLog(Task $task): bool
    {
        $user = auth()->user();

        return ! $task->timers()->where('user_id', $user->id)->exists()
            && (float) $task->timeLogs()->where('user_id', $user->id)->sum('hours') <= 0;
    }

    protected function resetQaTimeLog(): void
    {
        $this->qa_log_date = now()->toDateString();
        $this->qa_log_hours = '0';
        $this->qa_log_minutes = '0';
        $this->resetErrorBag(['qa_log_date', 'qa_log_hours', 'qa_log_minutes']);
    }

    /**
     * Log the time typed into the window (if any), stop the submitter's timer on the task, then
     * submit it. Returns false when the time entered isn't valid.
     */
    protected function submitTaskForQa(Task $task, TaskWorkflowService $workflow): bool
    {
        Gate::authorize('transitionStatus', [$task, TaskStatus::QaTesting]);

        $user = auth()->user();
        $askedForTime = $this->needsQaTimeLog($task);

        $data = $this->validate([
            'submission_note' => ['nullable', 'string'],
            ...($askedForTime ? [
                'qa_log_date' => [
                    'required',
                    'date',
                    'before_or_equal:today',
                    ...($task->start_date ? ['after_or_equal:'.$task->start_date->toDateString()] : []),
                ],
                'qa_log_hours' => ['required', 'integer', 'min:0', 'max:24'],
                'qa_log_minutes' => ['required', 'integer', 'min:0', 'max:59'],
            ] : []),
        ], [
            'qa_log_date.after_or_equal' => 'Time can\'t be logged before the task\'s start date ('.optional($task->start_date)->format('d M Y').').',
        ]);

        if ($askedForTime) {
            $hours = Duration::fromParts((int) $data['qa_log_hours'], (int) $data['qa_log_minutes']);

            if ($hours > 24) {
                $this->addError('qa_log_hours', "A single day can't have more than 24 hours logged.");

                return false;
            }

            if ($hours > 0 && Gate::allows('logTime', $task)) {
                $workflow->logTime($task, $user, Carbon::parse($data['qa_log_date']), $hours);
            }
        }

        if ($timer = $task->timers()->where('user_id', $user->id)->first()) {
            $workflow->stopTimer($timer);
            $this->dispatch('timer-changed');
        }

        $workflow->submitForQa($task, $user, (float) $task->timeLogs()->sum('hours'), $data['submission_note'] ?: null);

        $this->submission_note = '';
        $this->resetQaTimeLog();

        return true;
    }
}
