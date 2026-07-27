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

it('stores pdf payloads on disk with a text-column-safe cache marker', function () {
    // Database cache drivers use a mediumtext column (~16 MB). Full manuals
    // exceed that, so payloads live on disk and the cache only holds "disk".
    $version = config('user-manual.version', '1.0');
    $prefix = config('user-manual.cache_prefix', 'user-manual');
    $introPath = rtrim(config('user-manual.content_path'), '/')."/{$version}/en/introduction.md";
    $pdfService = app(PdfGeneratorService::class);

    $this->actingAs($this->makeAuthenticatable())
        ->get(route('user-manual.pdf.page', ['locale' => 'en', 'page' => 'introduction']))
        ->assertOk();
    $this->actingAs($this->makeAuthenticatable())
        ->get(route('user-manual.pdf.full', ['locale' => 'en']))
        ->assertOk();

    $pageKey = "{$prefix}.pdf.page.{$version}.en.introduction.".File::lastModified($introPath);
    $keys = [...Cache::get($pdfService->fullPdfIndexKey($version, 'en'), []), $pageKey];

    expect($keys)->toHaveCount(2);
    foreach ($keys as $key) {
        expect(Cache::get($key))->toBe('disk');

        $path = $pdfService->pdfCachePath($key);
        expect(File::exists($path))->toBeTrue();

        $payload = File::get($path);
        expect(mb_check_encoding($payload, 'UTF-8'))->toBeTrue()
            ->and(base64_decode($payload, true))->not->toBeFalse()
            ->and(base64_decode($payload))->toContain('%PDF-');
    }
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

it('caches full pdf exports and clears every variant with user-manual:clear-cache', function () {
    $version = config('user-manual.version', '1.0');
    $pdfService = app(PdfGeneratorService::class);
    $indexKey = $pdfService->fullPdfIndexKey($version, 'en');

    $this->actingAs($this->makeAuthenticatable())
        ->get(route('user-manual.pdf.full', ['locale' => 'en']))
        ->assertOk();

    $cachedKeys = Cache::get($indexKey, []);
    expect($cachedKeys)->not->toBeEmpty();
    foreach ($cachedKeys as $key) {
        expect(Cache::has($key))->toBeTrue();
    }

    $this->artisan('user-manual:clear-cache')->assertSuccessful();

    foreach ($cachedKeys as $key) {
        expect(Cache::has($key))->toBeFalse();
    }
    expect(Cache::get($indexKey, []))->toBeEmpty();
});

it('invalidates the full pdf cache when a content file is modified', function () {
    $version = config('user-manual.version', '1.0');
    $introPath = rtrim(config('user-manual.content_path'), '/')."/{$version}/en/introduction.md";
    $pdfService = app(PdfGeneratorService::class);
    $indexKey = $pdfService->fullPdfIndexKey($version, 'en');

    $this->actingAs($this->makeAuthenticatable())
        ->get(route('user-manual.pdf.full', ['locale' => 'en']))
        ->assertOk();

    $keysBefore = Cache::get($indexKey, []);
    expect($keysBefore)->toHaveCount(1);

    // Editing content changes the max mtime baked into the cache key, so the
    // previous variant is not reused.
    touch($introPath, time() + 5);
    clearstatcache();

    $this->actingAs($this->makeAuthenticatable())
        ->get(route('user-manual.pdf.full', ['locale' => 'en']))
        ->assertOk();

    $keysAfter = Cache::get($indexKey, []);
    expect($keysAfter)->toHaveCount(2)
        ->and(array_values(array_diff($keysAfter, $keysBefore)))->toHaveCount(1);
});

it('does not serve a full manual pdf across differing permission levels', function () {
    // A viewer who can also read the restricted "material" page gets a larger,
    // multi-page manual.
    $broad = $this->actingAs($this->makeAuthenticatable(['material_access']))
        ->get(route('user-manual.pdf.full', ['locale' => 'en']));
    $broad->assertOk();
    $broadPdf = $broad->getContent();

    // A viewer limited to public pages must receive their own smaller manual,
    // never the broad viewer's cached copy (which would leak "material").
    $narrow = $this->actingAs($this->makeAuthenticatable())
        ->get(route('user-manual.pdf.full', ['locale' => 'en']));
    $narrow->assertOk();
    $narrowPdf = $narrow->getContent();

    expect($narrowPdf)->toContain('%PDF-')
        ->and($narrowPdf)->not->toBe($broadPdf)
        ->and(strlen($narrowPdf))->toBeLessThan(strlen($broadPdf));
});

it('warms full manual pdf variants so the first web export is a cache hit', function () {
    $version = config('user-manual.version', '1.0');
    $pdfService = app(PdfGeneratorService::class);
    $indexKey = $pdfService->fullPdfIndexKey($version, 'en');

    $this->artisan('user-manual:cache')->assertSuccessful();

    $keys = Cache::get($indexKey, []);
    expect($keys)->not->toBeEmpty();

    $warmedKey = $keys[0];
    expect(Cache::get($warmedKey))->toBe('disk')
        ->and(File::exists($pdfService->pdfCachePath($warmedKey)))->toBeTrue();

    $warmedPayload = File::get($pdfService->pdfCachePath($warmedKey));

    $response = $this->actingAs($this->makeAuthenticatable())
        ->get(route('user-manual.pdf.full', ['locale' => 'en']));

    $response->assertOk();
    expect($response->getContent())->toContain('%PDF-')
        ->and(Cache::get($warmedKey))->toBe('disk')
        ->and(File::get($pdfService->pdfCachePath($warmedKey)))->toBe($warmedPayload)
        ->and(Cache::get($indexKey, []))->toBe($keys);
});

it('skips full manual warming when pdf.warm_full is false', function () {
    config(['user-manual.pdf.warm_full' => false]);

    $version = config('user-manual.version', '1.0');
    $indexKey = app(PdfGeneratorService::class)->fullPdfIndexKey($version, 'en');

    $this->artisan('user-manual:cache')->assertSuccessful();

    expect(Cache::get($indexKey, []))->toBeEmpty();

    $response = $this->actingAs($this->makeAuthenticatable(['material_access']))
        ->get(route('user-manual.pdf.full', ['locale' => 'en']));

    $response->assertOk();
    expect($response->getContent())->toContain('%PDF-')
        ->and(Cache::get($indexKey, []))->toHaveCount(1);
});

it('warms distinct full pdf variants per access profile without leaking across users', function () {
    config(['user-manual.pdf.warm_profiles' => [
        [],
        ['material_access'],
    ]]);

    $version = config('user-manual.version', '1.0');
    $indexKey = app(PdfGeneratorService::class)->fullPdfIndexKey($version, 'en');

    $this->artisan('user-manual:cache')->assertSuccessful();

    expect(Cache::get($indexKey, []))->toHaveCount(2);

    $narrow = $this->actingAs($this->makeAuthenticatable())
        ->get(route('user-manual.pdf.full', ['locale' => 'en']));
    $narrow->assertOk();
    $narrowPdf = $narrow->getContent();

    $broad = $this->actingAs($this->makeAuthenticatable(['material_access']))
        ->get(route('user-manual.pdf.full', ['locale' => 'en']));
    $broad->assertOk();
    $broadPdf = $broad->getContent();

    expect($narrowPdf)->toContain('%PDF-')
        ->and($broadPdf)->toContain('%PDF-')
        ->and($narrowPdf)->not->toBe($broadPdf)
        ->and(strlen($narrowPdf))->toBeLessThan(strlen($broadPdf))
        ->and(Cache::get($indexKey, []))->toHaveCount(2);
});

it('exports multibyte pages larger than the pcre backtrack limit', function () {
    $contentRoot = rtrim(config('user-manual.content_path', resource_path('user-manual')), '/');
    $version = config('user-manual.version', '1.0');
    $localeDir = "{$contentRoot}/{$version}/bn";
    $filePath = "{$localeDir}/large-page.md";
    File::ensureDirectoryExists($localeDir);

    // Over the 1 MB pcre.backtrack_limit that mPDF enforces on every single
    // WriteHTML() call. Each Bengali codepoint is 3 bytes, so this exercises
    // both safe chunking and multibyte-boundary safety in one pass. A naive
    // byte-wise split would corrupt the text; no chunking at all would throw
    // an MpdfException.
    $paragraph = "এটি একটি দীর্ঘ পরীক্ষামূলক অনুচ্ছেদ যা বহু-বাইট ইউনিকোড অক্ষর ধারণ করে এবং পিডিএফ রপ্তানির সীমা যাচাই করে।\n\n";
    $markdown = "# বৃহৎ বহু-বাইট নথি\n\n".str_repeat($paragraph, 4000);
    expect(strlen($markdown))->toBeGreaterThan(1_000_000);
    File::put($filePath, $markdown);

    try {
        $response = app(PdfGeneratorService::class)->generatePagePdf('bn', 'large-page');

        expect($response->getStatusCode())->toBe(200);
        expect($response->getContent())->toContain('%PDF-');
        // A truncated/failed render would be tiny; a complete multi-page PDF is not.
        expect(strlen($response->getContent()))->toBeGreaterThan(50_000);
    } finally {
        if (File::exists($filePath)) {
            File::delete($filePath);
        }
        if (File::isDirectory($localeDir) && File::files($localeDir) === []) {
            File::deleteDirectory($localeDir);
        }
    }
});

it('embeds public directory images into the exported pdf', function () {
    $version = config('user-manual.version', '1.0');
    $pageFile = rtrim(config('user-manual.content_path'), '/')."/{$version}/en/image-page.md";

    $imageRel = 'user-manual-test/pixel.png';
    $imageFile = public_path($imageRel);
    File::ensureDirectoryExists(dirname($imageFile));
    File::put($imageFile, base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='
    ));
    File::put($pageFile, "# Image Page\n\nBefore.\n\n![Pixel](/{$imageRel})\n\nAfter.\n");

    try {
        $service = app(PdfGeneratorService::class);

        $withImage = $service->generatePagePdf('en', 'image-page');
        expect($withImage->getStatusCode())->toBe(200)
            ->and($withImage->getContent())->toContain('%PDF-');

        // Regenerate the same page without the image; the embedded image bytes
        // should make the first export larger, proving the image was included
        // rather than silently dropped.
        File::put($pageFile, "# Image Page\n\nBefore.\n\nAfter.\n");
        touch($pageFile, time() + 5);
        clearstatcache();
        $withoutImage = $service->generatePagePdf('en', 'image-page');

        expect(strlen($withImage->getContent()))->toBeGreaterThan(strlen($withoutImage->getContent()));
    } finally {
        File::delete($pageFile);
        File::delete($imageFile);
        File::deleteDirectory(public_path('user-manual-test'));
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
