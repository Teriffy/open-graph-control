# Open Graph Control — Design Spec

- **Status:** Approved for implementation planning
- **Date:** 2026-04-19
- **Author:** Evžen Leonenko
- **Target:** WordPress plugin, distributed via wordpress.org/plugins
- **Plugin slug:** `open-graph-control`
- **Plugin name:** Open Graph Control
- **Namespace:** `EvzenLeonenko\OpenGraphControl`
- **License:** GPL-2.0-or-later

---

## 1. Goal

Build a standalone WordPress plugin that gives site owners full, granular control over Open Graph meta tags emitted to **twelve** social platforms. Primary differentiators over the existing `opengraph` plugin (Will Norris / Matthias Pfefferle) and SEO plugin OG modules (Yoast, Rank Math, AIOSEO):

- Real admin UI (zero-code configuration)
- Per-post override editor with per-platform overrides
- Live preview cards for all 12 platforms
- Length and image validation with inline warnings
- Opinionated detection + takeover of SEO plugin OG output (no duplicate tags)
- Image auto-resize into per-platform optimized sizes (landscape / square / vertical Pinterest)
- Pinterest Rich Pins (JSON-LD) built-in
- Modern tech (React via `@wordpress/scripts`, PSR-4 OO, PHP 8.0+)

**Explicitly out of scope for MVP (v1.0):**

- Import from competing SEO plugins (v1.1)
- Per-archive / per-author / per-term editor UI — MVP uses site defaults as fallback, extensible via filters (v1.1)
- Dynamic OG image generation from templates (v1.2)
- AI description generation, A/B testing, scheduled variants (v2.0)

## 2. Scope: supported platforms

| # | Platform | Tags emitted | Per-post override slot |
|---|---|---|---|
| 1 | Facebook | `og:*`, `fb:app_id`, `article:*` | yes (grouped in "Overrides ▾") |
| 2 | LinkedIn | `og:*` | yes |
| 3 | X / Twitter | `twitter:*` + `og:*` fallback | yes (dedicated tab) |
| 4 | iMessage (iOS) | `og:*` + square image preference | yes |
| 5 | Threads | `og:*` | yes |
| 6 | Mastodon | `og:*` + `<meta name="fediverse:creator">` | yes |
| 7 | Bluesky | `og:*` | yes |
| 8 | WhatsApp | `og:*` + image size constraint (<300KB) | yes |
| 9 | Discord | `og:*` + `<meta name="theme-color">` | yes |
| 10 | Pinterest | `og:*` + JSON-LD Rich Pin (Article / Product / Recipe) | yes (dedicated tab, vertical image) |
| 11 | Telegram | `og:*` | yes |
| 12 | Slack | `og:*` + `twitter:*` fallback | yes |

## 3. Architecture

### 3.1 Layered module map

```
Bootstrap (open-graph-control.php)
    |
    +-- Renderer       (wp_head output pipeline)
    +-- Admin          (settings page + meta box + REST)
    +-- Integrations   (SEO plugin detect + takeover)
    +-- Resolvers      (fallback chain for title/desc/image/type/url/locale)
    |
    +-- Core/Domain
         - Platforms registry (12 classes, PlatformInterface)
         - TagBuilder (escaped <meta> HTML)
         - Options repository (wp_options: ogc_settings)
         - Post meta repository (postmeta: _ogc_meta)
         - Image variant registry (add_image_size)
```

### 3.2 Render pipeline

1. `wp_head` at priority 1 → `Renderer\Head::render()`.
2. Context detector classifies request: singular post, front, blog index, term archive, author, date, search, 404.
3. Resolvers run, each walks its filterable fallback chain until a non-empty value emerges.
4. `PlatformRegistry::enabled_platforms()` — each Platform receives resolved values and returns `Tag[]`.
5. Deduplicate by canonical property/name, preserving first occurrence. `og:*` and `twitter:*` coexist by design.
6. `TagBuilder` renders `<meta>` HTML with `esc_attr` on content.
7. Output wrapped in HTML comments (`<!-- Open Graph Control v{x} -->`), toggleable.

### 3.3 Strict mode

