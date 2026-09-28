<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WorkHistory;

class WorkHistoryPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, WorkHistory $workHistory): bool
    {
        return $user->can('view-all-work-history') || $workHistory->user_id === $user->id;
    }

    /**
     * Determine whether the user can edit the recorded hours.
     */
    public function update(User $user, WorkHistory $workHistory): bool
    {
        return $user->can('edit-completed-hours');
    }
}
