# Laravel User Manual

Markdown-based in-app user manual for Laravel applications.

**Author:** [muhammadmahedihasan](https://github.com/muhammadmahedihasan)

## Requirements

- PHP 8.2+
- Laravel 11, 12, or 13 (Laravel 13 requires PHP 8.3+)

## Features

- Markdown content with GitHub-flavored rendering
- Multi-locale support (`/docs/en/...`, `/docs/bn/...`)
- Sidebar navigation from `navigation.md`
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

Add your content under `resources/docs/{version}/{locale}/` (see [Content structure](#content-structure) below).

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
└── partials/nav.blade.php
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
resources/docs/{version}/{locale}/
├── navigation.md
├── introduction.md
└── ...
```

Example `navigation.md`:

```markdown
- [Introduction](/docs/en/introduction)
- [Users](/docs/en/users)
  - [Roles](/docs/en/roles)
```

Page slugs are restricted to `[a-z0-9-]+` at the route level. Path traversal attempts (e.g. `../`) are rejected with a 404.

## Configuration

See `config/user-manual.php` after publishing.

Key options:

| Key | Description |
|-----|-------------|
| `route_prefix` | URL prefix (default: `docs`) |
| `middleware` | Route middleware stack |
| `locales` | Supported locales |
| `permission-mapper` | Page slug → permissions map |
| `super_admin_roles` | Roles that bypass page restrictions |
| `cache_ttl` | Cache lifetime in seconds (default: `3600`) |
| `cache_prefix` | Cache key prefix |
| `commonmark` | CommonMark converter overrides (see below) |
| `ui.vite_assets` | Optional Vite entrypoints to layer host app CSS on top of package defaults |

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
