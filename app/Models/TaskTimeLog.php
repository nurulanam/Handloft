<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One worked-time entry against a task, for a day. A person can have several on the same task and
 * day (each work-timer session, or another manual log); the day's time is their sum. See WorkHistory
 * for the separate, one-shot end-of-task record.
 */
#[Fillable(['task_id', 'user_id', 'logged_date', 'hours', 'note'])]
class TaskTimeLog extends Model
{
    protected function casts(): array
    {
        return [
            'logged_date' => 'date',
            'hours' => 'decimal:2',
        ];
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
}
