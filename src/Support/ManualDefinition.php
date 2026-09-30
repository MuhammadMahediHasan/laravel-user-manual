<?php

namespace MuhammadMahediHasan\UserManual\Support;

use InvalidArgumentException;

final class ManualDefinition
{
    /**
     * @param  array<string, mixed>  $overrides
     * @param  array<string, mixed>  $routeGroup
     */
    private function __construct(
        public readonly string $id,
        public readonly bool $implicit,
        private readonly array $overrides,
        public readonly array $routeGroup,
    ) {}

    public static function implicit(): self
    {
        return new self('default', true, [], []);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public static function named(string $id, array $config): self
    {
        if (! preg_match('/^[A-Za-z0-9_-]+$/', $id)) {
            throw new InvalidArgumentException("Invalid user manual name [{$id}]. Use letters, numbers, hyphens, and underscores.");
        }

        $routeGroup = $config['route'] ?? [];
        unset($config['route']);

        if (! is_array($routeGroup)) {
            throw new InvalidArgumentException("User manual [{$id}] route group must be an array.");
        }

        /** @var array<string, mixed> $routeGroup */
        return new self($id, false, $config, $routeGroup);
    }

    public function showRouteName(): string
    {
        if ($this->implicit) {
            return $this->rootString('route_name', 'user-manual.show');
        }

        $name = $this->overrides['route_name'] ?? null;

        return is_string($name) && $name !== '' ? $name : $this->id.'.user-manual.show';
    }

    public function pdfPageRouteName(): string
    {
        if ($this->implicit) {
            return 'user-manual.pdf.page';
        }

        $name = $this->overrides['pdf_page_route_name'] ?? null;

        return is_string($name) && $name !== '' ? $name : $this->id.'.user-manual.pdf.page';
    }

    public function pdfFullRouteName(): string
    {
        if ($this->implicit) {
            return 'user-manual.pdf.full';
        }

        $name = $this->overrides['pdf_full_route_name'] ?? null;

        return is_string($name) && $name !== '' ? $name : $this->id.'.user-manual.pdf.full';
    }

    /**
     * @return list<string>
     */
    public function routeNames(): array
    {
        return [$this->showRouteName(), $this->pdfPageRouteName(), $this->pdfFullRouteName()];
    }

    public function routePrefix(): string
    {
        if ($this->implicit || ! array_key_exists('route_prefix', $this->overrides)) {
            return $this->rootString('route_prefix', 'user-manual');
        }

        $prefix = $this->overrides['route_prefix'];

        return trim(is_string($prefix) ? $prefix : 'user-manual', '/');
    }

    /**
     * Prefix visitors actually see, including an optional Laravel route-group prefix.
     */
    public function publicPrefix(): string
    {
        $groupPrefix = $this->routeGroup['prefix'] ?? '';
        $groupPrefix = is_string($groupPrefix) ? trim($groupPrefix, '/') : '';

        return trim($groupPrefix.'/'.$this->routePrefix(), '/');
    }

    public function matchKey(): string
    {
        $domain = $this->routeGroup['domain'] ?? '*';

        if (! is_string($domain) || $domain === '') {
            $domain = '*';
        }

        return $domain.'|'.$this->publicPrefix();
    }

    /**
     * @return list<string>
     */
    public function locales(): array
    {
        $locales = $this->implicit
            ? config('user-manual.locales', ['en'])
            : ($this->overrides['locales'] ?? config('user-manual.locales', ['en']));

        if (! is_array($locales) || $locales === []) {
            return ['en'];
        }

        return array_values(array_map(
            static fn (mixed $locale): string => (string) $locale,
            $locales,
        ));
    }

    /**
     * Middleware for this manual. A route-group `middleware` list replaces the manual list.
     *
     * @return list<string>
     */
    public function middleware(): array
    {
        if (array_key_exists('middleware', $this->routeGroup)) {
            return $this->normalizeMiddleware($this->routeGroup['middleware']);
        }

        if ($this->implicit || ! array_key_exists('middleware', $this->overrides)) {
            return $this->normalizeMiddleware(config('user-manual.middleware', ['web']));
        }

        return $this->normalizeMiddleware($this->overrides['middleware']);
    }

    public function stringOption(string $key, string $default): string
    {
        if (! $this->implicit && array_key_exists($key, $this->overrides) && is_string($this->overrides[$key])) {
            return $this->overrides[$key];
        }

        return $this->rootString($key, $default);
    }

    public function resolve(string $relativeKey, mixed $fallback): mixed
    {
        if ($this->implicit) {
            return $fallback;
        }

        if ($relativeKey === 'cache_prefix' && ! array_key_exists('cache_prefix', $this->overrides)) {
            $root = is_string($fallback) ? $fallback : 'user-manual';

            return $root.'.'.$this->id;
        }

        if ($relativeKey === 'route_name') {
            return $this->showRouteName();
        }

        if ($relativeKey === 'pdf_page_route_name') {
            return $this->pdfPageRouteName();
        }

        if ($relativeKey === 'pdf_full_route_name') {
            return $this->pdfFullRouteName();
        }

        if ($relativeKey === 'public_prefix') {
            return $this->publicPrefix();
        }

        if ($relativeKey === 'route_prefix') {
            return $this->routePrefix();
        }

        if (str_starts_with($relativeKey, 'permission-mapper')) {
            if (! array_key_exists('permission-mapper', $this->overrides)) {
                return $fallback;
            }

            return data_get($this->overrides, $relativeKey);
        }

        if (! $this->has($relativeKey)) {
            return $fallback;
        }

        $override = data_get($this->overrides, $relativeKey);
        $top = explode('.', $relativeKey, 2)[0];

        if (in_array($top, ['ui', 'pdf'], true) && is_array($override) && is_array($fallback)) {
            /** @var array<string, mixed> $fallback */
            /** @var array<string, mixed> $override */
            return array_replace_recursive($fallback, $override);
        }

        return $override;
    }

    private function has(string $relativeKey): bool
    {
        $cursor = $this->overrides;

        foreach (explode('.', $relativeKey) as $segment) {
            if (! is_array($cursor) || ! array_key_exists($segment, $cursor)) {
                return false;
            }

            $cursor = $cursor[$segment];
        }

        return true;
    }

    private function rootString(string $key, string $default): string
    {
        $value = config('user-manual.'.$key, $default);

        if (! is_string($value) || $value === '') {
            return $default;
        }

        return $key === 'route_prefix' ? trim($value, '/') : $value;
    }

    /**
     * @return list<string>
     */
    private function normalizeMiddleware(mixed $middleware): array
    {
        if (is_string($middleware) && $middleware !== '') {
            return [$middleware];
        }

        if (! is_array($middleware)) {
            return ['web'];
        }

        return array_values(array_map(
            static fn (mixed $item): string => (string) $item,
            $middleware,
        ));
    }
}
