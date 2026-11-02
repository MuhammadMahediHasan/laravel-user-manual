<?php

namespace MuhammadMahediHasan\UserManual\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use League\CommonMark\Exception\CommonMarkException;
use Mpdf\MpdfException;
use MuhammadMahediHasan\UserManual\Pdf\FullPdfStore;
use MuhammadMahediHasan\UserManual\Pdf\PdfRenderer;
use MuhammadMahediHasan\UserManual\Pdf\PdfScope;
use MuhammadMahediHasan\UserManual\Support\AccessProfile;
use MuhammadMahediHasan\UserManual\Support\Config;
use MuhammadMahediHasan\UserManual\Support\ManualDefinition;
use MuhammadMahediHasan\UserManual\Support\ManualRegistry;
use MuhammadMahediHasan\UserManual\UserManualManager;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Builds manual PDFs.
 *
 * - A single page is rendered on request and streamed; nothing is stored.
 * - The full manual is pre-generated per role (user-manual:pdf or a queued
 *   job) and only streamed from storage on request.
 */
class PdfGeneratorService
{
    public function __construct(
        private readonly MarkdownRenderer $markdownRenderer,
        private readonly NavigationParser $navigationParser,
        private readonly PermissionResolver $permissionResolver,
        private readonly UserManualManager $userManual,
        private readonly PdfRenderer $renderer,
        private readonly FullPdfStore $store,
        private readonly ManualRegistry $manuals,
    ) {}

