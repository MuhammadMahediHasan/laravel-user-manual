@php
    use MuhammadMahediHasan\UserManual\Support\Config;

    $routePrefix = trim(Config::string('user-manual.route_prefix', 'user-manual'), '/');
    $locales = Config::stringList('user-manual.locales', ['en']);

    $resolveSlug = function (string $url) use ($routePrefix, $locales): string {
        $path = trim(parse_url($url, PHP_URL_PATH) ?? '', '/');
        $segments = explode('/', $path);

        if ($segments[0] === $routePrefix) {
            if (in_array($segments[1] ?? '', $locales, true)) {
                return $segments[2] ?? '';
            }

            return $segments[1] ?? '';
        }

        return basename($path);
    };

    $isActive = function (array $item) use ($page, $resolveSlug): bool {
        if ($item['external']) {
            return false;
        }

        return $resolveSlug($item['url']) === $page;
    };
@endphp

<ul class="user-manual__nav-list">
    @foreach ($items as $item)
        @php
            $active = $isActive($item);
        @endphp

        <li class="user-manual__nav-item">
            <a
                href="{{ $item['url'] }}"
                @if($item['external']) target="_blank" rel="noopener noreferrer" @endif
                @class([
                    'user-manual__nav-link',
                    'user-manual__nav-link--active' => $active,
                ])
            >
                <span class="user-manual__nav-label">{{ $item['title'] }}</span>
                @if($item['external'])
                    <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                         class="user-manual__nav-external" aria-hidden="true">
                        <path d="M15 3h6v6"/>
                        <path d="M10 14 21 3"/>
                        <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/>
                    </svg>
                @endif
            </a>

            @if (! empty($item['children']))
                <ul class="user-manual__nav-list user-manual__nav-list--nested">
                    @include('user-manual::partials.nav', ['items' => $item['children'], 'page' => $page])
                </ul>
            @endif
        </li>
    @endforeach
</ul>
