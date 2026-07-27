<?php

use MuhammadMahediHasan\UserManual\Services\PdfGeneratorService;

function chunker(): PdfGeneratorService
{
    return app(PdfGeneratorService::class);
}

it('returns the html unchanged when it fits in a single chunk', function () {
    $html = '<p>Short content that is well under the limit.</p>';

    expect(chunker()->chunkHtml($html, 500000))->toBe([$html]);
});

it('splits large html losslessly at tag boundaries', function () {
    $html = str_repeat('<p>Some <strong>bold</strong> body text for chunking.</p>', 500);

    $chunks = chunker()->chunkHtml($html, 200);

    expect(implode('', $chunks))->toBe($html)
        ->and(count($chunks))->toBeGreaterThan(1);

    foreach ($chunks as $i => $chunk) {
        expect(strlen($chunk))->toBeLessThanOrEqual(200);

        // Every chunk except the last must end at a tag boundary.
        if ($i !== count($chunks) - 1) {
            expect(substr($chunk, -1))->toBe('>');
        }
    }
});

it('never splits a multibyte codepoint across chunks', function () {
    $html = str_repeat('<p>এটি একটি বহু-বাইট অনুচ্ছেদ।</p>', 400);

    $chunks = chunker()->chunkHtml($html, 150);

    expect(implode('', $chunks))->toBe($html)
        ->and(count($chunks))->toBeGreaterThan(1);

    foreach ($chunks as $chunk) {
        expect(mb_check_encoding($chunk, 'UTF-8'))->toBeTrue();
    }
});

it('keeps multibyte text intact even without tag boundaries in range', function () {
    // A long run of 3-byte characters with no '>' forces the hard-limit
    // fallback, which must back off UTF-8 continuation bytes.
    $html = str_repeat('অ', 500);

    $chunks = chunker()->chunkHtml($html, 100);

    expect(implode('', $chunks))->toBe($html)
        ->and(count($chunks))->toBeGreaterThan(1);

    foreach ($chunks as $chunk) {
        expect(strlen($chunk))->toBeLessThanOrEqual(100)
            ->and(mb_check_encoding($chunk, 'UTF-8'))->toBeTrue();
    }
});
