# Archive overrides — Open Graph Control v0.3

**Status:** Design approved, awaiting spec review · **Target release:** 0.3.0 · **Authors:** Evžen Leonenko (approver), Claude Opus 4.7 (drafter)

## Goal

Let site owners set Open Graph `title`, `description`, `image`, and a global `exclude` flag on **archive pages** — specifically category, tag, custom-taxonomy term, and author archives. Today the plugin supports site-wide defaults and per-post overrides; archive routes (`/category/recepty/`, `/author/evzen/`, `/portfolio-type/photography/`) fall through to site defaults with no way to customise them.

The feature ships as v0.3 after the v0.2.1 security release.

## User story

> *As a site owner I want the `/category/recepty/` archive page to share with a tailored title and image on Facebook, instead of reusing my site name. Same for author archives and custom taxonomy archives. I want to edit that directly on the term edit screen (the WordPress-native place) and also see a single-screen audit of every archive I've already configured.*

## Scope

### In scope (v0.3)

- Four archive kinds: **category, tag, author, custom taxonomies** (discovered at runtime via `get_taxonomies( [ 'public' => true ], 'names' )`, minus attachment).
- Four per-archive fields: `title`, `description`, `image_id` (attachment ID), `exclude` (suppress all tags).
- Two admin UI surfaces:
  - **Inline editor** injected into the term / user edit screen (WordPress-native discovery).
  - **Central audit section** inside the plugin's settings page (`Open Graph Control → Archive overrides`) with search + kind filter, read-mostly with "Edit →" linking back to the inline editor.
- REST API under `/open-graph-control/v1/archive-meta/...`.
- Integration into the existing resolver chain as a new `archive_override` step, runnable ahead of `seo_plugin_{title,desc}` and any site-level fallback.
- Output cache invalidation on term / user meta updates.
- PHPUnit unit coverage + Playwright WP-integration E2E.

### Explicitly out of scope (v0.3)

- **Date archives** (`/2026/04/`) — rare OG customisation use-case, separate key model (no term/user ID), deferred indefinitely unless user demand surfaces.
- **Custom post type archives** (`/products/` when `products` is a CPT with archive enabled) — belongs to the post-type defaults feature (already handled via `post_types.{slug}.default_type`), not a free-form override.
- **Per-platform overrides for archives** (`platforms.twitter.title` per archive) — niche; 95% of use-cases are covered by the 4 shared fields. Add in a follow-up if someone asks.
- **`og:type` override for archives** — archives are semantically always `website`, overriding to `article` / `profile` doesn't reflect reality.
- **Bulk actions** on the central audit table (select multiple → set common image) — nice-to-have, deferred to v0.3.1 after the base ships.
- **Project B — dynamic OG image generation** — separate design doc, separate release (v0.4).

## Data model

### Storage

| Archive kind | Storage | Meta key | Auth callback |
|---|---|---|---|
| Term (category / tag / custom tax) | `wp_termmeta` | `_ogc_meta` | `current_user_can( 'manage_terms', $taxonomy )` (falls back to `manage_categories` for built-in taxes per WP core) |
| Author (user) | `wp_usermeta` | `_ogc_meta` | `current_user_can( 'edit_user', $user_id )` |

### Shape

Stored as a serialised associative array with four allowlisted keys:

```php
[
    'title'       => string,   // empty string = no override
    'description' => string,
    'image_id'    => int,      // 0 = no override
    'exclude'     => ['all'] | [],
]
```

Shape deliberately mirrors the `'title'`, `'description'`, `'image_id'`, `'exclude'` subset of the post-level `_ogc_meta` stored on posts. Keeps the React editor component reusable across inline surfaces.

### Registration

- `register_term_meta( $taxonomy, '_ogc_meta', [ 'single' => true, 'type' => 'object', 'show_in_rest' => false, 'default' => [], 'auth_callback' => ... ] )` called once per detected public taxonomy at `init`.
- `register_meta( 'user', '_ogc_meta', ... )` registered once.
- `show_in_rest => false` is deliberate: the bespoke `ArchiveMetaController` is the **only** REST surface for `_ogc_meta`, so WordPress core's auto-registered meta endpoints never expose the key. This keeps the permission model in one place.
- Despite `'type' => 'object'`, we don't call `serialize()` ourselves — WordPress's meta API runs `maybe_serialize()` automatically for array values, and `maybe_unserialize()` on read.

### Invalidation hooks