- Default: emit `<meta property="og:*">` **and** `<meta name="og:*">` variants to maximize scraper compatibility (HTML5 compliant per WHATWG MetaExtensions).
- Strict: only canonical forms (`property=` for OG, `name=` for Twitter).
- Setting lives in `ogc_settings.output.strict_mode`; also honors legacy constant `OPENGRAPH_STRICT_MODE` for users migrating from upstream plugin.

### 3.4 Fallback chains (defaults)

| Resolver | Chain |
|---|---|
| **Title** | post override → SEO plugin title → `get_the_title()` → `get_bloginfo('name')` |
| **Description** | post override → SEO plugin description → `get_the_excerpt()` → trimmed `get_the_content()` → `get_bloginfo('description')` |
| **Image** | post override → featured image → first `<img>` in content → first block image → site master image |
| **Type** | post override → per-post-type default → `website` |
| **URL** | `get_permalink()` → current URL |
| **Locale** | post override → site setting → `get_locale()` |

Each step filterable via `ogc_resolve_{field}_chain` (array of callables) and final value via `ogc_resolve_{field}_value`.

### 3.5 Non-post contexts

| Context | MVP behavior |
|---|---|
| Front page (static) | Post override + site defaults |
| Front page (posts) | Site defaults |
| Blog index | Site defaults, `type=website` |
| Term archive | Archive title / description, site master image |
| Author archive | User display name / bio / avatar, `type=profile` |
| Date archive | Site defaults + archive title |
| Search results | **Disabled** by default |
| 404 | **Disabled** by default |
| Singular CPT | Same as post if `public=true` (toggleable per CPT) |

Per-archive / per-author editor UI → v1.1. MVP exposes `ogc_archive_meta` and `ogc_author_meta` filters for programmatic override.

## 4. Data model

### 4.1 Global settings — `wp_options`

Single serialized option `ogc_settings`:

```php
[
  'version' => 1,
  'site' => [
    'name', 'description', 'locale', 'type',
    'master_image_id' => int,       // attachment ID
    'theme_color' => '#hex',        // Discord
  ],
  'platforms' => [
    'facebook'  => ['enabled' => bool, 'fb_app_id' => string],
    'twitter'   => ['enabled', 'card' => 'summary|summary_large_image',
                    'site' => '@handle', 'creator' => '@handle'],
    'linkedin'  => ['enabled'],
    'imessage'  => ['enabled', 'prefer_square' => bool],
    'threads'   => ['enabled'],
    'mastodon'  => ['enabled', 'fediverse_creator' => '@user@instance'],
    'bluesky'   => ['enabled'],
    'whatsapp'  => ['enabled', 'max_image_kb' => int],
    'discord'   => ['enabled'],
    'pinterest' => ['enabled', 'rich_pins_type' => 'article|product|recipe'],
    'telegram'  => ['enabled'],
    'slack'     => ['enabled'],
  ],
  'post_types' => [
    // keyed by post type slug; dynamically populated on admin page load
    'post' => ['enabled' => true, 'default_type' => 'article'],
    'page' => ['enabled' => true, 'default_type' => 'website'],
  ],
  'non_post_pages' => [
    'front'     => ['enabled' => true,  'use' => 'site_defaults'],
    'blog'      => ['enabled' => true,  'use' => 'site_defaults'],
    'archive'   => ['enabled' => true,  'use' => 'archive_meta'],
    'author'    => ['enabled' => true,  'use' => 'profile'],
    'search'    => ['enabled' => false],
    'not_found' => ['enabled' => false],
  ],
  'integrations' => [
    'detected'     => ['yoast', 'rankmath', ...],
    'takeover'     => ['yoast' => true, 'rankmath' => true, ...],
  ],
  'output' => [
    'strict_mode'     => false,
    'comment_markers' => true,
    'cache_ttl'       => 0,       // OFF by default
  ],
  'fallback_chains' => [
    'title'       => ['post_meta_override', 'seo_plugin_title', 'post_title', 'site_name'],
    'description' => [...],
    'image'       => [...],
  ],
]
```

Rationale: one option row → single `get_option` hit via `alloptions` cache, easy export/import. If it exceeds ~128 KB, split into sub-options in a migration.

### 4.2 Per-post overrides — `wp_postmeta`

Single postmeta key `_ogc_meta`:

