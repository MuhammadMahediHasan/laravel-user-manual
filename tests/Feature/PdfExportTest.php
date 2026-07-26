<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Mpdf\Mpdf;
use MuhammadMahediHasan\UserManual\Services\PdfGeneratorService;

it('exports single page as pdf for authenticated users', function () {
    $response = $this->actingAs($this->makeAuthenticatable())
        ->get(route('user-manual.pdf.page', ['locale' => 'en', 'page' => 'introduction']));

    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/pdf');
    expect($response->getContent())->toContain('%PDF-');
});

it('returns forbidden when exporting a restricted page as pdf without permission', function () {
    $this->actingAs($this->makeAuthenticatable())
        ->get(route('user-manual.pdf.page', ['locale' => 'en', 'page' => 'material']))
        ->assertForbidden();
});

it('exports restricted page as pdf when permission is granted', function () {
    $response = $this->actingAs($this->makeAuthenticatable(['material_access']))
        ->get(route('user-manual.pdf.page', ['locale' => 'en', 'page' => 'material']));

    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/pdf');
    expect($response->getContent())->toContain('%PDF-');
});

it('exports full manual as pdf for authenticated users', function () {
    $response = $this->actingAs($this->makeAuthenticatable())
        ->get(route('user-manual.pdf.full', ['locale' => 'en']));

    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/pdf');
    expect($response->getContent())->toContain('%PDF-');
});

it('caches pdf exports and clears them with user-manual:clear-cache', function () {
    $contentRoot = rtrim(config('user-manual.content_path', resource_path('user-manual')), '/');
    $version = config('user-manual.version', '1.0');
    $cachePrefix = config('user-manual.cache_prefix', 'user-manual');
    $navPath = "{$contentRoot}/{$version}/en/navigation.md";

    $pdfService = app(PdfGeneratorService::class);
    $maxMtime = $pdfService->calculateMaxLastModified('en', $version, $contentRoot, $navPath);

    // Initial request populates cache
    $response = $this->actingAs($this->makeAuthenticatable())
        ->get(route('user-manual.pdf.full', ['locale' => 'en']));

    $response->assertOk();
    $cacheKey = "{$cachePrefix}.pdf.full.{$version}.en.{$maxMtime}";
    expect(Cache::has($cacheKey))->toBeTrue();

    // Clear cache command removes the PDF cache key
    $this->artisan('user-manual:clear-cache')->assertSuccessful();
    expect(Cache::has($cacheKey))->toBeFalse();
});

it('invalidates pdf cache when a content file is modified', function () {
    $contentRoot = rtrim(config('user-manual.content_path', resource_path('user-manual')), '/');
    $version = config('user-manual.version', '1.0');
    $cachePrefix = config('user-manual.cache_prefix', 'user-manual');
    $introPath = "{$contentRoot}/{$version}/en/introduction.md";
    $navPath = "{$contentRoot}/{$version}/en/navigation.md";

    $pdfService = app(PdfGeneratorService::class);

    // Populate initial cache
    $this->actingAs($this->makeAuthenticatable())
        ->get(route('user-manual.pdf.full', ['locale' => 'en']));

    $initialMtime = $pdfService->calculateMaxLastModified('en', $version, $contentRoot, $navPath);
    $initialCacheKey = "{$cachePrefix}.pdf.full.{$version}.en.{$initialMtime}";
    expect(Cache::has($initialCacheKey))->toBeTrue();

    // Touch intro file to update timestamp
    touch($introPath, time() + 5);
    clearstatcache();

    $newMtime = $pdfService->calculateMaxLastModified('en', $version, $contentRoot, $navPath);
    $newCacheKey = "{$cachePrefix}.pdf.full.{$version}.en.{$newMtime}";

    expect($newMtime)->toBeGreaterThan($initialMtime);
    expect(Cache::has($newCacheKey))->toBeFalse();

    // Second request generates fresh cache for updated timestamp
    $this->actingAs($this->makeAuthenticatable())
        ->get(route('user-manual.pdf.full', ['locale' => 'en']));

    expect(Cache::has($newCacheKey))->toBeTrue();
});

it('handles large html payloads by chunking WriteHTML calls', function () {
    $contentRoot = rtrim(config('user-manual.content_path', resource_path('user-manual')), '/');
    $version = config('user-manual.version', '1.0');
    $largeFilePath = "{$contentRoot}/{$version}/en/large-page.md";

    // Generate large markdown file (~300 KB)
    $paragraphs = array_fill(0, 1500, "Paragraph content with **bold formatting** and `inline code` for testing large HTML payload.\n\n");
    File::put($largeFilePath, "# Large Documentation Page\n\n".implode('', $paragraphs));

    try {
        $pdfService = app(PdfGeneratorService::class);
        $response = $pdfService->generatePagePdf('en', 'large-page');

        expect($response->getStatusCode())->toBe(200);
        expect($response->getContent())->toContain('%PDF-');
    } finally {
        if (File::exists($largeFilePath)) {
            File::delete($largeFilePath);
        }
    }
});

it('returns 404 when pdf export is disabled in config', function () {
    config(['user-manual.pdf.enabled' => false]);

    $this->actingAs($this->makeAuthenticatable())
        ->get(route('user-manual.pdf.page', ['locale' => 'en', 'page' => 'introduction']))
        ->assertNotFound();

    $this->actingAs($this->makeAuthenticatable())
        ->get(route('user-manual.pdf.full', ['locale' => 'en']))
        ->assertNotFound();
});

it('configures custom fonts in mpdf instance', function () {
    config([
        'user-manual.pdf.fonts.font_dirs' => [__DIR__.'/../fixtures'],
        'user-manual.pdf.fonts.font_data' => [
            'customfont' => [
                'R' => 'DejaVuSansCondensed.ttf',
            ],
        ],
    ]);

    $service = app(PdfGeneratorService::class);
    $mpdf = $service->createMpdfInstance();

    expect($mpdf)->toBeInstanceOf(Mpdf::class);
});
