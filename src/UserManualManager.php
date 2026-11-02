<?php

namespace MuhammadMahediHasan\UserManual;

use Closure;
use Illuminate\Bus\UniqueLock;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use InvalidArgumentException;
use MuhammadMahediHasan\UserManual\Contracts\RoleResolver;
use MuhammadMahediHasan\UserManual\Jobs\GenerateFullManualPdf;
use MuhammadMahediHasan\UserManual\Pdf\FullPdfStore;
use MuhammadMahediHasan\UserManual\Pdf\PdfScope;
use MuhammadMahediHasan\UserManual\Roles\ClosureRoleResolver;
use MuhammadMahediHasan\UserManual\Roles\DefaultRoleResolver;
use MuhammadMahediHasan\UserManual\Support\Config;
use MuhammadMahediHasan\UserManual\Support\CurrentManual;
use MuhammadMahediHasan\UserManual\Support\ManualDefinition;
use MuhammadMahediHasan\UserManual\Support\ManualRegistry;

class UserManualManager
{
    /** @var (Closure(Authenticatable, string, array<string, mixed>): bool)|null */
    protected ?Closure $accessResolver = null;

    protected ?RoleResolver $roleResolver = null;

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

    /**
     * Use closures instead of a RoleResolver class (overrides `roles.resolver`).
     *
     * @param  Closure(ManualDefinition): iterable<int|string, array<int|string, mixed>|string>  $roles
     * @param  Closure(Authenticatable, ManualDefinition): (int|string|null)  $roleFor
     */
    public function resolveRolesUsing(Closure $roles, Closure $roleFor): static
    {
        $this->roleResolver = new ClosureRoleResolver($roles, $roleFor);

        return $this;
    }

    /**
     * Use a RoleResolver instance or class for every manual (overrides `roles.resolver`).
     *
     * @param  RoleResolver|class-string<RoleResolver>|null  $resolver  null goes back to the config.
     */
    public function useRoleResolver(RoleResolver|string|null $resolver): static
    {
        $this->roleResolver = is_string($resolver) ? $this->makeRoleResolver($resolver) : $resolver;

        return $this;
    }

    /**
     * The role resolver of the current manual.
     */
    public function roleResolver(): RoleResolver
    {
        if ($this->roleResolver !== null) {
            return $this->roleResolver;
        }

        return $this->makeRoleResolver(
            Config::string('user-manual.roles.resolver', DefaultRoleResolver::class),
        );
    }

    public function roleFor(Authenticatable $user, ManualDefinition $manual): string
    {
        $key = $this->roleResolver()->roleFor($user, $manual);

        return $key === null || $key === '' ? DefaultRoleResolver::ROLE : (string) $key;
    }

    /**
     * @return array<string, array<int|string, mixed>|string>
     */
    public function roles(ManualDefinition $manual): array
    {
        $roles = [];

        foreach ($this->roleResolver()->roles($manual) as $key => $profile) {
            $roles[(string) $key] = $profile;
        }

        return $roles;
    }

    /**
     * @return array<int|string, mixed>|string|null null when the role doesn't exist.
     */
    public function roleProfile(ManualDefinition $manual, string $roleKey): array|string|null
    {
        return $this->roles($manual)[$roleKey] ?? null;
    }

    /**
     * Queue the full-manual PDF of one role. A no-op while the same job is
     * already waiting or running.
     */
    public function dispatchFullPdf(ManualDefinition $manual, string $locale, string $roleKey, bool $force = false): void
    {
        $this->inManual($manual, function () use ($manual, $locale, $roleKey, $force): void {
            $job = $this->makeJob($manual, $locale, $roleKey, $force);
            $pending = dispatch($job);

            // On a sync connection the job would render inside the web request
            // and hit max_execution_time. Run it after the response is sent.
            if ($this->runsInline($job) && ! app()->runningInConsole()) {
                $pending->afterResponse();
            }
        });
    }

