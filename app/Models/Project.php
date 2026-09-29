<?php

namespace App\Models;

use App\Enums\ProjectStatus;
use App\Enums\TaskStatus;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'description', 'status', 'created_by', 'coordinator_id', 'start_date', 'deadline'])]
class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ProjectStatus::class,
            'start_date' => 'date',
            'deadline' => 'date',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function coordinator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'coordinator_id');
    }

    /**
     * All tasks in this project, including subtasks.
     *
     * @return HasMany<Task, $this>
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /**
     * Only the top-level tasks (excludes subtasks) — used for board/progress display.
     *
     * @return HasMany<Task, $this>
     */
    public function topLevelTasks(): HasMany
    {
        return $this->tasks()->whereNull('parent_task_id');
    }

    /**
     * Reads the eager-loaded `task_count` from `withCount()` when available
     * (e.g. on the projects list, to avoid an N+1 query per project) and
     * falls back to a live count otherwise (e.g. on a single project's page).
     */
    protected function taskCount(): Attribute
    {
        return Attribute::make(get: fn () => $this->attributes['task_count'] ?? $this->topLevelTasks()->count());
    }

    protected function completedCount(): Attribute
    {
        return Attribute::make(get: fn () => $this->attributes['completed_count'] ?? $this->topLevelTasks()->where('status', TaskStatus::Done)->count());
    }

    protected function progressPercent(): Attribute
    {
        return Attribute::make(get: function () {
            $total = $this->taskCount;

            return $total > 0 ? (int) round($this->completedCount / $total * 100) : 0;
        });
    }
}
