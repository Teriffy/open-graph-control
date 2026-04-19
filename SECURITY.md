# Security policy

## Reporting a vulnerability

Please **don't open a public issue** for security problems. Instead:

- Email the maintainer at **teriffy@gmail.com** with details of the vulnerability, or
- Use GitHub's [private security advisory](https://github.com/Teriffy/open-graph-control/security/advisories/new).

Include:

1. Plugin version
2. WordPress version + PHP version
3. Steps to reproduce
4. Impact (what can an attacker do?)
5. Suggested fix if you have one

I aim to respond within **3 business days** and publish a fix within **30 days** of a confirmed valid report. Credit will be given in the release notes unless you prefer anonymity.

## Supported versions

| Version | Supported |
|---|---|
| 0.2.x | ✅ |
| 0.1.x | ❌ (pre-release, upgrade to 0.2.x) |
| 0.0.x | ❌ (dev snapshots) |

## Scope

In scope:

- Injection / XSS / CSRF in the plugin's admin UI or REST endpoints
- Capability or nonce bypasses
- Unsafe handling of untrusted input (`og:*` meta values, imported JSON)
- Unauthorised write to `ogc_settings` or `_ogc_meta`

Out of scope (generally):

- WordPress core vulnerabilities (report those to the core security team)
- Other plugins' OG output when Open Graph Control is not the active emitter
- Social exploits (spam, trademark abuse) in content you chose to publish

## Defensive posture

Open Graph Control is designed so that **no user data leaves your server**. The plugin does not call any external API, does not phone home, and does not ship telemetry. Everything it does runs locally inside your WordPress install.

### What the plugin touches

| Surface | Reads | Writes |
|---|---|---|
| `ogc_settings` option | ✓ | `manage_options` only |
| `_ogc_meta` post meta | ✓ | `edit_post` per post ID |
| `wp_head` output | ✓ (escaped) | — |
| WP-Cron (image regen) | scheduled | attachment metadata |
| External HTTP | ✗ never | ✗ never |
| File system | ✗ never | ✗ never (reads bundled assets only) |

### Layered defenses

1. **Capability checks on every REST endpoint.** `manage_options` for site-wide settings and previews, `edit_post` (per post ID) for the meta box. No public or logged-in-only write path.
2. **Nonce enforcement.** Admin-post actions use `check_admin_referer`; REST relies on WordPress core's `X-WP-Nonce` cookie check for cookie-authenticated requests.
3. **Output escaping at the edge.** Every meta tag attribute flows through `esc_attr`. Every URL through `esc_url_raw`. Every admin-surface text through `esc_html` / `esc_html__`.
4. **JSON-LD context awareness.** The Pinterest Rich Pins payload is encoded with `JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT` so no string value — post title, author name, description — can break out of the surrounding `<script type="application/ld+json">` tag. A second-layer `str_replace('</', '<\/')` wraps the emission in `Head::render()` as belt-and-suspenders.
5. **Allowlist-filtered post meta writes.** Only `title`, `description`, `image_id`, `type`, `platforms`, `exclude` can be written to `_ogc_meta`. Arbitrary keys are dropped.
6. **URL scheme filtering.** Image URLs extracted from post content are routed through `esc_url_raw`, which rejects `javascript:`, `data:`, and any protocol not in `wp_allowed_protocols()`.
7. **Rate-limited previews.** The `/preview` REST endpoint is capped at 20 calls/minute per user to prevent use as an ad-hoc renderer.
8. **Static type analysis.** PHPStan level 8 runs on every push — type-level guarantees that sinks receive the shape the code expects.
9. **PHPUnit + Playwright coverage.** 173 unit tests (including regression tests for every past security fix) plus a WordPress integration suite running against a real wp-env instance.

### OWASP Top 10 (2021) coverage

| Risk | Applicable | How we handle it |
|---|---|---|
| A01 Broken Access Control | ✓ | Capability + per-object checks on every write path |
| A02 Cryptographic Failures | — | Plugin does not handle secrets or crypto |
| A03 Injection (SQL/XSS) | ✓ | All queries via WP APIs (no raw `$wpdb`); output escaping + JSON_HEX_TAG |
| A04 Insecure Design | ✓ | Deep-merge schema, allowlist-filtered meta, no mass-assignment |
| A05 Security Misconfiguration | ✓ | Safe defaults, strict mode opt-in, no debug output in production |
| A06 Vulnerable Components | ✓ | Dependabot + `composer audit` gated in CI |
| A07 Identification & Authentication | — | Delegated to WordPress core |
| A08 Software & Data Integrity | ✓ | Import/export signed with schema version; downgrade rejected |
| A09 Logging & Monitoring | — | Delegated to host/site logging |
| A10 Server-Side Request Forgery | — | Plugin does not issue outbound HTTP requests |

### Audit trail

Public security fixes are tagged with `security:` in their commit subject and linked from the changelog.

- **2026-04-19** — Stored XSS via JSON-LD `<script>` breakout (author-role required). [Commit `d330319`](https://github.com/Teriffy/open-graph-control/commit/d330319).

Thank you for helping keep Open Graph Control users safe.
