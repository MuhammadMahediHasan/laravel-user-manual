<?php

use MuhammadMahediHasan\UserManual\Services\MarkdownRenderer;

it('renders markdown to html', function () {
    $renderer = new MarkdownRenderer;

    $html = $renderer->render("# Hello\n\n**World**");

    expect($html)->toContain('<h1>Hello</h1>')
        ->and($html)->toContain('<strong>World</strong>');
});

it('extracts the first heading as title', function () {
    $renderer = new MarkdownRenderer;

    expect($renderer->extractTitle("# My Page\n\nBody"))->toBe('My Page');
});

it('returns null when markdown has no heading', function () {
    $renderer = new MarkdownRenderer;

    expect($renderer->extractTitle('Plain text only'))->toBeNull();
});

it('escapes raw html and blocks unsafe links to prevent stored xss', function () {
    $renderer = new MarkdownRenderer;

    $html = $renderer->render(<<<'MD'
# Safe page

<script>alert('xss')</script>

[Click me](javascript:alert('xss'))
MD);

    expect($html)->not->toMatch('/<script/i')
        ->and($html)->toContain('&lt;script')
        ->and($html)->not->toMatch('/href="javascript:/');
});
