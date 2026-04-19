# Open Graph Control

[![CI](https://github.com/Teriffy/open-graph-control/actions/workflows/ci.yml/badge.svg)](https://github.com/Teriffy/open-graph-control/actions/workflows/ci.yml)
![PHP](https://img.shields.io/badge/PHP-%3E%3D8.1-blue)
![WordPress](https://img.shields.io/badge/WordPress-%3E%3D6.2-blue)
![License](https://img.shields.io/badge/License-GPL--2.0--or--later-green)
![Tests](https://img.shields.io/badge/tests-170%20%7C%2018%20E2E-brightgreen)

A WordPress plugin that emits Open Graph and social meta tags for 12 platforms, with per-platform rules, SEO-plugin conflict handling, Pinterest Rich Pins, output cache and live per-post preview.

## Status (v0.2.0 — feature-complete developer preview)

**Backend**

- 12 platform classes — Facebook, X / Twitter, LinkedIn, iMessage, Threads, Mastodon, Bluesky, WhatsApp, Discord, Pinterest, Telegram, Slack
- 6 resolvers (title, description, image, type, URL, locale) with filterable fallback chains
- Pinterest Rich Pins JSON-LD (Article / Product / Recipe)
- 7 SEO plugin integrations with clean takeover — Yoast, Rank Math, All in One SEO, SEOPress, Jetpack, The SEO Framework, Slim SEO
- 3 auto-registered image sizes (landscape 1200×630, square 600×600, Pinterest 1000×1500)
- Transient-based output cache with smart invalidation hooks

**Admin UI (React)**

- Top-level admin menu, 10 settings sections (Overview, Site defaults, Platforms, Post types, Images, Fallback chains, Integrations, Debug/Test, Import/Export, Advanced)
- Per-post meta box with Base + X / Twitter + Pinterest + Per-platform tabs, live preview for all 12 platforms, inline validation
- MediaUpload widget for master image + per-platform overrides
- One-time admin notice when a competing SEO plugin is detected (take-over or keep choice)
- Bulk "Regenerate OG image sizes" action for existing attachments
- Reset-to-defaults + import/export JSON

**REST API** under `open-graph-control/v1`: `/settings`, `/preview`, `/conflicts`, `/post-types`, `/meta/{id}`, `/images/regenerate`, `/settings/reset`. All `manage_options`-gated, with rate-limiting on `/preview`.

**WP-CLI**: `wp ogc tags <post_id>`, `wp ogc validate <post_id>`, `wp ogc regenerate`.

**Quality gates (CI)**

- PHP 8.1–8.4 matrix × PHPUnit (170 tests, 327 assertions) + PHPStan level 8 + WPCS
- Code coverage uploaded as artifact
- JS lint (`@wordpress/scripts` ESLint + Prettier) + Webpack build
- Playwright fixture suite (18 tests: rendering + `@axe-core` WCAG 2 A/AA scan)
- [WordPress/plugin-check-action](https://github.com/WordPress/plugin-check-action) against the built dist zip on every push

## Quick start

```bash
composer install
npm install

composer test                   # PHPUnit unit tests
composer stan                   # PHPStan level 8
composer cs                     # WPCS
composer coverage               # pcov-driven HTML + clover

npm run build                   # webpack production bundles
npm run lint:js                 # ESLint + Prettier
npm run e2e                     # Playwright fixture suite
npm run e2e:wp                  # Playwright WP integration (needs wp-env)

bash bin/make-dist.sh           # zip dist/open-graph-control-*.zip
```

Minimum PHP: 8.1 · Minimum WordPress: 6.2.

## Documentation

- [readme.txt](readme.txt) — WordPress.org plugin directory format
- [docs/filters.md](docs/filters.md) — `ogc_*` filter reference with examples
- [docs/superpowers/specs/](docs/superpowers/specs/) — original design spec

## Releasing

Tag the release (`git tag v0.2.0 && git push --tags`). The `.github/workflows/release.yml` workflow builds the zip, attaches it to the GitHub Release, and pushes to WordPress.org SVN when the `WP_SVN_USERNAME` / `WP_SVN_PASSWORD` secrets are set. Manual SVN path: `bin/publish.sh`.

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).
