# Archive overrides (v0.3) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ship per-archive / per-author Open Graph overrides: four fields (title, description, image_id, exclude) editable inline on term/user edit screens plus a central audit table in settings, with resolver-chain integration and REST surface.

**Architecture:** Native `term_meta` + `user_meta` storage under key `_ogc_meta`. New `archive_override` step slotted into existing resolver chains ahead of `seo_plugin_*`. New REST controller `ArchiveMetaController` mirrors the `MetaController` shape. Two admin UI surfaces — WP-native `<tr>` injection on term edit screens via `{taxonomy}_edit_form_fields`, user-profile block via `show_user_profile`/`edit_user_profile`, both backed by a shared `build/admin/archive.js` React bundle; plus a new `ArchiveOverrides` section in the existing settings SPA with a search/filter audit table linking back to the native edit screens.

**Tech Stack:** PHP 8.1 · WordPress 6.2+ · @wordpress/components React · PHPUnit 10.5 + Brain Monkey stubs · Playwright for E2E · PHPStan level 8

**Spec:** `docs/superpowers/specs/2026-04-19-archive-overrides-design.md`

---

## File Structure

### New files (PHP)

| Path | Responsibility |
|---|---|
| `src/ArchiveMeta/Repository.php` | Get/save 4-field `_ogc_meta` for a term or user, allowlist-filter unknown keys |
| `src/Admin/Rest/ArchiveMetaController.php` | 5 REST endpoints under `/archive-meta` + `/archive-overrides`; permission callbacks, sanitisation, image-id and taxonomy validation |
| `src/Admin/TermEditor.php` | Hooks into `{taxonomy}_edit_form_fields` and `edited_{taxonomy}` to inject and save the React mount point. Nonce handled by the WP-core term update nonce that's already on the page |
| `src/Admin/UserEditor.php` | Hooks into `show_user_profile` / `edit_user_profile` / `personal_options_update` / `edit_user_profile_update` for the user-profile surface |

### Existing files (PHP, modify)

| Path | Change |
|---|---|
| `src/Resolvers/Context.php` | Add `for_archive_term( string $taxonomy, int $term_id )` factory; add `archive_term_id(): ?int` accessor |
| `src/Resolvers/Title.php` | Add `from_archive_override` step; constructor gains `ArchiveMeta\Repository` dep |
| `src/Resolvers/Description.php` | Same |
| `src/Resolvers/Image.php` | Same |
| `src/Options/DefaultSettings.php` | Insert `'archive_override'` into the three default fallback chains, between `post_meta_override` and `seo_plugin_*`/`featured_image` |
| `src/Renderer/Head.php` | Rename `is_post_excluded()` → `is_context_excluded()`; query archive repo for archive/author contexts; extend `detect_context()` to call `Context::for_archive_term` when the queried object is a term |
| `src/Renderer/Cache.php` | Register new hooks: `(added\|updated\|deleted)_term_meta`, `deleted_term`, `(added\|updated\|deleted)_user_meta`, `deleted_user` — all filtered on `meta_key === '_ogc_meta'` where applicable |
| `src/Bootstrap.php` | Register `archivemeta.repository` + `rest.archive_meta` + `admin.term_editor` + `admin.user_editor`; inject repo into the three resolver services |
| `src/Plugin.php` | Register the two new admin editor services on `init` / `admin_init` |
| `src/Admin/Assets.php` | Enqueue the `archive` bundle on `edit-tags.php`, `term.php`, `user-edit.php`, `profile.php` screens |

### New files (JS / React)

| Path | Responsibility |
|---|---|
| `assets/admin/archive/index.jsx` | Entry point for the `archive` webpack bundle. Mounts `<ArchiveEditor>` onto any `#ogc-archive-root` element, reads `data-kind` / `data-tax` / `data-id` |
| `assets/admin/archive/ArchiveEditor.jsx` | The 4-field editor component; REST GET on mount, POST on save, status feedback reusing `.ogc-section-footer__status-region` pattern |
| `assets/admin/archive/archive.scss` | Minimal styles; imports `../shared/utilities` |
| `assets/admin/settings/sections/ArchiveOverrides.jsx` | Central audit table in settings SPA: search, kind filter, "fields-set" severity pills, "Edit →" link to native WP edit screen |

### Existing files (JS / React, modify)

| Path | Change |
|---|---|
| `assets/admin/shared/api.js` | Add `archiveMeta.getTerm()`, `.saveTerm()`, `.getUser()`, `.saveUser()`, `.listOverrides()` helpers |
| `assets/admin/settings/App.jsx` | Register the new `archive-overrides` entry in the `SECTIONS` array |
| `webpack.config.js` | Add `archive: 'assets/admin/archive/index.jsx'` entry |

### New files (tests)

| Path | Responsibility |
|---|---|
| `tests/phpunit/Unit/ArchiveMeta/RepositoryTest.php` | Shape contract, allowlist filter, zero-value defaults |
| `tests/phpunit/Unit/Admin/Rest/ArchiveMetaControllerTest.php` | Permission callbacks, sanitisation, invalid taxonomy/user, image-id validation, aggregation endpoint |
| `tests/e2e/playwright/07-archive-override.spec.ts` | Term override roundtrip (category) + author override roundtrip, via actual wp-admin screens |

### Existing tests (modify)

| Path | Change |
|---|---|
| `tests/phpunit/Unit/Resolvers/TitleTest.php` | New cases: archive_override returns term-meta title, falls through when empty, null for post context |
| `tests/phpunit/Unit/Resolvers/DescriptionTest.php` | Same pattern |
| `tests/phpunit/Unit/Resolvers/ImageTest.php` | Same pattern |
| `tests/phpunit/Unit/Renderer/HeadTest.php` | Archive context with `exclude === ['all']` emits nothing; archive context without exclude emits overridden values |
| `tests/e2e/playwright/05-a11y-admin.spec.ts` | Add `Archive overrides` section to the axe scan |

---

## Task 1: Extend Context for archive-term identity

**Files:**
- Modify: `src/Resolvers/Context.php`
- Test: `tests/phpunit/Unit/Resolvers/ContextTest.php`

- [ ] **Step 1: Write the failing tests**

Append to `tests/phpunit/Unit/Resolvers/ContextTest.php`:

```php
public function test_for_archive_term_stores_taxonomy_and_term_id(): void {
    $context = Context::for_archive_term( 'category', 42 );
    self::assertTrue( $context->is_archive() );
    self::assertTrue( $context->is_archive_term() );
    self::assertSame( 42, $context->archive_term_id() );
    self::assertSame( 'category', $context->archive_kind() );
}

public function test_for_archive_returns_null_term_id(): void {
    $context = Context::for_archive( 'post_type' );
    self::assertFalse( $context->is_archive_term() );
    self::assertNull( $context->archive_term_id() );
}
```