```php
[
  'title'       => string,
  'description' => string,
  'image_id'    => int,          // attachment ID
  'type'        => string,
  'platforms'   => [
    'pinterest' => ['image_id' => int, 'title' => string],
    'twitter'   => ['card' => 'summary', 'title' => string, 'image_id' => int],
    'imessage'  => ['image_id' => int],
    // ...
  ],
  'exclude'     => ['search'],   // contexts to suppress
]
```

Single blob preferred over per-field keys (fewer rows, fewer DB ops). If `meta_query` usage arises, migration path is available.

### 4.3 Image sizes

Registered in `init` via `add_image_size`:

| Slug | Dimensions | Crop | Use |
|---|---|---|---|
| `ogc_landscape` | 1200 × 630 | hard (center) | FB, LI, X large, iMsg wide, Threads, Discord, WhatsApp |
| `ogc_square` | 600 × 600 | hard (center) | X summary, iMsg square |
| `ogc_pinterest` | 1000 × 1500 | hard (center) | Pinterest |

Existing attachments can be regenerated via a bulk action exposed on the Images settings tab (schedules WP-Cron job, progress reported in UI).

### 4.4 Cache

Output cache **disabled by default** (`cache_ttl=0`). When enabled:

- Key: `ogc_tags_{md5(context_signature)}` stored as transient.
- Invalidation hooks: `save_post`, `delete_post`, `updated_post_meta`, `update_option('ogc_settings')`, `update_option('blogname'|'blogdescription')`, `switch_theme`.

## 5. Admin UI

### 5.1 Top-level menu

Separate top-level WP admin menu item **"Open Graph Control"** (dashicon `share`), not buried under Settings.

### 5.2 Settings page — vertical sidebar nav

| Section | Purpose |
|---|---|
| **Overview** | Status dashboard: detected SEO plugins, enabled platforms count, recent posts missing OG image, last render time |
| **Site defaults** | Name, description, locale, master image, type, Discord theme-color |
| **Platforms (12)** | Per-platform enable + specific config (fb_app_id, twitter card/site/creator, fediverse_creator, pinterest rich_pins_type) |
| **Post types** | Enable + default OG type per public CPT |
| **Images** | Registered sizes, "Regenerate existing attachments" bulk action |
| **Fallback chains** | Visual drag-and-drop editor for title / description / image chains |
| **Integrations** | Detected SEO plugins + per-plugin takeover toggle |
| **Debug / Test** | URL input → renders all tags + Rich Pin JSON-LD; links to FB Sharing Debugger, X Card Validator, LinkedIn Post Inspector; ping recache buttons |
| **Import / Export** | JSON dump / load of `ogc_settings` with schema version guard |
| **Advanced** | Strict mode, HTML comment markers, cache TTL, debug logging |

### 5.3 Per-post meta box

Rendered below the editor (PHP shell + React mount). Works in both Gutenberg and Classic Editor.

**Tabs (grouped, 4 primary + accordion):**

1. **Base** — title / description / master image / type overrides (90% of usage)
2. **X / Twitter** — twitter-specific overrides (card, title, description, image)
3. **Pinterest** — Rich Pin type + vertical image override
4. **Per-platform overrides ▾** (accordion) — FB, LinkedIn, Threads, iMessage, Mastodon, WhatsApp, Telegram, Slack, Bluesky, Discord. Each expandable with title / description / image slots.

**Live preview pane** (right side of meta box):

- Dropdown selects which platform is previewed
- React components for all 12 platforms share one state (changes propagate instantly, debounced 150ms)
- Approximate visual replica of each platform's card (not pixel-perfect; goal is recognition, not fidelity)

**Inline validation** below the form:

- ⚠ Warnings: over character limits, image too small, missing alt text
- ✓ Success: Rich Pin schema valid, Twitter handle verified format
- ⓘ Info: which fallback value will be used when override is empty

**Footer:** `[ Reset to defaults ]` `[ Save post ]` (save flows through normal post save).

### 5.4 Capabilities

- `edit_posts` — edit per-post OG (aligns with post editing)
- `manage_options` — global settings + debug panel
- Filterable: `ogc_capability_{area}` (area ∈ `settings`, `debug`, `integrations`, `import_export`)

