<?php

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User;
use MuhammadMahediHasan\UserManual\Access\DefaultAccessChecker;
use MuhammadMahediHasan\UserManual\Contracts\AccessChecker;
use MuhammadMahediHasan\UserManual\Services\PdfGeneratorService;
use MuhammadMahediHasan\UserManual\Services\PermissionResolver;
use MuhammadMahediHasan\UserManual\UserManualManager;

class AllowListAccessChecker implements AccessChecker
{
    public function hasPermission(Authenticatable $user, string $permission): bool
    {
        return $permission === 'material_access';
    }

    public function hasAnyRole(Authenticatable $user, array $roles): bool
    {
        return false;
    }
}

/** A user with no hasRole() method: roles come from an attribute. */
function plainUser(mixed $roles): User
{
    $user = new User;
    $user->setRawAttributes([]);
    $user->setRelation('roles', $roles);

    return $user;
}

function resolverFor(Authenticatable $user): PermissionResolver
{
    return new PermissionResolver($user, new UserManualManager);
}

it('checks roles of users without hasRole() through roles.user_roles', function () {
    $editor = new class extends Model
    {
        protected $attributes = ['name' => 'Translator'];
    };

    expect(resolverFor(plainUser(collect([$editor])))->canAccessPage('translation'))->toBeFalse();

    config(['user-manual.permission-mapper.translation' => ['roles' => ['Translator']]]);

    expect(resolverFor(plainUser(collect([$editor])))->canAccessPage('translation'))->toBeTrue()
        ->and(resolverFor(plainUser(collect()))->canAccessPage('translation'))->toBeFalse();
});

it('applies super_admin_roles to a role column', function () {
    config(['user-manual.roles.user_roles' => 'role']);

    $user = new User;
    $user->setRawAttributes(['role' => 'Super Admin']);

    expect(resolverFor($user)->canAccessPage('material'))->toBeTrue();
});

it('still uses hasRole() when the user has it', function () {
    expect((new DefaultAccessChecker)->hasAnyRole($this->makeAuthenticatable(roles: ['Editor']), ['Editor']))->toBeTrue()
        ->and((new DefaultAccessChecker)->hasAnyRole($this->makeAuthenticatable(roles: ['Editor']), ['Admin']))->toBeFalse();
});

it('uses a custom access checker from config', function () {
    config(['user-manual.access_checker' => AllowListAccessChecker::class]);

    $user = $this->makeAuthenticatable();

    expect(resolverFor($user)->canAccessPage('material'))->toBeTrue()
        ->and(resolverFor($user)->canAccessPage('translation'))->toBeFalse();
});

it('rejects an access checker that does not implement the contract', function () {
    config(['user-manual.access_checker' => stdClass::class]);

    resolverFor($this->makeAuthenticatable())->canAccessPage('material');
})->throws(InvalidArgumentException::class);

it('does not use the access checker for role profiles of full pdfs', function () {
    config(['user-manual.access_checker' => AllowListAccessChecker::class]);

    $service = app(PdfGeneratorService::class);

    // The checker would deny every role check; the profile still decides.
    expect(array_column($service->itemsForAccessProfile('en', ['roles' => ['Super Admin']]), 'slug'))
        ->toBe(['introduction', 'material'])
        ->and(array_column($service->itemsForAccessProfile('en', []), 'slug'))
        ->toBe(['introduction']);
});
