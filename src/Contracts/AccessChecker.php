<?php

namespace MuhammadMahediHasan\UserManual\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * How the package asks "does this user have permission X / one of roles Y?"
 * when applying permission-mapper rules and super_admin_roles.
 *
 * Set your own class with the `access_checker` config key.
 */
interface AccessChecker
{
    public function hasPermission(Authenticatable $user, string $permission): bool;

    /**
     * @param  list<string>  $roles  Role names.
     */
    public function hasAnyRole(Authenticatable $user, array $roles): bool;
}
