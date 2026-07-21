<?php

namespace MuhammadMahediHasan\UserManual\Facades;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Facade;
use MuhammadMahediHasan\UserManual\UserManualManager;

/**
 * @method static UserManualManager resolveAccessUsing(\Closure $resolver)
 * @method static bool|null canAccess(Authenticatable $user, string $slug, array $requirements)
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
