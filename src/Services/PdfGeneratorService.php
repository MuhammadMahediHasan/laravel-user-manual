<?php

namespace MuhammadMahediHasan\UserManual\Services;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use League\CommonMark\Exception\CommonMarkException;
use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
use Mpdf\Mpdf;
use Mpdf\MpdfException;
use Mpdf\Output\Destination;
use MuhammadMahediHasan\UserManual\Support\AccessProfile;
use MuhammadMahediHasan\UserManual\Support\Config;
use MuhammadMahediHasan\UserManual\UserManualManager;

readonly class PdfGeneratorService
{
    public function __construct(
        private MarkdownRenderer $markdownRenderer,
        private NavigationParser $navigationParser,
        private PermissionResolver $permissionResolver,
        private UserManualManager $userManual,
    ) {}

    /**
     * @throws MpdfException
     * @throws CommonMarkException
     */
    public function generatePagePdf(string $locale, string $page): Response
    {
        App::setLocale($locale);

        $version = Config::string('user-manual.version', '1.0');
        $contentRoot = rtrim(Config::string('user-manual.content_path', resource_path('user-manual')), '/');
        $filePath = "{$contentRoot}/{$version}/{$locale}/{$page}.md";

        if (! File::exists($filePath)) {
            abort(404);
        }

        $cachePrefix = Config::string('user-manual.cache_prefix', 'user-manual');
        $cacheTtl = Config::integer('user-manual.cache_ttl', 3600);
        $fileModified = File::lastModified($filePath);
        $cacheKey = "{$cachePrefix}.pdf.page.{$version}.{$locale}.{$page}.{$fileModified}";

        $pdfBase64 = $this->rememberPdf($cacheKey, $cacheTtl, function () use ($locale, $filePath, $page) {
            $markdown = File::get($filePath);
            $content = $this->markdownRenderer->render($markdown);
            $title = $this->markdownRenderer->extractTitle($markdown) ?? ucwords(str_replace('-', ' ', $page));

            /** @var view-string $viewName */
            $viewName = 'user-manual::pdf.page';

            return $this->renderMpdfView($viewName, compact('content', 'title', 'locale'), $locale);
        });

        return $this->makePdfResponse($pdfBase64, "{$page}.pdf");
    }

    /**
     * @throws MpdfException
     * @throws CommonMarkException
     */
    public function generateFullPdf(string $locale): Response
    {
        App::setLocale($locale);

        $version = Config::string('user-manual.version', '1.0');
        $contentRoot = rtrim(Config::string('user-manual.content_path', resource_path('user-manual')), '/');
        $navPath = "{$contentRoot}/{$version}/{$locale}/navigation.md";

        if (! File::exists($navPath)) {
            abort(404);
        }

        // Resolve the accessible page set for the current viewer *before*
        // caching. The rendered manual is permission-dependent, so its cache
        // key must be scoped by a signature of the accessible slugs. Without
        // this, the first viewer to populate the cache would define the manual
        // for everyone — leaking restricted pages to lower-privilege viewers,
        // or serving an empty manual warmed without a user.
        $tree = $this->navigationParser->buildTree($this->navigationParser->parse($navPath));
        $items = $this->flattenTreeWithIndex($this->permissionResolver->filterNavigation($tree));

        $pdfBase64 = $this->cacheFullPdfForItems($locale, $items);

        $appName = Config::string('user-manual.ui.app_name', (string) config('app.name', 'user-manual'));
        $filename = Str::slug($appName)."-manual-{$locale}.pdf";

        return $this->makePdfResponse($pdfBase64, $filename);
    }

    /**
     * Pre-generate and cache a full-manual PDF for an already-resolved page set.
     * Used by user-manual:cache with synthetic access profiles so the first web
     * export for a matching viewer is a cache hit.
     *
     * @param  list<array{slug: string, title: string, level: int, index: string}>  $items
     *
     * @throws MpdfException
     * @throws CommonMarkException
     */
    public function warmFullPdf(string $locale, array $items): void
    {
        if ($items === []) {
            return;
        }

        App::setLocale($locale);
        $this->cacheFullPdfForItems($locale, $items);
    }

    /**
     * Build the flattened, numbered page list a viewer with the given access
     * profile would see. Profiles are the same shapes as pdf.warm_profiles.
     *
     * @param  array<int|string, mixed>|string  $profile
     * @return list<array{slug: string, title: string, level: int, index: string}>
     */
    public function itemsForAccessProfile(string $locale, array|string $profile): array
    {
        $version = Config::string('user-manual.version', '1.0');
        $contentRoot = rtrim(Config::string('user-manual.content_path', resource_path('user-manual')), '/');
        $navPath = "{$contentRoot}/{$version}/{$locale}/navigation.md";

        if (! File::exists($navPath)) {
            return [];
        }

        $tree = $this->navigationParser->buildTree($this->navigationParser->parse($navPath));
        $resolver = new PermissionResolver(
            AccessProfile::makeUser($profile),
            $this->userManual,
        );

        return $this->flattenTreeWithIndex($resolver->filterNavigation($tree));
    }

    /**
     * @param  list<array{slug: string, title: string, level: int, index: string}>  $items
     *
     * @throws MpdfException
     * @throws CommonMarkException
     */
    private function cacheFullPdfForItems(string $locale, array $items): string
    {
        $version = Config::string('user-manual.version', '1.0');
        $contentRoot = rtrim(Config::string('user-manual.content_path', resource_path('user-manual')), '/');
        $navPath = "{$contentRoot}/{$version}/{$locale}/navigation.md";
        $maxLastModified = $this->calculateMaxLastModified($locale, $version, $contentRoot, $navPath);
        $cachePrefix = Config::string('user-manual.cache_prefix', 'user-manual');
        $cacheTtl = Config::integer('user-manual.cache_ttl', 3600);
        $cacheKey = "{$cachePrefix}.pdf.full.{$version}.{$locale}.{$maxLastModified}.{$this->accessSignature($items)}";

        $this->rememberFullPdfCacheKey($version, $locale, $cacheKey, $cacheTtl);

        return $this->rememberPdf($cacheKey, $cacheTtl, function () use ($items, $locale, $version, $contentRoot, $cachePrefix) {
            $pages = [];
            foreach ($items as $item) {
                $slug = $item['slug'];
                $filePath = "{$contentRoot}/{$version}/{$locale}/{$slug}.md";

                if (! File::exists($filePath)) {
                    continue;
                }

                $markdown = File::get($filePath);
                $fileModified = File::lastModified($filePath);
                $htmlKey = "{$cachePrefix}.{$version}.{$locale}.{$slug}.{$fileModified}";
                $cachedHtml = Cache::get($htmlKey);
                $content = is_string($cachedHtml)
                    ? $cachedHtml
                    : $this->markdownRenderer->render($markdown);
                $rawTitle = $this->markdownRenderer->extractTitle($markdown) ?? $item['title'];
                $pages[] = [
                    'slug' => $slug,
                    'title' => $rawTitle,
                    'full_title' => "{$item['index']} {$rawTitle}",
                    'level' => $item['level'],
                    'index' => $item['index'],
                    'content' => $content,
                ];
            }

            return $this->renderMpdfView('user-manual::pdf.document', compact('pages', 'locale'), $locale);
        });
    }

    /**
     * Cache key of the index tracking every full-manual PDF variant (one per
     * accessible page set) for a locale, so they can be invalidated as a group.
     */
    public function fullPdfIndexKey(string $version, string $locale): string
    {
        $cachePrefix = Config::string('user-manual.cache_prefix', 'user-manual');

        return "{$cachePrefix}.pdf.full.index.{$version}.{$locale}";
    }

    /**
     * Forget every cached full-manual PDF variant for a locale along with the
     * index that tracks them. Returns the number of PDF entries removed.
     */
    public function forgetFullPdfCaches(string $version, string $locale): int
    {
        $indexKey = $this->fullPdfIndexKey($version, $locale);
        $keys = Cache::get($indexKey, []);
        $cleared = 0;

        if (is_array($keys)) {
            foreach ($keys as $key) {
                if (is_string($key) && $this->forgetPdf($key)) {
                    $cleared++;
                }
            }
        }

        Cache::forget($indexKey);

        return $cleared;
    }

    /**
     * Absolute path of the on-disk file that holds a PDF cache payload.
     * Full manuals exceed MySQL mediumtext (~16 MB), so payloads live on disk
     * while the Laravel cache only stores a small marker for TTL/invalidation.
     */
    public function pdfCachePath(string $cacheKey): string
    {
        $directory = rtrim(Config::string(
            'user-manual.pdf.cache_path',
            storage_path('app/user-manual/pdfs'),
        ), '/');

        return $directory.'/'.sha1($cacheKey).'.b64';
    }

    /**
     * Remove a PDF cache entry (Laravel marker + on-disk payload). Returns true
     * when either the marker or the file was present.
     */
    public function forgetPdf(string $cacheKey): bool
    {
        $hadMarker = Cache::forget($cacheKey);
        $path = $this->pdfCachePath($cacheKey);
        $hadFile = File::exists($path);

        if ($hadFile) {
            File::delete($path);
        }

        return $hadMarker || $hadFile;
    }

    /**
     * Delete every on-disk PDF payload. Used by clear-cache so orphaned files
     * from old mtime/signature keys do not accumulate.
     */
    public function flushPdfCacheFiles(): void
    {
        $directory = rtrim(Config::string(
            'user-manual.pdf.cache_path',
            storage_path('app/user-manual/pdfs'),
        ), '/');

        if (File::isDirectory($directory)) {
            File::deleteDirectory($directory);
        }
    }

    /**
     * @param  callable(): string  $callback
     */
    private function rememberPdf(string $cacheKey, int $ttl, callable $callback): string
    {
        $cached = $this->getStoredPdf($cacheKey);

        if ($cached !== null) {
            return $cached;
        }

        $pdfBase64 = $callback();
        $this->storePdf($cacheKey, $pdfBase64, $ttl);

        return $pdfBase64;
    }

    private function getStoredPdf(string $cacheKey): ?string
    {
        if (! Cache::has($cacheKey)) {
            return null;
        }

        $marker = Cache::get($cacheKey);

        // Current format: small marker in cache, payload on disk.
        if ($marker === 'disk') {
            $path = $this->pdfCachePath($cacheKey);

            return File::exists($path) ? File::get($path) : null;
        }

        // Legacy format: base64 PDF stored directly in the cache store.
        if (is_string($marker) && $marker !== '') {
            return $marker;
        }

        return null;
    }

    private function storePdf(string $cacheKey, string $pdfBase64, int $ttl): void
    {
        $path = $this->pdfCachePath($cacheKey);
        File::ensureDirectoryExists(dirname($path));
        File::put($path, $pdfBase64);
        Cache::put($cacheKey, 'disk', $ttl);
    }

    /**
     * @param  list<array{slug: string, title: string, level: int, index: string}>  $items
     */
    private function accessSignature(array $items): string
    {
        return substr(sha1(implode('|', array_column($items, 'slug'))), 0, 12);
    }

    private function rememberFullPdfCacheKey(string $version, string $locale, string $cacheKey, int $ttl): void
    {
        $indexKey = $this->fullPdfIndexKey($version, $locale);
        $keys = Cache::get($indexKey, []);

        if (! is_array($keys)) {
            $keys = [];
        }

        if (! in_array($cacheKey, $keys, true)) {
            $keys[] = $cacheKey;
            Cache::put($indexKey, $keys, $ttl);
        }
    }

    public function calculateMaxLastModified(string $locale, string $version, string $contentRoot, string $navPath): int
    {
        $timestamps = [File::exists($navPath) ? File::lastModified($navPath) : 0];
        $localeDir = "{$contentRoot}/{$version}/{$locale}";

        if (File::isDirectory($localeDir)) {
            foreach (File::glob("{$localeDir}/*.md") as $file) {
                $timestamps[] = File::lastModified($file);
            }
        }

        return max($timestamps);
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws MpdfException
     */
    private function renderMpdfView(string $viewName, array $data, string $locale): string
    {
        $html = $this->resolveImageSources(view($viewName, $data)->render());
        $mpdf = $this->createMpdfInstance($locale);

        // mPDF parses HTML with PCRE and throws once the string exceeds
        // pcre.backtrack_limit (1 MB by default), so long manuals must be
        // written in chunks. Split on tag boundaries to keep each chunk both
        // well-formed and UTF-8 safe (see chunkHtml).
        $chunks = $this->chunkHtml($html);
        unset($html);

        foreach ($chunks as $chunk) {
            $mpdf->WriteHTML($chunk);
        }
        unset($chunks);

        // Base64 keeps the cached value ASCII-safe so it survives text-column
        // cache stores (e.g. the database driver); raw PDF bytes are not valid
        // UTF-8 and would throw a QueryException on write.
        return base64_encode($mpdf->Output('', Destination::STRING_RETURN));
    }

    /**
     * Rewrite every <img src> to an absolute local filesystem path so mPDF can
     * load it. Unlike a browser, mPDF has no request host, so root-relative
     * (e.g. "/storage/x.png") and relative image URLs cannot be resolved and
     * are silently dropped. Remote (http/https) and inline (data:) sources are
     * left untouched, as are paths that don't map to an existing public file.
     */
    public function resolveImageSources(string $html): string
    {
        return preg_replace_callback(
            '/(<img\b[^>]*?\bsrc\s*=\s*["\'])([^"\']*)(["\'])/i',
            fn (array $m): string => $m[1].$this->resolveImagePath($m[2]).$m[3],
            $html,
        ) ?? $html;
    }

    private function resolveImagePath(string $src): string
    {
        $src = trim($src);

        if ($src === '' || Str::startsWith($src, ['http://', 'https://', 'data:'])) {
            return $src;
        }

        $path = parse_url($src, PHP_URL_PATH) ?: $src;

        if (is_file($path)) {
            return $path;
        }

        $publicPath = public_path(ltrim($path, '/'));

        return is_file($publicPath) ? $publicPath : $src;
    }

    /**
     * Split HTML into chunks no larger than $maxBytes, cutting only immediately
     * after a '>' so a chunk never ends inside a tag or a multibyte UTF-8
     * sequence ('>' is single-byte ASCII and cannot occur inside one). The
     * default stays well under PHP's 1 MB pcre.backtrack_limit, which mPDF
     * enforces on every WriteHTML() call.
     *
     * @return list<string>
     */
    public function chunkHtml(string $html, int $maxBytes = 500000): array
    {
        $length = strlen($html);

        if ($length <= $maxBytes) {
            return [$html];
        }

        $chunks = [];
        $offset = 0;

        while ($offset < $length) {
            if ($length - $offset <= $maxBytes) {
                $chunks[] = substr($html, $offset);
                break;
            }

            $cut = strrpos(substr($html, $offset, $maxBytes), '>');

            if ($cut === false) {
                // No tag boundary in range (pathological): fall back to the hard
                // limit, but back off any trailing UTF-8 continuation bytes so a
                // multibyte character is never split across chunks.
                $cut = $maxBytes - 1;

                while ($cut > 0 && (ord($html[$offset + $cut + 1] ?? 'A') & 0xC0) === 0x80) {
                    $cut--;
                }
            }

            $end = $offset + $cut + 1;
            $chunks[] = substr($html, $offset, $end - $offset);
            $offset = $end;
        }

        return $chunks;
    }

    private function makePdfResponse(string $pdfBase64, string $filename): Response
    {
        $binary = base64_decode($pdfBase64);

        return response($binary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
            'Content-Length' => (string) strlen($binary),
        ]);
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
                $childrenResults = $this->flattenTreeWithIndex($children, $level + 1, "{$currentIndex}.");
                $result = array_merge($result, $childrenResults);
            }

            $counter++;
        }

        return $result;
    }

    /**
     * @throws MpdfException
     */
    public function createMpdfInstance(string $locale = 'en'): Mpdf
    {
        $defaultConfig = (new ConfigVariables)->getDefaults();
        $fontDirs = $defaultConfig['fontDir'];

        $defaultFontConfig = (new FontVariables)->getDefaults();
        $fontData = $defaultFontConfig['fontdata'];

        $customFontDirs = Config::array('user-manual.pdf.fonts.font_dirs', []);
        $rawCustomFontData = Config::array('user-manual.pdf.fonts.font_data', []);
        $customFontData = [];

        foreach ($rawCustomFontData as $fontKey => $fontSettings) {
            $lowerKey = strtolower((string) $fontKey);
            if (is_array($fontSettings) && isset($fontSettings['R'])) {
                if (! isset($fontSettings['B']) && is_string($fontSettings['R'])) {
                    $fontSettings['B'] = $fontSettings['R'];
                }
                if (! isset($fontSettings['I']) && is_string($fontSettings['R'])) {
                    $fontSettings['I'] = $fontSettings['R'];
                }
                if (! isset($fontSettings['BI']) && isset($fontSettings['B']) && is_string($fontSettings['B'])) {
                    $fontSettings['BI'] = $fontSettings['B'];
                }
                $customFontData[$lowerKey] = $fontSettings;
            }
        }

        $mpdfConfig = [
            'fontDir' => array_merge($fontDirs, $customFontDirs),
            'fontdata' => array_merge($fontData, $customFontData),
            'default_font' => Config::string('user-manual.pdf.default_font', 'sans-serif'),
            'defaultPageNumStyle' => $locale === 'bn' ? 'bengali' : '1',
            'autoScriptToLang' => true,
            'autoLangToFont' => false,
            'tempDir' => Config::string('user-manual.pdf.temp_dir', sys_get_temp_dir()),
            'mode' => 'utf-8',
            'format' => Config::string('user-manual.pdf.paper_format', 'A4'),
            'orientation' => Config::string('user-manual.pdf.orientation', 'P'),
            'margin_left' => Config::integer('user-manual.pdf.margins.left', 15),
            'margin_right' => Config::integer('user-manual.pdf.margins.right', 15),
            'margin_top' => Config::integer('user-manual.pdf.margins.top', 16),
            'margin_bottom' => Config::integer('user-manual.pdf.margins.bottom', 16),
            'margin_header' => Config::integer('user-manual.pdf.margins.header', 9),
            'margin_footer' => Config::integer('user-manual.pdf.margins.footer', 9),
        ];

        return new Mpdf($mpdfConfig);
    }
}
