<?php

use Illuminate\Contracts\Auth\Authenticatable;
use MuhammadMahediHasan\UserManual\Facades\UserManual;
use MuhammadMahediHasan\UserManual\Services\PermissionResolver;
use MuhammadMahediHasan\UserManual\UserManualManager;

it('allows public pages for authenticated users', function () {
    $resolver = new PermissionResolver(
        $this->makeAuthenticatable(),
        new UserManualManager,
    );

    expect($resolver->canAccessPage('introduction'))->toBeTrue();
});

it('allows pages mapped with star array wildcard', function () {
    config(['user-manual.permission-mapper.dashboard' => ['*']]);

    $resolver = new PermissionResolver(
        $this->makeAuthenticatable(),
        new UserManualManager,
    );

    expect($resolver->canAccessPage('dashboard'))->toBeTrue();
});

it('allows unlisted pages for authenticated users', function () {
    $resolver = new PermissionResolver(
        $this->makeAuthenticatable(),
        new UserManualManager,
    );

    expect($resolver->canAccessPage('unlisted-page'))->toBeTrue();
});

it('denies access when user is not authenticated', function () {
    $resolver = new PermissionResolver(null, new UserManualManager);

    expect($resolver->canAccessPage('introduction'))->toBeFalse();
});

it('denies restricted pages without permission', function () {
    $resolver = new PermissionResolver(
        $this->makeAuthenticatable(),
        new UserManualManager,
    );

    expect($resolver->canAccessPage('material'))->toBeFalse();
});

it('allows restricted pages with matching permission', function () {
    $resolver = new PermissionResolver(
        $this->makeAuthenticatable(['material_access']),
        new UserManualManager,
    );

    expect($resolver->canAccessPage('material'))->toBeTrue();
});

it('allows role based pages when user has required role', function () {
    config(['user-manual.permission-mapper.translation' => ['roles' => ['Editor']]]);

    $resolver = new PermissionResolver(
        $this->makeAuthenticatable(roles: ['Editor']),
        new UserManualManager,
    );

    expect($resolver->canAccessPage('translation'))->toBeTrue();
});

it('denies role based pages when user lacks required role', function () {
    config(['user-manual.permission-mapper.translation' => ['roles' => ['Editor']]]);

    $resolver = new PermissionResolver(
        $this->makeAuthenticatable(),
        new UserManualManager,
    );

    expect($resolver->canAccessPage('translation'))->toBeFalse();
});

it('allows super admin to access any page', function () {
    $resolver = new PermissionResolver(
        $this->makeAuthenticatable(roles: ['Super Admin']),
        new UserManualManager,
    );

    expect($resolver->canAccessPage('translation'))->toBeTrue();
});

it('denies access for users without can or hasRole methods', function () {
    $resolver = new PermissionResolver(
        $this->makeBasicAuthenticatable(),
        new UserManualManager,
    );

    expect($resolver->canAccessPage('material'))->toBeFalse();
});

it('resolves user from configured auth guards', function () {
    config(['user-manual.auth_guards' => ['web', 'admin']]);

    auth('admin')->login($this->makeAuthenticatable(['material_access']));

    $resolver = new PermissionResolver(null, new UserManualManager);

    expect($resolver->canAccessPage('material'))->toBeTrue();
});

it('uses injected user before request and guard resolution', function () {
    config(['user-manual.auth_guards' => ['admin']]);

    auth('admin')->login($this->makeAuthenticatable());

    $resolver = new PermissionResolver(
        $this->makeAuthenticatable(['material_access']),
        new UserManualManager,
    );

    expect($resolver->canAccessPage('material'))->toBeTrue();
});

it('parses slugs from locale prefixed urls', function () {
    $resolver = new PermissionResolver(null, new UserManualManager);

    expect($resolver->slugFromUrl('/user-manual/en/material'))->toBe('material');
});

it('parses slugs from legacy urls without locale', function () {
    $resolver = new PermissionResolver(null, new UserManualManager);

    expect($resolver->slugFromUrl('/user-manual/material'))->toBe('material');
});

it('parses slugs from arbitrary paths using basename', function () {
    $resolver = new PermissionResolver(null, new UserManualManager);

    expect($resolver->slugFromUrl('https://example.com/guides/setup'))->toBe('setup');
});

it('filters navigation by permissions', function () {
    $resolver = new PermissionResolver(
        $this->makeAuthenticatable(),
        new UserManualManager,
    );

    $navigation = [
        [
            'title' => 'Material',
            'url' => '/user-manual/en/material',
            'external' => false,
            'children' => [],
        ],
    ];

    expect($resolver->filterNavigation($navigation))->toBe([]);
});

it('keeps external navigation links regardless of permissions', function () {
    $resolver = new PermissionResolver(
        $this->makeAuthenticatable(),
        new UserManualManager,
    );

    $navigation = [
        [
            'title' => 'External',
            'url' => 'https://example.com/help',
            'external' => true,
            'children' => [],
        ],
    ];

    expect($resolver->filterNavigation($navigation))->toHaveCount(1);
});

it('keeps parent items when accessible children exist', function () {
    $resolver = new PermissionResolver(
        $this->makeAuthenticatable(['material_access']),
        new UserManualManager,
    );

    $navigation = [
        [
            'title' => 'LMS',
            'url' => '/user-manual/en/lms',
            'external' => false,
            'children' => [
                [
                    'title' => 'Material',
                    'url' => '/user-manual/en/material',
                    'external' => false,
                    'children' => [],
                ],
            ],
        ],
    ];

    $filtered = $resolver->filterNavigation($navigation);

    expect($filtered)->toHaveCount(1)
        ->and($filtered[0]['title'])->toBe('LMS')
        ->and($filtered[0]['children'])->toHaveCount(1);
});

it('uses custom access resolver when registered', function () {
    UserManual::resolveAccessUsing(fn (Authenticatable $user, string $slug, array $requirements) => $slug === 'material');

    $resolver = new PermissionResolver(
        $this->makeAuthenticatable(),
        app(UserManualManager::class),
    );

    expect($resolver->canAccessPage('material'))->toBeTrue()
        ->and($resolver->canAccessPage('translation'))->toBeFalse();
});

it('falls back to permission checks when custom resolver returns null', function () {
    UserManual::resolveAccessUsing(fn () => null);

    $resolver = new PermissionResolver(
        $this->makeAuthenticatable(['material_access']),
        app(UserManualManager::class),
    );

    expect($resolver->canAccessPage('material'))->toBeTrue();
});

it('allows non array requirement values for authenticated users', function () {
    config(['user-manual.permission-mapper.weird' => true]);

    $resolver = new PermissionResolver(
        $this->makeAuthenticatable(),
        new UserManualManager,
    );

    expect($resolver->canAccessPage('weird'))->toBeTrue();
});
