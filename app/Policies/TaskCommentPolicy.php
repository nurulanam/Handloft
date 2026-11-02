<?php

namespace App\Policies;

use App\Models\TaskComment;
use App\Models\User;

class TaskCommentPolicy
{
    /**
     * Determine whether the user can edit the comment. Only its author can — the task's reporter,
     * assignee or a manager seeing it isn't the same as having written it.
     */
    public function update(User $user, TaskComment $comment): bool
    {
        return $comment->user_id === $user->id;
    }

    /**
     * Determine whether the user can delete the comment (kept in the database, hidden from the
     * thread). Only its author can.
     */
    public function delete(User $user, TaskComment $comment): bool
    {
        return $comment->user_id === $user->id;
    }
}
