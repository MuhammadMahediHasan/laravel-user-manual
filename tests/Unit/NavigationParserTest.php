<?php

use MuhammadMahediHasan\UserManual\Services\NavigationParser;

it('parses navigation markdown links', function () {
    $parser = new NavigationParser;

    $items = $parser->parse(__DIR__.'/../fixtures/docs/1.0/en/navigation.md');

    expect($items)->toHaveCount(2)
        ->and($items[0]['title'])->toBe('Introduction')
        ->and($items[0]['url'])->toBe('/docs/en/introduction')
        ->and($items[0]['depth'])->toBe(0)
        ->and($items[1]['title'])->toBe('Material');
});

it('builds a nested navigation tree', function () {
    $parser = new NavigationParser;

    $items = [
        ['title' => 'Parent', 'url' => '/docs/en/parent', 'external' => false, 'depth' => 0],
        ['title' => 'Child', 'url' => '/docs/en/child', 'external' => false, 'depth' => 1],
    ];

    $tree = $parser->buildTree($items);

    expect($tree)->toHaveCount(1)
        ->and($tree[0]['title'])->toBe('Parent')
        ->and($tree[0]['children'])->toHaveCount(1)
        ->and($tree[0]['children'][0]['title'])->toBe('Child');
});

it('returns an empty list when navigation file is missing', function () {
    $parser = new NavigationParser;

    expect($parser->parse(__DIR__.'/../fixtures/docs/1.0/en/missing-navigation.md'))->toBe([]);
});

it('returns an empty tree for empty navigation items', function () {
    $parser = new NavigationParser;

    expect($parser->buildTree([]))->toBe([]);
});