| Hook | Filter | Action |
|---|---|---|
| `added_term_meta`, `updated_term_meta`, `deleted_term_meta` | `meta_key === '_ogc_meta'` | Flush transient for the archive term context (term_id + its taxonomy) |
| `deleted_term` | — | Flush transient for that term's archive context |
| `added_user_meta`, `updated_user_meta`, `deleted_user_meta` | `meta_key === '_ogc_meta'` | Flush transient for `Context::for_author( $user_id )` |
| `deleted_user` | — | Same |

Wired into the existing `Renderer\Cache` — add new `add_action` registrations in `Cache::register()`. Note: WordPress does not emit an `edited_term_meta` hook; the `added_ / updated_ / deleted_` trio is the correct surface for meta writes, and each callback must short-circuit when `$meta_key !== '_ogc_meta'` to avoid flushing on unrelated writes.

### Shared repository

New class `ArchiveMeta\Repository` with three public methods:

```php
public function get_for_term( int $term_id ): array;
public function get_for_user( int $user_id ): array;
public function save( string $kind, int $id, array $data ): bool;
```

- Both getters return the 4-key shape with zero-values for missing fields (never `null`).
- `save()` applies an `array_intersect_key` against an allowlist of four keys before writing.
- Dispatched from the container as `archivemeta.repository`.

## Resolver integration

### New step

Add a single `from_archive_override` step to each of the three resolvers that today consume the fallback chain. Implementation in each resolver:

```php
private function from_archive_override( Context $context ): ?string {
    if ( $context->is_author() ) {
        $user_id = $context->user_id();
        return $user_id > 0
            ? ( $this->archive->get_for_user( $user_id )['title'] ?: null )  // or 'description' / 'image_id'
            : null;
    }
    if ( $context->is_archive_term() ) {
        $term_id = $context->archive_term_id();
        return $term_id > 0
            ? ( $this->archive->get_for_term( $term_id )['title'] ?: null )
            : null;
    }
    return null;
}
```

### Chain placement (default)

| Field | Chain after this feature |
|---|---|
| title | `post_meta_override → archive_override → seo_plugin_title → post_title → site_name` |
| description | `post_meta_override → archive_override → seo_plugin_desc → post_excerpt → post_content_trim → site_description` |
| image | `post_meta_override → archive_override → featured_image → first_content_image → first_block_image → site_master_image` |

- For post contexts, `archive_override` resolves to `null` (no author / term in context), so the chain behaves identically to today.
- For archive contexts, `post_meta_override` also resolves to `null`, so the new step is effectively first.
- Placing `archive_override` *before* `seo_plugin_title` matches the principle that a user's explicit configuration wins over a third-party plugin's default. The `ogc_resolve_{field}_chain` filter lets callers flip the order if they want SEO-plugin-first.

### Exclude handling

- `Head::render()` already short-circuits on `_ogc_meta.exclude === ['all']` for post contexts. Extend `Head::is_post_excluded()` → `Head::is_context_excluded()` so it queries the archive repository when `Context` is an archive / author.

### Context class changes

`Context` already has `archive_kind` metadata but **not** `archive_term_id`. Extend:

- Add `Context::for_archive_term( string $taxonomy, int $term_id )` factory (preferred for category / tag / custom tax). `$taxonomy` fills the existing `archive_kind` slot — callers of the new factory get an archive context with both the taxonomy slug *and* the term ID available.
- Keep the existing `Context::for_archive( string $kind )` factory untouched for "kind-only" archives (e.g. the `post_type` fallback branch in `Head::detect_context()`). No deprecation — both factories coexist indefinitely.
- `Head::detect_context()` resolves the queried object via `get_queried_object()` and calls `for_archive_term()` when the archive is a taxonomy term (queried object has `term_id` + `taxonomy`), and the existing `for_archive()` otherwise.

## REST API

All endpoints live under the existing `open-graph-control/v1` namespace.

| Method | Path | Purpose | Permission callback |
|---|---|---|---|
| `GET` | `/archive-meta/term/(?P<tax>[a-z0-9_-]+)/(?P<id>\d+)` | Read term override | `current_user_can( 'manage_terms', $tax )` |
| `POST` | `/archive-meta/term/{tax}/{id}` | Write term override | Same |
| `GET` | `/archive-meta/user/(?P<id>\d+)` | Read user override | `current_user_can( 'edit_user', $id )` |
| `POST` | `/archive-meta/user/{id}` | Write user override | Same |
| `GET` | `/archive-overrides` | List all archives with an override (for central audit table) | `current_user_can( 'manage_options' )` |

