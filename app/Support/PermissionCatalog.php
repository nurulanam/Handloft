<?php

namespace App\Support;

use App\Enums\Role;

/**
 * What each role may do, as shown and edited in Settings → Roles & permissions.
 *
 * Only permissions the app actually checks are listed (grouped, with plain-language labels). Super
 * Admin always has everything; Manager and Team Member are adjustable. Changing settings (including
 * these permissions) stays with Super Admins, so nobody can lock them out.
 */
final class PermissionCatalog
{
    /** @var array<string, array<string, array{0: string, 1: string}>> group => [permission => [label, description]] */
    public const GROUPS = [
        'Tasks' => [
            'view-all-tasks' => ['See every task', 'Otherwise only tasks they created, are assigned to, review, or handed to someone.'],
            'create-task' => ['Create tasks', 'Start new tasks and add subtasks.'],
            'reassign-task' => ['Reassign tasks', 'Hand any task they can see to someone else.'],
        ],
        'Projects' => [
            'manage-projects' => ['Create and edit projects', 'Set up projects, their coordinator, status and timeline.'],
        ],
        'Team' => [
            'manage-users' => ['Manage team members', 'Add people and edit their details and role. Super Admin accounts stay off limits.'],
        ],
        'Reports & time' => [
            'view-reports' => ["See the whole team's reports", 'Otherwise they only see their own report.'],
            'export-data' => ['Export reports', 'Download reports as CSV or Excel.'],
            'edit-completed-hours' => ['Correct logged hours', "Edit or remove anyone's time entries, always with a reason."],
        ],
    ];

    /** A permission that only makes sense together with another one. */
    public const REQUIRES = [
        'export-data' => 'view-reports',
        'edit-completed-hours' => 'view-reports',
    ];

    /** Always Super Admin only, shown for reference. */
    public const LOCKED = [
        'manage-settings' => ['Change settings and permissions', 'Email, schedule, loading screen, and this page.'],
    ];

    /**
     * Every permission that exists (the editable ones plus legacy and Super Admin-only ones).
     */
    public const ALL = [
        'manage-users',
        'manage-roles-permissions',
        'view-all-tasks',
        'create-task',
        'assign-task',
        'reassign-task',
        'manage-projects',
        'view-own-work-history',
        'view-all-work-history',
        'edit-completed-hours',
        'manage-templates',
        'view-reports',
        'export-data',
        'view-audit-log',
        'manage-settings',
    ];

    /** Out-of-the-box grants for the adjustable roles. */
    public const DEFAULTS = [
        'manager' => ['view-all-tasks', 'create-task', 'assign-task', 'manage-projects', 'view-own-work-history', 'view-all-work-history', 'view-reports', 'export-data'],
        'team-member' => ['create-task', 'assign-task', 'view-own-work-history'],
    ];

    /**
     * @return list<Role>
     */
    public static function editableRoles(): array
    {
        return [Role::Manager, Role::TeamMember];
    }

    /**
     * @return list<string>
     */
    public static function editable(): array
    {
        return array_merge(...array_map('array_keys', array_values(self::GROUPS)));
    }

    /**
     * Keep only editable permissions, and drop any whose prerequisite isn't granted.
     *
     * @param  array<int, string>  $granted
     * @return list<string>
     */
    public static function normalize(array $granted): array
    {
        $granted = array_values(array_intersect(self::editable(), $granted));

        return array_values(array_filter($granted, fn (string $permission) => ! isset(self::REQUIRES[$permission]) || in_array(self::REQUIRES[$permission], $granted, true)));
    }

    public static function label(string $permission): string
    {
        foreach (self::GROUPS as $permissions) {
            if (isset($permissions[$permission])) {
                return $permissions[$permission][0];
            }
        }

        return $permission;
    }
}
