<?php

namespace MuhammadMahediHasan\UserManual\Pdf;

use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Filesystem\FilesystemAdapter;
use MuhammadMahediHasan\UserManual\Support\Config;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Stores pre-generated full-manual PDFs on a Laravel filesystem disk.
 *
 * Layout under `pdf.directory` on `pdf.disk`:
 *
 *   {manual}/{version}/{locale}/manifest.json
 *   {manual}/{version}/{locale}/{signature}-{contentHash}.pdf
 *
 * The manifest maps each role key to a file. Roles that can read the same
 * pages share one file (same signature), and a content change produces a new
 * file name, so a file is only ever replaced by pointing the role at a new
 * one, then deleting the old one once no role uses it.
 *
 * @phpstan-type ManifestEntry array{file: string, signature: string, content_hash: string, generated_at: string}
 */
class FullPdfStore
{
    public function __construct(
        private readonly FilesystemFactory $filesystems,
        private readonly CacheFactory $cache,
    ) {}

    public function disk(): Filesystem
    {
        return $this->filesystems->disk(Config::string('user-manual.pdf.disk', 'local'));
    }

    public function rootDirectory(): string
    {
        return trim(Config::string('user-manual.pdf.directory', 'user-manual/pdfs'), '/');
    }

    public function directory(PdfScope $scope): string
    {
        return "{$this->rootDirectory()}/{$scope->manual}/{$scope->version}/{$scope->locale}";
    }

    public function path(PdfScope $scope, string $file): string
    {
        return $this->directory($scope).'/'.$file;
    }

    public function fileName(string $signature, string $contentHash): string
    {
        return "{$signature}-{$contentHash}.pdf";
    }

    /**
     * @return array<string, ManifestEntry>
     */
    public function manifest(PdfScope $scope): array
    {
        $path = $this->manifestPath($scope);
        $disk = $this->disk();

        if (! $disk->exists($path)) {
            return [];
        }

        $decoded = json_decode((string) $disk->get($path), true);

        if (! is_array($decoded)) {
            return [];
        }

        $manifest = [];

        foreach ($decoded as $roleKey => $entry) {
            if (is_array($entry) && isset($entry['file'], $entry['signature'], $entry['content_hash']) && is_string($entry['file'])) {
                $manifest[(string) $roleKey] = [
                    'file' => $entry['file'],
                    'signature' => (string) $entry['signature'],
                    'content_hash' => (string) $entry['content_hash'],
                    'generated_at' => (string) ($entry['generated_at'] ?? ''),
                ];
            }
        }

        return $manifest;
    }

    /**
     * The role's entry, only when its file is actually on the disk.
     *
     * @return ManifestEntry|null
     */
    public function entry(PdfScope $scope, string $roleKey): ?array
    {
        $entry = $this->manifest($scope)[$roleKey] ?? null;

        if ($entry === null || ! $this->disk()->exists($this->path($scope, $entry['file']))) {
            return null;
        }

        return $entry;
    }

    /**
     * An already stored file for this page set and content, if any role has one.
     */
    public function findFile(PdfScope $scope, string $signature, string $contentHash): ?string
    {
        $file = $this->fileName($signature, $contentHash);

        return $this->disk()->exists($this->path($scope, $file)) ? $file : null;
    }

    /**
     * Point a role at a PDF. When $localFile is given it is uploaded first, so
     * the manifest never references a file that isn't there. The role's
     * previous file is deleted afterwards if no other role still uses it.
     *
     * Returns the stored path on the disk.
     */
    public function put(PdfScope $scope, string $roleKey, string $signature, string $contentHash, ?string $localFile = null): string
    {
        $file = $this->fileName($signature, $contentHash);
        $path = $this->path($scope, $file);

        if ($localFile !== null) {
            $stream = fopen($localFile, 'rb');

            if ($stream === false) {
                throw new RuntimeException("Unable to read generated PDF [{$localFile}].");
            }

            try {
                $this->disk()->writeStream($path, $stream);
            } finally {
                if (is_resource($stream)) {
                    fclose($stream);
                }
            }
        } elseif (! $this->disk()->exists($path)) {
            throw new RuntimeException("PDF [{$path}] does not exist.");
        }

        $this->withManifestLock($scope, function () use ($scope, $roleKey, $file, $signature, $contentHash): void {
            $manifest = $this->manifest($scope);
            $previous = $manifest[$roleKey]['file'] ?? null;

            $manifest[$roleKey] = [
                'file' => $file,
                'signature' => $signature,
                'content_hash' => $contentHash,
                'generated_at' => now()->toIso8601String(),
            ];

            $this->writeManifest($scope, $manifest);

            if ($previous !== null && $previous !== $file) {
                $this->deleteIfUnused($scope, $manifest, $previous);
            }
        });

        return $path;
    }