- [ ] **Step 2: Run tests, confirm failure**

Run: `composer test -- --filter ContextTest`
Expected: 2 new tests FAIL with "method not defined".

- [ ] **Step 3: Implement `Context::for_archive_term` + accessors**

In `src/Resolvers/Context.php`, alongside the existing `for_archive` factory:

```php
public static function for_archive_term( string $taxonomy, int $term_id ): self {
    return new self(
        self::TYPE_ARCHIVE,
        null,
        [
            'archive_kind'    => $taxonomy,
            'archive_term_id' => $term_id,
        ]
    );
}

public function is_archive_term(): bool {
    return self::TYPE_ARCHIVE === $this->type
        && null !== ( $this->meta['archive_term_id'] ?? null );
}

public function archive_term_id(): ?int {
    $id = $this->meta['archive_term_id'] ?? null;
    return is_int( $id ) ? $id : null;
}
```

Add a public `archive_kind(): ?string` accessor if it doesn't already exist (reads `$this->meta['archive_kind']`).

- [ ] **Step 4: Verify tests pass**

Run: `composer test -- --filter ContextTest`
Expected: all green.

- [ ] **Step 5: Commit**

```bash
git add src/Resolvers/Context.php tests/phpunit/Unit/Resolvers/ContextTest.php
git commit -m "feat(context): add for_archive_term factory for taxonomy archive contexts"
```

---

## Task 2: ArchiveMeta repository

**Files:**
- Create: `src/ArchiveMeta/Repository.php`
- Test: `tests/phpunit/Unit/ArchiveMeta/RepositoryTest.php`

- [ ] **Step 1: Write the failing test file**

`tests/phpunit/Unit/ArchiveMeta/RepositoryTest.php`:

```php
<?php
declare(strict_types=1);

namespace EvzenLeonenko\OpenGraphControl\Tests\Unit\ArchiveMeta;

use Brain\Monkey;
use Brain\Monkey\Functions;
use EvzenLeonenko\OpenGraphControl\ArchiveMeta\Repository;
use PHPUnit\Framework\TestCase;

final class RepositoryTest extends TestCase {

    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();
    }

    protected function tearDown(): void {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_get_for_term_returns_zero_values_when_unset(): void {
        Functions\when( 'get_term_meta' )->justReturn( '' );
        $r = new Repository();
        self::assertSame(
            [ 'title' => '', 'description' => '', 'image_id' => 0, 'exclude' => [] ],
            $r->get_for_term( 5 )
        );
    }

    public function test_get_for_term_returns_stored_values(): void {
        Functions\when( 'get_term_meta' )->justReturn(
            [ 'title' => 'Recepty', 'image_id' => 42 ]
        );
        $r = new Repository();
        $out = $r->get_for_term( 5 );
        self::assertSame( 'Recepty', $out['title'] );
        self::assertSame( 42, $out['image_id'] );
        self::assertSame( '', $out['description'] );
        self::assertSame( [], $out['exclude'] );
    }

    public function test_save_allowlist_filters_unknown_keys(): void {
        $captured = null;
        Functions\expect( 'update_term_meta' )
            ->once()
            ->andReturnUsing(
                function ( $term_id, $key, $value ) use ( &$captured ) {
                    $captured = $value;
                    return true;
                }
            );
        $r = new Repository();
        $r->save(
            'term',
            5,
            [
                'title'        => 'X',
                'random_key'   => 'dropped',
                'image_id'     => 9,
                'exclude'      => [ 'all' ],
                '__proto__'    => 'nope',
            ]
        );
        self::assertSame(
            [ 'title' => 'X', 'image_id' => 9, 'exclude' => [ 'all' ] ],
            $captured
        );
    }

    public function test_save_routes_user_kind_to_user_meta(): void {
        Functions\expect( 'update_user_meta' )->once()->andReturn( true );
        Functions\expect( 'update_term_meta' )->never();
        ( new Repository() )->save( 'user', 3, [ 'title' => 'Evžen' ] );
    }
}
```

- [ ] **Step 2: Run tests, confirm failure**

Run: `composer test -- --filter RepositoryTest`
Expected: 4 tests FAIL with "Class not found" or similar.

- [ ] **Step 3: Implement `src/ArchiveMeta/Repository.php`**

```php
<?php
/**
 * Per-archive override repository.
 *
 * @package EvzenLeonenko\OpenGraphControl
 */

declare(strict_types=1);

namespace EvzenLeonenko\OpenGraphControl\ArchiveMeta;

defined( 'ABSPATH' ) || exit;

/**
 * Reads and writes the _ogc_meta blob on terms + users.
 *
 * Storage uses native `term_meta` / `user_meta` — each override row lives on
 * the object it belongs to. A 4-field allowlist (`title`, `description`,
 * `image_id`, `exclude`) is applied on every write; extra keys submitted via
 * the REST API or a misconfigured filter are dropped.
 */
class Repository {

    public const META_KEY = '_ogc_meta';

    /** @var array<int, string> */
    private const ALLOWED_KEYS = [ 'title', 'description', 'image_id', 'exclude' ];

    /**
     * @return array{title: string, description: string, image_id: int, exclude: array<int, string>}
     */
    public function get_for_term( int $term_id ): array {
        $raw = get_term_meta( $term_id, self::META_KEY, true );
        return $this->normalize( is_array( $raw ) ? $raw : [] );
    }

    /**
     * @return array{title: string, description: string, image_id: int, exclude: array<int, string>}
     */
    public function get_for_user( int $user_id ): array {
        $raw = get_user_meta( $user_id, self::META_KEY, true );
        return $this->normalize( is_array( $raw ) ? $raw : [] );
    }

    /**
     * @param array<string, mixed> $data
     */
    public function save( string $kind, int $id, array $data ): bool {
        $filtered = array_intersect_key( $data, array_flip( self::ALLOWED_KEYS ) );
        if ( 'user' === $kind ) {
            return (bool) update_user_meta( $id, self::META_KEY, $filtered );
        }
        return (bool) update_term_meta( $id, self::META_KEY, $filtered );
    }

    /**
     * @param array<string, mixed> $stored
     * @return array{title: string, description: string, image_id: int, exclude: array<int, string>}
     */
    private function normalize( array $stored ): array {
        return [
            'title'       => (string) ( $stored['title'] ?? '' ),
            'description' => (string) ( $stored['description'] ?? '' ),
            'image_id'    => (int) ( $stored['image_id'] ?? 0 ),
            'exclude'     => is_array( $stored['exclude'] ?? null )
                ? array_values( $stored['exclude'] )
                : [],
        ];
    }
}
```

- [ ] **Step 4: Verify tests pass + PHPStan**

Run: `composer test -- --filter RepositoryTest && composer stan`
Expected: tests green, PHPStan clean.

- [ ] **Step 5: Commit**

