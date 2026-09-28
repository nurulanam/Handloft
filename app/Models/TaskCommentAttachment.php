<?php

namespace App\Models;

use Database\Factories\TaskCommentAttachmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['task_comment_id', 'uploaded_by', 'path', 'original_name', 'size'])]
class TaskCommentAttachment extends Model
{
    /** @use HasFactory<TaskCommentAttachmentFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<TaskComment, $this>
     */
    public function comment(): BelongsTo
    {
        return $this->belongsTo(TaskComment::class, 'task_comment_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
