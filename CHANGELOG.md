# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Changed

- Default content directory renamed from `resources/docs` to `resources/user-manual`

## [1.0.0] - 2026-07-21

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