    /**
     * Remove a role from the manifest and delete its file if no other role uses it.
     */
    public function forgetRole(PdfScope $scope, string $roleKey): bool
    {
        return $this->withManifestLock($scope, function () use ($scope, $roleKey): bool {
            $manifest = $this->manifest($scope);

            if (! isset($manifest[$roleKey])) {
                return false;
            }

            $file = $manifest[$roleKey]['file'];
            unset($manifest[$roleKey]);

            $this->writeManifest($scope, $manifest);
            $this->deleteIfUnused($scope, $manifest, $file);

            return true;
        });
    }

    /**
     * Drop manifest entries for roles that no longer exist (when $roleKeys is
     * given) and delete every PDF no role references. Returns files deleted.
     *
     * @param  list<string>|null  $roleKeys
     */
    public function prune(PdfScope $scope, ?array $roleKeys = null): int
    {
        return $this->withManifestLock($scope, function () use ($scope, $roleKeys): int {
            $manifest = $this->manifest($scope);

            if ($roleKeys !== null) {
                $kept = array_intersect_key($manifest, array_flip($roleKeys));

                if (count($kept) !== count($manifest)) {
                    $manifest = $kept;
                    $this->writeManifest($scope, $manifest);
                }
            }

            $used = array_flip(array_column($manifest, 'file'));
            $deleted = 0;

            foreach ($this->disk()->files($this->directory($scope)) as $path) {
                $file = basename($path);

                if (str_ends_with($file, '.pdf') && ! isset($used[$file])) {
                    $this->disk()->delete($path);
                    $deleted++;
                }
            }

            return $deleted;
        });
    }

    /**
     * Delete every stored PDF of a manual (all versions and locales).
     */
    public function flush(string $manual): void
    {
        $this->disk()->deleteDirectory("{$this->rootDirectory()}/{$manual}");
    }

    /**
     * Stream a stored PDF to the browser without loading it into memory.
     */
    public function response(PdfScope $scope, string $file, string $downloadName): StreamedResponse
    {
        $disk = $this->disk();
        $path = $this->path($scope, $file);
        $headers = ['Content-Type' => 'application/pdf'];

        if ($disk instanceof FilesystemAdapter) {
            return $disk->response($path, $downloadName, $headers, 'inline');
        }

        return new StreamedResponse(function () use ($disk, $path): void {
            $stream = $disk->readStream($path);

            if (is_resource($stream)) {
                fpassthru($stream);
                fclose($stream);
            }
        }, 200, $headers + ['Content-Disposition' => 'inline; filename="'.$downloadName.'"']);
    }

    private function manifestPath(PdfScope $scope): string
    {
        return $this->directory($scope).'/manifest.json';
    }

    /**
     * @param  array<string, ManifestEntry>  $manifest
     */
    private function writeManifest(PdfScope $scope, array $manifest): void
    {
        ksort($manifest);

        $this->disk()->put(
            $this->manifestPath($scope),
            (string) json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
        );
    }

    /**
     * @param  array<string, ManifestEntry>  $manifest
     */
    private function deleteIfUnused(PdfScope $scope, array $manifest, string $file): void
    {
        if (! in_array($file, array_column($manifest, 'file'), true)) {
            $this->disk()->delete($this->path($scope, $file));
        }
    }

    /**
     * Run a manifest read-modify-write under a cache lock so parallel queue
     * workers (possibly on different servers) don't overwrite each other.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    private function withManifestLock(PdfScope $scope, callable $callback): mixed
    {
        $store = $this->cache->store()->getStore();

        if (! $store instanceof LockProvider) {
            return $callback();
        }

        return $store->lock('user-manual.pdf.manifest.'.sha1($this->directory($scope)), 30)
            ->block(15, $callback);
    }
}
