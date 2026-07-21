<?php

namespace MuhammadMahediHasan\UserManual\Support;

final class ManualConfig
{
    public static function string(string $key, string $default): string
    {
        $value = config($key, $default);

        return is_string($value) ? $value : $default;
    }

    public static function bool(string $key, bool $default): bool
    {
        $value = config($key, $default);

        return is_bool($value) ? $value : $default;
    }

    public static function integer(string $key, int $default): int
    {
        $value = config($key, $default);

        if (is_int($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        return $default;
    }

    /**
     * @param  list<string>  $default
     * @return list<string>
     */
    public static function stringList(string $key, array $default): array
    {
        $value = config($key, $default);

        if (! is_array($value)) {
            return $default;
        }

        return array_values(array_map(
            static fn (mixed $item): string => (string) $item,
            $value,
        ));
    }

    /**
     * @param  array<int|string, mixed>  $default
     * @return array<int|string, mixed>
     */
    public static function array(string $key, array $default): array
    {
        $value = config($key, $default);

        return is_array($value) ? $value : $default;
    }
}
