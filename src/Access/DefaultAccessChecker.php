<?php

namespace MuhammadMahediHasan\UserManual\Access;

use Illuminate\Contracts\Auth\Authenticatable;
use MuhammadMahediHasan\UserManual\Contracts\AccessChecker;
use MuhammadMahediHasan\UserManual\Support\UserRoles;

/**
 * Works with any role setup:
 *
 * - Permissions use Laravel's own `$user->can()` (Gate), so policies,
 *   Gate::define() and role packages that hook into the Gate all apply.
 * - Roles use the user's `hasRole()` method when it has one; otherwise the
 *   role names found at `roles.user_roles` (a relation, a column, an enum…).
 */
class DefaultAccessChecker implements AccessChecker
{
    public function hasPermission(Authenticatable $user, string $permission): bool
    {
        return method_exists($user, 'can') && $user->can($permission);
    }

    public function hasAnyRole(Authenticatable $user, array $roles): bool
    {
        if ($roles === []) {
            return false;
        }

        if (method_exists($user, 'hasRole')) {
            return (bool) $user->hasRole($roles);
        }

        return array_intersect($roles, UserRoles::names($user)) !== [];
    }
}
