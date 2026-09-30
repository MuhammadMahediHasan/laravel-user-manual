<?php

use MuhammadMahediHasan\UserManual\Support\Config;
use MuhammadMahediHasan\UserManual\Support\CurrentManual;
use MuhammadMahediHasan\UserManual\Support\ManualPath;
use MuhammadMahediHasan\UserManual\Support\ManualRegistry;

it('reads the root config when no manual is active', function () {
    config(['user-manual.cache_prefix' => 'root-prefix']);

    expect(Config::string('user-manual.cache_prefix', 'user-manual'))->toBe('root-prefix')
        ->and(Config::string('user-manual.route_name', 'user-manual.show'))->toBe('user-manual.show');
});

it('inherits root settings and overrides only the active manual', function () {
    config([
        'user-manual.cache_prefix' => 'root-prefix',
        'user-manual.ui.primary_color' => '#111111',
        'user-manual.manuals' => [
            'admin' => [
                'content_path' => '/tmp/admin-manual',
                'ui' => [
                    'app_name' => 'Admin',
                ],
            ],
        ],
    ]);

    $current = app(CurrentManual::class);
    $current->set('admin');

    try {
        expect(Config::string('user-manual.ui.app_name', 'Laravel'))->toBe('Admin')
            ->and(Config::string('user-manual.ui.primary_color', '#FF2D20'))->toBe('#111111')
            ->and(Config::string('user-manual.cache_prefix', 'user-manual'))->toBe('root-prefix.admin')
            ->and(Config::string('user-manual.route_name', 'user-manual.show'))->toBe('admin.user-manual.show')
            ->and(Config::string('user-manual.pdf_page_route_name', 'user-manual.pdf.page'))->toBe('admin.user-manual.pdf.page')
            ->and(Config::array('user-manual.ui', [])['app_name'])->toBe('Admin');
    } finally {
        $current->set(null);
    }
});

it('rejects manuals that would match the same host and path', function () {
    config(['user-manual.manuals' => [
        'staff' => ['route_prefix' => 'manual'],
        'partner' => ['route_prefix' => 'manual'],
    ]]);

    expect(fn () => app(ManualRegistry::class)->assertDistinct())
        ->toThrow(InvalidArgumentException::class);
});

it('allows the same path when route groups use different domains', function () {
    config(['user-manual.manuals' => [
        'portal' => [
            'route_prefix' => 'user-manual',
            'route' => ['domain' => 'abc.com'],
        ],
        'admin' => [
            'route_prefix' => 'user-manual',
            'route' => ['domain' => 'x.abc.com'],
        ],
    ]]);

    app(ManualRegistry::class)->assertDistinct();

    expect(true)->toBeTrue();
});

it('rejects duplicate route names', function () {
    config(['user-manual.manuals' => [
        'staff' => [
            'route_prefix' => 'manual',
            'route_name' => 'manual.show',
        ],
        'partner' => [
            'route_prefix' => 'partner/manual',
            'route_name' => 'manual.show',
        ],
    ]]);

    expect(fn () => app(ManualRegistry::class)->assertDistinct())
        ->toThrow(InvalidArgumentException::class);
});

it('reads a page slug from a multi-segment prefix', function () {
    expect(ManualPath::slug('/partner/manual/bn/material', 'partner/manual', ['en', 'bn']))->toBe('material')
        ->and(ManualPath::slug('/partner/manual/material', 'partner/manual', ['en', 'bn']))->toBe('material')
        ->and(ManualPath::slug('https://example.com/guides/setup', 'partner/manual', ['en', 'bn']))->toBe('setup');
});
