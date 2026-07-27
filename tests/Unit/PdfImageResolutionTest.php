<?php

use Illuminate\Support\Facades\File;
use MuhammadMahediHasan\UserManual\Services\PdfGeneratorService;

it('rewrites root-relative image sources to absolute public paths', function () {
    $relative = 'user-manual-test/pixel.png';
    $file = public_path($relative);
    File::ensureDirectoryExists(dirname($file));
    File::put($file, 'x');

    try {
        $html = app(PdfGeneratorService::class)
            ->resolveImageSources('<p><img src="/'.$relative.'" alt="a" /></p>');

        expect($html)->toContain('src="'.$file.'"')
            ->and($html)->not->toContain('src="/'.$relative.'"');
    } finally {
        File::delete($file);
        File::deleteDirectory(public_path('user-manual-test'));
    }
});

it('resolves root-relative sources that carry a query string', function () {
    $relative = 'user-manual-test/pixel.png';
    $file = public_path($relative);
    File::ensureDirectoryExists(dirname($file));
    File::put($file, 'x');

    try {
        $html = app(PdfGeneratorService::class)
            ->resolveImageSources('<img src="/'.$relative.'?v=2">');

        expect($html)->toContain('src="'.$file.'"');
    } finally {
        File::delete($file);
        File::deleteDirectory(public_path('user-manual-test'));
    }
});

it('leaves remote, data and unresolved image sources unchanged', function () {
    $html = '<img src="https://example.com/a.png">'
        .'<img src="data:image/png;base64,AAAA">'
        .'<img src="/does/not/exist.png">';

    expect(app(PdfGeneratorService::class)->resolveImageSources($html))->toBe($html);
});
