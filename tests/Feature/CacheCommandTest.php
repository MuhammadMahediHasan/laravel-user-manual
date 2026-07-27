<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use MuhammadMahediHasan\UserManual\Services\PdfGeneratorService;

it('regenerates all user manual caches with user-manual:cache command', function () {
    $contentRoot = rtrim(config('user-manual.content_path', resource_path('user-manual')), '/');
    $version = config('user-manual.version', '1.0');
    $cachePrefix = config('user-manual.cache_prefix', 'user-manual');
    $introPath = "{$contentRoot}/{$version}/en/introduction.md";

    // Clear initial caches
    $this->artisan('user-manual:clear-cache')->assertSuccessful();

    $pdfService = app(PdfGeneratorService::class);
    $introMtime = File::lastModified($introPath);

    $pagePdfKey = "{$cachePrefix}.pdf.page.{$version}.en.introduction.{$introMtime}";
    $pageContentKey = "{$cachePrefix}.{$version}.en.introduction.{$introMtime}";
    $fullPdfIndexKey = $pdfService->fullPdfIndexKey($version, 'en');

    expect(Cache::has($pagePdfKey))->toBeFalse();
    expect(Cache::has($pageContentKey))->toBeFalse();

    // Run cache command to warm/regenerate everything
    $this->artisan('user-manual:cache')->assertSuccessful();

    expect(Cache::has($pageContentKey))->toBeTrue();
    expect(Cache::has($pagePdfKey))->toBeTrue();

    // Default pdf.warm_profiles warms the public authenticated variant so the
    // first matching web full-export is a cache hit.
    $fullKeys = Cache::get($fullPdfIndexKey, []);
    expect($fullKeys)->not->toBeEmpty();
    foreach ($fullKeys as $key) {
        expect(Cache::has($key))->toBeTrue();
    }
});
