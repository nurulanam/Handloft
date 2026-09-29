<?php

namespace App\Enums;

enum TaskStatus: string
{
    case Todo = 'todo';
    case InProgress = 'in_progress';
    case QaTesting = 'qa_testing';
    case Rejected = 'rejected';
    case ReadyToDeploy = 'ready_to_deploy';
    case Done = 'done';

    public function label(): string
    {
        return match ($this) {
            self::Todo => 'To Do',
            self::InProgress => 'In Progress',
            self::QaTesting => 'QA Testing',
            self::Rejected => 'Rejected',
            self::ReadyToDeploy => 'Ready to Deploy',
            self::Done => 'Done',
        };
    }

    /**
     * Tailwind classes for the status pill. Written as full literal class
     * strings (not interpolated) so Tailwind's build-time scanner picks them up.
     */
    public function pillClasses(): string
    {
        return match ($this) {
            self::Todo => 'bg-zinc-100 text-zinc-700',
            self::InProgress => 'bg-sky-100 text-sky-700',
            self::QaTesting => 'bg-amber-100 text-amber-700',
            self::Rejected => 'bg-red-100 text-red-700',
            self::ReadyToDeploy => 'bg-lime-100 text-lime-700',
            self::Done => 'bg-brand/10 text-brand',
        };
    }

    /**
     * How far along the workflow this status sits, for "has this task at
     * least reached X" checks (e.g. gating who may log time). Rejected sits
     * alongside QaTesting rather than after it, since a task can bounce
     * between the two — it's a sibling outcome of testing, not a further
     * stage past it.
     */
    private function rank(): int
    {
        return match ($this) {
            self::Todo => 0,
            self::InProgress => 1,
            self::QaTesting, self::Rejected => 2,
            self::ReadyToDeploy => 3,
            self::Done => 4,
        };
    }

    /**
     * Whether this status has progressed at least as far as $status in the
     * workflow (based on current status, not whether it was ever reached).
     */
    public function isAtLeast(self $status): bool
    {
        return $this->rank() >= $status->rank();
    }

    /**
     * The statuses a task in this status is allowed to move to next. This is
     * the single source of truth for the workflow's shape; TaskPolicy layers
     * on top of it to decide who may perform each specific move.
     *
     * @return array<int, self>
     */
    public function nextStatuses(): array
    {
        return match ($this) {
            self::Todo => [self::InProgress],
            self::InProgress => [self::QaTesting],
            self::QaTesting => [self::Rejected, self::ReadyToDeploy],
            // The QA/Reviewer can also flip Rejected <-> ReadyToDeploy directly,
            // to correct their own call without bouncing it back through the
            // assignee first.
            self::Rejected => [self::QaTesting, self::ReadyToDeploy],
            self::ReadyToDeploy => [self::Done, self::Rejected],
            self::Done => [],
        };
    }
}
