<?php

namespace MuhammadMahediHasan\UserManual\Support;

use BackedEnum;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Reads a user's roles without assuming a role package.
 *
 * `roles.user_roles` is a data_get() path on the user that may hold a
 * collection/array of role models, one role model, an enum, or a plain
 * string/int (e.g. a `role` column). `roles.name` is the name attribute of a
 * role model.
 */
final class UserRoles
{
    /**
     * Role names of the user (models → their name attribute, enums → value).
     *
     * @return list<string>
     */
    public static function names(Authenticatable $user): array
    {
        $nameAttribute = self::nameAttribute();
        $names = [];

        foreach (self::values($user) as $role) {
            $name = $role instanceof Model ? $role->getAttribute($nameAttribute) : self::scalar($role);

            if (is_scalar($name) && (string) $name !== '') {
                $names[] = (string) $name;
            }
        }

        return $names;
    }

    /**
     * Role key of the user's first role (models → their key, enums → value).
     */
    public static function firstKey(Authenticatable $user): ?string
    {
        foreach (self::values($user) as $role) {
            $key = $role instanceof Model ? $role->getKey() : self::scalar($role);

            if (is_scalar($key) && (string) $key !== '') {
                return (string) $key;
            }
        }

        return null;
    }

    /**
     * @return list<mixed>
     */
    private static function values(Authenticatable $user): array
    {
        $path = Config::string('user-manual.roles.user_roles', 'roles');

        if ($path === '') {
            return [];
        }

        $value = data_get($user, $path);

        if ($value === null) {
            return [];
        }

        if (is_iterable($value)) {
            $values = [];

            foreach ($value as $item) {
                $values[] = $item;
            }

            return $values;
        }

        return [$value];
    }

    private static function scalar(mixed $value): mixed
    {
        if ($value instanceof BackedEnum) {
            return $value->value;
        }

        if ($value instanceof UnitEnum) {
            return $value->name;
        }

        return $value;
    }

    private static function nameAttribute(): string
    {
        return Config::string('user-manual.roles.name', 'name');
    }
}
