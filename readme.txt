=== Open Graph Control ===
Contributors: evzenleonenko
Tags: open graph, social meta, twitter cards, pinterest, mastodon
Requires at least: 6.2
Tested up to: 6.9
Requires PHP: 8.1
Stable tag: 0.4.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Full control over Open Graph and social meta tags across 12 platforms, with live preview and SEO plugin conflict handling.

== Description ==

Open Graph Control emits correctly-escaped Open Graph, Twitter Card, and platform-specific meta tags for twelve platforms:

* Facebook
* LinkedIn
* X / Twitter
* iMessage (iOS)
* Threads
* Mastodon (with fediverse:creator)
* Bluesky
* WhatsApp
* Discord (with theme-color)
* Pinterest (with Rich Pins JSON-LD)
* Telegram
* Slack

= What it does =

* Emits `og:*`, `article:*`, `twitter:*`, `fediverse:creator`, `theme-color`, and schema.org JSON-LD Rich Pins on `wp_head`
* Site-wide defaults stored in one option row; per-post overrides in one meta blob
* Per-archive overrides — edit OG title / description / image directly on the category, tag, custom taxonomy, or author edit screen
* Dynamic field sources — map ACF or JetEngine custom fields to OG title and description per post type (v0.4+)
* Three platform-optimized image sizes auto-registered (landscape 1200×630, square 600×600, Pinterest 1000×1500)
* Filterable fallback chains for title, description, image, type, URL, and locale
* Detects seven competing SEO/social plugins (Yoast SEO, Rank Math, All in One SEO, SEOPress, Jetpack, The SEO Framework, Slim SEO) and, with the site owner's consent, disables their Open Graph output to avoid duplicate tags

= v0.4 feature: Dynamic OG card generation =

* Auto-generated OG cards: server-side 1200×630 PNG rendering for posts, archives, and authors without explicit OG imagery
* Opt-in via Settings → Images → Card template
* Fixed layout customizable by filters (site logo, colors, background, text styling)
* Built with GD (universally available on PHP hosts); Imagick renderer planned for v0.5
* Bundled Inter font (SIL OFL) for predictable typography

Per-post overrides can additionally be set programmatically via the `ogc_resolve_{title,description,image,type,url,locale}_value` filters or by writing to the `_ogc_meta` post meta key directly.

= Not an SEO plugin =

This plugin handles social/share meta tags only. It does not manage titles or descriptions for Google; use alongside your favorite SEO plugin. If that SEO plugin already emits Open Graph tags, Open Graph Control will offer to take over so you don't end up with duplicate tags.

== Security ==

Open Graph Control is built so **no user data leaves your server**. The plugin does not call any external API, does not phone home, does not ship telemetry, and does not load assets from third-party CDNs.

**What the plugin protects against:**

* **XSS in social meta tags** — every tag attribute is escaped with `esc_attr`, every URL with `esc_url_raw`. The Pinterest Rich Pins JSON-LD payload uses `JSON_HEX_TAG` flags so no post title or author name can break out of the surrounding `<script>` tag.
* **Privilege escalation** — every REST endpoint is gated by `manage_options` (site settings) or `edit_post` per post ID (meta box). No anonymous or subscriber-level write path exists.
* **CSRF** — admin-post actions use `check_admin_referer`; REST relies on WordPress core's `X-WP-Nonce` cookie check.
* **Mass-assignment** — per-post overrides are allowlist-filtered to six documented keys; arbitrary keys are dropped.
* **Dangerous URL schemes** — image URLs extracted from post content are filtered through `wp_allowed_protocols()`, so `javascript:` / `data:` never reach the output.
* **Abuse of the preview endpoint** — rate-limited to 20 calls/minute per user.

**Supply-chain hygiene:** PHP 8.1+, zero Composer runtime dependencies, JavaScript build only pulls packages from the `@wordpress/*` and `@axe-core/playwright` namespaces. PHPStan level 8 + 218 PHPUnit unit tests + Playwright integration suite run on every push.

