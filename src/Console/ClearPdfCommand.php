<?php

namespace MuhammadMahediHasan\UserManual\Console;

use Illuminate\Console\Command;
use MuhammadMahediHasan\UserManual\Pdf\FullPdfStore;
use MuhammadMahediHasan\UserManual\Support\CurrentManual;
use MuhammadMahediHasan\UserManual\Support\ManualRegistry;
use MuhammadMahediHasan\UserManual\UserManualManager;

class ClearPdfCommand extends Command
{
    protected $signature = 'user-manual:pdf-clear {manual? : Only this manual}';

    protected $description = 'Delete the pre-generated full user manual PDFs';

    public function handle(ManualRegistry $manuals, CurrentManual $currentManual, FullPdfStore $store, UserManualManager $userManual): int
    {
        $manualName = $this->argument('manual');
        $manualName = is_string($manualName) && $manualName !== '' ? $manualName : null;
        $selected = $manuals->selected($manualName);

        if ($selected === []) {
            $this->components->error("Unknown user manual [{$manualName}].");

            return self::FAILURE;
        }

        foreach ($selected as $manual) {
            // The manual may override pdf.disk / pdf.directory.
            $currentManual->using($manual->implicit ? null : $manual->id, fn () => $store->flush($manual->id));

            // Cleared PDFs must be re-queueable right away.
            $userManual->releaseLocks(null, $manual->implicit ? null : $manual->id);
        }

        $this->components->info('Deleted full manual PDFs of '.count($selected).' manual(s).');

        return self::SUCCESS;
    }
}
