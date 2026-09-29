<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One day's worked-hours entry against a task. A task that spans several days
 * accumulates several of these (one per person per day) instead of a single
 * end-of-task hours figure — see WorkHistory for that separate, one-shot record.
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
