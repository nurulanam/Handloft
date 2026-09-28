<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;

class ProjectPolicy
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
    public function view(User $user, Project $project): bool
    {
        return true;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('manage-projects');
    }

    /**
     * Determine whether the user can update the project (fields, status, archive).
     */
    public function update(User $user, Project $project): bool
    {
        return $user->can('manage-projects') || $project->created_by === $user->id;
    }
}
