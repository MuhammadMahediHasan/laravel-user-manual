<?php

namespace MuhammadMahediHasan\UserManual\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use MuhammadMahediHasan\UserManual\Support\ManualConfig;

class ClearCacheCommand extends Command
{
    protected $signature = 'user-manual:clear-cache';

    protected $description = 'Clear cached user manual markdown and navigation';

    public function handle(): int
    {
        $prefix = ManualConfig::string('user-manual.cache_prefix', 'user-manual');
        $version = ManualConfig::string('user-manual.version', '1.0');
        $contentRoot = rtrim(ManualConfig::string('user-manual.content_path', resource_path('docs')), '/');
        $cleared = 0;

        foreach (ManualConfig::stringList('user-manual.locales', ['en']) as $locale) {
            $localePath = "{$contentRoot}/{$version}/{$locale}";
            $navPath = "{$localePath}/navigation.md";

            if (File::exists($navPath) && Cache::forget("{$prefix}.{$version}.{$locale}.nav.tree.".File::lastModified($navPath))) {
                $cleared++;
            }

            if (! File::isDirectory($localePath)) {
                continue;
            }

            foreach (File::glob("{$localePath}/*.md") as $file) {
                $page = basename($file, '.md');

                if ($page === 'navigation') {
                    continue;
                }

                if (Cache::forget("{$prefix}.{$version}.{$locale}.{$page}.".File::lastModified($file))) {
                    $cleared++;
                }
            }
        }

        $this->components->info("Cleared {$cleared} user manual cache entries.");

        return self::SUCCESS;
    }
}
