<?php

namespace MuhammadMahediHasan\UserManual\Http\Controllers;

use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use MuhammadMahediHasan\UserManual\Services\MarkdownRenderer;
use MuhammadMahediHasan\UserManual\Services\NavigationParser;
use MuhammadMahediHasan\UserManual\Services\PermissionResolver;
use MuhammadMahediHasan\UserManual\Support\ManualConfig;

class DocsController extends Controller
{
    public function __construct(
        private readonly MarkdownRenderer $markdownRenderer,
        private readonly NavigationParser $navigationParser,
        private readonly PermissionResolver $permissionResolver,
    ) {}

    /**
     * Render a docs page for the given locale and slug, applying permission checks and caching rendered markdown and navigation.
     */
    public function show(Request $request, string $locale, string $page): Factory|View
    {
        $locales = ManualConfig::stringList('user-manual.locales', ['en']);

        if (! in_array($locale, $locales, true)) {
            abort(404);
        }

        abort_unless($this->permissionResolver->canAccessPage($page), 403);

        if (ManualConfig::bool('user-manual.set_locale_on_visit', true)) {
            App::setLocale($locale);
            session([ManualConfig::string('user-manual.locale_session_key', 'locale') => $locale]);
        }

        $version = ManualConfig::string('user-manual.version', '1.0');
        $contentRoot = rtrim(ManualConfig::string('user-manual.content_path', resource_path('user-manual')), '/');
        $filePath = "{$contentRoot}/{$version}/{$locale}/{$page}.md";
        $navPath = "{$contentRoot}/{$version}/{$locale}/navigation.md";

        if (! File::exists($filePath)) {
            abort(404);
        }

        $markdown = File::get($filePath);
        $cachePrefix = ManualConfig::string('user-manual.cache_prefix', 'user-manual');
        $cacheTtl = ManualConfig::integer('user-manual.cache_ttl', 3600);
        $fileModified = File::lastModified($filePath);
        $navModified = File::lastModified($navPath);

        $content = Cache::remember("{$cachePrefix}.{$version}.{$locale}.{$page}.{$fileModified}", $cacheTtl, function () use ($markdown) {
            return $this->markdownRenderer->render($markdown);
        });

        $navigation = Cache::remember("{$cachePrefix}.{$version}.{$locale}.nav.tree.{$navModified}", $cacheTtl, function () use ($navPath) {
            return $this->navigationParser->buildTree($this->navigationParser->parse($navPath));
        });

        $navigation = $this->permissionResolver->filterNavigation($navigation);

        $title = $this->markdownRenderer->extractTitle($markdown) ?? ucwords(str_replace('-', ' ', $page));

        return view(ManualConfig::string('user-manual.view', 'user-manual::show'), compact('content', 'navigation', 'page', 'title', 'locale'));
    }
}
