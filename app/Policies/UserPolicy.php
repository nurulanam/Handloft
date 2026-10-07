<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\User;

class UserPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('manage-users');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, User $model): bool
    {
        return $user->can('manage-users');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('manage-users');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, User $model): bool
    {
        // manage-users can be given to other roles (Settings → Roles & permissions), but a Super Admin
        // account is only ever edited by a Super Admin.
        if ($model->hasRole(Role::SuperAdmin->value) && ! $user->hasRole(Role::SuperAdmin->value)) {
            return false;
        }

        return $user->can('manage-users');
    }

    /**
     * Whether the user may give someone the Super Admin role (only Super Admins can).
     */
    public function grantSuperAdmin(User $user): bool
    {
        return $user->hasRole(Role::SuperAdmin->value);
    }
}