### POST payload

```json
{
    "title": "…",
    "description": "…",
    "image_id": 42,
    "exclude": []
}
```

### GET /archive-overrides response

```json
{
    "terms": [
        { "tax": "category", "term_id": 12, "name": "Recepty", "fields_set": ["title", "image_id"] },
        { "tax": "post_tag", "term_id": 7,  "name": "covid",   "fields_set": ["exclude"] }
    ],
    "users": [
        { "user_id": 3, "name": "Evžen Leonenko", "fields_set": ["title", "description"] }
    ]
}
```

Scans `wp_termmeta` and `wp_usermeta` once for rows with `meta_key = '_ogc_meta'`, deserialises, filters to non-empty shapes, and joins with `wp_terms` / `wp_users` for the display name.

No pagination in v0.3 — small-blog datasets fit on a single page. If a site shows up with >500 overrides, add pagination in v0.3.1.

### Validation

- `tax` must pass `taxonomy_exists()`.
- `id` must be positive and `get_term( $id, $tax )` / `get_userdata( $id )` must return a valid object.
- `image_id` must be `> 0` and `get_post_type( $image_id ) === 'attachment'`.
- `exclude` array is intersected with the single-element allowlist `[ 'all' ]`.
- Unknown keys in payload are dropped by the repository's allowlist filter (defence in depth).

### Controller

New class `Admin\Rest\ArchiveMetaController extends AbstractController` — mirrors `MetaController`'s shape. Sanitisation helper identical to `MetaController::sanitize()` plus the image-id / taxonomy-existence gates listed above.

## Admin UI

### A. Inline editor (WP-native)

**Hook wiring:**

```php
// term edit screens for every public taxonomy
foreach ( get_taxonomies( [ 'public' => true ], 'names' ) as $tax ) {
    add_action( "{$tax}_edit_form_fields",  [ $this, 'render_term_editor' ], 10, 2 );
    add_action( "edited_{$tax}",            [ $this, 'save_term_editor' ], 10, 2 );
}

// user profile
add_action( 'show_user_profile',            [ $this, 'render_user_editor' ] );
add_action( 'edit_user_profile',            [ $this, 'render_user_editor' ] );
add_action( 'personal_options_update',      [ $this, 'save_user_editor' ] );
add_action( 'edit_user_profile_update',     [ $this, 'save_user_editor' ] );
```

**Mount markup (term):** injected as `<tr>` rows before the closing `</table>` of the standard edit form.

```html
<tr class="form-field">
    <th><label>Open Graph overrides</label></th>
    <td><div id="ogc-archive-root" data-kind="term" data-tax="category" data-id="12"></div></td>
</tr>
```

**Mount markup (user):** `<h2>` + `<div id="ogc-archive-root" data-kind="user" data-id="3">` inside the profile table.

**Bundle:** new webpack entry `build/admin/archive.js` (+ `archive.css`) containing a single React component that:
1. Reads mount `data-*` attributes to determine kind / taxonomy / id.
2. Fetches initial state from the REST endpoint.
3. Renders four controls (`TextControl` × 2 + `MediaPicker` + `CheckboxControl`).
4. Saves through the REST endpoint on an explicit "Save overrides" button (no autosave — matches the per-post meta box pattern).

Enqueued in `Assets::enqueue()` when hook is one of `edit-tags.php`, `term.php`, `user-edit.php`, `profile.php`.

Nonce: WP core's per-term and per-user update nonces already cover the save path (the form we inject into has one). `X-WP-Nonce` covers the REST path.

### B. Central audit section

New section `Security → Archive overrides` in the existing React settings app:

```jsx
{ key: 'archive-overrides', label: 'Archive overrides', Component: ArchiveOverrides }
```

Component fetches `GET /archive-overrides`, renders:

- Top row: search input (client-side filter by `name`), kind dropdown (`All | category | post_tag | custom tax slugs | user`).
- Table (`.wp-list-table widefat striped`) with columns `Kind · Name · Fields set · Action`.
- "Fields set" column renders a row of small severity-coloured pills using the existing tokens (`--ogc-color-info-surface` for title/description/image, `--ogc-color-error-surface` for `exclude`).
- "Edit →" links to the WP-native edit screen (`edit-tags.php?taxonomy={tax}&tag_ID={id}` for terms, `user-edit.php?user_id={id}` for users), so there's a single editing path — the central view is audit-only.
- Empty state: a centred message ("No archives configured yet. Set one up on a category, tag, or author edit screen.") with a link to `/wp-admin/edit-tags.php?taxonomy=category`.

