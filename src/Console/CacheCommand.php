<?php

namespace MuhammadMahediHasan\UserManual\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use MuhammadMahediHasan\UserManual\Services\MarkdownRenderer;
use MuhammadMahediHasan\UserManual\Services\NavigationParser;
use MuhammadMahediHasan\UserManual\Services\PdfGeneratorService;
use MuhammadMahediHasan\UserManual\Support\Config;

class CacheCommand extends Command
{
    protected $signature = 'user-manual:cache';

    protected $description = 'Warm and pre-generate user manual navigation, markdown, and PDF export caches';

    public function handle(
        MarkdownRenderer $markdownRenderer,
        NavigationParser $navigationParser,
        PdfGeneratorService $pdfGeneratorService,
    ): int {
        $this->call('user-manual:clear-cache');

        $prefix = Config::string('user-manual.cache_prefix', 'user-manual');
        $version = Config::string('user-manual.version', '1.0');
        $contentRoot = rtrim(Config::string('user-manual.content_path', resource_path('user-manual')), '/');
        $cacheTtl = Config::integer('user-manual.cache_ttl', 3600);
        $pdfEnabled = Config::bool('user-manual.pdf.enabled', true);

        $totalPages = 0;
        $totalPdfs = 0;

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
                $markdown = File::get($file);
                $rendered = $markdownRenderer->render($markdown);
                Cache::put("{$prefix}.{$version}.{$locale}.{$page}.{$fileModified}", $rendered, $cacheTtl);
                $totalPages++;

                if ($pdfEnabled) {
                    $pdfGeneratorService->generatePagePdf($locale, $page);
                    $totalPdfs++;
                }
            }

            if ($pdfEnabled && File::exists($navPath)) {
                $pdfGeneratorService->generateFullPdf($locale);
                $totalPdfs++;
            }
        }

        $this->components->info("Regenerated user manual caches: {$totalPages} pages and {$totalPdfs} PDF exports.");

        return self::SUCCESS;
    }
}
