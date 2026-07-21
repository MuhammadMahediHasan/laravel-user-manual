<?php

namespace MuhammadMahediHasan\UserManual\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use MuhammadMahediHasan\UserManual\Support\ManualConfig;
use MuhammadMahediHasan\UserManual\UserManualManager;

class PermissionResolver
{
    public function __construct(
        private readonly ?Authenticatable $user = null,
        private readonly ?UserManualManager $userManual = null,
    ) {}

    public function canAccessPage(string $slug): bool
    {
        $user = $this->authenticatedUser();

        if ($user === null) {
            return false;
        }

        if ($this->isSuperAdmin($user)) {
            return true;
        }

        $requirements = config("user-manual.permission-mapper.{$slug}");

        if ($requirements === null || $this->requiresNoPermissionCheck($requirements)) {
            return true;
        }

        if (! is_array($requirements)) {
            return true;
        }

        $resolved = $this->userManual()->canAccess($user, $slug, $requirements);

        if ($resolved !== null) {
            return $resolved;
        }

        return $this->matchesRequirements($user, $requirements);
    }

    /**
     * @param  list<array{title: string, url: string, external: bool, children: list<mixed>}>  $navigation
     * @return list<array{title: string, url: string, external: bool, children: list<mixed>}>
     */
    public function filterNavigation(array $navigation): array
    {
        $filtered = [];

        foreach ($navigation as $item) {
            $children = $this->filterNavigation($item['children']);

            if ($item['external']) {
                $item['children'] = $children;
                $filtered[] = $item;

                continue;
            }

            $slug = $this->slugFromUrl($item['url']);

            if ($children !== []) {
                $item['children'] = $children;
                $filtered[] = $item;

                continue;
            }

            if ($slug !== '' && $this->canAccessPage($slug)) {
                $item['children'] = [];
                $filtered[] = $item;
            }
        }

        return $filtered;
    }

    public function slugFromUrl(string $url): string
    {
        $routePrefix = trim(ManualConfig::string('user-manual.route_prefix', 'user-manual'), '/');
        $locales = ManualConfig::stringList('user-manual.locales', ['en']);

        $path = trim(parse_url($url, PHP_URL_PATH) ?? '', '/');
        $segments = explode('/', $path);

        if ($segments[0] === $routePrefix && in_array($segments[1] ?? '', $locales, true)) {
            return $segments[2] ?? '';
        }

        if ($segments[0] === $routePrefix) {
            return $segments[1] ?? '';
        }

        return basename($path);
    }

    private function requiresNoPermissionCheck(mixed $requirements): bool
    {
        if ($requirements === '*') {
            return true;
        }

        return is_array($requirements) && in_array('*', $requirements, true);
    }

    /**
     * @param  array<int, string>|array{roles?: list<string>}  $requirements
     */
    private function matchesRequirements(Authenticatable $user, array $requirements): bool
    {
        if (isset($requirements['roles']) && is_array($requirements['roles'])) {
            return $this->userHasAnyRole($user, $requirements['roles']);
        }

        foreach ($requirements as $key => $value) {
            if ($key === 'roles') {
                continue;
            }

            if (is_string($value) && $this->userCan($user, $value)) {
                return true;
            }
        }

        return false;
    }

    private function isSuperAdmin(Authenticatable $user): bool
    {
        $roles = ManualConfig::stringList('user-manual.super_admin_roles', []);

        if ($roles === []) {
            return false;
        }

        return $this->userHasAnyRole($user, $roles);
    }

    /**
     * @param  list<string>  $roles
     */
    private function userHasAnyRole(Authenticatable $user, array $roles): bool
    {
        if (! method_exists($user, 'hasRole')) {
            return false;
        }

        return $user->hasRole($roles);
    }

    private function userCan(Authenticatable $user, string $permission): bool
    {
        return method_exists($user, 'can') && $user->can($permission);
    }

    private function userManual(): UserManualManager
    {
        return $this->userManual ?? app(UserManualManager::class);
    }

    private function authenticatedUser(): ?Authenticatable
    {
        if ($this->user !== null) {
            return $this->user;
        }

        if ($user = request()->user()) {
            return $user;
        }

        foreach (ManualConfig::stringList('user-manual.auth_guards', []) as $guard) {
            if ($user = auth($guard)->user()) {
                return $user;
            }
        }

        return null;
    }
}
