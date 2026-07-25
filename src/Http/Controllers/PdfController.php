<?php

namespace MuhammadMahediHasan\UserManual\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use MuhammadMahediHasan\UserManual\Services\PdfGeneratorService;
use MuhammadMahediHasan\UserManual\Services\PermissionResolver;
use MuhammadMahediHasan\UserManual\Support\Config;

class PdfController extends Controller
{
    public function __construct(
        private readonly PermissionResolver $permissionResolver,
        private readonly PdfGeneratorService $pdfGeneratorService,
    ) {}

    /**
     * Export a single documentation page as a PDF.
     */
    public function exportPagePdf(Request $request, string $locale, string $page): Response
    {
        abort_unless(Config::bool('user-manual.pdf.enabled', true), 404);

        $locales = Config::stringList('user-manual.locales', ['en']);
        if (! in_array($locale, $locales, true)) {
            abort(404);
        }

        abort_unless($this->permissionResolver->canAccessPage($page), 403);

        return $this->pdfGeneratorService->generatePagePdf($locale, $page);
    }

    /**
     * Export the full documentation manual (all accessible sections) as a single PDF.
     */
    public function exportFullPdf(Request $request, string $locale): Response
    {
        abort_unless(Config::bool('user-manual.pdf.enabled', true), 404);

        $locales = Config::stringList('user-manual.locales', ['en']);
        if (! in_array($locale, $locales, true)) {
            abort(404);
        }

        return $this->pdfGeneratorService->generateFullPdf($locale);
    }
}
