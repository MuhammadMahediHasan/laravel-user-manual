<?php

namespace MuhammadMahediHasan\UserManual\Support;

final class CurrentManual
{
    private ?string $id = null;

    public function id(): ?string
    {
        return $this->id;
    }

    public function set(?string $id): void
    {
        $this->id = $id;
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public function using(?string $id, callable $callback): mixed
    {
        $previous = $this->id;
        $this->id = $id;

        try {
            return $callback();
        } finally {
            $this->id = $previous;
        }
    }
}
