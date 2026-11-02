<?php

namespace MuhammadMahediHasan\UserManual\Events;

/**
 * A role now points at an up-to-date full-manual PDF.
 */
class FullPdfGenerated
{
    public function __construct(
        public readonly string $manual,
        public readonly string $locale,
        public readonly string $roleKey,
        public readonly string $path,
        public readonly bool $reused = false,
    ) {}
}
