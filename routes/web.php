<?php

use Illuminate\Support\Facades\Route;
use MuhammadMahediHasan\UserManual\Http\Controllers\DocsController;
use MuhammadMahediHasan\UserManual\Http\Controllers\PdfController;
use MuhammadMahediHasan\UserManual\Support\Config;
use MuhammadMahediHasan\UserManual\Support\ManualAssets;

$prefix = trim(Config::string('user-manual.route_prefix', 'user-manual'), '/');
$defaultLocale = Config::string('user-manual.default_locale', 'en');
$defaultPage = Config::string('user-manual.default_page', 'introduction');
$locales = implode('|', Config::stringList('user-manual.locales', ['en']));
$middleware = Config::stringList('user-manual.middleware', ['web']);
$routeName = Config::string('user-manual.route_name', 'user-manual.show');

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

Route::middleware($middleware)->group(function () use ($prefix, $defaultLocale, $defaultPage, $locales, $routeName) {
    Route::redirect($prefix, "{$prefix}/{$defaultLocale}/{$defaultPage}");

    Route::get("{$prefix}/{locale}/export/pdf", [PdfController::class, 'exportFullPdf'])
        ->where('locale', $locales)
        ->name('user-manual.pdf.full');

    Route::get("{$prefix}/{locale}/{page}/pdf", [PdfController::class, 'exportPagePdf'])
        ->where('locale', $locales)
        ->where('page', '[a-z0-9\-]+')
        ->name('user-manual.pdf.page');

    Route::get("{$prefix}/{locale}/{page?}", [DocsController::class, 'show'])
        ->where('locale', $locales)
        ->where('page', '[a-z0-9\-]+')
        ->defaults('page', $defaultPage)
        ->name($routeName);

    Route::redirect("{$prefix}/{page}", "{$prefix}/{$defaultLocale}/{page}")
        ->where('page', '[a-z0-9\-]+');
});
