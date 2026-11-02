<?php

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Schema;
use MuhammadMahediHasan\UserManual\Roles\EloquentRoleResolver;
use MuhammadMahediHasan\UserManual\Services\PdfGeneratorService;
use MuhammadMahediHasan\UserManual\Support\ManualDefinition;

/*
| Two common shapes, no role package involved:
| - roles + permissions tables joined by a pivot (many-to-many users ↔ roles)
| - users.role_id → roles, with permissions in a JSON column ("abilities")
*/
class EloquentTestRole extends Model
{
    protected $table = 'roles';

    protected $guarded = [];

    protected $casts = ['abilities' => 'array'];

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(EloquentTestPermission::class, 'role_has_permissions', 'role_id', 'permission_id');
    }
}

class EloquentTestPermission extends Model
{
    protected $table = 'permissions';

    protected $guarded = [];
}

class EloquentTestUser extends User
{
    protected $table = 'users';

    protected $guarded = [];

    public function role(): BelongsTo
    {
        return $this->belongsTo(EloquentTestRole::class);
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(EloquentTestRole::class, 'role_user', 'user_id', 'role_id');
    }
}

class OnlyWebRoles
{
    public function __invoke(Builder $query, ManualDefinition $manual): void
    {
        $query->where('guard_name', 'web');
    }
}

beforeEach(function () {
    config([
        'database.default' => 'testing',
        'database.connections.testing' => ['driver' => 'sqlite', 'database' => ':memory:'],
        'user-manual.roles.model' => EloquentTestRole::class,
    ]);

    Schema::create('roles', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('guard_name')->default('web');
        $table->json('abilities')->nullable();
        $table->timestamps();
    });
    Schema::create('permissions', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->timestamps();
    });
    Schema::create('role_has_permissions', function (Blueprint $table) {
        $table->unsignedBigInteger('role_id');
        $table->unsignedBigInteger('permission_id');
    });
    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->foreignId('role_id')->nullable();
        $table->timestamps();
    });
    Schema::create('role_user', function (Blueprint $table) {
        $table->unsignedBigInteger('user_id');
        $table->unsignedBigInteger('role_id');
    });

    $material = EloquentTestPermission::create(['name' => 'material_access']);
    EloquentTestRole::create(['name' => 'Super Admin']);
    EloquentTestRole::create(['name' => 'Editor', 'abilities' => ['material_access']])->permissions()->attach($material);
    EloquentTestRole::create(['name' => 'Api', 'guard_name' => 'api']);
});

function eloquentRoles(): array
{
    return iterator_to_array((new EloquentRoleResolver)->roles(ManualDefinition::implicit()));
}

it('lists roles with permissions from a relation and their own name', function () {
    expect(eloquentRoles())->toBe([
        1 => ['roles' => ['Super Admin']],
        2 => ['material_access', 'roles' => ['Editor']],
        3 => ['roles' => ['Api']],
    ]);
});

it('reads permissions from an array column', function () {
    config(['user-manual.roles.permissions' => 'abilities']);

    expect(eloquentRoles()[2])->toBe(['material_access', 'roles' => ['Editor']]);
});

it('lists roles without permissions', function () {
    config(['user-manual.roles.permissions' => null]);

    expect(eloquentRoles()[2])->toBe(['roles' => ['Editor']]);
});

it('narrows roles with a scope class', function () {
    config(['user-manual.roles.scope' => OnlyWebRoles::class]);

    expect(array_keys(eloquentRoles()))->toBe([1, 2]);
});

it('finds the role of a user through a many-to-many relation', function () {
    $user = EloquentTestUser::create();
    $user->roles()->attach(2);

    expect((new EloquentRoleResolver)->roleFor($user->fresh(), ManualDefinition::implicit()))->toBe('2');
});

it('finds the role of a user through a role_id column', function () {
    $user = EloquentTestUser::create(['role_id' => 3]);

    config(['user-manual.roles.user_roles' => 'role']);
    expect((new EloquentRoleResolver)->roleFor($user, ManualDefinition::implicit()))->toBe('3');

    config(['user-manual.roles.user_roles' => 'role_id']);
    expect((new EloquentRoleResolver)->roleFor($user, ManualDefinition::implicit()))->toBe('3');
});

it('returns null for users without roles', function () {
    expect((new EloquentRoleResolver)->roleFor($this->makeBasicAuthenticatable(), ManualDefinition::implicit()))->toBeNull();
});

it('gives super admins every page through super_admin_roles', function () {
    $profile = eloquentRoles()[1];
    $items = app(PdfGeneratorService::class)->itemsForAccessProfile('en', $profile);

    expect(array_column($items, 'slug'))->toBe(['introduction', 'material']);
});

it('needs a model', function () {
    config(['user-manual.roles.model' => null]);

    eloquentRoles();
})->throws(InvalidArgumentException::class);
