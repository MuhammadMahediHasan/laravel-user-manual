<?php

namespace MuhammadMahediHasan\UserManual\Tests;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Auth\User;
use MuhammadMahediHasan\UserManual\Facades\UserManual;
use MuhammadMahediHasan\UserManual\UserManualServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            UserManualServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
        $app['config']->set('cache.default', 'array');
        $app['config']->set('auth.guards.admin', [
            'driver' => 'session',
            'provider' => 'users',
        ]);
        $app['config']->set('auth.providers.users', [
            'driver' => 'eloquent',
            'model' => User::class,
        ]);
        $app['config']->set('user-manual.content_path', __DIR__.'/fixtures/user-manual');
        $app['config']->set('user-manual.middleware', ['web', 'auth']);
        $app['config']->set('user-manual.auth_guards', ['web']);
        $app['config']->set('user-manual.cache_prefix', 'user-manual-test');
        $app['config']->set('user-manual.permission-mapper', [
            'introduction' => '*',
            'material' => ['material_access'],
            'translation' => ['roles' => ['Super Admin']],
        ]);
        $app['config']->set('user-manual.super_admin_roles', ['Super Admin']);
    }

    protected function defineRoutes($router): void
    {
        $router->get('/login', fn () => 'login')->name('login');
    }

    protected function getPackageAliases($app): array
    {
        return [
            'UserManual' => UserManual::class,
        ];
    }

    protected function makeAuthenticatable(
        array $permissions = [],
        array $roles = [],
    ): Authenticatable {
        $user = new class extends User
        {
            public array $testPermissions = [];

            public array $testRoles = [];

            public function can($ability, $arguments = []): bool
            {
                return in_array($ability, $this->testPermissions, true);
            }

            public function hasRole($roles, ?string $guard = null): bool
            {
                $roles = is_array($roles) ? $roles : [$roles];

                return count(array_intersect($roles, $this->testRoles)) > 0;
            }
        };

        $user->testPermissions = $permissions;
        $user->testRoles = $roles;

        return $user;
    }

    protected function makeBasicAuthenticatable(): Authenticatable
    {
        return new class implements Authenticatable
        {
            use \Illuminate\Auth\Authenticatable;

            public function getAuthIdentifierName(): string
            {
                return 'id';
            }

            public function getAuthIdentifier(): int
            {
                return 1;
            }

            public function getAuthPasswordName(): string
            {
                return 'password';
            }

            public function getAuthPassword(): string
            {
                return '';
            }

            public function getRememberToken(): ?string
            {
                return null;
            }

            public function setRememberToken($value): void {}

            public function getRememberTokenName(): string
            {
                return 'remember_token';
            }
        };
    }
}
