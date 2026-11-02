<?php

namespace MuhammadMahediHasan\UserManual\Roles;

use Illuminate\Contracts\Auth\Authenticatable;
use MuhammadMahediHasan\UserManual\Contracts\RoleResolver;
use MuhammadMahediHasan\UserManual\Support\ManualDefinition;

/**
 * Used when the app has no role system: one "default" role that every
 * signed-in user belongs to, with no extra permissions.
 */
class DefaultRoleResolver implements RoleResolver
{
    public const ROLE = 'default';

    public function roles(ManualDefinition $manual): iterable
    {
        return [self::ROLE => []];
    }

    public function roleFor(Authenticatable $user, ManualDefinition $manual): string
    {
        return self::ROLE;
    }
}
