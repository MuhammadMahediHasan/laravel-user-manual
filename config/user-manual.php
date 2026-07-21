<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Routes
    |--------------------------------------------------------------------------
    */
    'route_prefix' => 'docs',
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
        'primary_color' => '#BC3863',
        'app_name' => null,
    ],

];