## 6. SEO plugin integration

### 6.1 Detection

Runs on `admin_init` and `plugins_loaded`. Detected plugins cached in `ogc_settings.integrations.detected` and invalidated on `activated_plugin` / `deactivated_plugin`.

Detection signatures:

| Plugin | Check |
|---|---|
| Yoast SEO | `defined('WPSEO_VERSION')` \|\| `class_exists('WPSEO_Options')` |
| Rank Math | `class_exists('RankMath')` \|\| `defined('RANK_MATH_VERSION')` |
| AIOSEO | `defined('AIOSEO_VERSION')` \|\| `class_exists('AIOSEO\\Plugin\\AIOSEO')` |
| SEOPress | `defined('SEOPRESS_VERSION')` \|\| `function_exists('seopress_init')` |
| Jetpack (OG) | `class_exists('Jetpack')` + enhanced-distribution module active |
| The SEO Framework | `defined('THE_SEO_FRAMEWORK_VERSION')` |
| Slim SEO | `defined('SLIM_SEO_VERSION')` |

### 6.2 Takeover — official filters, not `remove_action`

Use each plugin's own opt-out API:

| Plugin | Takeover filter |
|---|---|
| Yoast | `add_filter('wpseo_enable_open_graph', '__return_false')` |
| Rank Math | `add_filter('rank_math/opengraph/facebook/enable_tags', '__return_false')` + Twitter variant |
| AIOSEO | `add_filter('aioseo_disable_social_meta', '__return_true')` |
| SEOPress | `add_filter('seopress_social_og_enable', '__return_false')` + Twitter variant |
| Jetpack | `add_filter('jetpack_enable_open_graph', '__return_false')` |
| TSF | `add_filter('the_seo_framework_og_output', '__return_false')` |
| Slim SEO | `add_filter('slim_seo_open_graph_tags', '__return_empty_array')` |

Unknown plugin → warn, no automated takeover (safer than guessing).

### 6.3 UX flow

`activated_plugin` hook triggers detection. If OG-emitting SEO plugin found:

```
⚠ Open Graph Control: Yoast SEO detected
[ ✓ Take over OG output (recommended) ] [ Keep Yoast ] [ Decide later ]
```

Decision stored in `ogc_settings.integrations.takeover.{plugin}`. Reversible from Integrations section.

### 6.4 Extensibility filters

- `ogc_detected_plugins` (array) — third parties can register detection
- `ogc_apply_takeover_{plugin}` (bool) — force override
- `ogc_takeover_callbacks` (array) — map custom takeover functions

## 7. Security

| Vector | Mitigation |
|---|---|
| Output | `esc_attr` on all `<meta content>`, `esc_url` for URLs, `esc_html` for displayed strings |
| Input | `sanitize_text_field` for short text, strip tags for description (plain text only), `absint`, `sanitize_url` |
| Nonces | All settings forms + REST endpoints (`wp_create_nonce('wp_rest')`) |
| Capabilities | `manage_options` for settings, `edit_post` per-post |
| SQL | No custom queries; WP API only. If needed, `$wpdb->prepare()` |
| Uploads | WordPress Media Library only (MIME / sanitize handled by core) |
| External requests | Only in Debug / Test panel, opt-in. `wp_remote_post` with timeout. **Zero phone-home.** |
| Serialization | Validate `is_array` after `maybe_unserialize` (object injection guard) |
| REST rate limit | Preview endpoint capped at 20 calls/min/user (UX protection, not security) |
| SVG | Upload disabled by default |

## 8. Internationalization

- Textdomain: `open-graph-control`
- PHP: `__()`, `esc_html__()`, etc., with textdomain argument
- JS: `@wordpress/i18n`, `wp_set_script_translations` registration
- Languages folder: `languages/` — `.pot` generated via `wp i18n make-pot`
- Translation distribution: translate.wordpress.org

## 9. Testing

| Layer | Tool | Scope |
|---|---|---|
| Unit | PHPUnit + Brain Monkey | Resolvers, TagBuilder, Platforms, PlatformRegistry |
| Integration | WP PHPUnit Testsuite | Options/PostMeta repositories, end-to-end `wp_head` render per context |
| E2E admin | Playwright | Settings save, meta box, live preview, takeover UX |
| Static | PHPStan level 8 (+ WP stubs), PHPCS (WP Coding Standards) | All PHP |
| JS lint | ESLint (`@wordpress/eslint-plugin`), Prettier | React components + a11y |
| Snapshot | Playwright screenshots | 12 preview cards per platform |

