<?php

use MuhammadMahediHasan\UserManual\Services\PermissionResolver;
use MuhammadMahediHasan\UserManual\Support\AccessProfile;
use MuhammadMahediHasan\UserManual\UserManualManager;

it('builds a public profile user with no permissions or roles', function () {
    $user = AccessProfile::makeUser([]);

    expect($user->can('material_access'))->toBeFalse()
        ->and($user->hasRole('Admin'))->toBeFalse();
});

it('builds a profile user with listed permissions and roles', function () {
    $user = AccessProfile::makeUser([
        'material_access',
        'roles' => ['Editor'],
    ]);

    expect($user->can('material_access'))->toBeTrue()
        ->and($user->can('other'))->toBeFalse()
        ->and($user->hasRole('Editor'))->toBeTrue()
        ->and($user->hasRole('Admin'))->toBeFalse();
});

it('builds an unrestricted profile for all and star', function () {
    foreach (['all', ['*']] as $profile) {
        $user = AccessProfile::makeUser($profile);

        expect($user->can('anything'))->toBeTrue()
            ->and($user->hasRole('anything'))->toBeTrue();
    }
});

it('lets unrestricted profiles pass permission-mapped pages', function () {
    config(['user-manual.permission-mapper.material' => ['material_access']]);

    $resolver = new PermissionResolver(
        AccessProfile::makeUser('all'),
        new UserManualManager,
    );

    expect($resolver->canAccessPage('material'))->toBeTrue();
});
