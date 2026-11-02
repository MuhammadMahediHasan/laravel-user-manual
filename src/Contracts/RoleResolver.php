<?php

namespace MuhammadMahediHasan\UserManual\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;
use MuhammadMahediHasan\UserManual\Support\ManualDefinition;

/**
 * Tells the package which roles exist and which role a user has, so one
 * full-manual PDF can be pre-generated per role and served without rendering.
 *
 * Bind your own implementation with the `roles.resolver` config key (per
 * manual if needed) or at runtime with UserManual::resolveRolesUsing().
 */
interface RoleResolver
{
    /**
     * Every role the manual should have a full PDF for, keyed by a stable role
     * key (an id is safer than a name, which can be renamed).
     *
     * Each value is an access profile, the same shapes AccessProfile accepts:
     * - []                                → signed-in user with no permissions
     * - ['perm_a', 'perm_b']              → user with those permissions
     * - ['perm_a', 'roles' => ['Editor']] → permissions plus role names
     * - 'all'                             → every page
     *
     * @return iterable<int|string, array<int|string, mixed>|string>
     */
    public function roles(ManualDefinition $manual): iterable;

    /**
     * The role key of the given user. Must be one of the keys returned by
     * roles(). Return null to use the "default" role key.
     */
    public function roleFor(Authenticatable $user, ManualDefinition $manual): int|string|null;
}
