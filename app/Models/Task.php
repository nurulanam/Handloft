<?php

namespace App\Models;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use Database\Factories\TaskFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['title', 'description', 'created_by', 'task_category_id', 'qa_id', 'parent_task_id', 'priority', 'status', 'start_date', 'deadline', 'notes'])]
class Task extends Model
{
    /** @use HasFactory<TaskFactory> */
    use HasFactory;

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

    public function isOverdue(): bool
    {
        return $this->deadline !== null
            && $this->deadline->isPast()
            && $this->status !== TaskStatus::Completed
            && $this->status !== TaskStatus::Cancelled;
    }
}
