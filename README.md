# Open Graph Control

A WordPress plugin that emits Open Graph and social meta tags for 12 platforms, with per-platform rules, SEO-plugin conflict handling, and Pinterest Rich Pins.

## Status (v0.0.1 — developer preview)

Backend rendering pipeline **feature-complete**:

- 12 platform classes (Facebook, Twitter, LinkedIn, iMessage, Threads, Mastodon, Bluesky, WhatsApp, Discord, Pinterest, Telegram, Slack)
- 6 resolvers (title, description, image, type, URL, locale) with filterable fallback chains
- Pinterest Rich Pins schema.org JSON-LD (Article / Product / Recipe)
- 7 SEO plugin integrations with clean takeover (Yoast, Rank Math, All in One SEO, SEOPress, Jetpack, The SEO Framework, Slim SEO)
- 3 REST endpoints (`/settings`, `/preview`, `/conflicts`) under `open-graph-control/v1`
- Options + PostMeta repositories with deep-merge and schema versioning
- 3 auto-registered image sizes (landscape 1200×630, square 600×600, Pinterest 1000×1500)

**Not yet:**

- Admin settings page (React / `@wordpress/scripts` shell is scaffolded)
- Per-post meta box with live preview
- Per-archive / per-author editor UI
- Dynamic OG image generation

For now, all configuration is programmatic via WP filters or direct option/postmeta writes.

## Development

```bash
composer install
npm install

composer test       # PHPUnit unit tests (~114 tests)
composer stan       # PHPStan level 8
composer cs         # WordPress Coding Standards (relaxed for PSR-4)
composer cs:fix     # Auto-fix formatting

npm run build       # Build React bundles to build/
npm run lint:js     # ESLint

npx wp-env start    # Local WP dev env at http://localhost:8888
```

## Documentation

- [Design spec](docs/superpowers/specs/2026-04-19-open-graph-control-design.md)
- [Implementation plan](docs/superpowers/plans/2026-04-19-open-graph-control.md)
- [readme.txt](readme.txt) (WP.org format)

## License

GPL-2.0-or-later.
