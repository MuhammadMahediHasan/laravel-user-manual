<?php

namespace MuhammadMahediHasan\UserManual\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use League\CommonMark\Exception\CommonMarkException;
use MuhammadMahediHasan\UserManual\Services\MarkdownRenderer;
use MuhammadMahediHasan\UserManual\Services\NavigationParser;
use MuhammadMahediHasan\UserManual\Support\Config;
use MuhammadMahediHasan\UserManual\Support\CurrentManual;
use MuhammadMahediHasan\UserManual\Support\ManualRegistry;

class CacheCommand extends Command
{
    protected $signature = 'user-manual:cache {manual? : Manual name to warm}';

    protected $description = 'Warm the user manual navigation and rendered markdown caches';

    /**
     * @throws CommonMarkException
     */
    public function handle(
        MarkdownRenderer $markdownRenderer,
        NavigationParser $navigationParser,
        ManualRegistry $manuals,
        CurrentManual $currentManual,
    ): int {
        $manualName = $this->argument('manual');
        $manualName = is_string($manualName) ? $manualName : null;
        $selected = $manuals->selected($manualName);

        if ($selected === []) {
            $this->components->error("Unknown user manual [{$manualName}].");

            return self::FAILURE;
        }

        $this->call('user-manual:clear-cache', array_filter([
            'manual' => $manualName,
        ]));

        $totalPages = 0;

        foreach ($selected as $manual) {
            $totalPages += $currentManual->using(
                $manual->implicit ? null : $manual->id,
                fn (): int => $this->warmActive($markdownRenderer, $navigationParser),
            );
        }

        $this->components->info("Regenerated user manual caches: {$totalPages} pages.");
        $this->components->info('Full manual PDFs are generated separately with user-manual:pdf.');

        return self::SUCCESS;
    }

    /**
     * @throws CommonMarkException
     */
    private function warmActive(
        MarkdownRenderer $markdownRenderer,
        NavigationParser $navigationParser,
    ): int {
        $prefix = Config::string('user-manual.cache_prefix', 'user-manual');
        $version = Config::string('user-manual.version', '1.0');
        $contentRoot = rtrim(Config::string('user-manual.content_path', resource_path('user-manual')), '/');
        $cacheTtl = Config::integer('user-manual.cache_ttl', 3600);
        $totalPages = 0;

        foreach (Config::stringList('user-manual.locales', ['en']) as $locale) {
            $localePath = "{$contentRoot}/{$version}/{$locale}";
            $navPath = "{$localePath}/navigation.md";

            if (File::exists($navPath)) {
                $navModified = File::lastModified($navPath);
                $tree = $navigationParser->buildTree($navigationParser->parse($navPath));
                Cache::put("{$prefix}.{$version}.{$locale}.nav.tree.{$navModified}", $tree, $cacheTtl);
            }

            if (! File::isDirectory($localePath)) {
                continue;
            }

            foreach (File::glob("{$localePath}/*.md") as $file) {
                $page = basename($file, '.md');

                if ($page === 'navigation') {
                    continue;
                }

                $fileModified = File::lastModified($file);
                $rendered = $markdownRenderer->render(File::get($file));
                Cache::put("{$prefix}.{$version}.{$locale}.{$page}.{$fileModified}", $rendered, $cacheTtl);
                $totalPages++;
            }
        }

        return $totalPages;
    }
}
