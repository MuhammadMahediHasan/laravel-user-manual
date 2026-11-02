<?php

namespace MuhammadMahediHasan\UserManual\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use MuhammadMahediHasan\UserManual\Services\PdfGeneratorService;
use MuhammadMahediHasan\UserManual\Services\PermissionResolver;
use MuhammadMahediHasan\UserManual\Support\Config;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class PdfController extends Controller
{
    public function __construct(
        private readonly PermissionResolver $permissionResolver,
        private readonly PdfGeneratorService $pdfGeneratorService,
    ) {}

    /**
     * Export a single documentation page as a PDF, rendered on request.
     */
    public function exportPagePdf(Request $request, string $locale, string $page): Response
    {
        $this->abortUnlessEnabled($locale, 'page');

        abort_unless($this->permissionResolver->canAccessPage($page), 403);

        return $this->pdfGeneratorService->pagePdf($locale, $page);
    }

    /**
     * Serve the pre-generated full manual of the viewer's role.
     */
    public function exportFullPdf(Request $request, string $locale): SymfonyResponse
    {
        $this->abortUnlessEnabled($locale, 'full');

        $user = $this->permissionResolver->user();
        abort_if($user === null, 403);

        return $this->pdfGeneratorService->fullPdfResponse($request, $locale, $user);
    }

    private function abortUnlessEnabled(string $locale, string $type): void
    {
        abort_unless(
            Config::bool('user-manual.pdf.enabled', true) && Config::bool("user-manual.pdf.{$type}.enabled", true),
            404,
        );

        abort_unless(in_array($locale, Config::stringList('user-manual.locales', ['en']), true), 404);
    }
}
