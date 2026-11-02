<?php

use MuhammadMahediHasan\UserManual\Access\DefaultAccessChecker;
use MuhammadMahediHasan\UserManual\Jobs\GenerateFullManualPdf;
use MuhammadMahediHasan\UserManual\Roles\DefaultRoleResolver;

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
    | Manuals
    |--------------------------------------------------------------------------
    |
    | Leave this empty for one manual configured by the keys above.
    |
    | Set it when one application serves more than one manual. Each entry
    | inherits those keys and overrides only what it sets. A manual is its
    | content_path plus a route that does not collide with another manual.
    | Give each manual its own route_prefix, or pass any Laravel route-group
    | attributes (domain, prefix, middleware, where) through `route`.
    | If both `middleware` and `route.middleware` are set, the route-group
    | list is the one registered.
    |
    | Named manuals default their route names to "{name}.user-manual.show"
    | and their cache prefix to "{cache_prefix}.{name}" unless overridden.
    |
    | 'manuals' => [
    |     'staff' => [
    |         'content_path' => resource_path('user-manual/staff'),
    |         'route_prefix' => 'manual',
    |         'locales' => ['en', 'bn'],
    |     ],
    |     'admin' => [
    |         'content_path' => resource_path('user-manual/admin'),
    |         'route_prefix' => 'user-manual',
    |         'locales' => ['en', 'bn'],
    |         'route' => [
    |             'domain' => env('ADMIN_DOMAIN'),
    |         ],
    |     ],
    | ],
    |
    */
    'manuals' => [],

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
    /*
    | How roles and permissions are checked (permission-mapper rules and
    | super_admin_roles). The default works with any setup: permissions via
    | Laravel's $user->can() (Gate), roles via $user->hasRole() when the user
    | has it, otherwise the role names found at `roles.user_roles`. Set your
    | own MuhammadMahediHasan\UserManual\Contracts\AccessChecker class here.
    */
    'access_checker' => DefaultAccessChecker::class,

    /*
    |--------------------------------------------------------------------------
    | Roles
    |--------------------------------------------------------------------------
    |
    | Which roles exist and which role a user has. Used to pre-generate one
    | full manual PDF per role. `resolver` is a class implementing
    | MuhammadMahediHasan\UserManual\Contracts\RoleResolver:
    |
    | - DefaultRoleResolver:  no roles, one "default" PDF for everyone
    | - ConfigRoleResolver:   roles listed in `list` (roles kept in code,
    |                         e.g. a users.role column or an enum)
    | - EloquentRoleResolver: roles stored in a table (`model`), with any role
    |                         package or none
    | - your own class, or UserManual::resolveRolesUsing() closures
    |
    | Examples
    |   Role table with a permissions relation (e.g. spatie/laravel-permission):
    |     'resolver' => EloquentRoleResolver::class, 'model' => App\Models\Role::class,
    |   users.role_id → roles table, roles.abilities JSON column:
    |     'resolver' => EloquentRoleResolver::class, 'model' => App\Models\Role::class,
    |     'permissions' => 'abilities', 'user_roles' => 'role',
    |   users.role string/enum column:
    |     'resolver' => ConfigRoleResolver::class, 'user_roles' => 'role',
    |     'list' => ['admin' => 'all', 'editor' => ['post_edit'], 'member' => []],
    |
    */
    'roles' => [
        'resolver' => DefaultRoleResolver::class,

        // ConfigRoleResolver: role key => access profile
        // ([] | ['perm_a', ...] | ['perm_a', 'roles' => ['Editor']] | 'all')
        'list' => [],

        // EloquentRoleResolver
        'model' => null,
        'name' => 'name',
        'permissions' => 'permissions',     // relation or array/JSON column; null = none
        'permission_name' => 'name',        // name attribute of permission models
        'scope' => null,                    // invokable class: (Builder $query, ManualDefinition $manual)

        // Where a user's role(s) are (data_get path): a relation, a model,
        // a column or an enum. Used by the resolvers and the access checker.
        'user_roles' => 'roles',
    ],

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
        | Single page PDF
        |
        | Rendered on request and streamed. Nothing is stored or queued.
        */
        'page' => [
            'enabled' => true,
        ],

        /*
        | Full manual PDF
        |
        | Pre-generated per role, stored on `disk` under `directory`, and only
        | streamed on request. Generate them with `php artisan user-manual:pdf`
        | (e.g. on deploy) and call UserManual::regenerateRole($key) when a
        | role's permissions change. A missing PDF is queued on first request
        | and the viewer sees `pending_view` (HTTP 202, or JSON for API calls).
        */
        'full' => [
            'enabled' => true,
            'pending_view' => 'user-manual::pdf.pending',
            'retry_after' => 60,
        ],

        'disk' => env('USER_MANUAL_PDF_DISK', 'local'),
        'directory' => 'user-manual/pdfs',

        /*
        | Background generation. `job` may be a subclass of
        | MuhammadMahediHasan\UserManual\Jobs\GenerateFullManualPdf.
        | `unique_for` is how long (seconds) a queued job blocks duplicates; null
        | means `timeout` + 60, so a killed job can't block a role for long.
        | Stuck anyway? `php artisan user-manual:pdf --unlock`.
        | On a `sync` connection, jobs started from a web request run after the
        | response is sent; use a real queue with a worker in production.
        */
        'queue' => [
            'connection' => env('USER_MANUAL_PDF_QUEUE_CONNECTION'),
            'queue' => env('USER_MANUAL_PDF_QUEUE'),
            'timeout' => 600,
            'tries' => 1,
            'unique_for' => null,
            'job' => GenerateFullManualPdf::class,
        ],

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
