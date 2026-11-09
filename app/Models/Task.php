<?php

namespace App\Models;

use App\Enums\TaskPriority;
use App\Enums\TaskRepeat;
use App\Enums\TaskStatus;
use App\Events\TasksChanged;
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
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

#[Fillable(['title', 'description', 'created_by', 'task_category_id', 'project_id', 'qa_id', 'parent_task_id', 'priority', 'status', 'start_date', 'deadline', 'repeat', 'repeat_every', 'repeat_on', 'repeat_until', 'repeat_max', 'repeat_count', 'repeat_next_at', 'notes'])]
class Task extends Model
{
    /** @use HasFactory<TaskFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Prefix for the human-readable task key (e.g. "HL-42"), Jira-style.
     */
    public const KEY_PREFIX = 'HL';

    /**
     * Let open task boards know about every change. A broadcast problem (e.g. Reverb is down) must
     * never stop the task from saving, so it is reported and swallowed.
     */
    protected static function booted(): void
    {
        // After the surrounding transaction (if any) commits, so a browser that refreshes the moment
        // it hears never reads the old state.
        $announce = function (self $task): void {
            DB::afterCommit(function () use ($task) {
                try {
                    TasksChanged::dispatch($task->id);
                } catch (\Throwable $e) {
                    report($e);
                }
            });
        };

        // A repeating task that is done schedules its comeback; anything else has none.
        static::saving(function (self $task) {
            if (! $task->repeat) {
                $task->repeat_count = 0;
            }

            if (! $task->repeat || $task->status !== TaskStatus::Done) {
                $task->repeat_next_at = null;
            } elseif ($task->repeat_next_at === null || $task->isDirty(['repeat', 'repeat_every', 'repeat_on', 'repeat_until', 'repeat_max'])) {
                $task->repeat_next_at = $task->nextRepeatDate();
            }
        });

        static::saved($announce);
        static::deleted($announce);
        static::restored($announce);
    }

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
            'repeat' => TaskRepeat::class,
            'repeat_every' => 'integer',
            'repeat_on' => 'array',
            'repeat_until' => 'date',
            'repeat_next_at' => 'datetime',
        ];
    }

    /**
     * When this repeating task should next come back, counting from today; null once the series has
     * run its course (the end date passed or the repeat limit reached).
     */
    public function nextRepeatDate(): ?Carbon
    {
        if (! $this->repeat || ($this->repeat_max && $this->repeat_count >= $this->repeat_max)) {
            return null;
        }

        $next = $this->repeat->nextAfter(now(), $this->repeat_every ?: 1, $this->repeat_on);

        return $this->repeat_until && $next->gt($this->repeat_until) ? null : $next;
    }

    /**
     * "Every 2 weeks on Mon, Wed · 3 more times · until 30 Dec 2026", or null when it doesn't repeat.
     */
    protected function repeatSummary(): Attribute
    {
        return Attribute::make(get: function () {
            if (! $this->repeat) {
                return null;
            }

            $n = $this->repeat_every ?: 1;
            $text = $n === 1 ? ucfirst($this->repeat->value) : "Every {$n} ".$this->repeat->unit().'s';

            if ($this->repeat === TaskRepeat::Weekly && $this->repeat_on) {
                $text .= ' on '.collect($this->repeat_on)->sortBy(fn ($d) => ($d + 6) % 7)->map(fn ($d) => ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'][$d])->implode(', ');
            } elseif ($this->repeat === TaskRepeat::Monthly && $this->repeat_on) {
                $text .= ' on day '.$this->repeat_on[0];
            }

            if ($this->repeat_max) {
                $text .= ' · '.max($this->repeat_max - $this->repeat_count, 0).' left';
            }

            return $this->repeat_until ? $text.' · until '.$this->repeat_until->format('d M Y') : $text;
        });
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
        return $this->hasOne(WorkHistory::class)->latestOfMany();
    }

    /**
     * @return HasMany<TaskTimeLog, $this>
     */
    public function timeLogs(): HasMany
    {
        return $this->hasMany(TaskTimeLog::class)->latest('logged_date')->latest('id');
    }

    /**
     * Work timers running on this task right now (one per person working on it).
     *
     * @return HasMany<TaskTimer, $this>
     */
    public function timers(): HasMany
    {
        return $this->hasMany(TaskTimer::class);
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
