<?php

namespace MuhammadMahediHasan\UserManual\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use MuhammadMahediHasan\UserManual\Events\FullPdfGenerationFailed;
use MuhammadMahediHasan\UserManual\Pdf\FullPdfGenerator;
use Throwable;

/**
 * Generates the full-manual PDF of one role in the background.
 *
 * Unique per manual, locale and role: while one is waiting or running,
 * dispatching another is a no-op, so a page that keeps asking for a missing
 * PDF never floods the queue.
 *
 * Extend it and set `pdf.queue.job` to add middleware, backoff, etc.
 */
class GenerateFullManualPdf implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $timeout = 600;

    public int $tries = 1;

    public int $uniqueFor = 660;

    public function __construct(
        public readonly string $manual,
        public readonly string $locale,
        public readonly string $roleKey,
        public readonly bool $force = false,
    ) {}

    public function uniqueId(): string
    {
        return "{$this->manual}|{$this->locale}|{$this->roleKey}";
    }

    public function handle(FullPdfGenerator $generator): void
    {
        // Run inline after a web response (sync connection), the job would
        // inherit the request's max_execution_time and die half way. Queue
        // workers enforce $timeout themselves, so leave their limit alone.
        if (! app()->runningInConsole()) {
            ignore_user_abort(true);

            if ($this->timeout > 0 && function_exists('set_time_limit')) {
                set_time_limit($this->timeout);
            }
        }

        $generator->generate($this->manual, $this->locale, $this->roleKey, $this->force);
    }

    public function failed(?Throwable $exception = null): void
    {
        event(new FullPdfGenerationFailed($this->manual, $this->locale, $this->roleKey, $exception));
    }
}
