# Laravel User Manual

Markdown-based in-app user manual for Laravel applications.

**Author:** [muhammadmahedihasan](https://github.com/muhammadmahedihasan)

## Requirements

- PHP 8.2+
- Laravel 11, 12, or 13 (Laravel 13 requires PHP 8.3+)

## Features

- Markdown content with GitHub-flavored rendering
- Multi-locale support (`/user-manual/en/...`, `/user-manual/bn/...`)
- Sidebar navigation from `navigation.md`
- PDF Export (single-page & full manual) powered by mPDF with cover page, TOC, and locale-aware numbering
- Optional permission-based page and sidebar filtering
- Publishable config, views, translations, and default assets
- Self-contained default styling (no Tailwind required)
- Rendered content and navigation caching keyed by file modification time
- Safe CommonMark defaults with configurable overrides
- Custom access resolver via facade

## Installation

```bash
composer require muhammadmahedihasan/laravel-user-manual
```

Publish config, views, translations, and assets:

```bash
php artisan vendor:publish --tag=user-manual
```

Or publish individually:

```bash
php artisan vendor:publish --tag=user-manual-config
php artisan vendor:publish --tag=user-manual-views
php artisan vendor:publish --tag=user-manual-lang
php artisan vendor:publish --tag=user-manual-assets
```

Add your content under `resources/user-manual/{version}/{locale}/` (see [Content structure](#content-structure) below).

## Default styling

The package ships scoped CSS and JS under `resources/assets/` and auto-publishes them to `public/vendor/user-manual/` on boot when missing. Default views use the `user-manual__*` class namespace, so the manual renders with a readable sidebar layout, markdown typography, and nav states without any host-app Tailwind setup.

```
public/vendor/user-manual/
├── css/user-manual.css
└── js/user-manual.js
```

Publish `user-manual-assets` to customize the bundled files. Set `ui.vite_assets` in config to layer your app's Vite CSS on top of the package defaults (useful for host-specific markdown classes).

### Customizing views

Publish views to edit the layout in your app:

```bash
php artisan vendor:publish --tag=user-manual-views
```

This copies stub views to:

```
resources/views/vendor/user-manual/
├── show.blade.php
├── partials/nav.blade.php
└── pdf/
    ├── cover.blade.php
    ├── document.blade.php
    ├── footer.blade.php
    ├── header.blade.php
    ├── layout.blade.php
    └── page.blade.php
```

Published views automatically override the package defaults. Keep using `user-manual::show` in config — no view path change needed.

**After upgrading the package**, re-publish views and assets if you rely on published copies:

```bash
php artisan vendor:publish --tag=user-manual-views --force
php artisan vendor:publish --tag=user-manual-assets --force
php artisan view:clear
```

If you skip re-publishing, stale published views may still reference old Tailwind-only classes and omit the package stylesheet.

## Content structure

```
resources/user-manual/{version}/{locale}/
├── navigation.md
├── introduction.md
└── ...
```

Example `navigation.md`:

```markdown
- [Introduction](/user-manual/en/introduction)
- [Users](/user-manual/en/users)
  - [Roles](/user-manual/en/roles)
```

Page slugs are restricted to `[a-z0-9-]+` at the route level. Path traversal attempts (e.g. `../`) are rejected with a 404.

## Configuration

See `config/user-manual.php` after publishing.

Key options:

| Key | Description |
|-----|-------------|
| `route_prefix` | URL prefix (default: `user-manual`) |
| `middleware` | Route middleware stack |
| `locales` | Supported locales |
| `permission-mapper` | Page slug → permissions map |
| `super_admin_roles` | Roles that bypass page restrictions |
| `cache_ttl` | Cache lifetime in seconds (default: `3600`) |
| `cache_prefix` | Cache key prefix |
| `commonmark` | CommonMark converter overrides (see below) |
| `pdf.enabled` | Enable/disable PDF exports (default: `true`) |
| `pdf.default_font` | Default PDF font family (e.g. `kalpurush`, `nikosh`, `sans-serif`) |
| `pdf.fonts` | Custom font directories and font data mappings for mPDF |
| `pdf.cover_page` | Cover page settings (enabled, title, subtitle, version, date_format) |
| `ui.vite_assets` | Optional Vite entrypoints to layer host app CSS on top of package defaults |

### PDF Export

The package provides single-page and full-manual PDF exports handled by a dedicated `PdfController` and `PdfGeneratorService` using `mpdf/mpdf`.

#### Export Routes

- **Single Page PDF**: `/user-manual/{locale}/{page}/pdf` (named route: `user-manual.pdf.page`)
- **Full Manual PDF**: `/user-manual/{locale}/export/pdf` (named route: `user-manual.pdf.full`)

#### PDF Features & Custom Typography

- **Locale-Aware Formatting**: Page numbers (e.g. `{PAGENO}/{nbpg}`) automatically render as `4/11` for English (`en`) and `৪/১১` for Bengali (`bn`). Dates and section numbers adapt dynamically to the requested locale via `ManualNumber` and Carbon.
- **Custom Fonts**: Register custom TrueType fonts (like Bengali fonts `kalpurush` or `nikosh`) under `pdf.fonts` in `config/user-manual.php`:

```php
'pdf' => [
    'default_font' => 'kalpurush',
    'fonts' => [
        'font_dirs' => [ resource_path('fonts') ],
        'font_data' => [
            'kalpurush' => [
                'R' => 'kalpurush.ttf',
                'B' => 'kalpurush.ttf',
                'useOTL' => 0xFF,
                'useKashida' => 75,
            ],
        ],
    ],
],
```

*(Font keys are automatically normalized to lowercase to comply with mPDF font requirements).*

- **Custom Cover, Header & Footer**: Publish views (`php artisan vendor:publish --tag=user-manual-views`) to customize PDF cover page, header, and footer Blade templates.

### Caching

Rendered markdown and the navigation tree are cached automatically. Cache keys include each source file's last-modified time, so edits to `.md` files take effect immediately without running `user-manual:clear-cache`.

Use the clear-cache command as a manual fallback:

```bash
php artisan user-manual:clear-cache
```

### CommonMark and HTML in markdown

By default, the renderer **escapes raw HTML** and **blocks unsafe links** (`javascript:`) to prevent stored XSS (rendered output is unescaped in the view).

```php
// Default behaviour — no config entry needed
'commonmark' => [],
```

If your docs are written by trusted staff and use raw HTML (e.g. `<img class="docs-screenshot">` for styled screenshots), override `html_input`:

```php
'commonmark' => [
    'html_input' => 'allow',
],
```

Only enable this when you trust everyone who can edit markdown files. `allow_unsafe_links` remains `false` unless you explicitly override it.

After changing CommonMark settings, clear the manual cache:

```bash
php artisan user-manual:clear-cache
```

### Permission mapper

```php
'permission-mapper' => [
    'introduction' => '*',
    'material' => ['material_access'],
    'translation' => ['roles' => ['Super Admin']],
],
```

- `*` or `['*']` → any authenticated user
- `['perm_a', 'perm_b']` → user needs any one permission
- `['roles' => ['Admin']]` → user needs any one role

### Custom access resolver

```php
use MuhammadMahediHasan\UserManual\Facades\UserManual;

UserManual::resolveAccessUsing(function ($user, string $slug, array $requirements): bool {
    // custom logic
    return true;
});
```

## Commands

```bash
php artisan user-manual:clear-cache
```

## Writing manual pages with an AI agent

This package ships a skill (`laravel-user-manual-authoring`) that teaches the agent
the correct conventions for authoring manual pages — file locations, slug rules,
`navigation.md` syntax, and permission mapping. Copy `SKILL.md` into your project's
skill directory (e.g. `.claude/skills/`) after installing.

## Development

From the package directory:

```bash
composer install
composer test          # Pest
composer pint          # Code style
composer phpstan       # Static analysis
```

CI runs Pint, PHPStan (level 5), and Pest across PHP 8.2–8.4 and Laravel 11/12/13.

## Changelog

See [CHANGELOG.md](CHANGELOG.md).

## License

MIT — see [LICENSE](LICENSE).
