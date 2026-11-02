<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Mpdf\Mpdf;
use MuhammadMahediHasan\UserManual\Pdf\PdfRenderer;
use MuhammadMahediHasan\UserManual\Services\PdfGeneratorService;

it('exports single page as pdf for authenticated users', function () {
    $response = $this->actingAs($this->makeAuthenticatable())
        ->get(route('user-manual.pdf.page', ['locale' => 'en', 'page' => 'introduction']));

    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/pdf');
    expect($response->getContent())->toContain('%PDF-');
});

it('renders single page pdfs on request without storing anything', function () {
    Storage::fake('local');

    $this->actingAs($this->makeAuthenticatable())
        ->get(route('user-manual.pdf.page', ['locale' => 'en', 'page' => 'introduction']))
        ->assertOk();

    expect(Storage::disk('local')->allFiles())->toBe([]);
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
        $response = app(PdfGeneratorService::class)->pagePdf('bn', 'large-page');

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

        $withImage = $service->pagePdf('en', 'image-page');
        expect($withImage->getStatusCode())->toBe(200)
            ->and($withImage->getContent())->toContain('%PDF-');

        // Regenerate the same page without the image; the embedded image bytes
        // should make the first export larger, proving the image was included
        // rather than silently dropped.
        File::put($pageFile, "# Image Page\n\nBefore.\n\nAfter.\n");
        touch($pageFile, time() + 5);
        clearstatcache();
        $withoutImage = $service->pagePdf('en', 'image-page');

        expect(strlen($withImage->getContent()))->toBeGreaterThan(strlen($withoutImage->getContent()));
    } finally {
        File::delete($pageFile);
        File::delete($imageFile);
        File::deleteDirectory(public_path('user-manual-test'));
    }
});

it('returns 404 for the export a toggle disables', function () {
    config(['user-manual.pdf.page.enabled' => false]);

    $this->actingAs($this->makeAuthenticatable())
        ->get(route('user-manual.pdf.page', ['locale' => 'en', 'page' => 'introduction']))
        ->assertNotFound();

    config(['user-manual.pdf.page.enabled' => true, 'user-manual.pdf.full.enabled' => false]);

    $this->actingAs($this->makeAuthenticatable())
        ->get(route('user-manual.pdf.page', ['locale' => 'en', 'page' => 'introduction']))
        ->assertOk();
    $this->actingAs($this->makeAuthenticatable())
        ->get(route('user-manual.pdf.full', ['locale' => 'en']))
        ->assertNotFound();
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

    $mpdf = app(PdfRenderer::class)->createMpdfInstance();

    expect($mpdf)->toBeInstanceOf(Mpdf::class);
});