    /**
     * Render one page as a PDF, right now.
     *
     * @throws MpdfException
     * @throws CommonMarkException
     */
    public function pagePdf(string $locale, string $page): Response
    {
        App::setLocale($locale);

        $filePath = $this->localeDirectory($locale)."/{$page}.md";

        if ($page === 'navigation' || ! File::exists($filePath)) {
            abort(404);
        }

        $markdown = File::get($filePath);
        $content = $this->markdownRenderer->render($markdown);
        $title = $this->markdownRenderer->extractTitle($markdown) ?? ucwords(str_replace('-', ' ', $page));

        $binary = $this->renderer->renderToString('user-manual::pdf.page', compact('content', 'title', 'locale'), $locale);

        return response($binary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$page.'.pdf"',
            'Content-Length' => (string) strlen($binary),
        ]);
    }

    /**
     * Serve the pre-generated full manual of the user's role.
     *
     * A missing PDF is queued (once: the job is unique) and the user is told
     * to come back. An outdated one is still served while a newer one is
     * generated in the background.
     */
    public function fullPdfResponse(Request $request, string $locale, Authenticatable $user): SymfonyResponse
    {
        $manual = $this->manuals->current();
        $scope = $this->scope($manual, $locale);
        $roleKey = $this->userManual->roleFor($user, $manual);
        $entry = $this->store->entry($scope, $roleKey);

        if ($entry !== null) {
            if ($entry['content_hash'] !== $this->contentHash($locale)) {
                $this->userManual->dispatchFullPdf($manual, $locale, $roleKey);
            }

            return $this->store->response($scope, $entry['file'], $this->downloadName($locale));
        }

        $this->userManual->dispatchFullPdf($manual, $locale, $roleKey);

        // A sync queue (or a fast worker) may already have produced it.
        $entry = $this->store->entry($scope, $roleKey);

        if ($entry !== null) {
            return $this->store->response($scope, $entry['file'], $this->downloadName($locale));
        }

        return $this->pendingResponse($request, $locale);
    }

    /**
     * Render the full manual for the given pages into a file.
     *
     * @param  list<array{slug: string, title: string, level: int, index: string}>  $items
     *
     * @throws MpdfException
     * @throws CommonMarkException
     */
    public function writeFullPdf(string $locale, array $items, string $path): void
    {
        App::setLocale($locale);

        $version = $this->version();
        $localeDir = $this->localeDirectory($locale);
        $cachePrefix = Config::string('user-manual.cache_prefix', 'user-manual');
        $pages = [];

        foreach ($items as $item) {
            $slug = $item['slug'];
            $filePath = "{$localeDir}/{$slug}.md";

            if (! File::exists($filePath)) {
                continue;
            }

            $markdown = File::get($filePath);
            $htmlKey = "{$cachePrefix}.{$version}.{$locale}.{$slug}.".File::lastModified($filePath);
            $cachedHtml = Cache::get($htmlKey);
            $rawTitle = $this->markdownRenderer->extractTitle($markdown) ?? $item['title'];

            $pages[] = [
                'slug' => $slug,
                'title' => $rawTitle,
                'full_title' => "{$item['index']} {$rawTitle}",
                'level' => $item['level'],
                'index' => $item['index'],
                'content' => is_string($cachedHtml) ? $cachedHtml : $this->markdownRenderer->render($markdown),
            ];
        }

        $this->renderer->renderToFile('user-manual::pdf.document', compact('pages', 'locale'), $locale, $path);
    }

    public function hasNavigation(string $locale): bool
    {
        return File::exists($this->localeDirectory($locale).'/navigation.md');
    }

    /**
     * The flattened, numbered page list a user with the given access profile
     * would see.
     *
     * @param  array<int|string, mixed>|string  $profile
     * @return list<array{slug: string, title: string, level: int, index: string}>
     */
    public function itemsForAccessProfile(string $locale, array|string $profile): array
    {
        $navPath = $this->localeDirectory($locale).'/navigation.md';

        if (! File::exists($navPath)) {
            return [];
        }

        $tree = $this->navigationParser->buildTree($this->navigationParser->parse($navPath));
        $resolver = new PermissionResolver(AccessProfile::makeUser($profile), $this->userManual);

        return $this->flattenTreeWithIndex($resolver->filterNavigation($tree));
    }

    /**
     * Identifies a page set: roles with the same signature share one PDF.
     *
     * @param  list<array{slug: string, title: string, level: int, index: string}>  $items
     */
    public function signature(array $items): string
    {
        return substr(sha1(implode('|', array_map(
            static fn (array $item): string => "{$item['index']}:{$item['slug']}",
            $items,
        ))), 0, 16);
    }

    /**
     * Changes whenever a markdown file of the locale is added, removed or edited.
     */
    public function contentHash(string $locale): string
    {
        $localeDir = $this->localeDirectory($locale);
        $parts = [];

        if (File::isDirectory($localeDir)) {
            foreach (File::glob("{$localeDir}/*.md") as $file) {
                $parts[] = basename($file).':'.File::lastModified($file);
            }
        }

        sort($parts);

        return substr(sha1(implode('|', $parts)), 0, 12);
    }

    public function scope(ManualDefinition $manual, string $locale): PdfScope
    {
        return new PdfScope($manual->id, $this->version(), $locale);
    }

    public function downloadName(string $locale): string
    {
        $appName = Config::string('user-manual.ui.app_name', (string) config('app.name', 'user-manual'));

        return Str::slug($appName)."-manual-{$locale}.pdf";
    }

    private function pendingResponse(Request $request, string $locale): SymfonyResponse
    {
        $retryAfter = Config::integer('user-manual.pdf.full.retry_after', 60);
        $headers = ['Retry-After' => (string) $retryAfter];
        $message = __('user-manual::messages.pdf_generating_message', [], $locale);

        if ($request->expectsJson()) {
            return new JsonResponse([
                'status' => 'generating',
                'message' => $message,
                'retry_after' => $retryAfter,
            ], 202, $headers);
        }

        /** @var view-string $view */
        $view = Config::string('user-manual.pdf.full.pending_view', 'user-manual::pdf.pending');

        return response()->view($view, [
            'locale' => $locale,
            'message' => $message,
            'retryAfter' => $retryAfter,
        ], 202, $headers);
    }

    private function version(): string
    {
        return Config::string('user-manual.version', '1.0');
    }

    private function localeDirectory(string $locale): string
    {
        $contentRoot = rtrim(Config::string('user-manual.content_path', resource_path('user-manual')), '/');

        return "{$contentRoot}/{$this->version()}/{$locale}";
    }

    /**
     * @param  list<array{title: string, url: string, external: bool, children: list<mixed>}>  $tree
     * @return list<array{slug: string, title: string, level: int, index: string}>
     */
    private function flattenTreeWithIndex(array $tree, int $level = 0, string $prefix = ''): array
    {
        $result = [];
        $counter = 1;

        foreach ($tree as $node) {
            $currentIndex = $prefix !== '' ? "{$prefix}{$counter}" : "{$counter}";

            if (! $node['external']) {
                $slug = $this->permissionResolver->slugFromUrl($node['url']);
                if ($slug !== '') {
                    $result[] = [
                        'slug' => $slug,
                        'title' => $node['title'],
                        'level' => $level,
                        'index' => $currentIndex,
                    ];
                }
            }

            if (! empty($node['children'])) {
                /** @var list<array{title: string, url: string, external: bool, children: list<mixed>}> $children */
                $children = $node['children'];
                $result = array_merge($result, $this->flattenTreeWithIndex($children, $level + 1, "{$currentIndex}."));
            }

            $counter++;
        }

        return $result;
    }
}
