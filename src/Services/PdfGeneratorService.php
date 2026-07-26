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
use MuhammadMahediHasan\UserManual\Support\Config;

readonly class PdfGeneratorService
{
    public function __construct(
        private MarkdownRenderer $markdownRenderer,
        private NavigationParser $navigationParser,
        private PermissionResolver $permissionResolver,
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

        $pdfBase64 = Cache::remember($cacheKey, $cacheTtl, function () use ($locale, $filePath, $page) {
            $markdown = File::get($filePath);
            $content = $this->markdownRenderer->render($markdown);
            $title = $this->markdownRenderer->extractTitle($markdown) ?? ucwords(str_replace('-', ' ', $page));

            /** @var view-string $viewName */
            $viewName = 'user-manual::pdf.page';

            return $this->renderMpdfView($viewName, compact('content', 'title', 'locale'), $locale);
        });

        return $this->makePdfResponse((string) $pdfBase64, "{$page}.pdf");
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

        $maxLastModified = $this->calculateMaxLastModified($locale, $version, $contentRoot, $navPath);
        $cachePrefix = Config::string('user-manual.cache_prefix', 'user-manual');
        $cacheTtl = Config::integer('user-manual.cache_ttl', 3600);
        $cacheKey = "{$cachePrefix}.pdf.full.{$version}.{$locale}.{$maxLastModified}";

        $pdfBase64 = Cache::remember($cacheKey, $cacheTtl, function () use ($locale, $navPath, $version, $contentRoot) {
            $tree = $this->navigationParser->buildTree($this->navigationParser->parse($navPath));
            $filteredTree = $this->permissionResolver->filterNavigation($tree);
            $items = $this->flattenTreeWithIndex($filteredTree);

            $pages = [];
            foreach ($items as $item) {
                $slug = $item['slug'];
                $filePath = "{$contentRoot}/{$version}/{$locale}/{$slug}.md";

                if (File::exists($filePath)) {
                    $markdown = File::get($filePath);
                    $content = $this->markdownRenderer->render($markdown);
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
            }

            return $this->renderMpdfView('user-manual::pdf.document', compact('pages', 'locale'), $locale);
        });

        $appName = Config::string('user-manual.ui.app_name', (string) config('app.name', 'user-manual'));
        $filename = Str::slug($appName)."-manual-{$locale}.pdf";

        return $this->makePdfResponse((string) $pdfBase64, $filename);
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
        $html = view($viewName, $data)->render();
        $mpdf = $this->createMpdfInstance($locale);

        $chunkSize = 250000;
        if (strlen($html) > $chunkSize) {
            $chunks = str_split($html, $chunkSize);
            foreach ($chunks as $chunk) {
                $mpdf->WriteHTML($chunk);
            }
        } else {
            $mpdf->WriteHTML($html);
        }

        return base64_encode($mpdf->Output('', Destination::STRING_RETURN));
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
