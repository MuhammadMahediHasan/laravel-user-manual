<?php

namespace MuhammadMahediHasan\UserManual\Pdf;

use League\CommonMark\Exception\CommonMarkException;
use Mpdf\MpdfException;
use MuhammadMahediHasan\UserManual\Events\FullPdfGenerated;
use MuhammadMahediHasan\UserManual\Services\PdfGeneratorService;
use MuhammadMahediHasan\UserManual\Support\Config;
use MuhammadMahediHasan\UserManual\Support\CurrentManual;
use MuhammadMahediHasan\UserManual\Support\ManualRegistry;
use MuhammadMahediHasan\UserManual\UserManualManager;
use RuntimeException;

/**
 * Generates and stores the full-manual PDF of one role. Shared by the queued
 * job and the user-manual:pdf command.
 */
class FullPdfGenerator
{
    public const GENERATED = 'generated';

    public const REUSED = 'reused';

    public const UNCHANGED = 'unchanged';

    public const FORGOTTEN = 'forgotten';

    public const SKIPPED = 'skipped';

    public function __construct(
        private readonly ManualRegistry $manuals,
        private readonly CurrentManual $currentManual,
        private readonly UserManualManager $userManual,
        private readonly PdfGeneratorService $pdfService,
        private readonly FullPdfStore $store,
    ) {}

    /**
     * @param  bool  $force  Render again even if the stored PDF is up to date.
     * @return self::* What happened.
     *
     * @throws MpdfException
     * @throws CommonMarkException
     */
    public function generate(string $manualId, string $locale, string $roleKey, bool $force = false): string
    {
        $manual = $this->manuals->find($manualId)
            ?? throw new RuntimeException("Unknown user manual [{$manualId}].");

        return $this->currentManual->using(
            $manual->implicit ? null : $manual->id,
            function () use ($manual, $locale, $roleKey, $force): string {
                $scope = $this->pdfService->scope($manual, $locale);

                if (! in_array($locale, $manual->locales(), true) || ! $this->pdfService->hasNavigation($locale)) {
                    return self::SKIPPED;
                }

                $profile = $this->userManual->roleProfile($manual, $roleKey);

                if ($profile === null) {
                    // The role was deleted since the job was queued.
                    $this->store->forgetRole($scope, $roleKey);

                    return self::FORGOTTEN;
                }

                $items = $this->pdfService->itemsForAccessProfile($locale, $profile);
                $signature = $this->pdfService->signature($items);
                $contentHash = $this->pdfService->contentHash($locale);
                $current = $this->store->entry($scope, $roleKey);

                if (! $force && $current !== null && $current['signature'] === $signature && $current['content_hash'] === $contentHash) {
                    return self::UNCHANGED;
                }

                // Another role with the same pages may already have this PDF.
                if (! $force && $this->store->findFile($scope, $signature, $contentHash) !== null) {
                    $path = $this->store->put($scope, $roleKey, $signature, $contentHash);
                    event(new FullPdfGenerated($manual->id, $locale, $roleKey, $path, reused: true));

                    return self::REUSED;
                }

                $tempFile = $this->tempFile();

                try {
                    $this->pdfService->writeFullPdf($locale, $items, $tempFile);
                    $path = $this->store->put($scope, $roleKey, $signature, $contentHash, $tempFile);
                } finally {
                    if (is_file($tempFile)) {
                        unlink($tempFile);
                    }
                }

                event(new FullPdfGenerated($manual->id, $locale, $roleKey, $path, reused: false));

                return self::GENERATED;
            },
        );
    }

    private function tempFile(): string
    {
        $directory = Config::string('user-manual.pdf.temp_dir', sys_get_temp_dir());
        $file = tempnam($directory, 'user-manual-');

        if ($file === false) {
            throw new RuntimeException("Unable to create a temporary file in [{$directory}].");
        }

        return $file;
    }
}
