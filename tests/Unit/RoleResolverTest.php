<?php

use Illuminate\Contracts\Auth\Authenticatable;
use MuhammadMahediHasan\UserManual\Contracts\RoleResolver;
use MuhammadMahediHasan\UserManual\Facades\UserManual;
use MuhammadMahediHasan\UserManual\Roles\DefaultRoleResolver;
use MuhammadMahediHasan\UserManual\Support\ManualDefinition;
use MuhammadMahediHasan\UserManual\UserManualManager;

class FixedRoleResolver implements RoleResolver
{
    public function roles(ManualDefinition $manual): iterable
    {
        return [1 => ['material_access'], 2 => 'all'];
    }

    public function roleFor(Authenticatable $user, ManualDefinition $manual): int
    {
        return 2;
    }
}

afterEach(fn () => UserManual::useRoleResolver(null));

it('uses one default role for everyone out of the box', function () {
    $manager = new UserManualManager;
    $manual = ManualDefinition::implicit();

    expect($manager->roleResolver())->toBeInstanceOf(DefaultRoleResolver::class)
        ->and($manager->roles($manual))->toBe(['default' => []])
        ->and($manager->roleFor($this->makeAuthenticatable(), $manual))->toBe('default');
});

it('uses the resolver class from config with string role keys', function () {
    config(['user-manual.roles.resolver' => FixedRoleResolver::class]);

    $manager = new UserManualManager;
    $manual = ManualDefinition::implicit();

    expect($manager->roles($manual))->toBe(['1' => ['material_access'], '2' => 'all'])
        ->and($manager->roleFor($this->makeAuthenticatable(), $manual))->toBe('2')
        ->and($manager->roleProfile($manual, '2'))->toBe('all')
        ->and($manager->roleProfile($manual, '3'))->toBeNull()
        ->and(app(RoleResolver::class))->toBeInstanceOf(FixedRoleResolver::class);
});

it('lets closures override the config', function () {
    config(['user-manual.roles.resolver' => FixedRoleResolver::class]);

    UserManual::resolveRolesUsing(fn () => ['admin' => 'all'], fn () => null);

    $manual = ManualDefinition::implicit();

    expect(UserManual::roles($manual))->toBe(['admin' => 'all'])
        ->and(UserManual::roleFor($this->makeAuthenticatable(), $manual))->toBe('default');
});

it('rejects a resolver that does not implement the contract', function () {
    config(['user-manual.roles.resolver' => stdClass::class]);

    (new UserManualManager)->roleResolver();
})->throws(InvalidArgumentException::class);
