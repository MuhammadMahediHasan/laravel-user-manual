<?php

namespace MuhammadMahediHasan\UserManual;

use Illuminate\Support\Facades\File;
use Illuminate\Support\ServiceProvider;
use MuhammadMahediHasan\UserManual\Console\CacheCommand;
use MuhammadMahediHasan\UserManual\Console\ClearCacheCommand;
use MuhammadMahediHasan\UserManual\Services\MarkdownRenderer;
use MuhammadMahediHasan\UserManual\Services\NavigationParser;
use MuhammadMahediHasan\UserManual\Services\PdfGeneratorService;
use MuhammadMahediHasan\UserManual\Services\PermissionResolver;
use MuhammadMahediHasan\UserManual\Support\Config;
use MuhammadMahediHasan\UserManual\Support\ManualAssets;

class UserManualServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/user-manual.php', 'user-manual');

        $this->app->singleton(UserManualManager::class);

        $this->app->bind(PermissionResolver::class, fn ($app) => new PermissionResolver(
            user: null,
            userManual: $app->make(UserManualManager::class),
        ));

        $this->app->singleton(MarkdownRenderer::class);
        $this->app->singleton(NavigationParser::class);
        $this->app->singleton(PdfGeneratorService::class);
    }

    public function boot(): void
    {
        $this->registerViews();
        $this->registerAssets();
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'user-manual');
        $this->ensureDemoDocsExist();

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/user-manual.php' => config_path('user-manual.php'),
            ], 'user-manual-config');

            $this->publishes([
                __DIR__.'/../resources/views' => resource_path('views/vendor/user-manual'),
            ], 'user-manual-views');

            $this->publishes([
                __DIR__.'/../lang' => lang_path('vendor/user-manual'),
            ], 'user-manual-lang');

            $this->publishes([
                __DIR__.'/../resources/assets' => public_path('vendor/user-manual'),
            ], 'user-manual-assets');

            $this->publishes([
                __DIR__.'/../stubs/docs' => resource_path('user-manual'),
            ], 'user-manual-docs');

            $this->publishes([
                __DIR__.'/../config/user-manual.php' => config_path('user-manual.php'),
                __DIR__.'/../resources/views' => resource_path('views/vendor/user-manual'),
                __DIR__.'/../lang' => lang_path('vendor/user-manual'),
                __DIR__.'/../resources/assets' => public_path('vendor/user-manual'),
                __DIR__.'/../stubs/docs' => resource_path('user-manual'),
            ], 'user-manual');

            $this->commands([
                ClearCacheCommand::class,
                CacheCommand::class,
            ]);
        }

        if (Config::bool('user-manual.register_routes', true)) {
            $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        }
    }

    protected function ensureDemoDocsExist(): void
    {
        $contentPath = rtrim(Config::string('user-manual.content_path', resource_path('user-manual')), '/');
        $version = Config::string('user-manual.version', '1.0');
        $defaultLocale = Config::string('user-manual.default_locale', 'en');

        $targetDir = "{$contentPath}/{$version}/{$defaultLocale}";
        $stubsDir = __DIR__.'/../stubs/docs/1.0/en';

        if (! File::isDirectory($stubsDir)) {
            return;
        }

        File::ensureDirectoryExists($targetDir);

        foreach (['navigation.md', 'introduction.md'] as $file) {
            $targetFile = "{$targetDir}/{$file}";
            $sourceFile = "{$stubsDir}/{$file}";

            if (! File::exists($targetFile) && File::exists($sourceFile)) {
                File::copy($sourceFile, $targetFile);
            }
        }
    }

    protected function registerAssets(): void
    {
        ManualAssets::ensurePublished();
    }

    protected function registerViews(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'user-manual');

        $publishedViews = resource_path('views/vendor/user-manual');

        if (is_dir($publishedViews)) {
            $this->loadViewsFrom($publishedViews, 'user-manual');
        }
    }
}
