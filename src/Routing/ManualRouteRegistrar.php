<?php

namespace MuhammadMahediHasan\UserManual\Routing;

use Illuminate\Support\Facades\Route;
use MuhammadMahediHasan\UserManual\Http\Controllers\DocsController;
use MuhammadMahediHasan\UserManual\Http\Controllers\PdfController;
use MuhammadMahediHasan\UserManual\Http\Middleware\SetCurrentManual;
use MuhammadMahediHasan\UserManual\Support\ManualAssets;
use MuhammadMahediHasan\UserManual\Support\ManualDefinition;
use MuhammadMahediHasan\UserManual\Support\ManualRegistry;

final class ManualRouteRegistrar
{
    public function __construct(private readonly ManualRegistry $manuals) {}

    public function register(): void
    {
        $this->registerAssetRoute();
        $this->manuals->assertDistinct();

        foreach ($this->manuals->all() as $manual) {
            $this->registerManual($manual);
        }
    }

    private function registerAssetRoute(): void
    {
        Route::get('vendor/user-manual/{path}', function (string $path) {
            $normalizedPath = str_replace(['..', '\\'], '', $path);
            $published = public_path('vendor/user-manual/'.$normalizedPath);

            if (is_file($published)) {
                return response()->file($published, ManualAssets::responseHeaders($normalizedPath));
            }

            $package = ManualAssets::sourcePath($normalizedPath);

            abort_unless(is_file($package), 404);

            return response()->file($package, ManualAssets::responseHeaders($normalizedPath));
        })->where('path', '.*')->name('user-manual.asset');
    }

    private function registerManual(ManualDefinition $manual): void
    {
        $prefix = $manual->routePrefix();
        $defaultLocale = $manual->stringOption('default_locale', 'en');
        $defaultPage = $manual->stringOption('default_page', 'introduction');
        $locales = implode('|', array_map(
            static fn (string $locale): string => preg_quote($locale, '/'),
            $manual->locales(),
        ));
        $middleware = $manual->middleware();

        if (! $manual->implicit) {
            $middleware[] = SetCurrentManual::class.':'.$manual->id;
        }

        $group = $manual->routeGroup;
        unset($group['middleware']);
        $group['middleware'] = $middleware;

        Route::group($group, function () use ($manual, $prefix, $defaultLocale, $defaultPage, $locales) {
            Route::redirect($prefix, "{$prefix}/{$defaultLocale}/{$defaultPage}");

            Route::get("{$prefix}/{locale}/export/pdf", [PdfController::class, 'exportFullPdf'])
                ->where('locale', $locales)
                ->name($manual->pdfFullRouteName());

            Route::get("{$prefix}/{locale}/{page}/pdf", [PdfController::class, 'exportPagePdf'])
                ->where('locale', $locales)
                ->where('page', '[a-z0-9\-]+')
                ->name($manual->pdfPageRouteName());

            Route::get("{$prefix}/{locale}/{page?}", [DocsController::class, 'show'])
                ->where('locale', $locales)
                ->where('page', '[a-z0-9\-]+')
                ->defaults('page', $defaultPage)
                ->name($manual->showRouteName());

            Route::redirect("{$prefix}/{page}", "{$prefix}/{$defaultLocale}/{page}")
                ->where('page', '[a-z0-9\-]+');
        });
    }
}