## Capabilities summary

Same as the REST table — pulled from WordPress core conventions:

| Surface | Built-in taxes | Custom taxes | Authors |
|---|---|---|---|
| Read override | `manage_categories` | `manage_terms` (taxonomy-scoped) | `edit_user` |
| Write override | Same | Same | Same |
| View central audit table | `manage_options` (site-admin only) |||
| Call `GET /archive-overrides` | `manage_options` |||

No new custom capabilities introduced — this matters because WordPress.org reviewers prefer plugins that reuse WP core's cap model over inventing their own.

## Testing strategy

### PHPUnit (unit, Brain Monkey stubs)

New files:

- `tests/phpunit/Unit/ArchiveMeta/RepositoryTest.php`
  - `get_for_term()` returns zero-values for unset keys
  - `get_for_term()` returns stored values for set keys
  - `save()` allowlist-filters unknown keys
  - `save()` trims the `exclude` array to valid options
- `tests/phpunit/Unit/Admin/Rest/ArchiveMetaControllerTest.php`
  - GET returns 403 without capability
  - GET returns 404 for nonexistent term / user
  - POST sanitises title / description (simulated `sanitize_text_field`)
  - POST rejects non-attachment `image_id`
  - POST round-trips through repository
  - `GET /archive-overrides` aggregates term + user meta correctly

Extensions to existing:

- `tests/phpunit/Unit/Resolvers/TitleTest.php` + `DescriptionTest.php` + `ImageTest.php`
  - New `from_archive_override` step for author + term contexts
  - Falls through when override is empty
- `tests/phpunit/Unit/Renderer/HeadTest.php`
  - Archive context with `exclude === ['all']` emits nothing
  - Archive context without exclude emits tags with override values

### Playwright WP-integration (E2E)

- `tests/e2e/playwright/07-archive-override.spec.ts`
  - Create category "Recepty" via `wp term create`
  - Log in, navigate to `edit-tags.php?taxonomy=category`, click "Recepty"
  - Fill OG title + OG description in the inline editor, click "Save overrides"
  - Visit `/?cat={id}`, assert `<meta property="og:title" content="Recepty z české spíže">`
- Continuation: author override
  - Navigate to user profile
  - Fill + save
  - Visit author archive, assert override rendered
- Accessibility: extend `05-a11y-admin.spec.ts` (or add `08-a11y-archive.spec.ts`) to run axe against both the inline editor and the central audit section.

### Responsive

- `06-responsive.spec.ts` already covers the settings page at ≤782px. Add a case for the central audit table (flex column layout, touch-sized action buttons).

### PHPStan

Level 8 must still pass. New classes get proper generic types for the repository (`array<string, mixed>`) and typed shape for the serialised meta (`array{title: string, description: string, image_id: int, exclude: array<int, string>}`).

## Decision log

| Decision | Choice | Rejected alternative + reason |
|---|---|---|
| Scope of archive kinds | category + tag + author + custom taxonomies | MVP (only built-in) — 20% of use cases miss; Full (+ date + CPT archives) — marginal and adds key-model complexity |
| UI surfaces | Both inline + central | Inline-only — no audit path at scale; Central-only — users never look there, discoverability drops |
| Fields in v0.3 | title, description, image_id, exclude | Full parity (add type, platforms) — 95% of users won't use, adds UI weight |
| Storage | Native `term_meta` + `user_meta` | Single `ogc_archive_overrides` option — grows unbounded, loses native hook support |
| Resolver placement | `archive_override` before `seo_plugin_*` | `archive_override` after — implicit assumption that SEO plugin defaults should win; user expectation is the opposite once they've *explicitly* configured an override |
| Central table pagination | None in v0.3 | Paginated — small-dataset YAGNI; revisit when someone reports >500 overrides |
| New custom capabilities | None | Custom `ogc_edit_archive_meta` cap — WP.org reviewers prefer core caps; no real security delta |

## Open questions / follow-ups (not in v0.3)

1. **Bulk actions** on the central table — wait for demand.
2. **Date archives** — wait for demand.
3. **Per-platform overrides for archives** — wait for demand.
4. **Dynamic OG image generation (Project B)** — separate spec.
5. **Bulk-import archive overrides from JSON** — extend existing Import/Export once storage is stable.
