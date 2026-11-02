<?php

namespace MuhammadMahediHasan\UserManual\Facades;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Facade;
use MuhammadMahediHasan\UserManual\Contracts\RoleResolver;
use MuhammadMahediHasan\UserManual\Support\ManualDefinition;
use MuhammadMahediHasan\UserManual\UserManualManager;

/**
 * @method static UserManualManager resolveAccessUsing(\Closure $resolver)
 * @method static bool|null canAccess(Authenticatable $user, string $slug, array $requirements)
 * @method static UserManualManager resolveRolesUsing(\Closure $roles, \Closure $roleFor)
 * @method static UserManualManager useRoleResolver(RoleResolver|string|null $resolver)
 * @method static RoleResolver roleResolver()
 * @method static string roleFor(Authenticatable $user, ManualDefinition $manual)
 * @method static array roles(ManualDefinition $manual)
 * @method static void dispatchFullPdf(ManualDefinition $manual, string $locale, string $roleKey, bool $force = false)
 * @method static void regenerateRole(int|string $roleKey, ?string $manual = null)
 * @method static void forgetRole(int|string $roleKey, ?string $manual = null)
 * @method static int releaseLocks(int|string|null $roleKey = null, ?string $manual = null)
 *
 * @see UserManualManager
 */
class UserManual extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return UserManualManager::class;
    }
}
