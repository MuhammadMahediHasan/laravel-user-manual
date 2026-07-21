<?php

namespace MuhammadMahediHasan\UserManual;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;

class UserManualManager
{
    /** @var (Closure(Authenticatable, string, array<string, mixed>): bool)|null */
    protected ?Closure $accessResolver = null;

    /**
     * @param  Closure(Authenticatable, string, array<string, mixed>): bool  $resolver
     */
    public function resolveAccessUsing(Closure $resolver): static
    {
        $this->accessResolver = $resolver;

        return $this;
    }

    /**
     * @param  array<string, mixed>  $requirements
     */
    public function canAccess(Authenticatable $user, string $slug, array $requirements): ?bool
    {
        if ($this->accessResolver === null) {
            return null;
        }

        return ($this->accessResolver)($user, $slug, $requirements);
    }
}
