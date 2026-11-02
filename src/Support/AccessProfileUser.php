<?php

namespace MuhammadMahediHasan\UserManual\Support;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * A stand-in user built from a role's access profile (see AccessProfile).
 * It answers permission and role checks from the profile itself, so the
 * configured access checker is not consulted for it.
 */
interface AccessProfileUser extends Authenticatable
{
    public function hasPermission(string $permission): bool;

    /**
     * @param  list<string>  $roles
     */
    public function hasAnyRole(array $roles): bool;
}
