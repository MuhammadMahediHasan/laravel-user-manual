<?php

namespace MuhammadMahediHasan\UserManual\Roles;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use MuhammadMahediHasan\UserManual\Contracts\RoleResolver;
use MuhammadMahediHasan\UserManual\Support\ManualDefinition;

/**
 * Wraps the closures given to UserManual::resolveRolesUsing().
 */
final class ClosureRoleResolver implements RoleResolver
{
    /**
     * @param  Closure(ManualDefinition): iterable<int|string, array<int|string, mixed>|string>  $roles
     * @param  Closure(Authenticatable, ManualDefinition): (int|string|null)  $roleFor
     */
    public function __construct(
        private readonly Closure $roles,
        private readonly Closure $roleFor,
    ) {}

    public function roles(ManualDefinition $manual): iterable
    {
        return ($this->roles)($manual);
    }

    public function roleFor(Authenticatable $user, ManualDefinition $manual): int|string|null
    {
        return ($this->roleFor)($user, $manual);
    }
}
