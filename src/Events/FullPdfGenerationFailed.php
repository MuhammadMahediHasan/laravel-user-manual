<?php

namespace MuhammadMahediHasan\UserManual\Events;

use Throwable;

/**
 * A queued full-manual PDF job failed for good (after all its tries).
 */
class FullPdfGenerationFailed
{
    public function __construct(
        public readonly string $manual,
        public readonly string $locale,
        public readonly string $roleKey,
        public readonly ?Throwable $exception = null,
    ) {}
}
