<?php

namespace App\Livewire\Concerns;

use App\Enums\TaskActivityType;
use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\User;
use App\Services\TaskWorkflowService;
use App\Support\Duration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * The "Submit for QA Testing" window, shared by the board and the task page. When the person
 * submitting hasn't logged any time on the task yet, the window asks for it right there (see
 * components/tasks/qa-time-log); a work timer they have running on the task is stopped and logged.
 * The window also asks who will review the work when nobody suitable is on the task yet, so a
 * submission can't be blocked by a missing QA / Reviewer.
 * The using component owns `$submission_note`.
 */
trait SubmitsForQa
{
    public string $qa_log_date = '';

    public string $qa_log_hours = '0';

    public string $qa_log_minutes = '0';

    /**
     * QA / Reviewer chosen in the window. Only used when the task has nobody suitable assigned
     * (see needsQaReviewer()); otherwise the task's existing reviewer stands.
     */
    public string $qa_reviewer_id = '';

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

    /**
     * Whether the window must ask who will review the work: nobody is assigned as QA / Reviewer yet,
     * or the person assigned is the one submitting — nobody can approve their own task.
     */
    protected function needsQaReviewer(Task $task): bool
    {
        return blank($task->qa_id) || (int) $task->qa_id === auth()->id();
    }

    protected function resetQaTimeLog(): void
    {
        $this->qa_log_date = now()->toDateString();
        $this->qa_log_hours = '0';
        $this->qa_log_minutes = '0';
        $this->resetErrorBag(['qa_log_date', 'qa_log_hours', 'qa_log_minutes']);
    }

    /**
     * Clear everything the window owns, so reopening it never shows a stale reviewer or time entry.
     */
    protected function resetQaSubmission(): void
    {
        $this->qa_reviewer_id = '';
        $this->resetErrorBag('qa_reviewer_id');
        $this->resetQaTimeLog();
    }

    /**
     * Log the time typed into the window (if any), assign the chosen QA / Reviewer when the task has
     * none, stop the submitter's timer on the task, then submit it. Returns false when what was
     * entered isn't valid.
     */
    protected function submitTaskForQa(Task $task, TaskWorkflowService $workflow): bool
    {
        Gate::authorize('transitionStatus', [$task, TaskStatus::QaTesting]);

        $user = auth()->user();
        $askedForTime = $this->needsQaTimeLog($task);
        $askedForReviewer = $this->needsQaReviewer($task);

        $data = $this->validate([
            'submission_note' => ['nullable', 'string'],
            ...($askedForReviewer ? [
                // Never the submitter: work has to be reviewed by someone else.
                'qa_reviewer_id' => ['required', 'integer', 'exists:users,id', Rule::notIn([$user->id])],
            ] : []),
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
            'qa_reviewer_id.required' => 'Choose who will review this before submitting it.',
            'qa_reviewer_id.not_in' => 'You can\'t review your own work — pick someone else.',
            'qa_log_date.after_or_equal' => 'Time can\'t be logged before the task\'s start date ('.optional($task->start_date)->format('d M Y').').',
        ]);

        if ($askedForReviewer) {
            $reviewer = User::findOrFail($data['qa_reviewer_id']);

            $task->update(['qa_id' => $reviewer->id]);
            $task->activities()->create([
                'causer_id' => $user->id,
                'type' => TaskActivityType::MetaUpdated,
                'description' => 'QA / Reviewer set to '.$reviewer->name." by {$user->name}",
                'occurred_at' => now(),
            ]);
        }

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
        $this->resetQaSubmission();

        return true;
    }
}
