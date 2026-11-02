# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.0.0] - 2026-11-02

Full manual PDFs are now pre-generated once per role and streamed from storage instead of being rendered or cached during a web request. Single page PDFs are rendered on request. The package works with any role setup and no longer assumes a role package. See "Upgrading to 2.0" in the README.

### Added

- Laravel 10 support (`illuminate/*` ^10.0, Orchestra Testbench 8, Pest 2)
- Optional `manuals` map so one application can serve more than one manual, each with its own content path, locales, route and settings. Route-group attributes such as `domain` are passed through
- Full manual PDFs pre-generated per role and stored on any filesystem disk (`pdf.disk`, `pdf.directory`). A download streams the viewer's role file and never renders. Roles that see the same pages share one file
- `user-manual:pdf` command (`{manual?}`, `--role`, `--locale`, `--queue`, `--force`, `--unlock`) and `user-manual:pdf-clear`
- Unique queued `GenerateFullManualPdf` job (`pdf.queue.connection`, `queue`, `timeout`, `tries`, `unique_for`, `job`). A missing PDF is queued once and the viewer gets a 202 "being prepared" page or JSON (`pdf.full.pending_view`, `pdf.full.retry_after`). On the `sync` connection, jobs started from a web request run after the response is sent
- `UserManual::regenerateRole()`, `UserManual::forgetRole()` and `UserManual::releaseLocks()`. When a role changes, its old PDF is served until the new one is ready, then deleted
- Outdated PDFs (markdown changed) keep being served while a fresh one is generated in the background
- `RoleResolver` contract (`roles.resolver`, `UserManual::resolveRolesUsing()`, `UserManual::useRoleResolver()`) with `DefaultRoleResolver` (no roles), `ConfigRoleResolver` (roles in config, e.g. a `role` column or enum) and `EloquentRoleResolver` (roles in a table: `roles.model`, `name`, `permissions`, `permission_name`, `scope`, `user_roles`)
- `AccessChecker` contract (`access_checker`) with `DefaultAccessChecker`: permissions through Laravel's Gate, roles through `hasRole()` when the user has it, otherwise through `roles.user_roles`
- `FullPdfGenerated` and `FullPdfGenerationFailed` events
- `pdf.page.enabled` and `pdf.full.enabled` toggles
- `PermissionResolver::user()` is public

### Changed

- **Breaking:** full manual PDFs are no longer rendered or cached on request. Run `php artisan user-manual:pdf` (e.g. on deploy) and configure `roles` for permission-restricted manuals
- **Breaking:** single page PDFs are rendered on request and no longer cached
- **Breaking:** `PdfGeneratorService::generatePagePdf()` / `generateFullPdf()` are replaced by `pagePdf()` / `fullPdfResponse()`. mPDF helpers (`createMpdfInstance()`, `chunkHtml()`, `resolveImageSources()`) moved to `Pdf\PdfRenderer`
- **Breaking:** `user-manual:cache` and `user-manual:clear-cache` only handle the markdown and navigation caches. Both accept an optional manual name
- `AccessProfile::makeUser()` returns an `AccessProfileUser`, which answers permission and role checks from the profile, so a custom `AccessChecker` never changes a role's PDF
- Role-based `permission-mapper` rules and `super_admin_roles` no longer require a `hasRole()` method on the user model
- README documents per-role PDFs, role resolvers, access checkers, queue setup and upgrading, with examples

### Removed

- **Breaking:** config keys `pdf.warm_full`, `pdf.warm_profiles` and `pdf.cache_path`
- **Breaking:** `PdfGeneratorService::warmFullPdf()`, `fullPdfIndexKey()`, `forgetFullPdfCaches()`, `pdfCachePath()`, `forgetPdf()`, `flushPdfCacheFiles()` and `calculateMaxLastModified()` (replaced by `contentHash()`)
- Base64 PDF payloads in the cache store and `storage/app/user-manual/pdfs/*.b64` files

### Fixed

- Redirects of named manuals stay absolute, so multi-segment prefixes no longer resolve to duplicated paths
- Demo pages are no longer seeded into named manuals

## [1.2.4] - 2026-07-27

### Added

- Markdown-based in-app user manual with GitHub-flavored rendering
- Multi-locale routing and sidebar navigation driven by `navigation.md`
- Permission-based page and navigation filtering with optional custom access resolver
- Publishable config, views, translations, and default CSS/JS assets
- Self-contained default styling scoped to `.user-manual__*` classes (no Tailwind required)
- Rendered content and navigation tree caching keyed by source file modification time
- Safe CommonMark defaults (`html_input: escape`, `allow_unsafe_links: false`) with configurable overrides
- `user-manual:clear-cache` Artisan command
- GitHub Actions CI (Pint, PHPStan, Pest) across PHP 8.2–8.4 and Laravel 11/12/13
- Full-manual PDF warming via `user-manual:cache` using synthetic access profiles (`pdf.warm_full`, `pdf.warm_profiles`)
- On-disk PDF cache payloads (`pdf.cache_path`) so large manuals are not limited by database cache `mediumtext` size
- Permission-scoped full PDF cache keys (access signature) so restricted pages cannot leak across viewers
- Safe HTML chunking for mPDF `WriteHTML` (tag-boundary and UTF-8 safe) for large documents
- Image `src` resolution for PDF export (root-relative public paths rewritten for mPDF)
- `AccessProfile` helper for console full-PDF warming without a real login session

### Changed

- Default content directory renamed from `resources/docs` to `resources/user-manual`
- `user-manual:cache` warms configured full-manual PDF variants
- `user-manual:clear-cache` clears full-PDF index entries and on-disk PDF payloads

### Fixed

- Docs pages no longer 500 when `navigation.md` is missing
- Full PDF export no longer fails when the cached payload exceeds MySQL `mediumtext`
- PDF images that used root-relative URLs are embedded correctly