```bash
git add src/ArchiveMeta/Repository.php tests/phpunit/Unit/ArchiveMeta/RepositoryTest.php
git commit -m "feat(archive): ArchiveMeta repository with term + user storage + allowlist"
```

---

## Task 3: Register `_ogc_meta` term + user meta

**Files:**
- Modify: `src/ArchiveMeta/Repository.php` (add `register_meta()` method + `register()` lifecycle hook)
- Modify: `src/Bootstrap.php` (register service)
- Modify: `src/Plugin.php` (call `register()` on boot)
- Test: `tests/phpunit/Unit/ArchiveMeta/RepositoryTest.php` (new test for registration)

- [ ] **Step 1: Write the failing registration test**

Append to `RepositoryTest.php`:

```php
public function test_register_meta_iterates_public_taxonomies(): void {
    $registered = [];
    Functions\when( 'get_taxonomies' )->justReturn(
        [ 'category' => 'category', 'post_tag' => 'post_tag', 'portfolio_type' => 'portfolio_type' ]
    );
    Functions\when( 'register_term_meta' )->alias(
        static function ( string $tax, string $key ) use ( &$registered ): void {
            $registered[] = "term:{$tax}:{$key}";
        }
    );
    Functions\when( 'register_meta' )->alias(
        static function ( string $type, string $key ) use ( &$registered ): void {
            $registered[] = "{$type}:{$key}";
        }
    );
    ( new Repository() )->register_meta();

    self::assertContains( 'term:category:_ogc_meta', $registered );
    self::assertContains( 'term:post_tag:_ogc_meta', $registered );
    self::assertContains( 'term:portfolio_type:_ogc_meta', $registered );
    self::assertContains( 'user:_ogc_meta', $registered );
}
```

- [ ] **Step 2: Run, confirm fail**

Run: `composer test -- --filter test_register_meta_iterates_public_taxonomies`
Expected: FAIL.

- [ ] **Step 3: Implement registration**

Add to `Repository`:

```php
public function register(): void {
    add_action( 'init', [ $this, 'register_meta' ], 5 );
}

public function register_meta(): void {
    /** @var array<string, string> $taxes */
    $taxes = get_taxonomies( [ 'public' => true ], 'names' );
    unset( $taxes['attachment'] );

    foreach ( $taxes as $tax ) {
        register_term_meta(
            $tax,
            self::META_KEY,
            [
                'single'        => true,
                'type'          => 'object',
                'show_in_rest'  => false,
                'default'       => [],
                'auth_callback' => static function ( bool $allowed, string $meta_key, int $object_id ) use ( $tax ): bool {
                    unset( $allowed, $meta_key );
                    return current_user_can( 'manage_terms', $tax, $object_id );
                },
            ]
        );
    }

    register_meta(
        'user',
        self::META_KEY,
        [
            'single'        => true,
            'type'          => 'object',
            'show_in_rest'  => false,
            'default'       => [],
            'auth_callback' => static function ( bool $allowed, string $meta_key, int $object_id ): bool {
                unset( $allowed, $meta_key );
                return current_user_can( 'edit_user', $object_id );
            },
        ]
    );
}
```

- [ ] **Step 4: Wire in Bootstrap**

In `src/Bootstrap.php`, alongside the existing repository setups:

```php
$container->set(
    'archivemeta.repository',
    static fn () => new ArchiveMetaRepository()
);
```

(Add `use EvzenLeonenko\OpenGraphControl\ArchiveMeta\Repository as ArchiveMetaRepository;` at the top.)

In `src/Plugin.php`, in the method that boots services (alongside `postmeta.repository`):

```php
$this->container->get( 'archivemeta.repository' )->register();
```

- [ ] **Step 5: Verify tests + lint**

Run: `composer test && composer stan && composer cs`
Expected: all green.

- [ ] **Step 6: Commit**

```bash
git add src/ArchiveMeta/Repository.php src/Bootstrap.php src/Plugin.php tests/phpunit/Unit/ArchiveMeta/RepositoryTest.php
git commit -m "feat(archive): register _ogc_meta on public taxonomies + users at init"
```

---

## Task 4: Resolver `archive_override` step — Title

**Files:**
- Modify: `src/Resolvers/Title.php` (add step, constructor dep)
- Modify: `src/Options/DefaultSettings.php` (insert `archive_override` into default title chain)
- Modify: `src/Bootstrap.php` (inject repo into `resolver.title`)
- Test: `tests/phpunit/Unit/Resolvers/TitleTest.php` (new cases)

- [ ] **Step 1: Write the failing tests**

Append to `TitleTest.php`:

```php
public function test_archive_override_returns_term_meta_title_for_term_archive(): void {
    Filters\expectApplied( 'ogc_resolve_title_chain' )->andReturnFirstArg();
    Filters\expectApplied( 'ogc_resolve_title_value' )->andReturnFirstArg();

    $archive = $this->createStub(
        \EvzenLeonenko\OpenGraphControl\ArchiveMeta\Repository::class
    );
    $archive->method( 'get_for_term' )->willReturn(
        [ 'title' => 'Recepty z české spíže', 'description' => '', 'image_id' => 0, 'exclude' => [] ]
    );

    $r = $this->resolver_with_archive(
        $archive,
        null,
        [ 'archive_override' ]
    );

    $context = \EvzenLeonenko\OpenGraphControl\Resolvers\Context::for_archive_term(
        'category',
        12
    );
    self::assertSame( 'Recepty z české spíže', $r->resolve( $context ) );
}

public function test_archive_override_returns_user_meta_title_for_author(): void {
    Filters\expectApplied( 'ogc_resolve_title_chain' )->andReturnFirstArg();
    Filters\expectApplied( 'ogc_resolve_title_value' )->andReturnFirstArg();

    $archive = $this->createStub(
        \EvzenLeonenko\OpenGraphControl\ArchiveMeta\Repository::class
    );
    $archive->method( 'get_for_user' )->willReturn(
        [ 'title' => 'Evžen Leonenko', 'description' => '', 'image_id' => 0, 'exclude' => [] ]
    );

    $r = $this->resolver_with_archive( $archive, null, [ 'archive_override' ] );
    $context = Context::for_author( 3 );
    self::assertSame( 'Evžen Leonenko', $r->resolve( $context ) );
}

public function test_archive_override_null_on_post_context(): void {
    Filters\expectApplied( 'ogc_resolve_title_chain' )->andReturnFirstArg();

    $archive = $this->createStub(
        \EvzenLeonenko\OpenGraphControl\ArchiveMeta\Repository::class
    );
    $archive->expects( self::never() )->method( 'get_for_term' );
    $archive->expects( self::never() )->method( 'get_for_user' );

    $r = $this->resolver_with_archive( $archive, null, [ 'archive_override' ] );
    self::assertNull( $r->resolve( Context::for_post( 1 ) ) );
}
```

Add this helper inside `TitleTest`:

