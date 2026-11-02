<?php

namespace MuhammadMahediHasan\UserManual\Roles;

use BackedEnum;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use MuhammadMahediHasan\UserManual\Contracts\RoleResolver;
use MuhammadMahediHasan\UserManual\Support\Config;
use MuhammadMahediHasan\UserManual\Support\ManualDefinition;
use MuhammadMahediHasan\UserManual\Support\UserRoles;

/**
 * Roles stored in a database table, with any role package or none:
 *
 *   'roles' => [
 *       'resolver' => EloquentRoleResolver::class,
 *       'model' => App\Models\Role::class,
 *       'name' => 'name',               // role name attribute
 *       'permissions' => 'permissions', // relation, array/JSON column, or null
 *       'permission_name' => 'name',    // name attribute of permission models
 *       'scope' => null,                // invokable class (Builder $query, ManualDefinition $manual)
 *       'user_roles' => 'roles',        // where a user's role(s) are: 'roles', 'role', 'role_id'…
 *   ],
 *
 * Role keys are the model keys. Each role's profile is its permission names
 * plus its own name as a role. Extend it and override query(),
 * permissionsFor() or roleFor() for anything the config can't express.
 */
class EloquentRoleResolver implements RoleResolver
{
    public function roles(ManualDefinition $manual): iterable
    {
        $nameAttribute = Config::string('user-manual.roles.name', 'name');

        foreach ($this->query($manual)->get() as $role) {
            $name = $role->getAttribute($nameAttribute);

            yield (string) $role->getKey() => [
                ...$this->permissionsFor($role, $manual),
                'roles' => is_scalar($name) && (string) $name !== '' ? [(string) $name] : [],
            ];
        }
    }

    public function roleFor(Authenticatable $user, ManualDefinition $manual): int|string|null
    {
        return UserRoles::firstKey($user);
    }

    /**
     * @return Builder<Model>
     */
    protected function query(ManualDefinition $manual): Builder
    {
        $model = $this->model();
        $query = $model::query();
        $permissions = $this->permissionsAttribute();

        if ($permissions !== null && method_exists($model, $permissions)) {
            $query->with($permissions);
        }

        $scope = Config::get('user-manual.roles.scope');

        if (is_string($scope) && $scope !== '') {
            $callable = app($scope);

            if (! is_callable($callable)) {
                throw new InvalidArgumentException("User manual role scope [{$scope}] must be invokable.");
            }

            $callable($query, $manual);
        }

        return $query;
    }

    /**
     * @return list<string>
     */
    protected function permissionsFor(Model $role, ManualDefinition $manual): array
    {
        $attribute = $this->permissionsAttribute();

        if ($attribute === null) {
            return [];
        }

        $value = $role->getAttribute($attribute);

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : [$value];
        }

        if (! is_iterable($value)) {
            return [];
        }

        $nameAttribute = Config::string('user-manual.roles.permission_name', 'name');
        $names = [];

        foreach ($value as $permission) {
            $name = match (true) {
                $permission instanceof Model => $permission->getAttribute($nameAttribute),
                $permission instanceof BackedEnum => $permission->value,
                default => $permission,
            };

            if (is_scalar($name) && (string) $name !== '') {
                $names[] = (string) $name;
            }
        }

        return $names;
    }

    /**
     * @return class-string<Model>
     */
    protected function model(): string
    {
        $model = Config::get('user-manual.roles.model');

        if (! is_string($model) || ! is_a($model, Model::class, true)) {
            throw new InvalidArgumentException('Set user-manual.roles.model to an Eloquent model to use EloquentRoleResolver.');
        }

        return $model;
    }

    private function permissionsAttribute(): ?string
    {
        $attribute = Config::get('user-manual.roles.permissions', 'permissions');

        return is_string($attribute) && $attribute !== '' ? $attribute : null;
    }
}
