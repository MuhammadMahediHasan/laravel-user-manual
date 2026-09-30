<?php

namespace MuhammadMahediHasan\UserManual\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use MuhammadMahediHasan\UserManual\Services\PdfGeneratorService;
use MuhammadMahediHasan\UserManual\Support\Config;
use MuhammadMahediHasan\UserManual\Support\CurrentManual;
use MuhammadMahediHasan\UserManual\Support\ManualRegistry;

class ClearCacheCommand extends Command
{
    protected $signature = 'user-manual:clear-cache {manual? : Manual name to clear}';

    protected $description = 'Clear cached user manual markdown, navigation, and PDF exports';

    public function handle(ManualRegistry $manuals, CurrentManual $currentManual): int
    {
        $manualName = $this->argument('manual');
        $manualName = is_string($manualName) ? $manualName : null;
        $selected = $manuals->selected($manualName);

        if ($selected === []) {
            $this->components->error("Unknown user manual [{$manualName}].");

            return self::FAILURE;
        }

        $cleared = 0;
        /** @var array<string, true> $flushedPaths */
        $flushedPaths = [];

        foreach ($selected as $manual) {
            $cleared += $currentManual->using(
                $manual->implicit ? null : $manual->id,
                function () use (&$flushedPaths): int {
                    return $this->clearActive($flushedPaths);
                },
            );
        }

        $this->components->info("Cleared {$cleared} user manual cache entries.");

        return self::SUCCESS;
    }

    /**
     * @param  array<string, true>  $flushedPaths
     */
    private function clearActive(array &$flushedPaths): int
    {
        $prefix = Config::string('user-manual.cache_prefix', 'user-manual');
        $version = Config::string('user-manual.version', '1.0');
        $contentRoot = rtrim(Config::string('user-manual.content_path', resource_path('user-manual')), '/');
        $pdfService = app(PdfGeneratorService::class);
        $cleared = 0;
        $cachePath = Config::string('user-manual.pdf.cache_path', storage_path('app/user-manual/pdfs'));

        if (! isset($flushedPaths[$cachePath])) {
            // Drop on-disk PDF payloads first so orphaned files from prior keys
            // (old mtimes / access signatures) do not accumulate.
            $pdfService->flushPdfCacheFiles();
            $flushedPaths[$cachePath] = true;
        }

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

        return $cleared;
    }
}
