<?php

namespace MuhammadMahediHasan\UserManual\Support;

use Illuminate\Support\Facades\File;

final class ManualAssets
{
    public static function url(string $path): string
    {
        $normalizedPath = ltrim($path, '/');
        $published = public_path('vendor/user-manual/'.$normalizedPath);

        if (is_file($published)) {
            return asset('vendor/user-manual/'.$normalizedPath);
        }

        return route('user-manual.asset', ['path' => $normalizedPath]);
    }

    public static function sourcePath(string $path): string
    {
        return __DIR__.'/../../resources/assets/'.ltrim($path, '/');
    }

    public static function ensurePublished(): void
    {
        $files = [
            'css/user-manual.css' => public_path('vendor/user-manual/css/user-manual.css'),
            'js/user-manual.js' => public_path('vendor/user-manual/js/user-manual.js'),
        ];

        foreach ($files as $source => $destination) {
            if (is_file($destination)) {
                continue;
            }

            File::ensureDirectoryExists(dirname($destination));

            if (is_file(self::sourcePath($source))) {
                File::copy(self::sourcePath($source), $destination);
            }
        }
    }

    public static function responseHeaders(string $path): array
    {
        return match (pathinfo($path, PATHINFO_EXTENSION)) {
            'css' => ['Content-Type' => 'text/css; charset=UTF-8'],
            'js' => ['Content-Type' => 'application/javascript; charset=UTF-8'],
            default => [],
        };
    }
}
