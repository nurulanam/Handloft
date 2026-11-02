<?php

namespace App\Models;

use Database\Factories\TaskCommentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['task_id', 'user_id', 'body'])]
class TaskComment extends Model
{
    /** @use HasFactory<TaskCommentFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Whether the comment has been changed since it was written, so the thread
     * can say "edited" without comparing bodies.
     */
    public function isEdited(): bool
    {
        return $this->updated_at !== null && $this->updated_at->gt($this->created_at);
    }

    /**
     * @return BelongsTo<Task, $this>
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<TaskCommentAttachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(TaskCommentAttachment::class);
    }
}
