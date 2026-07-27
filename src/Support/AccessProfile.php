<?php

namespace MuhammadMahediHasan\UserManual\Support;

use Illuminate\Auth\GenericUser;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Builds a synthetic Authenticatable for console full-PDF warming so
 * PermissionResolver can filter navigation without a real login session.
 *
 * Profile shapes (each entry in pdf.warm_profiles):
 * - []                  → authenticated user with no extra permissions/roles
 * - ['perm_a', 'perm_b'] → user that can() those permissions
 * - ['roles' => [...]]  → user that hasRole() those roles (may also list permissions)
 * - ['*'] or 'all'      → unrestricted access to every mapped page
 */
final class AccessProfile
{
    /**
     * @param  array<int|string, mixed>|string  $profile
     */
    public static function makeUser(array|string $profile): Authenticatable
    {
        if (self::isUnrestricted($profile)) {
            return self::user(permissions: ['*'], roles: ['*'], unrestricted: true);
        }

        $permissions = [];
        $roles = [];

        if (! is_array($profile)) {
            return self::user();
        }

        if (isset($profile['roles']) && is_array($profile['roles'])) {
            foreach ($profile['roles'] as $role) {
                if (is_string($role) && $role !== '') {
                    $roles[] = $role;
                }
            }
        }

        foreach ($profile as $key => $value) {
            if ($key === 'roles') {
                continue;
            }

            if (is_int($key) && is_string($value) && $value !== '' && $value !== '*') {
                $permissions[] = $value;
            }
        }

        return self::user($permissions, $roles);
    }

    /**
     * @param  array<int|string, mixed>|string  $profile
     */
    private static function isUnrestricted(array|string $profile): bool
    {
        if ($profile === 'all' || $profile === '*') {
            return true;
        }

        return $profile === ['*'];
    }

    /**
     * @param  list<string>  $permissions
     * @param  list<string>  $roles
     */
    private static function user(
        array $permissions = [],
        array $roles = [],
        bool $unrestricted = false,
    ): Authenticatable {
        return new class($permissions, $roles, $unrestricted) extends GenericUser
        {
            /**
             * @param  list<string>  $permissions
             * @param  list<string>  $roles
             */
            public function __construct(
                public array $permissions,
                public array $roles,
                public bool $unrestricted,
            ) {
                parent::__construct(['id' => 0, 'name' => 'user-manual-warm-profile']);
            }

            public function can($ability, $arguments = []): bool
            {
                if ($this->unrestricted) {
                    return true;
                }

                return in_array($ability, $this->permissions, true);
            }

            public function hasRole($roles, ?string $guard = null): bool
            {
                if ($this->unrestricted) {
                    return true;
                }

                $roles = is_array($roles) ? $roles : [$roles];

                return count(array_intersect($roles, $this->roles)) > 0;
            }
        };
    }
}
