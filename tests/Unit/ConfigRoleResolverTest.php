<?php

use Illuminate\Foundation\Auth\User;
use MuhammadMahediHasan\UserManual\Roles\ConfigRoleResolver;
use MuhammadMahediHasan\UserManual\Support\ManualDefinition;

enum ConfigTestRole: string
{
    case Editor = 'editor';
    case Member = 'member';
}

beforeEach(function () {
    config([
        'user-manual.roles.user_roles' => 'role',
        'user-manual.roles.list' => [
            'admin' => 'all',
            'editor' => ['material_access'],
            'member' => [],
        ],
    ]);
});

function userWithRole(mixed $role): User
{
    $user = new User;
    $user->setRawAttributes([]);
    $user->role = $role;

    return $user;
}

it('lists the configured roles with their own name as a role', function () {
    expect(iterator_to_array((new ConfigRoleResolver)->roles(ManualDefinition::implicit())))->toBe([
        'admin' => 'all',
        'editor' => ['material_access', 'roles' => ['editor']],
        'member' => ['roles' => ['member']],
    ]);
});

it('reads the user role from a string column', function () {
    expect((new ConfigRoleResolver)->roleFor(userWithRole('editor'), ManualDefinition::implicit()))->toBe('editor');
});

it('reads the user role from an enum', function () {
    expect((new ConfigRoleResolver)->roleFor(userWithRole(ConfigTestRole::Member), ManualDefinition::implicit()))->toBe('member');
});

it('returns null for a role that is not listed', function () {
    expect((new ConfigRoleResolver)->roleFor(userWithRole('guest'), ManualDefinition::implicit()))->toBeNull()
        ->and((new ConfigRoleResolver)->roleFor(userWithRole(null), ManualDefinition::implicit()))->toBeNull();
});
