<?php

namespace MuhammadMahediHasan\UserManual\Pdf;

/**
 * Identifies one set of full-manual PDFs: a manual, content version and locale.
 */
final class PdfScope
{
    public function __construct(
        public readonly string $manual,
        public readonly string $version,
        public readonly string $locale,
    ) {}
}