```php
private function resolver_with_archive(
    \EvzenLeonenko\OpenGraphControl\ArchiveMeta\Repository $archive,
    array $postmeta = null,
    array $chain = null
): Title {
    // Same as existing resolver() helper, but with the archive repo injected.
    // … construct Title with postmeta + options + archive
}
```

(Adapt to the existing `resolver()` helper pattern in the file — the exact helper signature depends on what's already there.)

- [ ] **Step 2: Run tests, confirm failure**

Run: `composer test -- --filter TitleTest`
Expected: 3 new tests FAIL.

- [ ] **Step 3: Implement `from_archive_override` in Title**

Modify `src/Resolvers/Title.php`:

1. Add constructor param:

```php
public function __construct(
    private PostMetaRepository $postmeta,
    private OptionsRepository $options,
    private \EvzenLeonenko\OpenGraphControl\ArchiveMeta\Repository $archive
) {}
```

2. Add the match arm:

```php
private function step( string $step, Context $context ): ?string {
    return match ( $step ) {
        'post_meta_override' => $this->from_post_meta( $context ),
        'archive_override'   => $this->from_archive_override( $context ),
        'seo_plugin_title'   => $this->from_seo_plugin( $context ),
        'post_title'         => $context->is_singular() && null !== $context->post_id() ? (string) get_the_title( $context->post_id() ) : null,
        'site_name'          => (string) get_bloginfo( 'name' ),
        default              => null,
    };
}

private function from_archive_override( Context $context ): ?string {
    if ( $context->is_author() ) {
        $user_id = $context->user_id();
        if ( null === $user_id || $user_id <= 0 ) {
            return null;
        }
        $title = $this->archive->get_for_user( $user_id )['title'];
        return '' === $title ? null : $title;
    }
    if ( $context->is_archive_term() ) {
        $term_id = $context->archive_term_id();
        if ( null === $term_id || $term_id <= 0 ) {
            return null;
        }
        $title = $this->archive->get_for_term( $term_id )['title'];
        return '' === $title ? null : $title;
    }
    return null;
}
```

- [ ] **Step 4: Update DefaultSettings title chain**

In `src/Options/DefaultSettings.php`, find the `fallback_chains.title` default and insert `'archive_override'` between `'post_meta_override'` and `'seo_plugin_title'`:

```php
'title' => [ 'post_meta_override', 'archive_override', 'seo_plugin_title', 'post_title', 'site_name' ],
```

- [ ] **Step 5: Update Bootstrap wiring**

In `src/Bootstrap.php`, update `resolver.title`:

```php
$container->set(
    'resolver.title',
    static fn ( Container $c ) => new Title(
        $c->get( 'postmeta.repository' ),
        $c->get( 'options.repository' ),
        $c->get( 'archivemeta.repository' )
    )
);
```

- [ ] **Step 6: Run tests + stan + cs**

Run: `composer test && composer stan && composer cs`
Expected: all green.

- [ ] **Step 7: Commit**

```bash
git add src/Resolvers/Title.php src/Options/DefaultSettings.php src/Bootstrap.php tests/phpunit/Unit/Resolvers/TitleTest.php
git commit -m "feat(resolver): archive_override step in title chain"
```

---

## Task 5: Resolver `archive_override` step — Description

**Files:** Same structure as Task 4 but for `Description`.

- [ ] **Step 1-7: Repeat Task 4's pattern for `src/Resolvers/Description.php`**

Same test pattern, same implementation, same wiring. Default chain becomes:

```php
'description' => [ 'post_meta_override', 'archive_override', 'seo_plugin_desc', 'post_excerpt', 'post_content_trim', 'site_description' ],
```

Commit message:

```
feat(resolver): archive_override step in description chain
```

---

## Task 6: Resolver `archive_override` step — Image

**Files:** Same structure as Task 4 but for `Image`.

- [ ] **Step 1-7: Repeat Task 4's pattern for `src/Resolvers/Image.php`**

Difference: `from_archive_override` returns a **string representation of `image_id`** (matching the existing resolver convention — attachment IDs flow as numeric strings through the chain, resolved to URLs downstream by the Facebook / Twitter / Pinterest platform classes):

```php
private function from_archive_override( Context $context ): ?string {
    if ( $context->is_author() ) {
        $user_id = $context->user_id();
        if ( null === $user_id || $user_id <= 0 ) {
            return null;
        }
        $id = $this->archive->get_for_user( $user_id )['image_id'];
        return $id > 0 ? (string) $id : null;
    }
    if ( $context->is_archive_term() ) {
        $term_id = $context->archive_term_id();
        if ( null === $term_id || $term_id <= 0 ) {
            return null;
        }
        $id = $this->archive->get_for_term( $term_id )['image_id'];
        return $id > 0 ? (string) $id : null;
    }
    return null;
}
```

Default chain becomes:

```php
'image' => [ 'post_meta_override', 'archive_override', 'featured_image', 'first_content_image', 'first_block_image', 'site_master_image' ],
```

Commit message:

```
feat(resolver): archive_override step in image chain
```

---

## Task 7: `Head` archive-term context detection + exclude handling

**Files:**
- Modify: `src/Renderer/Head.php`
- Test: `tests/phpunit/Unit/Renderer/HeadTest.php`

- [ ] **Step 1: Write failing tests**

Append to `HeadTest.php`:

```php
public function test_detect_context_returns_archive_term_for_category(): void {
    $this->stubContextDetection( 'archive' );
    Functions\when( 'is_category' )->justReturn( true );
    Functions\when( 'get_queried_object' )->justReturn(
        (object) [ 'term_id' => 12, 'taxonomy' => 'category' ]
    );

    $head = $this->head( $this->registryWithTags( [] ) );
    $ref  = new \ReflectionClass( $head );
    $m    = $ref->getMethod( 'detect_context' );
    $m->setAccessible( true );
    $context = $m->invoke( $head );

    self::assertTrue( $context->is_archive_term() );
    self::assertSame( 12, $context->archive_term_id() );
    self::assertSame( 'category', $context->archive_kind() );
}

public function test_archive_context_excluded_emits_nothing(): void {
    $this->stubContextDetection( 'archive' );
    Functions\when( 'is_category' )->justReturn( true );
    Functions\when( 'get_queried_object' )->justReturn(
        (object) [ 'term_id' => 5, 'taxonomy' => 'category' ]
    );
    $archive = $this->createStub(
        \EvzenLeonenko\OpenGraphControl\ArchiveMeta\Repository::class
    );
    $archive->method( 'get_for_term' )->willReturn(
        [ 'title' => '', 'description' => '', 'image_id' => 0, 'exclude' => [ 'all' ] ]
    );

    $head = $this->head(
        $this->registryWithTags( [ new Tag( Tag::KIND_PROPERTY, 'og:title', 'X' ) ] ),
        [ 'non_post_pages.archive.enabled' => true ],
        [], // no post exclude
        $archive
    );

    ob_start();
    $head->render();
    self::assertSame( '', ob_get_clean() );
}
```

(Update the `$this->head()` helper to accept an optional `archive` repo stub.)

- [ ] **Step 2: Run, confirm fail**

Run: `composer test -- --filter HeadTest`
Expected: 2 new tests FAIL.

- [ ] **Step 3: Extend `Head::detect_context`**

In `src/Renderer/Head.php`, inside the existing `is_archive()` branch, prefer a term-aware factory when the queried object has a taxonomy:

```php
if ( is_archive() ) {
    $queried = get_queried_object();
    if (
        is_object( $queried )
        && isset( $queried->term_id )
        && isset( $queried->taxonomy )
        && is_string( $queried->taxonomy )
    ) {
        return Context::for_archive_term( (string) $queried->taxonomy, (int) $queried->term_id );
    }
    $kind = is_category() ? 'category' : ( is_tag() ? 'tag' : ( is_tax() ? 'taxonomy' : 'post_type' ) );
    return Context::for_archive( $kind );
}
```

- [ ] **Step 4: Rename `is_post_excluded` to `is_context_excluded` + extend**

Replace the existing method:

```php
private function is_context_excluded( Context $context ): bool {
    if ( $context->is_singular() && null !== $context->post_id() ) {
        $meta = $this->postmeta->get( $context->post_id() );
        return in_array( 'all', $meta['exclude'], true );
    }
    if ( $context->is_author() ) {
        $user_id = $context->user_id();
        if ( null === $user_id ) {
            return false;
        }
        return in_array( 'all', $this->archive->get_for_user( $user_id )['exclude'], true );
    }
    if ( $context->is_archive_term() ) {
        $term_id = $context->archive_term_id();
        if ( null === $term_id ) {
            return false;
        }
        return in_array( 'all', $this->archive->get_for_term( $term_id )['exclude'], true );
    }
    return false;
}
```

Update the call-site in `render()` accordingly. Add the new `archive` constructor param + update `Bootstrap.php` wiring for `renderer.head`.

- [ ] **Step 5: Verify tests + stan + cs**

Run: `composer test && composer stan && composer cs`
Expected: all green.

- [ ] **Step 6: Commit**

```bash
git add src/Renderer/Head.php src/Bootstrap.php tests/phpunit/Unit/Renderer/HeadTest.php
git commit -m "feat(head): detect archive-term context + exclude handling for archives/authors"
```

---

## Task 8: ArchiveMetaController — REST endpoints

**Files:**
- Create: `src/Admin/Rest/ArchiveMetaController.php`
- Create: `tests/phpunit/Unit/Admin/Rest/ArchiveMetaControllerTest.php`
- Modify: `src/Bootstrap.php` (register service)
- Modify: `src/Plugin.php` (register routes on `rest_api_init`)

- [ ] **Step 1: Write failing controller tests**

Create `tests/phpunit/Unit/Admin/Rest/ArchiveMetaControllerTest.php`. Cover:

1. `GET /archive-meta/term/{tax}/{id}` without cap → `false` from permission callback
2. `GET /archive-meta/user/{id}` without cap → false
3. `POST` with unknown `tax` → 400 "invalid_taxonomy"
4. `POST` with `image_id` pointing at a non-attachment → 400 "invalid_image"
5. Round-trip: POST `{ title: "X" }` then GET returns `{ title: "X", description: "", image_id: 0, exclude: [] }`
6. `GET /archive-overrides` aggregates: stub `get_terms` + `get_users` to return one term + one user with `_ogc_meta`, assert the response payload shape

Mirror `MetaControllerTest.php` style.

- [ ] **Step 2: Run, confirm fail**

Run: `composer test -- --filter ArchiveMetaControllerTest`
Expected: FAIL.

- [ ] **Step 3: Implement the controller**

Create `src/Admin/Rest/ArchiveMetaController.php`. Five routes:

```php
<?php
declare(strict_types=1);

namespace EvzenLeonenko\OpenGraphControl\Admin\Rest;

defined( 'ABSPATH' ) || exit;

use EvzenLeonenko\OpenGraphControl\ArchiveMeta\Repository;
use WP_REST_Request;
use WP_REST_Response;

final class ArchiveMetaController extends AbstractController {

    public function __construct( private Repository $archive ) {}

    public function register_routes(): void {
        register_rest_route(
            self::NAMESPACE_BASE,
            '/archive-meta/term/(?P<tax>[a-z0-9_-]+)/(?P<id>\d+)',
            [
                [
                    'methods'             => 'GET',
                    'callback'            => [ $this, 'get_term' ],
                    'permission_callback' => [ $this, 'can_manage_term_from_request' ],
                ],
                [
                    'methods'             => 'POST',
                    'callback'            => [ $this, 'save_term' ],
                    'permission_callback' => [ $this, 'can_manage_term_from_request' ],
                ],
            ]
        );

        register_rest_route(
            self::NAMESPACE_BASE,
            '/archive-meta/user/(?P<id>\d+)',
            [
                [
                    'methods'             => 'GET',
                    'callback'            => [ $this, 'get_user' ],
                    'permission_callback' => [ $this, 'can_edit_user_from_request' ],
                ],
                [
                    'methods'             => 'POST',
                    'callback'            => [ $this, 'save_user' ],
                    'permission_callback' => [ $this, 'can_edit_user_from_request' ],
                ],
            ]
        );

        register_rest_route(
            self::NAMESPACE_BASE,
            '/archive-overrides',
            [
                'methods'             => 'GET',
                'callback'            => [ $this, 'list_overrides' ],
                'permission_callback' => [ $this, 'require_manage_options' ],
            ]
        );
    }

    // Handlers: get_term, save_term, get_user, save_user, list_overrides
    // Permission helpers: can_manage_term_from_request, can_edit_user_from_request
    // Sanitise helper: array-intersect against 4 keys + sanitize_text_field / absint
}
```

Include taxonomy existence check, attachment-type check for `image_id`, and the `list_overrides` aggregation that uses `get_terms([ 'meta_key' => '_ogc_meta', 'hide_empty' => false, 'fields' => 'all_with_object_id' ])` + `get_users([ 'meta_key' => '_ogc_meta' ])`.

- [ ] **Step 4: Register + wire**

`src/Bootstrap.php`:

```php
$container->set(
    'rest.archive_meta',
    static fn ( Container $c ) => new ArchiveMetaController( $c->get( 'archivemeta.repository' ) )
);
```

`src/Plugin.php`, in the REST registration method:

```php
$this->container->get( 'rest.archive_meta' )->register();
```

- [ ] **Step 5: Verify tests + lint**

Run: `composer test && composer stan && composer cs`
Expected: all green.

- [ ] **Step 6: Commit**

```bash
git add src/Admin/Rest/ArchiveMetaController.php src/Bootstrap.php src/Plugin.php tests/phpunit/Unit/Admin/Rest/ArchiveMetaControllerTest.php
git commit -m "feat(rest): ArchiveMetaController for term + user override CRUD + audit"
```

---

## Task 9: Output cache invalidation for archive meta writes

**Files:**
- Modify: `src/Renderer/Cache.php`
- Test: extend a test in `tests/phpunit/Unit/Renderer/CacheTest.php` (or create if missing)

- [ ] **Step 1: Write failing test**

Test that `Cache::register()` wires the expected hooks. Test that `flush_term_meta($meta_id, $term_id, $meta_key)` short-circuits when `$meta_key !== '_ogc_meta'`, and calls `delete_transient` for the archive context transient when it matches.

- [ ] **Step 2: Run, confirm fail**

Expected: FAIL.

- [ ] **Step 3: Implement**

Extend `Cache::register()`:

```php
add_action( 'added_term_meta',   [ $this, 'flush_term_from_meta' ], 10, 4 );
add_action( 'updated_term_meta', [ $this, 'flush_term_from_meta' ], 10, 4 );
add_action( 'deleted_term_meta', [ $this, 'flush_term_from_meta' ], 10, 4 );
add_action( 'deleted_term',      [ $this, 'flush_term' ], 10, 1 );
add_action( 'added_user_meta',   [ $this, 'flush_user_from_meta' ], 10, 4 );
add_action( 'updated_user_meta', [ $this, 'flush_user_from_meta' ], 10, 4 );
add_action( 'deleted_user_meta', [ $this, 'flush_user_from_meta' ], 10, 4 );
add_action( 'deleted_user',      [ $this, 'flush_user' ], 10, 1 );
```

Add handler methods that short-circuit on `meta_key !== '_ogc_meta'` and call `delete_transient( $this->key_for( Context::for_archive_term(...) ) )` / `for_author(...)`.

- [ ] **Step 4: Verify tests + lint**

Run: `composer test && composer stan && composer cs`
Expected: all green.

- [ ] **Step 5: Commit**

```bash
git add src/Renderer/Cache.php tests/phpunit/Unit/Renderer/CacheTest.php
git commit -m "feat(cache): invalidate archive + author contexts on term/user meta writes"
```

---

## Task 10: React `ArchiveEditor` component + webpack entry

**Files:**
- Create: `assets/admin/archive/index.jsx`
- Create: `assets/admin/archive/ArchiveEditor.jsx`
- Create: `assets/admin/archive/archive.scss`
- Modify: `assets/admin/shared/api.js`
- Modify: `webpack.config.js`

- [ ] **Step 1: Add webpack entry**

In `webpack.config.js`, in the entry object add:

```js
archive: path.resolve( process.cwd(), 'assets/admin/archive', 'index.jsx' ),
```

- [ ] **Step 2: Add api helpers**

Append to `assets/admin/shared/api.js`:

```js
archive: {
    getTerm: ( tax, id ) => api._get( `/archive-meta/term/${ tax }/${ id }` ),
    saveTerm: ( tax, id, body ) => api._post( `/archive-meta/term/${ tax }/${ id }`, body ),
    getUser: ( id ) => api._get( `/archive-meta/user/${ id }` ),
    saveUser: ( id, body ) => api._post( `/archive-meta/user/${ id }`, body ),
    list: () => api._get( '/archive-overrides' ),
},
```

(Adapt key names + helpers to match the existing pattern in `api.js`.)

- [ ] **Step 3: Create `ArchiveEditor.jsx`**

```jsx
import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import {
    Button,
    CheckboxControl,
    Spinner,
    TextControl,
    TextareaControl,
} from '@wordpress/components';

import { api } from '../shared/api.js';
import MediaPicker from '../shared/MediaPicker.jsx';

export default function ArchiveEditor( { kind, tax, id } ) {
    const [ meta, setMeta ] = useState( null );
    const [ state, setState ] = useState( { kind: 'idle' } );

    useEffect( () => {
        const fetch =
            kind === 'user'
                ? api.archive.getUser( id )
                : api.archive.getTerm( tax, id );
        fetch.then( setMeta ).catch( ( err ) =>
            setState( { kind: 'error', message: err.message } )
        );
    }, [ kind, tax, id ] );

    if ( ! meta ) {
        return <Spinner />;
    }

    const excluded = ( meta.exclude || [] ).includes( 'all' );

    const patch = ( changes ) => setMeta( ( prev ) => ( { ...prev, ...changes } ) );

    const save = async () => {
        setState( { kind: 'saving' } );
        try {
            const next =
                kind === 'user'
                    ? await api.archive.saveUser( id, meta )
                    : await api.archive.saveTerm( tax, id, meta );
            setMeta( next );
            setState( { kind: 'saved' } );
        } catch ( err ) {
            setState( { kind: 'error', message: err.message } );
        }
    };

    return (
        <div className="ogc-archive-editor">
            <TextControl
                label={ __( 'OG title', 'open-graph-control' ) }
                value={ meta.title || '' }
                onChange={ ( v ) => patch( { title: v } ) }
            />
            <TextareaControl
                label={ __( 'OG description', 'open-graph-control' ) }
                value={ meta.description || '' }
                onChange={ ( v ) => patch( { description: v } ) }
            />
            <MediaPicker
                label={ __( 'OG image', 'open-graph-control' ) }
                value={ meta.image_id || 0 }
                onChange={ ( imageId ) => patch( { image_id: imageId } ) }
            />
            <CheckboxControl
                label={ __(
                    'Suppress OG tags for this archive',
                    'open-graph-control'
                ) }
                checked={ excluded }
                onChange={ ( enabled ) =>
                    patch( {
                        exclude: enabled
                            ? [ ...( meta.exclude || [] ).filter( ( x ) => x !== 'all' ), 'all' ]
                            : ( meta.exclude || [] ).filter( ( x ) => x !== 'all' ),
                    } )
                }
            />

            <div className="ogc-section-footer">
                <Button
                    variant="primary"
                    onClick={ save }
                    disabled={ state.kind === 'saving' }
                    aria-busy={ state.kind === 'saving' }
                >
                    { state.kind === 'saving'
                        ? __( 'Saving…', 'open-graph-control' )
                        : __( 'Save overrides', 'open-graph-control' ) }
                </Button>
                <span
                    role="status"
                    aria-live="polite"
                    aria-atomic="true"
                    className="ogc-section-footer__status-region"
                >
                    { state.kind === 'saved' && (
                        <span className="ogc-section-footer__status ogc-section-footer__status--saved">
                            { __( 'Saved.', 'open-graph-control' ) }
                        </span>
                    ) }
                    { state.kind === 'error' && (
                        <span className="ogc-section-footer__status ogc-section-footer__status--error">
                            { state.message }
                        </span>
                    ) }
                </span>
            </div>
        </div>
    );
}
```

- [ ] **Step 4: Create `index.jsx` entry**

```jsx
import { createRoot } from '@wordpress/element';
import ArchiveEditor from './ArchiveEditor.jsx';
import './archive.scss';

document.addEventListener( 'DOMContentLoaded', () => {
    document.querySelectorAll( '#ogc-archive-root' ).forEach( ( el ) => {
        const kind = el.getAttribute( 'data-kind' );
        const tax  = el.getAttribute( 'data-tax' ) || undefined;
        const id   = parseInt( el.getAttribute( 'data-id' ) || '0', 10 );
        if ( ! id ) return;
        createRoot( el ).render( <ArchiveEditor kind={ kind } tax={ tax } id={ id } /> );
    } );
} );
```

- [ ] **Step 5: Create `archive.scss`**

```scss
@import '../shared/utilities';

.ogc-archive-editor {
    max-width: 620px;
}
```

- [ ] **Step 6: Verify build + lint**

Run: `npm run lint:js && npm run build`
Expected: both green; `build/admin/archive.js` + `archive.css` produced.

- [ ] **Step 7: Commit**

```bash
git add assets/admin/archive/ assets/admin/shared/api.js webpack.config.js
git commit -m "feat(ui): React ArchiveEditor bundle for inline term + user edit"
```

---

## Task 11: Inline editor hooks — TermEditor

**Files:**
- Create: `src/Admin/TermEditor.php`
- Modify: `src/Bootstrap.php` + `src/Plugin.php`
- Modify: `src/Admin/Assets.php` (enqueue `archive` bundle on relevant screens)

- [ ] **Step 1: Create `TermEditor` class**

`src/Admin/TermEditor.php`:

```php
<?php
declare(strict_types=1);

namespace EvzenLeonenko\OpenGraphControl\Admin;

defined( 'ABSPATH' ) || exit;

use EvzenLeonenko\OpenGraphControl\ArchiveMeta\Repository;
use WP_Term;

final class TermEditor {

    public function __construct( private Repository $archive ) {}

    public function register(): void {
        add_action( 'admin_init', [ $this, 'hook_taxonomies' ] );
    }

    public function hook_taxonomies(): void {
        /** @var array<string, string> $taxes */
        $taxes = get_taxonomies( [ 'public' => true ], 'names' );
        unset( $taxes['attachment'] );
        foreach ( $taxes as $tax ) {
            add_action( "{$tax}_edit_form_fields", [ $this, 'render' ], 20, 2 );
        }
    }

    public function render( WP_Term $term, string $taxonomy ): void {
        if ( ! current_user_can( 'manage_terms', $taxonomy, $term->term_id ) ) {
            return;
        }
        printf(
            '<tr class="form-field ogc-archive-row"><th scope="row"><label>%s</label></th>' .
            '<td><div id="ogc-archive-root" data-kind="term" data-tax="%s" data-id="%d"></div></td></tr>',
            esc_html__( 'Open Graph overrides', 'open-graph-control' ),
            esc_attr( $taxonomy ),
            (int) $term->term_id
        );
    }
}
```

No explicit save hook — the React component writes through the REST endpoint. The native WP update button on the page doesn't trigger a save of our fields (intentional: editor autosaves are disabled, only the Save overrides button persists).

- [ ] **Step 2: Enqueue `archive` bundle on relevant screens**

In `src/Admin/Assets.php::enqueue()`:

```php
if ( in_array( $hook, [ 'edit-tags.php', 'term.php', 'user-edit.php', 'profile.php' ], true ) ) {
    $this->enqueue_bundle( 'archive' );
}
```

- [ ] **Step 3: Wire in Bootstrap + Plugin**

`Bootstrap.php`:

```php
$container->set(
    'admin.term_editor',
    static fn ( Container $c ) => new TermEditor( $c->get( 'archivemeta.repository' ) )
);
```

`Plugin.php`, in the admin-init boot method:

```php
$this->container->get( 'admin.term_editor' )->register();
```

- [ ] **Step 4: Verify build + stan + cs**

Run: `composer stan && composer cs && npm run build`
Expected: all green.

- [ ] **Step 5: Commit**

```bash
git add src/Admin/TermEditor.php src/Admin/Assets.php src/Bootstrap.php src/Plugin.php
git commit -m "feat(admin): inject ArchiveEditor into term edit screens"
```

---

## Task 12: Inline editor hooks — UserEditor

**Files:**
- Create: `src/Admin/UserEditor.php`
- Modify: `src/Bootstrap.php` + `src/Plugin.php`

- [ ] **Step 1: Create `UserEditor` class**

Similar to `TermEditor` but for `show_user_profile` + `edit_user_profile`. Wrap the mount in an `<h2>` + `<table class="form-table">` block matching the WP profile page markup.

```php
public function register(): void {
    add_action( 'show_user_profile', [ $this, 'render' ] );
    add_action( 'edit_user_profile', [ $this, 'render' ] );
}

public function render( $user ): void {
    if ( ! current_user_can( 'edit_user', (int) $user->ID ) ) {
        return;
    }
    printf(
        '<h2>%s</h2><table class="form-table"><tr><th scope="row">%s</th>' .
        '<td><div id="ogc-archive-root" data-kind="user" data-id="%d"></div></td></tr></table>',
        esc_html__( 'Open Graph overrides', 'open-graph-control' ),
        esc_html__( 'Author archive', 'open-graph-control' ),
        (int) $user->ID
    );
}
```

- [ ] **Step 2: Wire in Bootstrap + Plugin**

Same pattern as Task 11.

- [ ] **Step 3: Verify build + lint**

- [ ] **Step 4: Commit**

```bash
git add src/Admin/UserEditor.php src/Bootstrap.php src/Plugin.php
git commit -m "feat(admin): inject ArchiveEditor into user profile screens"
```

---

## Task 13: Central audit section in settings SPA

**Files:**
- Create: `assets/admin/settings/sections/ArchiveOverrides.jsx`
- Modify: `assets/admin/settings/App.jsx` (register section)
- Modify: `assets/admin/settings/settings.scss` (section-specific styles if needed)

- [ ] **Step 1: Write the component**

`ArchiveOverrides.jsx`:

- Fetch `GET /archive-overrides` on mount via `api.archive.list()`.
- Render search input + kind dropdown + `.wp-list-table widefat striped` table.
- Columns: `Kind (code) | Name | Fields set (pills) | Action (Edit →)`.
- "Fields set" pills use existing severity tokens — `.ogc-warning-item--*` style colours on small inline badges.
- "Edit →" links: for terms, `edit-tags.php?taxonomy={tax}&tag_ID={id}`; for users, `user-edit.php?user_id={id}` (current user profile if same).
- Empty state: "No archives configured yet." + link to `edit-tags.php?taxonomy=category`.

- [ ] **Step 2: Register in `SECTIONS`**

In `App.jsx`:

```jsx
import ArchiveOverrides from './sections/ArchiveOverrides.jsx';
// …
{ key: 'archive-overrides', label: 'Archive overrides', Component: ArchiveOverrides },
```

Slot it after `post-types` (archive data is adjacent to post-type configuration in concept).

- [ ] **Step 3: Verify build + lint**

- [ ] **Step 4: Commit**

```bash
git add assets/admin/settings/sections/ArchiveOverrides.jsx assets/admin/settings/App.jsx
git commit -m "feat(settings): Archive overrides audit section with search + filter"
```

---

## Task 14: Playwright E2E — term override roundtrip

**Files:**
- Create: `tests/e2e/playwright/07-archive-override.spec.ts`

- [ ] **Step 1: Write the spec**

```ts
import { expect, test } from '@playwright/test';
import { login } from './helpers';

test( 'category override renders on the category archive page', async ( { page } ) => {
    await login( page );

    // Create a category via REST (quicker than UI).
    const res = await page.request.post( '/wp-json/wp/v2/categories', {
        data: { name: 'E2E Recepty', slug: 'e2e-recepty' },
    } );
    const category = await res.json();
    const termId = category.id;

    // Go to the edit screen and fill the OG title.
    await page.goto( `/wp-admin/term.php?taxonomy=category&tag_ID=${ termId }&post_type=post` );
    await expect( page.locator( '#ogc-archive-root' ) ).toBeVisible();
    await page
        .locator( '#ogc-archive-root input[type="text"]' )
        .first()
        .fill( 'Recepty z české spíže' );
    await page.click( '#ogc-archive-root button:has-text("Save overrides")' );
    await expect(
        page.locator( '#ogc-archive-root' ).getByText( 'Saved.' )
    ).toBeVisible( { timeout: 5000 } );

    // Create at least one published post in this category so the archive exists.
    await page.request.post( '/wp-json/wp/v2/posts', {
        data: { title: 'Hello', status: 'publish', categories: [ termId ] },
    } );

    // Visit the category archive front-end.
    await page.goto( `/?cat=${ termId }` );
    const source = await page.content();
    expect( source ).toContain( 'Recepty z české spíže' );
    expect( source ).toContain( 'property="og:title"' );
} );

test( 'author override renders on the author archive page', async ( { page } ) => {
    // Similar flow — navigate to /wp-admin/profile.php, fill, save, visit author URL.
} );
```

- [ ] **Step 2: Run the spec**

Run: `OGC_E2E_WP=1 npx playwright test tests/e2e/playwright/07-archive-override.spec.ts --reporter=line`
Expected: both tests PASS.

- [ ] **Step 3: Commit**

```bash
git add tests/e2e/playwright/07-archive-override.spec.ts
git commit -m "test(e2e): archive override roundtrip for category + author"
```

---

## Task 15: Extend accessibility coverage to new surfaces

**Files:**
- Modify: `tests/e2e/playwright/05-a11y-admin.spec.ts`

- [ ] **Step 1: Add new axe scan for `Archive overrides` settings section**

Mirror the existing "security section has no axe violations" test: navigate to the settings page, click the new nav entry, wait for the heading, run `ogcViolations`, assert empty.

Optionally add a scan on the term edit screen itself:

```ts
test( 'term edit screen with archive editor has no axe violations', async ( { page } ) => {
    await login( page );
    // create a category, then
    await page.goto( `/wp-admin/term.php?taxonomy=category&tag_ID=${ termId }&post_type=post` );
    await expect( page.locator( '#ogc-archive-root' ) ).toBeVisible();
    expect( await ogcViolations( page ) ).toEqual( [] );
} );
```

- [ ] **Step 2: Run + fix any violations surfaced**

- [ ] **Step 3: Commit**

```bash
git add tests/e2e/playwright/05-a11y-admin.spec.ts
git commit -m "test(a11y): axe coverage for Archive overrides + term edit screen"
```

---

## Task 16: Documentation

**Files:**
- Modify: `readme.txt` (changelog entry for 0.3.0, feature description, FAQ)
- Modify: `README.md` (status section: v0.3 features, test-count badge)
- Modify: `docs/filters.md` (document `ogc_resolve_{title,description,image}_chain` now including `archive_override`)

- [ ] **Step 1: Update `readme.txt`**

- Append the "archive overrides" feature to `== Description ==`.
- Add a new FAQ entry ("Can I set a custom OG title for my category archive?").
- Add a `= 0.3.0 =` changelog entry.
- Bump `Stable tag: 0.3.0` and plugin header `Version: 0.3.0`.
- Bump `OGC_VERSION` in `open-graph-control.php`.

- [ ] **Step 2: Update `README.md`**

- Status section: move v0.3 items out of "not yet" into implemented.
- Update test-count badge.

- [ ] **Step 3: Commit**

```bash
git add readme.txt README.md docs/filters.md open-graph-control.php
git commit -m "docs: archive overrides feature + bump to 0.3.0"
```

---

## Task 17: Regenerate wp.org screenshots

**Files:**
- `.wordpress-org/screenshot-{1,2,3,4,5}.png`
- `readme.txt` (if screenshot-5 is added, append to `== Screenshots ==`)

- [ ] **Step 1: Add a new screenshot spec entry for "Archive overrides in the category edit screen"**

Extend `tests/e2e/playwright/wporg-screenshots.spec.ts` with a fifth test that visits the term edit screen, fills demo values in the archive editor, and captures.

- [ ] **Step 2: Run `bin/make-screenshots.sh`**

- [ ] **Step 3: Commit**

```bash
git add .wordpress-org/screenshot-*.png readme.txt tests/e2e/playwright/wporg-screenshots.spec.ts
git commit -m "docs(wporg): add archive overrides screenshot"
```

---

## Task 18: Release 0.3.0

- [ ] **Step 1: Full test sweep**

Run: `composer test && composer stan && composer cs && npm run lint:js && npm run build && OGC_E2E_WP=1 npx playwright test`
Expected: all green.

- [ ] **Step 2: Tag + push**

```bash
git tag -a v0.3.0 -m "v0.3.0 — archive + author overrides

…changelog summary…"
git push origin v0.3.0
```

- [ ] **Step 3: Verify release workflow**

Run: `gh run list --limit 1 --workflow=release.yml`
Expected: workflow completes successfully, new GitHub Release published with zip asset.

---

## Decision anchors (quick reminders during implementation)

- **Why native term_meta + user_meta:** WP-native hook integration, existing REST cap model, `maybe_serialize` handled for us, terms' deletion cleans up via core.
- **Why archive_override before seo_plugin_\*:** explicit user override wins over third-party defaults.
- **Why no bulk actions in v0.3:** YAGNI; revisit when users with >50 overrides complain.
- **Why no date archives:** key model is different (no ID), marginal use-case.
