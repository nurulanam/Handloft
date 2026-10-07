<?php

namespace App\Models;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use Database\Factories\TaskFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['title', 'description', 'created_by', 'task_category_id', 'project_id', 'qa_id', 'parent_task_id', 'priority', 'status', 'start_date', 'deadline', 'notes'])]
class Task extends Model
{
    /** @use HasFactory<TaskFactory> */
    use HasFactory;

    /**
     * Prefix for the human-readable task key (e.g. "HL-42"), Jira-style.
     */
    public const KEY_PREFIX = 'HL';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'priority' => TaskPriority::class,
            'status' => TaskStatus::class,
            'start_date' => 'date',
            'deadline' => 'date',
        ];
    }

    /**
     * A stable, unique, human-readable identifier (e.g. "HL-42"), Jira-style.
     * Derived from the primary key rather than a separate counter, so it's
     * always unique with no extra schema or race conditions to manage.
     */
    protected function taskKey(): Attribute
    {
        return Attribute::make(get: fn () => self::KEY_PREFIX.'-'.$this->id);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<TaskCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(TaskCategory::class, 'task_category_id');
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return HasMany<TaskAssignment, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(TaskAssignment::class);
    }

    /**
     * @return HasOne<TaskAssignment, $this>
     */
    public function currentAssignment(): HasOne
    {
        return $this->hasOne(TaskAssignment::class)->latestOfMany(['assigned_at', 'id']);
    }

    public function currentAssignee(): ?User
    {
        return $this->currentAssignment?->assignedTo;
    }

    /**
     * Tasks the user may see (the query twin of TaskPolicy::view()): everything
     * for a view-all-tasks holder, otherwise tasks they report, are assigned,
     * review, or have assigned to someone — so whoever creates a task can still
     * follow it after handing it off, even when they named someone else as Reporter.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function visibleTo(Builder $query, User $user): void
    {
        if ($user->can('view-all-tasks')) {
            return;
        }

        $query->where(function (Builder $q) use ($user) {
            $q->where('created_by', $user->id)
                ->orWhere('qa_id', $user->id)
                ->orWhereHas('currentAssignment', fn (Builder $a) => $a->where('assigned_to', $user->id))
                ->orWhereHas('assignments', fn (Builder $a) => $a->where('assigned_by', $user->id));
        });
    }

    /**
     * @return HasMany<TaskActivity, $this>
     */
    public function activities(): HasMany
    {
        return $this->hasMany(TaskActivity::class)->orderBy('occurred_at');
    }

    /**
     * @return HasMany<TaskAttachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(TaskAttachment::class);
    }

    /**
     * @return HasOne<WorkHistory, $this>
     */
    public function workHistory(): HasOne
    {
        return $this->hasOne(WorkHistory::class);
    }

    /**
     * @return HasMany<TaskTimeLog, $this>
     */
    public function timeLogs(): HasMany
    {
        return $this->hasMany(TaskTimeLog::class)->latest('logged_date');
    }

    /**
     * Sum of every daily time-log entry, computed from the loaded relation
     * when available so listing pages that eager-load `timeLogs` don't fire
     * an extra query per task.
     */
    protected function totalLoggedHours(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->relationLoaded('timeLogs')
                ? (float) $this->timeLogs->sum('hours')
                : (float) $this->timeLogs()->sum('hours'),
        );
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function qa(): BelongsTo
    {
        return $this->belongsTo(User::class, 'qa_id');
    }

    /**
     * @return BelongsTo<Task, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'parent_task_id');
    }

    /**
     * @return HasMany<Task, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(Task::class, 'parent_task_id');
    }

    /**
     * @return HasMany<TaskComment, $this>
     */
    public function comments(): HasMany
    {
        return $this->hasMany(TaskComment::class)->oldest();
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function starredBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'task_stars')->withTimestamps();
    }

    public function isOverdue(): bool
    {
        return $this->deadline !== null
            && $this->deadline->isPast()
            && $this->status !== TaskStatus::Done;
    }

    public function isAssignedTo(User $user): bool
    {
        return $this->currentAssignee()?->id === $user->id;
    }

    public function isReportedBy(User $user): bool
    {
        return $this->created_by === $user->id;
    }

    public function isReviewedBy(User $user): bool
    {
        return $this->qa_id !== null && $this->qa_id === $user->id;
    }

    /**
     * Whether the user holds any role (assignee, reporter, or QA/reviewer) on
     * this task — used to highlight "your" tasks on a board everyone can see.
     */
    public function isConnectedTo(User $user): bool
    {
        return $this->isAssignedTo($user) || $this->isReportedBy($user) || $this->isReviewedBy($user);
    }
}
