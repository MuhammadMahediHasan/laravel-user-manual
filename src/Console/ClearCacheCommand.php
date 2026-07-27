<?php

namespace MuhammadMahediHasan\UserManual\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use MuhammadMahediHasan\UserManual\Services\PdfGeneratorService;
use MuhammadMahediHasan\UserManual\Support\Config;

class ClearCacheCommand extends Command
{
    protected $signature = 'user-manual:clear-cache';

    protected $description = 'Clear cached user manual markdown, navigation, and PDF exports';

    public function handle(): int
    {
        $prefix = Config::string('user-manual.cache_prefix', 'user-manual');
        $version = Config::string('user-manual.version', '1.0');
        $contentRoot = rtrim(Config::string('user-manual.content_path', resource_path('user-manual')), '/');
        $pdfService = app(PdfGeneratorService::class);
        $cleared = 0;

        // Drop on-disk PDF payloads first so orphaned files from prior keys
        // (old mtimes / access signatures) do not accumulate.
        $pdfService->flushPdfCacheFiles();

        foreach (Config::stringList('user-manual.locales', ['en']) as $locale) {
            $localePath = "{$contentRoot}/{$version}/{$locale}";
            $navPath = "{$localePath}/navigation.md";

            if (File::exists($navPath) && Cache::forget("{$prefix}.{$version}.{$locale}.nav.tree.".File::lastModified($navPath))) {
                $cleared++;
            }

            // Full-manual PDFs are cached per accessible page set, so they are
            // tracked in an index and cleared as a group rather than by a
            // single reconstructable key.
            $cleared += $pdfService->forgetFullPdfCaches($version, $locale);

            if (! File::isDirectory($localePath)) {
                continue;
            }

            foreach (File::glob("{$localePath}/*.md") as $file) {
                $page = basename($file, '.md');

                if ($page === 'navigation') {
                    continue;
                }

                $mtime = File::lastModified($file);

                if (Cache::forget("{$prefix}.{$version}.{$locale}.{$page}.{$mtime}")) {
                    $cleared++;
                }

                if ($pdfService->forgetPdf("{$prefix}.pdf.page.{$version}.{$locale}.{$page}.{$mtime}")) {
                    $cleared++;
                }
            }
        }

        $this->components->info("Cleared {$cleared} user manual cache entries.");

        return self::SUCCESS;
    }
}
