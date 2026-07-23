<?php

namespace MuhammadMahediHasan\UserManual\Services;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use League\CommonMark\Exception\CommonMarkException;
use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
use Mpdf\Mpdf;
use Mpdf\MpdfException;
use Mpdf\Output\Destination;
use MuhammadMahediHasan\UserManual\Support\ManualConfig;

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

        $version = ManualConfig::string('user-manual.version', '1.0');
        $contentRoot = rtrim(ManualConfig::string('user-manual.content_path', resource_path('user-manual')), '/');
        $filePath = "{$contentRoot}/{$version}/{$locale}/{$page}.md";

        if (! File::exists($filePath)) {
            abort(404);
        }

        $markdown = File::get($filePath);
        $content = $this->markdownRenderer->render($markdown);
        $title = $this->markdownRenderer->extractTitle($markdown) ?? ucwords(str_replace('-', ' ', $page));

        $viewName = 'user-manual::pdf.page';
        $html = view($viewName, compact('content', 'title', 'locale'))->render();

        $mpdf = $this->createMpdfInstance($locale);
        $mpdf->WriteHTML($html);

        $pdfContent = $mpdf->Output('', Destination::STRING_RETURN);

        return response($pdfContent, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$page.'.pdf"',
        ]);
    }

    /**
     * @throws MpdfException
     * @throws CommonMarkException
     */
    public function generateFullPdf(string $locale): Response
    {
        App::setLocale($locale);

        $version = ManualConfig::string('user-manual.version', '1.0');
        $contentRoot = rtrim(ManualConfig::string('user-manual.content_path', resource_path('user-manual')), '/');
        $navPath = "{$contentRoot}/{$version}/{$locale}/navigation.md";

        if (! File::exists($navPath)) {
            abort(404);
        }

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

        $viewName = 'user-manual::pdf.document';
        $html = view($viewName, compact('pages', 'locale'))->render();

        $mpdf = $this->createMpdfInstance($locale);
        $mpdf->WriteHTML($html);

        $pdfContent = $mpdf->Output('', Destination::STRING_RETURN);
        $appName = ManualConfig::string('user-manual.ui.app_name', (string) config('app.name', 'user-manual'));
        $filename = Str::slug($appName)."-manual-{$locale}.pdf";

        return response($pdfContent, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
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

    public function createMpdfInstance(string $locale = 'en'): Mpdf
    {
        $defaultConfig = (new ConfigVariables)->getDefaults();
        $fontDirs = $defaultConfig['fontDir'];

        $defaultFontConfig = (new FontVariables)->getDefaults();
        $fontData = $defaultFontConfig['fontdata'];

        $customFontDirs = ManualConfig::array('user-manual.pdf.fonts.font_dirs', []);
        $rawCustomFontData = ManualConfig::array('user-manual.pdf.fonts.font_data', []);
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
            'default_font' => ManualConfig::string('user-manual.pdf.default_font', 'sans-serif'),
            'defaultPageNumStyle' => $locale === 'bn' ? 'bengali' : '1',
            'autoScriptToLang' => true,
            'autoLangToFont' => false,
            'tempDir' => ManualConfig::string('user-manual.pdf.temp_dir', sys_get_temp_dir()),
            'mode' => 'utf-8',
            'format' => ManualConfig::string('user-manual.pdf.paper_format', 'A4'),
            'orientation' => ManualConfig::string('user-manual.pdf.orientation', 'P'),
            'margin_left' => ManualConfig::integer('user-manual.pdf.margins.left', 15),
            'margin_right' => ManualConfig::integer('user-manual.pdf.margins.right', 15),
            'margin_top' => ManualConfig::integer('user-manual.pdf.margins.top', 16),
            'margin_bottom' => ManualConfig::integer('user-manual.pdf.margins.bottom', 16),
            'margin_header' => ManualConfig::integer('user-manual.pdf.margins.header', 9),
            'margin_footer' => ManualConfig::integer('user-manual.pdf.margins.footer', 9),
        ];

        return new Mpdf($mpdfConfig);
    }
}
