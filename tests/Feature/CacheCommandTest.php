<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use MuhammadMahediHasan\UserManual\Services\PdfGeneratorService;

it('regenerates all user manual caches with user-manual:cache command', function () {
    $contentRoot = rtrim(config('user-manual.content_path', resource_path('user-manual')), '/');
    $version = config('user-manual.version', '1.0');
    $cachePrefix = config('user-manual.cache_prefix', 'user-manual');
    $navPath = "{$contentRoot}/{$version}/en/navigation.md";
    $introPath = "{$contentRoot}/{$version}/en/introduction.md";

    // Clear initial caches
    $this->artisan('user-manual:clear-cache')->assertSuccessful();

    $pdfService = app(PdfGeneratorService::class);
    $maxMtime = $pdfService->calculateMaxLastModified('en', $version, $contentRoot, $navPath);
    $introMtime = File::lastModified($introPath);

    $fullPdfKey = "{$cachePrefix}.pdf.full.{$version}.en.{$maxMtime}";
    $pagePdfKey = "{$cachePrefix}.pdf.page.{$version}.en.introduction.{$introMtime}";
    $pageContentKey = "{$cachePrefix}.{$version}.en.introduction.{$introMtime}";

    expect(Cache::has($fullPdfKey))->toBeFalse();
    expect(Cache::has($pagePdfKey))->toBeFalse();
    expect(Cache::has($pageContentKey))->toBeFalse();

    // Run cache command to warm/regenerate everything
    $this->artisan('user-manual:cache')->assertSuccessful();

    expect(Cache::has($fullPdfKey))->toBeTrue();
    expect(Cache::has($pagePdfKey))->toBeTrue();
    expect(Cache::has($pageContentKey))->toBeTrue();
});
