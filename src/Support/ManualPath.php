<?php

namespace MuhammadMahediHasan\UserManual\Support;

final class ManualPath
{
    /**
     * Page slug from a manual URL. The route prefix may contain slashes
     * (`partner/manual`), so matching is on the path prefix rather than one segment.
     *
     * @param  list<string>  $locales
     */
    public static function slug(string $url, string $routePrefix, array $locales): string
    {
        $path = trim((string) (parse_url($url, PHP_URL_PATH) ?? ''), '/');
        $prefix = trim($routePrefix, '/');

        if ($prefix !== '' && ($path === $prefix || str_starts_with($path, $prefix.'/'))) {
            $rest = trim(substr($path, strlen($prefix)), '/');
            $segments = $rest === '' ? [] : explode('/', $rest);

            if (in_array($segments[0] ?? '', $locales, true)) {
                return $segments[1] ?? '';
            }

            return $segments[0] ?? '';
        }

        return basename($path);
    }
}
