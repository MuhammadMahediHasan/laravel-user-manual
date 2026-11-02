<?php

namespace MuhammadMahediHasan\UserManual\Console;

use Illuminate\Console\Command;
use MuhammadMahediHasan\UserManual\Pdf\FullPdfGenerator;
use MuhammadMahediHasan\UserManual\Pdf\FullPdfStore;
use MuhammadMahediHasan\UserManual\Services\PdfGeneratorService;
use MuhammadMahediHasan\UserManual\Support\Config;
use MuhammadMahediHasan\UserManual\Support\CurrentManual;
use MuhammadMahediHasan\UserManual\Support\ManualDefinition;
use MuhammadMahediHasan\UserManual\Support\ManualRegistry;
use MuhammadMahediHasan\UserManual\UserManualManager;
use Throwable;

class GeneratePdfCommand extends Command
{
    protected $signature = 'user-manual:pdf
        {manual? : Only this manual}
        {--role=* : Only these role keys}
        {--locale=* : Only these locales}
        {--queue : Queue one job per role instead of generating here}
        {--force : Render again even when the stored PDF is up to date}
        {--unlock : Release "already queued" locks left by killed jobs first}';

    protected $description = 'Pre-generate the full user manual PDF of every role';

    public function handle(
        ManualRegistry $manuals,
        CurrentManual $currentManual,
        UserManualManager $userManual,
        FullPdfGenerator $generator,
        FullPdfStore $store,
        PdfGeneratorService $pdfService,
    ): int {
        $manualName = $this->argument('manual');
        $manualName = is_string($manualName) && $manualName !== '' ? $manualName : null;
        $selected = $manuals->selected($manualName);

        if ($selected === []) {
            $this->components->error("Unknown user manual [{$manualName}].");

            return self::FAILURE;
        }

        /** @var list<string> $onlyRoles */
        $onlyRoles = array_values(array_filter((array) $this->option('role'), 'is_string'));
        /** @var list<string> $onlyLocales */
        $onlyLocales = array_values(array_filter((array) $this->option('locale'), 'is_string'));
        $queue = (bool) $this->option('queue');
        $force = (bool) $this->option('force');
        $failed = false;

        if ($this->option('unlock')) {
            $released = 0;

            foreach ($selected as $manual) {
                foreach ($onlyRoles === [] ? [null] : $onlyRoles as $role) {
                    $released += $userManual->releaseLocks($role, $manual->implicit ? null : $manual->id);
                }
            }

            $this->components->info("Released {$released} full PDF job lock(s).");
        }

        foreach ($selected as $manual) {
            $currentManual->using($manual->implicit ? null : $manual->id, function () use (
                $manual, $userManual, $generator, $store, $pdfService, $onlyRoles, $onlyLocales, $queue, $force, &$failed,
            ): void {
                if (! Config::bool('user-manual.pdf.enabled', true) || ! Config::bool('user-manual.pdf.full.enabled', true)) {
                    $this->components->warn("Full PDF export is disabled for [{$manual->id}].");

                    return;
                }

                $roleKeys = array_keys($userManual->roles($manual));
                $targets = $onlyRoles === [] ? $roleKeys : array_values(array_intersect($roleKeys, $onlyRoles));

                foreach (array_diff($onlyRoles, $roleKeys) as $unknown) {
                    $this->components->warn("Unknown role [{$unknown}] in [{$manual->id}].");
                }

                foreach ($manual->locales() as $locale) {
                    if ($onlyLocales !== [] && ! in_array($locale, $onlyLocales, true)) {
                        continue;
                    }

                    foreach ($targets as $roleKey) {
                        $failed = ! $this->generateOne($manual, $locale, $roleKey, $queue, $force, $userManual, $generator) || $failed;
                    }

                    if (! $queue) {
                        // Without a role filter the full role list is known, so
                        // entries of deleted roles can go too.
                        $deleted = $store->prune($pdfService->scope($manual, $locale), $onlyRoles === [] ? $roleKeys : null);

                        if ($deleted > 0) {
                            $this->components->info("Deleted {$deleted} unused PDF(s) of [{$manual->id}/{$locale}].");
                        }
                    }
                }
            });
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    private function generateOne(
        ManualDefinition $manual,
        string $locale,
        string $roleKey,
        bool $queue,
        bool $force,
        UserManualManager $userManual,
        FullPdfGenerator $generator,
    ): bool {
        $label = "{$manual->id} · {$locale} · role {$roleKey}";

        if ($queue) {
            $userManual->dispatchFullPdf($manual, $locale, $roleKey, $force);
            $this->components->twoColumnDetail($label, 'queued');

            return true;
        }

        try {
            $result = '';
            $this->components->task($label, function () use ($generator, $manual, $locale, $roleKey, $force, &$result): bool {
                $result = $generator->generate($manual->id, $locale, $roleKey, $force);

                return true;
            });

            if ($this->output->isVerbose()) {
                $this->line("  <fg=gray>{$result}</>");
            }

            return true;
        } catch (Throwable $e) {
            $this->components->error("{$label}: {$e->getMessage()}");
            report($e);

            return false;
        }
    }
}
