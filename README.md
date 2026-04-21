# Open Graph Control

[![CI](https://github.com/Teriffy/open-graph-control/actions/workflows/ci.yml/badge.svg)](https://github.com/Teriffy/open-graph-control/actions/workflows/ci.yml)
![PHP](https://img.shields.io/badge/PHP-%3E%3D8.1-blue)
![WordPress](https://img.shields.io/badge/WordPress-%3E%3D6.2-blue)
![License](https://img.shields.io/badge/License-GPL--2.0--or--later-green)
![Tests](https://img.shields.io/badge/tests-322%20total-brightgreen)
![PHPStan](https://img.shields.io/badge/PHPStan-level%208-blueviolet)
[![Security policy](https://img.shields.io/badge/security-policy-informational)](SECURITY.md)

A WordPress plugin that emits Open Graph and social meta tags for 12 platforms, with per-platform rules, SEO-plugin conflict handling, Pinterest Rich Pins, output cache and live per-post preview.

## Status (v0.4.0 — dynamic OG card generation in progress)

**Backend**

- 12 platform classes — Facebook, X / Twitter, LinkedIn, iMessage, Threads, Mastodon, Bluesky, WhatsApp, Discord, Pinterest, Telegram, Slack
- 6 resolvers (title, description, image, type, URL, locale) with filterable fallback chains
- **Per-archive overrides** (v0.3) — OG title / description / image editable on every category, tag, custom taxonomy term, and author edit screen, wired into the resolver chain via a dedicated `archive_override` step
- **Dynamic OG card generation** (v0.4) — server-side 1200×630 PNG rendering via GD for posts / archives / authors without explicit OG imagery. Auto-generated cards use a fixed layout customizable by filters (logo, site name, title, description, background color). Triggering is opt-in via Settings → Images → Card template; rendering happens on `shutdown` hook, never blocking the editor. Inter font (SIL OFL) is bundled.
- Pinterest Rich Pins JSON-LD (Article / Product / Recipe)
- 7 SEO plugin integrations with clean takeover — Yoast, Rank Math, All in One SEO, SEOPress, Jetpack, The SEO Framework, Slim SEO
- 3 auto-registered image sizes (landscape 1200×630, square 600×600, Pinterest 1000×1500)
- Transient-based output cache with smart invalidation hooks

**Admin UI (React)**

- Top-level admin menu, 12 settings sections (Overview, Site defaults, Platforms, Post types, Images, Fallback chains, Integrations, Debug/Test, Import/Export, Advanced, Archive overrides, Card template)
- Per-post meta box with Base + X / Twitter + Pinterest + Per-platform tabs, live preview for all 12 platforms, inline validation
- **Archive editor** (v0.3) on every taxonomy term + author edit screen — OG title / description / image with live character-count hints
- **Card template editor** (v0.4) in Settings → Images — opt-in toggle, live preview, customizable colors + logo + background, and stats dashboard (generated card count)
- MediaUpload widget for master image + per-platform overrides
- One-time admin notice when a competing SEO plugin is detected (take-over or keep choice)
- Bulk "Regenerate OG image sizes" action for existing attachments
- Reset-to-defaults + import/export JSON

**REST API** under `open-graph-control/v1`: `/settings`, `/preview`, `/conflicts`, `/post-types`, `/meta/{id}`, `/images/regenerate`, `/settings/reset`, `/og-card/generate`, `/og-card/regenerate`, `/og-card/status`, `/og-card/purge` (v0.4). All `manage_options`-gated, with rate-limiting on `/preview`.

**WP-CLI**: `wp ogc tags <post_id>`, `wp ogc validate <post_id>`, `wp ogc regenerate`, `wp ogc cards generate|regenerate|status|purge` (v0.4).

**Hooks** (filters and actions)

| Hook | Type | Signature | Since | Purpose |
|---|---|---|---|---|
| `ogc_resolve_{title,description,image,type,url,locale}_chain` | Filter | `(array $steps): array` | v0.0 | Customize the resolver fallback chain for any field (e.g., add a custom step before `site_default`) |
| `ogc_resolve_{title,description,image,type,url,locale}_value` | Filter | `(mixed $value, Context $context): mixed` | v0.0 | Final override for any resolved field after the entire chain runs |
| `ogc_resolve_image_step` | Filter | `(string\|null $value, string $step, Context $context): ?string` | v0.4 | Intercept and override the result of a specific resolver step (e.g., `post_meta_override`, `site_default`, `archive_override`) |
| `ogc_card_should_generate` | Filter | `(bool $should, CardKey $key): bool` | v0.4 | Control whether a card should be auto-generated for a given post / archive / author (default: true if image chain returns null and card template is enabled) |
| `ogc_card_renderer_prefer_imagick` | Filter | `(bool $prefer): bool` | v0.4 | Reserved for v0.5: hint the renderer to prefer Imagick over GD if available (currently always false; GD is used exclusively in v0.4) |
| `ogc_card_generated` | Action | `(CardKey $key, string $path): void` | v0.4 | Fired after a card is successfully rendered; `$key` is the post / archive / author identifier, `$path` is the local filesystem path to the generated PNG |

**Quality gates (CI)**

- PHP 8.1–8.4 matrix × PHPUnit (322 tests) + PHPStan level 8 + WPCS
- Code coverage uploaded as artifact
- JS lint (`@wordpress/scripts` ESLint + Prettier) + Webpack build
- Playwright fixture suite (18 tests: rendering + `@axe-core` WCAG 2 A/AA scan)
- Playwright WP suite (12 tests: activation, settings save, metabox override, frontend tags, axe on live wp-admin, responsive layout ≤782px, archive overrides) — opt-in via `OGC_E2E_WP=1`
- [WordPress/plugin-check-action](https://github.com/WordPress/plugin-check-action) against the built dist zip on every push

## Security

Open Graph Control is built so **no user data leaves your server**. The plugin does not call any external API, does not phone home, and does not ship telemetry. See [SECURITY.md](SECURITY.md) for the full defensive posture and disclosure process.

**Layered defenses**

- Capability checks on every REST endpoint (`manage_options` for site-wide settings, `edit_post` per post ID for the meta box — no public or subscriber-level write path)
- Nonce enforcement via `check_admin_referer` on admin-post actions and WP core's `X-WP-Nonce` for REST
- Output escaping at the edge: `esc_attr` on every tag attribute, `esc_url_raw` on every URL, `esc_html` on admin surfaces
- JSON-LD payloads encoded with `JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT` + second-layer `str_replace('</', '<\/')` — no string value can break out of the surrounding `<script>` tag
- Post meta writes allowlist-filtered to six documented keys; arbitrary keys dropped
- URL scheme filter via `wp_allowed_protocols()` — `javascript:` / `data:` rejected before reaching any meta output
- Rate-limited `/preview` REST endpoint (20 req/min per user)
- PHPStan level 8 + 218 PHPUnit tests + Playwright suite gated on every push

**OWASP Top 10 (2021) coverage**

| Risk | Applicable | How we handle it |
|---|---|---|
| A01 Broken Access Control | ✓ | Capability + per-object checks on every write path |
| A02 Cryptographic Failures | — | Plugin does not handle secrets or crypto |
| A03 Injection (SQL/XSS) | ✓ | WP APIs only (no raw `$wpdb`); output escaping + JSON_HEX_TAG |
| A04 Insecure Design | ✓ | Deep-merge schema, allowlist-filtered meta, no mass-assignment |
| A05 Security Misconfiguration | ✓ | Safe defaults, strict mode opt-in, no debug output in production |
| A06 Vulnerable Components | ✓ | Dependabot + `composer audit` gated in CI |
| A07 Identification & Authentication | — | Delegated to WordPress core |
| A08 Software & Data Integrity | ✓ | Import/export signed with schema version; downgrade rejected |
| A09 Logging & Monitoring | — | Delegated to host/site logging |
| A10 SSRF | — | Plugin does not issue outbound HTTP requests |

**Performance** — measured with the bundled `wp ogc bench` command:

| Context | Mean | p95 | p99 |
|---|---|---|---|
| Front page (tag render, 500 iter) | **0.047 ms** | 0.060 ms | 0.166 ms |
| Single post (tag render, 500 iter) | **0.396 ms** | 0.601 ms | 2.333 ms |
| Card render (GD, 1200×630, 10 iter) | **< 200 ms median** | — | — |

Output cache reduces cached contexts to a single `get_transient` read. Card render benchmarks vary by system CPU and GD library version; run `wp ogc bench` on your server for local measurements.

**Responsible disclosure** — please don't open a public issue for security problems. Use the [private advisory form](https://github.com/Teriffy/open-graph-control/security/advisories/new) or email the address in `SECURITY.md`. Response SLA: 3 business days; fix SLA: 30 days.

Public security fixes are tagged `security:` in their commit subject. Latest: [`3c96aa0`](https://github.com/Teriffy/open-graph-control/commit/3c96aa0) — stored XSS via JSON-LD script-tag breakout.

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

- [ROADMAP.md](ROADMAP.md) — living status, what's done, what's needed to ship to wordpress.org, what's next
- [readme.txt](readme.txt) — WordPress.org plugin directory format

## Releasing

Tag the release (`git tag v0.3.0 && git push --tags`). The `.github/workflows/release.yml` workflow builds the zip, attaches it to the GitHub Release, and pushes to WordPress.org SVN when the `WP_SVN_USERNAME` / `WP_SVN_PASSWORD` secrets are set. Manual SVN path: `bin/publish.sh`.

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).