**Responsible disclosure:** please email security reports to the address in `SECURITY.md` on GitHub, or open a private [security advisory](https://github.com/Teriffy/open-graph-control/security/advisories/new). Response SLA: 3 business days; fix SLA: 30 days for confirmed valid reports.

Public security fixes are tagged `security:` in the commit subject and surface in the changelog.

== Installation ==

1. Upload the plugin ZIP via **Plugins → Add New → Upload Plugin**, or extract the folder to `/wp-content/plugins/open-graph-control/`
2. Activate through the **Plugins** screen
3. (v0.2+) Configure under **Open Graph Control** in the admin sidebar

== Frequently Asked Questions ==

= Does this work with Yoast SEO / Rank Math / All in One SEO? =

Yes. On activation, the plugin detects your SEO plugin and can disable its Open Graph output via that plugin's own opt-out filter, so you don't end up with duplicate OG tags. You can also keep the SEO plugin's OG output and disable Open Graph Control's, in which case this plugin becomes a no-op.

= Is this a replacement for an SEO plugin? =

No. Open Graph Control handles social meta tags only. Titles and descriptions in Google search results are still controlled by your SEO plugin.

= Can I set a custom OG title for a category archive? =

Yes. Open the term edit screen for that category (or any tag, custom taxonomy term, or author) and fill in the Open Graph fields in the Archive overrides box. Every saved override is also listed in **Open Graph Control → Archive overrides** in the admin UI so you can audit them in one place.

= Can I customize which fallback values are used for the title or description? =

Yes. Each resolver walks a filterable chain. The full chain is filterable via `ogc_resolve_title_chain` / `ogc_resolve_description_chain` / `ogc_resolve_image_chain` (etc.), and the final value via `ogc_resolve_{field}_value`.

Example — override the title for a custom post type:

    add_filter(
        'ogc_resolve_title_value',
        function ( $title, $context ) {
            if ( $context->is_singular() && 'book' === get_post_type( $context->post_id() ) ) {
                $author = get_post_meta( $context->post_id(), 'book_author', true );
                return $author ? "{$title} — {$author}" : $title;
            }
            return $title;
        },
        10,
        2
    );

= Can I add a platform that isn't listed? =

The `ogc_detected_plugins` filter lets third parties register detectable SEO competitors, and the `Platforms\PlatformInterface` contract (in the source) is small enough to implement externally. Contributions welcome at the GitHub repo.

= Will this slow down my site? =

Open Graph Control runs only on `wp_head` (front-end pages) and on the REST API when the admin UI is open. Measured in-process with the bundled `wp ogc bench` WP-CLI command (500 iterations, wp-env on Colima):

* Front page render: **mean 0.047 ms**, p95 0.060 ms, p99 0.166 ms
* Single-post render (full resolver chain): **mean 0.396 ms**, p95 0.601 ms, p99 2.333 ms

Archive-term contexts (category / tag / custom taxonomy / author) add one extra `get_term_meta` (or `get_user_meta`) call per request when the `archive_override` resolver step runs — still well under 1 ms.

Output caching is opt-in via the Advanced settings and further reduces this to a single `get_transient` read (<0.05 ms) for cached contexts.

= Is the plugin GPL? =

Yes, GPL-2.0-or-later. Source is on GitHub (URL in the plugin header).

== Screenshots ==

1. Emitted tags shown in page source for a typical post
2. (v0.2) Settings page — platforms overview
3. (v0.2) Per-post meta box with live preview
4. (v0.2) SEO plugin conflict notice
5. (v0.3) Archive overrides — edit OG title / description / image for a category archive directly on the term edit screen
6. (v0.4) Card template settings — toggle, preview, and customization options in Settings → Images

== Known Limitations ==

* **Imagick renderer support is planned for v0.5.** v0.4 uses the GD extension exclusively (widely available on PHP hosts).

== Changelog ==

= 0.4.2 =
* FIX: Fatal TypeError in cache invalidation when `deleted_user_meta` / `deleted_term_meta` fired (e.g., during Google Site Kit OAuth user-option deletion). The first hook argument is an array of meta IDs on delete, but was typed `int`.

= 0.4.1 =
* NEW: Dynamic field sources — map ACF or JetEngine custom fields to OG title / description per post type
* NEW: Filters `ogc_resolve_title_step` and `ogc_resolve_description_step` for extending the respective resolver chains
* Settings → Field sources sub-section with per-plugin detection badge and per-post-type dropdowns

= 0.4.0 =
* feature: dynamic OG card generation — server-side 1200×630 PNG rendering via GD for posts, archives, and authors without explicit OG imagery. Opt-in per site-wide defaults (Settings → Images → Card template).
* feature: card template customization — filters to override card layout colors, logo, background, and text styling.
* feature: new REST endpoints `/og-card/generate`, `/og-card/regenerate`, `/og-card/status`, `/og-card/purge` under `open-graph-control/v1`.
* feature: WP-CLI `wp ogc cards` subcommand — generate, regenerate, view status, or purge generated cards.
* feature: new filter hooks `ogc_resolve_image_step`, `ogc_card_should_generate`, `ogc_card_renderer_prefer_imagick` and action hook `ogc_card_generated` for extending card behavior.
* bundled: Inter font (SIL OFL, 400 + 700 weights) for predictable typography in rendered cards.
* note: Imagick renderer support is reserved for v0.5; v0.4 uses GD exclusively.

= 0.3.0 =
* feature: per-archive overrides — edit OG title / description / image for any category, tag, custom taxonomy term, or author directly on its edit screen. Values are persisted in term/user meta with sanitization and capability checks (`edit_term` / `edit_user`).
* feature: new `archive_override` step wired into the default `ogc_resolve_{title,description,image}_chain` for `author` and taxonomy-term contexts; always runs before the existing `site_default` / `post_meta_override` steps for those contexts.
* feature: **Archive overrides** settings section lists every saved override across taxonomies + authors for auditing and bulk clearing.
* feature: term / author meta is registered via `register_meta( 'term', … )` / `register_meta( 'user', … )` with server-side validation, so REST writes are consistent with the admin UI.
* test: adds one WP integration test (`07-archive-override.spec.ts`, 2 scenarios) plus two a11y scans covering the archive editor and the settings section; PHPUnit suite grows by 45 unit tests.

= 0.2.1 =
* security: prevent stored XSS via JSON-LD `<script>` tag breakout in the Pinterest Rich Pins payload. Encodes with `JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT`; adds a second-layer `str_replace` in the renderer. Reachable by an Author-role user via the post title before the fix; no exploitation known in the wild. Regression tests added.
* security: reject non-http(s) URL schemes (`javascript:`, `data:`, `vbscript:`) extracted from post content before they can reach any meta tag output.
* docs: add `SECURITY.md` defensive-posture section, OWASP Top 10 coverage table, and a "Security" panel in the admin settings UI.

= 0.2.0 =
* Full React admin UI: 10 settings sections (Overview, Site defaults, Platforms, Post types, Images, Fallback chains, Integrations, Debug/Test, Import/Export, Advanced)
* Per-post meta box with Base + X / Twitter + Pinterest + Per-platform tabs, live preview for all 12 platforms, and inline validation warnings
* MediaUpload picker (WordPress media library modal) for site master image and per-post image overrides
* One-time admin_notices banner when an SEO competitor is detected, with "Take over" / "Keep their tags" / "Review in settings" actions
* Validator covering title/description length, missing image, Twitter handle format, Mastodon fediverse:creator format
* Settings import/export via JSON
* Debug panel renders current-context tags in a table and links out to Facebook / Twitter / LinkedIn / Pinterest validators
* REST endpoints: /settings (GET+POST), /preview (POST), /conflicts (GET), /post-types (GET), /meta/{id} (GET+POST)
* Seeded i18n POT

= 0.0.1 =
* Initial development snapshot. Backend rendering pipeline (12 platforms, 7 SEO integrations, Pinterest Rich Pins JSON-LD).

== Upgrade Notice ==

= 0.4.0 =
v0.4 adds auto-generated OG cards for posts without explicit imagery. Enable via Settings → Images → Card template. No breaking changes; fully backward-compatible. Safe to upgrade.

= 0.3.0 =
Adds per-archive OG overrides for categories, tags, custom taxonomy terms, and authors. Fully additive — existing sites keep the same rendered tags until you edit a term or author.

= 0.2.1 =
Security release. Patches a stored XSS in the Pinterest Rich Pins JSON-LD (author-role exploitable before 0.2.1). Upgrade recommended; no data migration needed.

= 0.2.0 =
First release with a functional admin UI. Schema version 1 is considered stable.
