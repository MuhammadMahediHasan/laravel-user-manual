---
name: laravel-user-manual-authoring
description: Use this skill when a feature has just been finished and should be documented for end users, OR when a developer explicitly asks to write/update/add a user manual page. Covers where docs files go, navigation.md syntax, permission mapping, locale handling, and markdown conventions the laravel-user-manual renderer expects. Trigger on requests like "document this feature," "add this to the manual," "write a manual page for X," "update the user manual," as well as proactively after completing user-facing feature work that would benefit from documentation.
---

# Writing pages for laravel-user-manual

## Where content lives

Pages live under `resources/user-manual/{version}/{locale}/{slug}.md`, e.g.
`resources/user-manual/1.0/en/invoicing.md`. If the app supports multiple locales,
write the page for the default locale first, then create matching files for
each other configured locale (check `config('user-manual.locales')`).
Missing-locale files are not auto-fallback — an absent file 404s for that
locale.

## Slug rules

- Slugs must match `[a-z0-9-]+` — lowercase, numbers, hyphens only. No
  underscores, no camelCase, no nested paths.
- The slug is the filename without `.md` and becomes the URL segment:
  `resources/user-manual/1.0/en/invoicing.md` → `/docs/en/invoicing`.

## Page structure

- The page title comes ONLY from the first `# H1` in the file — there is no
  front matter support. Always start the file with a single `# Title`.
- Standard GitHub-flavored markdown is supported: tables, fenced code blocks,
  strikethrough, autolinks.
- Raw HTML is escaped by default (see commonmark config) — don't rely on
  `<div>`/`<script>` working unless the host app has explicitly set
  `html_input => 'allow'` in `config/user-manual.php`. If unsure, write plain
  markdown.
- Keep pages focused on one feature/concept. Prefer several short linked
  pages over one long page.

## Adding the page to navigation

Every version/locale folder has a `navigation.md` that defines the sidebar.
New pages MUST be added here manually — they will not appear in the sidebar
otherwise (though they remain directly accessible by URL).

Format:

```markdown
- [Introduction](/docs/en/introduction)
- [Invoicing](/docs/en/invoicing)
  - [Recurring invoices](/docs/en/invoicing-recurring)
```

Nest sub-pages two spaces under their parent. Link paths must include the
locale segment and match the file's slug exactly.

## Permission mapping (if the feature is access-restricted)

If the feature being documented is behind a permission or role in the host
app, add a matching entry in `config/user-manual.php` under
`permission-mapper`, keyed by slug:

```php
'permission-mapper' => [
    'invoicing' => ['invoicing_access'],       // needs any one permission
    'invoicing-recurring' => ['roles' => ['Admin']], // needs any one role
    'introduction' => '*',                      // any authenticated user
],
```

Unlisted slugs are accessible to any authenticated user by default (fail-open)
— don't skip this step for anything that should actually be restricted, since
an omission silently makes the page public.

## When NOT to use this

Skip this skill for internal-only changes (refactors, bugfixes with no
user-visible behavior change, admin/dev tooling, migrations). Only write or
update manual pages for changes a Manual reader — an end user of the host
app — would actually notice or need help with. When unsure whether a change
is user-facing, ask rather than assuming a page is needed.

## After adding or editing a page

- No cache-busting step needed — cache keys include file modification time,
  so changes appear immediately.
- If you changed `commonmark` config specifically, clear the manual cache:
  `php artisan user-manual:clear-cache`.
- Verify the page renders and appears in the sidebar before considering the
  documentation task done — a missing `navigation.md` entry is the most common
  mistake.

## Checklist before marking documentation complete

- [ ] File created at correct `{version}/{locale}/{slug}.md` path
- [ ] Slug matches `[a-z0-9-]+`
- [ ] File starts with a single `# H1` title
- [ ] Added to `navigation.md` at the correct nesting level
- [ ] Permission mapper entry added if the feature is restricted
- [ ] Written for every locale the app supports (or explicitly confirmed
      single-locale is acceptable)