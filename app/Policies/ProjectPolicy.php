<?php

namespace App\Policies;

use App\Enums\Role;
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

    /**
     * Determine whether the user can delete the project. Deleting is destructive (it takes the
     * project and its place in every task list out of the app), so it stays with Super Admins.
     */
    public function delete(User $user, Project $project): bool
    {
        return $user->hasRole(Role::SuperAdmin->value);
    }
}
