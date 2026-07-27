# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- Full-manual PDF warming via `user-manual:cache` using synthetic access profiles (`pdf.warm_full`, `pdf.warm_profiles`)
- On-disk PDF cache payloads (`pdf.cache_path`) so large manuals are not limited by database cache `mediumtext` size
- Permission-scoped full PDF cache keys (access signature) so restricted pages cannot leak across viewers
- Safe HTML chunking for mPDF `WriteHTML` (tag-boundary and UTF-8 safe) for large documents
- Image `src` resolution for PDF export (root-relative public paths rewritten for mPDF)
- `AccessProfile` helper for console full-PDF warming without a real login session

### Changed

- Default content directory renamed from `resources/docs` to `resources/user-manual`
- `user-manual:cache` warms configured full-manual PDF variants; it no longer skips full exports or caches an empty console variant
- `user-manual:clear-cache` clears full-PDF index entries and on-disk PDF payloads
- README documents PDF caching, warm profiles, and a note that large full PDFs may need a higher host PHP `memory_limit`

### Fixed

- Docs pages no longer 500 when `navigation.md` is missing
- Full PDF export no longer fails when the cached payload exceeds MySQL `mediumtext`
- PDF images that used root-relative URLs are embedded correctly

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
