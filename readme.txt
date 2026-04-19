=== Open Graph Control ===
Contributors: evzenleonenko
Tags: open graph, social meta, twitter cards, pinterest, mastodon
Requires at least: 6.2
Tested up to: 6.9
Requires PHP: 8.1
Stable tag: 0.2.0
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
* Three platform-optimized image sizes auto-registered (landscape 1200×630, square 600×600, Pinterest 1000×1500)
* Filterable fallback chains for title, description, image, type, URL, and locale
* Detects seven competing SEO/social plugins (Yoast SEO, Rank Math, All in One SEO, SEOPress, Jetpack, The SEO Framework, Slim SEO) and, with the site owner's consent, disables their Open Graph output to avoid duplicate tags

= What it doesn't do (yet) =

* Per-archive and per-author editor UI — v0.3
* Dynamic OG image generation from templates — v0.4
* Playwright end-to-end test suite — v0.3

Per-post overrides can additionally be set programmatically via the `ogc_resolve_{title,description,image,type,url,locale}_value` filters or by writing to the `_ogc_meta` post meta key directly.

= Not an SEO plugin =

This plugin handles social/share meta tags only. It does not manage titles or descriptions for Google; use alongside your favorite SEO plugin. If that SEO plugin already emits Open Graph tags, Open Graph Control will offer to take over so you don't end up with duplicate tags.

== Installation ==

1. Upload the plugin ZIP via **Plugins → Add New → Upload Plugin**, or extract the folder to `/wp-content/plugins/open-graph-control/`
2. Activate through the **Plugins** screen
3. (v0.2+) Configure under **Open Graph Control** in the admin sidebar

== Frequently Asked Questions ==

= Does this work with Yoast SEO / Rank Math / All in One SEO? =

Yes. On activation, the plugin detects your SEO plugin and can disable its Open Graph output via that plugin's own opt-out filter, so you don't end up with duplicate OG tags. You can also keep the SEO plugin's OG output and disable Open Graph Control's, in which case this plugin becomes a no-op.

= Is this a replacement for an SEO plugin? =

No. Open Graph Control handles social meta tags only. Titles and descriptions in Google search results are still controlled by your SEO plugin.

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

Open Graph Control runs only on `wp_head` (front-end pages) and on the REST API when the admin UI is open. Output caching is opt-in via the Advanced settings; by default the plugin resolves tags fresh on each request because the resolver pipeline is already fast (~1 ms typical).

= Is the plugin GPL? =

Yes, GPL-2.0-or-later. Source is on GitHub (URL in the plugin header).

== Screenshots ==

1. Emitted tags shown in page source for a typical post
2. (v0.2) Settings page — platforms overview
3. (v0.2) Per-post meta box with live preview
4. (v0.2) SEO plugin conflict notice

== Changelog ==

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

= 0.2.0 =
First release with a functional admin UI. Schema version 1 is considered stable.