    /**
     * Release the "already queued" locks of full-PDF jobs, e.g. after a worker
     * or request was killed mid-job and left them behind. Returns the number of
     * locks checked. Without arguments: every role of every manual and locale.
     */
    public function releaseLocks(int|string|null $roleKey = null, ?string $manual = null): int
    {
        $released = 0;
        $lock = new UniqueLock(app(CacheRepository::class));

        foreach ($this->selectedManuals($manual) as $definition) {
            $released += $this->inManual($definition, function () use ($definition, $roleKey, $lock): int {
                $roleKeys = $roleKey === null ? array_keys($this->roles($definition)) : [(string) $roleKey];
                $count = 0;

                foreach ($definition->locales() as $locale) {
                    foreach ($roleKeys as $key) {
                        $lock->release($this->makeJob($definition, $locale, (string) $key));
                        $count++;
                    }
                }

                return $count;
            });
        }

        return $released;
    }

    /**
     * Re-generate a role's full PDFs in every manual and locale (or one manual),
     * for example after its permissions changed. The current PDF keeps being
     * served until the new one is ready.
     */
    public function regenerateRole(int|string $roleKey, ?string $manual = null): void
    {
        foreach ($this->selectedManuals($manual) as $definition) {
            foreach ($definition->locales() as $locale) {
                $this->dispatchFullPdf($definition, $locale, (string) $roleKey);
            }
        }
    }

    /**
     * Delete a role's full PDFs in every manual and locale (or one manual),
     * for example after the role was deleted.
     */
    public function forgetRole(int|string $roleKey, ?string $manual = null): void
    {
        $store = app(FullPdfStore::class);

        foreach ($this->selectedManuals($manual) as $definition) {
            $this->inManual($definition, function () use ($definition, $store, $roleKey): void {
                $version = Config::string('user-manual.version', '1.0');

                foreach ($definition->locales() as $locale) {
                    $store->forgetRole(new PdfScope($definition->id, $version, $locale), (string) $roleKey);
                }
            });
        }
    }

    /**
     * Build the configured job for one role. Must be called with the manual set
     * as current so its `pdf.queue` overrides apply.
     */
    public function makeJob(ManualDefinition $manual, string $locale, string $roleKey, bool $force = false): GenerateFullManualPdf
    {
        $class = Config::string('user-manual.pdf.queue.job', GenerateFullManualPdf::class);

        if (! is_a($class, GenerateFullManualPdf::class, true)) {
            throw new InvalidArgumentException('user-manual.pdf.queue.job must extend ['.GenerateFullManualPdf::class."], [{$class}] given.");
        }

        $job = new $class($manual->id, $locale, $roleKey, $force);
        $job->timeout = Config::integer('user-manual.pdf.queue.timeout', $job->timeout);
        $job->tries = Config::integer('user-manual.pdf.queue.tries', $job->tries);
        // By default a crashed job blocks re-queueing only a little longer than
        // it may run, not for an hour.
        $uniqueFor = Config::get('user-manual.pdf.queue.unique_for');
        $job->uniqueFor = is_numeric($uniqueFor) ? (int) $uniqueFor : $job->timeout + 60;

        $connection = Config::get('user-manual.pdf.queue.connection');
        $queue = Config::get('user-manual.pdf.queue.queue');

        if (is_string($connection) && $connection !== '') {
            $job->onConnection($connection);
        }

        if (is_string($queue) && $queue !== '') {
            $job->onQueue($queue);
        }

        return $job;
    }

    private function runsInline(GenerateFullManualPdf $job): bool
    {
        $connection = $job->connection ?? config('queue.default');

        return is_string($connection)
            && ($connection === 'sync' || config("queue.connections.{$connection}.driver") === 'sync');
    }

    /**
     * @return list<ManualDefinition>
     */
    private function selectedManuals(?string $manual): array
    {
        $selected = app(ManualRegistry::class)->selected($manual);

        if ($manual !== null && $selected === []) {
            throw new InvalidArgumentException("Unknown user manual [{$manual}].");
        }

        return $selected;
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    private function inManual(ManualDefinition $manual, callable $callback): mixed
    {
        return app(CurrentManual::class)->using($manual->implicit ? null : $manual->id, $callback);
    }

    private function makeRoleResolver(string $class): RoleResolver
    {
        $resolver = app($class);

        if (! $resolver instanceof RoleResolver) {
            throw new InvalidArgumentException("User manual role resolver [{$class}] must implement [".RoleResolver::class.'].');
        }

        return $resolver;
    }
}