- Coverage gate: 70% line coverage on PHP core (Resolvers, Platforms, TagBuilder). UI via visual snapshots.
- CI matrix: PHP 8.0 / 8.1 / 8.2 / 8.3 / 8.4 × WP 6.2 / 6.5 / 6.7 / current.

## 10. File structure

```
open-graph-control/
  open-graph-control.php       # bootstrap
  uninstall.php
  readme.txt                   # WP.org
  README.md                    # GitHub
  LICENSE                      # GPLv2
  composer.json
  package.json
  .wp-env.json
  phpunit.xml.dist
  phpstan.neon
  phpcs.xml.dist
  eslint.config.js
  playwright.config.ts
  src/                         # EvzenLeonenko\OpenGraphControl
    Plugin.php
    Renderer/{Head,TagBuilder}.php
    Platforms/{PlatformInterface,AbstractPlatform,Facebook,Twitter,LinkedIn,
               IMessage,Threads,Mastodon,Bluesky,WhatsApp,Discord,Pinterest,
               Telegram,Slack,Registry}.php
    Resolvers/{ResolverInterface,Title,Description,Image,Type,Url,Locale}.php
    Options/{Repository,DefaultSettings}.php
    PostMeta/Repository.php
    Images/SizeRegistry.php
    Admin/{Page,MetaBox,Notices,Assets}.php
    Admin/Rest/{PreviewController,DebugController,ConflictController}.php
    Integrations/{IntegrationInterface,Detector,Yoast,RankMath,AIOSEO,
                  SEOPress,Jetpack,TSF,SlimSEO}.php
    Debug/Tester.php
  assets/                      # pre-build JSX/SCSS
    admin/settings/{index.jsx,App.jsx,sections/,store/}
    admin/metabox/{index.jsx,MetaBoxApp.jsx,tabs/,previews/}
    admin/shared/
    admin.scss
  build/                       # webpack output (gitignored, shipped)
  languages/
  vendor/                      # composer deps (bundled in dist)
  tests/
    phpunit/{Unit,Integration}/
    e2e/playwright/
```

## 11. Build & distribution

- `composer install --no-dev` → `vendor/`
- `npm ci && npm run build` → `build/`
- `bin/make-dist.sh` → zip excluding `tests/`, `node_modules/`, `.git`, lock files
- SVN workflow: `trunk/` + `tags/{version}/` + `assets/` (banner, icon, screenshots)
- GitHub Actions release job: on tag `v*`, build zip, push to SVN, create tag

## 12. Minimum requirements

- PHP 8.0+
- WordPress 6.2+
- MySQL 5.7+ / MariaDB 10.3+

## 13. Roadmap

| Version | Content |
|---|---|
| **v1.0 (MVP)** | This spec |
| v1.1 | Import from Yoast/RankMath/AIOSEO, per-archive/author/term editor UI, WPML/Polylang |
| v1.2 | Dynamic OG image generation (template + text overlay, GD/Imagick) |
| v2.0 | AI-generated descriptions, A/B variants, scheduled variants |

## 14. Open design decisions (deferred until implementation planning)

- Exact settings import/export JSON schema — draft during implementation
- Rate limiter internals (transient-based vs in-memory) — pick during Preview REST controller work
- Whether to ship a "safe mode" toggle that disables all output for emergency — revisit after v1.0 feedback

## 15. Acceptance criteria (MVP exit)

- All 12 platforms enabled by default and emit correct tags on singular post
- Per-post meta box saves and overrides site defaults
- Live preview updates within 200ms of input
- Activating plugin with Yoast/RankMath/AIOSEO/SEOPress triggers takeover notice with single-click accept
- PHPStan level 8 clean, PHPCS WP Standards clean
- Playwright E2E green on PHP 8.0 + WP 6.2 and PHP 8.4 + WP current
- `readme.txt` passes WP Plugin Check
- Plugin Check plugin reports zero errors, zero warnings
