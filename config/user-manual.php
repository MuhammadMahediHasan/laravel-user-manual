<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Routes
    |--------------------------------------------------------------------------
    */
    'route_prefix' => 'user-manual',
    'route_name' => 'user-manual.show',
    'middleware' => ['web', 'auth'],
    'auth_guards' => ['web'],
    'register_routes' => true,

    /*
    |--------------------------------------------------------------------------
    | Content
    |--------------------------------------------------------------------------
    */
    'version' => '1.0',
    'content_path' => resource_path('user-manual'),
    'default_page' => 'introduction',
    'locales' => ['en'],
    'default_locale' => 'en',
    'locale_labels' => [
        'en' => 'english',
        'bn' => 'bangla',
    ],
    'set_locale_on_visit' => true,
    'locale_session_key' => 'locale',

    /*
    |--------------------------------------------------------------------------
    | Cache
    |--------------------------------------------------------------------------
    */
    'cache_ttl' => 3600,
    'cache_prefix' => 'user-manual',

    /*
    |--------------------------------------------------------------------------
    | CommonMark
    |--------------------------------------------------------------------------
    |
    | Safe defaults escape raw HTML and block javascript: links. Override only
    | if you trust all markdown authors and accept stored XSS risk in rendered
    | pages (output is unescaped in the manual view).
    |
    | Example override:
    | 'commonmark' => ['html_input' => 'allow', 'allow_unsafe_links' => true],
    |
    */
    'commonmark' => [],

    /*
    |--------------------------------------------------------------------------
    | View
    |--------------------------------------------------------------------------
    |
    | Publish stub views to customize layout:
    | php artisan vendor:publish --tag=user-manual-views
    |
    | Published files: resources/views/vendor/user-manual/
    |
    */
    'view' => 'user-manual::show',

    /*
    |--------------------------------------------------------------------------
    | Access control
    |--------------------------------------------------------------------------
    |
    | permission-mapper values:
    | - '*' or ['*']  → any authenticated user
    | - ['perm_a', 'perm_b'] → user needs any one permission (OR)
    | - ['roles' => ['Admin']] → user needs any one role
    |
    | Pages not listed are available to any authenticated user.
    |
    */
    'super_admin_roles' => [],
    'permission-mapper' => [],

    /*
    |--------------------------------------------------------------------------
    | UI
    |--------------------------------------------------------------------------
    |
    | Default views load public/vendor/user-manual/css/user-manual.css and
    | js/user-manual.js (auto-published on boot). Publish user-manual-assets
    | to customize. Set vite_assets to layer host-app styles on top.
    |
    */
    'ui' => [
        'logo_url' => null,
        'home_url' => '/',
        'back_url' => '/dashboard',
        'login_url' => '/login',
        'vite_assets' => [],
        'primary_color' => '#FF2D20',
        'app_name' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | PDF Export Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for mPDF export features. Host applications can register
    | custom font directories and font data to support custom typography.
    |
    */
    'pdf' => [
        'enabled' => true,
        'paper_format' => 'A4',
        'orientation' => 'P',
        'margins' => [
            'left' => 15,
            'right' => 15,
            'top' => 16,
            'bottom' => 16,
            'header' => 9,
            'footer' => 9,
        ],
        'temp_dir' => sys_get_temp_dir(),

        /*
        | Full-manual PDF warming (user-manual:cache)
        |
        | Full manuals are permission-scoped. The cache command warms one PDF
        | per configured access profile so the first matching web export is a
        | cache hit. Each distinct accessible page set is a large cached blob —
        | more profiles mean longer warm runs and more cache storage.
        |
        | Profile shapes:
        | - []                   → authenticated user, no extra permissions
        | - ['perm_a', 'perm_b'] → user with those permissions
        | - ['roles' => [...]]   → user with those roles (may also list perms)
        | - ['*'] or 'all'       → unrestricted access to every page
        |
        | Set warm_full to false to skip full-manual warming entirely.
        */
        'warm_full' => true,
        'warm_profiles' => [
            [],
        ],

        /*
        | Directory for on-disk PDF cache payloads. Full manuals (and large
        | page PDFs) exceed MySQL mediumtext (~16 MB), so the Laravel cache
        | only stores a small marker while the base64 payload lives here.
        */
        'cache_path' => storage_path('app/user-manual/pdfs'),

        /*
        | Custom Fonts Settings
        |
        | 'font_dirs' => [ resource_path('fonts') ],
        | 'font_data' => [
        |     'solaimanlipi' => [
        |         'R' => 'SolaimanLipi.ttf',
        |         'B' => 'SolaimanLipi_Bold.ttf',
        |         'useOTL' => 0xFF,
        |         'useKashida' => 75,
        |     ],
        | ],
        */
        'default_font' => 'sans-serif',
        'fonts' => [
            'font_dirs' => [],
            'font_data' => [],
        ],

        'cover_page' => [
            'enabled' => true,
            'title' => null,
            'subtitle' => null,
            'version' => null,
            'date_format' => 'F Y',
            'logo_url' => null,
            'view' => 'user-manual::pdf.cover',
        ],

        'header' => [
            'show' => true,
            'view' => 'user-manual::pdf.header',
        ],

        'footer' => [
            'show' => true,
            'view' => 'user-manual::pdf.footer',
        ],
    ],

];
