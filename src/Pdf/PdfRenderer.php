<?php

namespace MuhammadMahediHasan\UserManual\Pdf;

use Illuminate\Support\Str;
use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
use Mpdf\Mpdf;
use Mpdf\MpdfException;
use Mpdf\Output\Destination;
use MuhammadMahediHasan\UserManual\Support\Config;

/**
 * Turns a Blade view into a PDF with mPDF. Knows nothing about manuals,
 * roles or storage.
 */
class PdfRenderer
{
    /**
     * Render a view and return the raw PDF bytes.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws MpdfException
     */
    public function renderToString(string $view, array $data, string $locale): string
    {
        return $this->write($view, $data, $locale)->Output('', Destination::STRING_RETURN);
    }

    /**
     * Render a view straight into a file, so a large manual is never held in
     * memory as one string.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws MpdfException
     */
    public function renderToFile(string $view, array $data, string $locale, string $path): void
    {
        $this->write($view, $data, $locale)->Output($path, Destination::FILE);
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws MpdfException
     */
    private function write(string $view, array $data, string $locale): Mpdf
    {
        /** @var view-string $view */
        $html = $this->resolveImageSources(view($view, $data)->render());
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

        return $mpdf;
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
