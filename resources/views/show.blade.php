<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', $locale) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @php
        use MuhammadMahediHasan\UserManual\Support\ManualAssets;
        use MuhammadMahediHasan\UserManual\Support\ManualConfig;

        $appName = ManualConfig::string('user-manual.ui.app_name', (string) config('app.name', 'Laravel'));
        $primaryColor = ManualConfig::string('user-manual.ui.primary_color', '#FF2D20');
        $routeName = ManualConfig::string('user-manual.route_name', 'user-manual.show');
        $locales = ManualConfig::stringList('user-manual.locales', ['en']);
        $viteAssets = ManualConfig::array('user-manual.ui.vite_assets', []);
    @endphp
    <title>{{ $title }} — {{ __('user-manual::messages.title') }} | {{ $appName }}</title>
    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="{{ ManualAssets::url('css/user-manual.css') }}">

    <style>
        :root {
            --user-manual-primary: {{ $primaryColor }};
        }
    </style>

    @if (!empty($viteAssets))
        @vite($viteAssets)
    @endif
</head>

<body class="user-manual">

    <div class="user-manual__shell">
        <div id="user-manual-sidebar-overlay" class="user-manual__overlay" data-user-manual-toggle="sidebar"></div>

        <aside id="user-manual-sidebar" class="user-manual__sidebar">
            <div class="user-manual__sidebar-header">
                <a href="{{ url(ManualConfig::string('user-manual.ui.home_url', '/')) }}" class="user-manual__brand">
                    @if ($logoUrl = ManualConfig::string('user-manual.ui.logo_url', ''))
                        <img src="{{ $logoUrl }}" alt="{{ $appName }}" class="user-manual__brand-logo">
                    @else
                        {{ $appName }}
                    @endif
                </a>
            </div>

            <div class="user-manual__sidebar-meta">
                <p class="user-manual__sidebar-title">{{ __('user-manual::messages.title') }}</p>
                <p class="user-manual__sidebar-version">{{ __('user-manual::messages.version') }}</p>
            </div>

            <nav class="user-manual__sidebar-nav">
                @include('user-manual::partials.nav', ['items' => $navigation, 'page' => $page])
            </nav>
        </aside>

        <div class="user-manual__main">
            <header class="user-manual__header">
                <div class="user-manual__header-start">
                    <button type="button" class="user-manual__menu-button" data-user-manual-toggle="sidebar"
                        aria-label="{{ __('user-manual::messages.toggle_navigation') }}">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"
                            fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                            stroke-linejoin="round" aria-hidden="true">
                            <line x1="4" x2="20" y1="12" y2="12" />
                            <line x1="4" x2="20" y1="6" y2="6" />
                            <line x1="4" x2="20" y1="18" y2="18" />
                        </svg>
                    </button>

                    <nav class="user-manual__breadcrumb" aria-label="Breadcrumb">
                        <a
                            href="{{ route($routeName, ['locale' => $locale, 'page' => ManualConfig::string('user-manual.default_page', 'introduction')]) }}">{{ __('user-manual::messages.title') }}</a>
                        <span class="user-manual__breadcrumb-separator">/</span>
                        <span class="user-manual__breadcrumb-current">{{ $title }}</span>
                    </nav>
                </div>

                <div class="user-manual__header-end">
                    @if (count($locales) > 1)
                        <div class="user-manual__locale-switcher">
                            @foreach ($locales as $availableLocale)
                                <a href="{{ route($routeName, ['locale' => $availableLocale, 'page' => $page]) }}"
                                    @class([
                                        'user-manual__locale-link',
                                        'user-manual__locale-link--active' => $locale === $availableLocale,
                                    ])>
                                    {{ __('user-manual::messages.' . ManualConfig::string('user-manual.locale_labels.' . $availableLocale, $availableLocale)) }}
                                </a>
                            @endforeach
                        </div>
                    @endif

                    <a href="{{ url(ManualConfig::string('user-manual.ui.home_url', '/')) }}" target="_blank"
                        rel="noopener noreferrer" title="{{ __('user-manual::messages.go_to_landing') }}"
                        class="user-manual__icon-button">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"
                            fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                            stroke-linejoin="round" aria-hidden="true">
                            <path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" />
                            <polyline points="9 22 9 12 15 12 15 22" />
                        </svg>
                    </a>
                </div>
            </header>

            <main class="user-manual__page">
                <article class="user-manual__article user-manual__content">
                    {!! $content !!}
                </article>
            </main>
        </div>
    </div>

    <script src="{{ ManualAssets::url('js/user-manual.js') }}" defer></script>

</body>

</html>
