<?php

namespace MuhammadMahediHasan\UserManual\Roles;

use Illuminate\Contracts\Auth\Authenticatable;
use MuhammadMahediHasan\UserManual\Contracts\RoleResolver;
use MuhammadMahediHasan\UserManual\Support\Config;
use MuhammadMahediHasan\UserManual\Support\ManualDefinition;
use MuhammadMahediHasan\UserManual\Support\UserRoles;

/**
 * Roles written in the config, for apps whose roles live in code (a `role`
 * column, an enum, a constant list…):
 *
 *   'roles' => [
 *       'resolver' => ConfigRoleResolver::class,
 *       'list' => [
 *           'admin' => 'all',
 *           'editor' => ['post_edit', 'post_publish'],
 *           'member' => [],
 *       ],
 *       'user_roles' => 'role', // users.role holds "admin", "editor", …
 *   ],
 *
 * The user's role is the first of their role names (see UserRoles) that is
 * listed. Each role also gets its own name as a role, so ['roles' => [...]]
 * rules in permission-mapper match it.
 */
class ConfigRoleResolver implements RoleResolver
{
    public function roles(ManualDefinition $manual): iterable
    {
        foreach ($this->list() as $key => $profile) {
            yield $key => is_array($profile)
                ? [...$profile, 'roles' => [(string) $key]]
                : $profile;
        }
    }

    public function roleFor(Authenticatable $user, ManualDefinition $manual): ?string
    {
        $list = $this->list();

        foreach (UserRoles::names($user) as $name) {
            if (array_key_exists($name, $list)) {
                return $name;
            }
        }

        return null;
    }

    /**
     * @return array<string, array<int|string, mixed>|string>
     */
    protected function list(): array
    {
        $list = [];

        foreach (Config::array('user-manual.roles.list', []) as $key => $profile) {
            if (is_array($profile) || is_string($profile)) {
                $list[(string) $key] = $profile;
            }
        }

        return $list;
    }
}
