<?php

namespace MuhammadMahediHasan\UserManual\Support;

use InvalidArgumentException;

final class ManualRegistry
{
    public function __construct(private readonly CurrentManual $currentManual) {}

    /**
     * @return list<ManualDefinition>
     */
    public function all(): array
    {
        $configured = config('user-manual.manuals', []);

        if (! is_array($configured) || $configured === []) {
            return [ManualDefinition::implicit()];
        }

        $manuals = [];

        foreach ($configured as $id => $overrides) {
            if (! is_string($id) || ! is_array($overrides)) {
                throw new InvalidArgumentException('Each user manual must be a named array of settings.');
            }

            /** @var array<string, mixed> $overrides */
            $manuals[] = ManualDefinition::named($id, $overrides);
        }

        return $manuals;
    }

    public function find(string $id): ?ManualDefinition
    {
        foreach ($this->all() as $manual) {
            if ($manual->id === $id) {
                return $manual;
            }
        }

        return null;
    }

    public function current(): ManualDefinition
    {
        $id = $this->currentManual->id();

        if ($id === null) {
            return ManualDefinition::implicit();
        }

        return $this->find($id) ?? ManualDefinition::implicit();
    }

    /**
     * @return list<ManualDefinition>
     */
    public function selected(?string $id): array
    {
        if ($id === null || $id === '') {
            return $this->all();
        }

        $manual = $this->find($id);

        return $manual === null ? [] : [$manual];
    }

    public function assertDistinct(): void
    {
        $names = [];
        $paths = [];

        foreach ($this->all() as $manual) {
            foreach ($manual->routeNames() as $name) {
                if (isset($names[$name])) {
                    throw new InvalidArgumentException("Duplicate user manual route name [{$name}] for [{$manual->id}] and [{$names[$name]}].");
                }

                $names[$name] = $manual->id;
            }

            $path = $manual->matchKey();

            if (isset($paths[$path])) {
                throw new InvalidArgumentException("User manuals [{$manual->id}] and [{$paths[$path]}] register the same host and path [{$path}].");
            }

            $paths[$path] = $manual->id;
        }
    }
}
