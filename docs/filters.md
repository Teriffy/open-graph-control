# Developer filter reference

Every field the plugin resolves and every platform decision runs through WordPress filters so you can extend behavior without forking.

## Resolver filters

The resolvers for **title**, **description**, **image**, **type**, **url** and **locale** each expose two filters.

### `ogc_resolve_{field}_chain`

Modify the list of resolver steps before they execute. Signature:

```php
apply_filters( "ogc_resolve_{$field}_chain", array $chain, \EvzenLeonenko\OpenGraphControl\Resolvers\Context $context ): array
```

Since v0.3, the default chain for `title`, `description`, and `image` includes an `archive_override` step that runs for author + taxonomy-term contexts. It reads the term-meta / user-meta override persisted by the term / author edit screen and short-circuits the chain when a value is set.

Example — force product posts to use the post excerpt before the full-content trim:

```php
add_filter(
    'ogc_resolve_description_chain',
    static function ( array $chain, $context ) {
        if ( $context->is_singular() && 'product' === get_post_type( $context->post_id() ) ) {
            return [ 'post_meta_override', 'post_excerpt', 'site_description' ];
        }
        return $chain;
    },
    10,
    2
);
```

### `ogc_resolve_{field}_value`

Final say on the resolved value. Signature:

```php
apply_filters( "ogc_resolve_{$field}_value", string $value, \EvzenLeonenko\OpenGraphControl\Resolvers\Context $context ): string
```

Example — suffix book titles with the author's name:

```php
add_filter(
    'ogc_resolve_title_value',
    static function ( string $title, $context ): string {
        if ( ! $context->is_singular() || 'book' !== get_post_type( $context->post_id() ) ) {
            return $title;
        }
        $author = get_post_meta( $context->post_id(), 'book_author', true );
        return $author ? "{$title} — {$author}" : $title;
    },
    10,
    2
);
```

### `ogc_seo_plugin_title` / `ogc_seo_plugin_desc`

Provide a title or description computed by another SEO plugin when the resolver reaches the `seo_plugin_title` / `seo_plugin_desc` step. Active integrations (Yoast, Rank Math, ...) register these automatically; custom plugins can hook in too.

```php
add_filter(
    'ogc_seo_plugin_title',
    static function ( ?string $title, $context ): ?string {
        if ( null !== $title ) {
            return $title;
        }
        return my_custom_seo_plugin_get_title_for_context( $context );
    },
    10,
    2
);
```

## Integration filters

### `ogc_detected_plugins`

Register a custom SEO integration. The array elements must implement `EvzenLeonenko\OpenGraphControl\Integrations\IntegrationInterface`.

```php
add_filter(
    'ogc_detected_plugins',
    static function ( array $integrations ): array {
        $integrations[] = new My_Custom_Integration();
        return $integrations;
    }
);
```

### `ogc_apply_takeover_{slug}`

Force takeover on or off for a specific integration regardless of user settings. `{slug}` matches `IntegrationInterface::slug()` — `yoast`, `rankmath`, `aioseo`, `seopress`, `jetpack`, `tsf`, `slim_seo`.

```php
// Always take over Yoast on this install, no matter what the UI says.
add_filter( 'ogc_apply_takeover_yoast', '__return_true' );
```

## Pinterest Rich Pin filters

### `ogc_pinterest_rich_pins_type`

Override the Rich Pin type per context (`article`, `product`, `recipe`).

```php
add_filter(
    'ogc_pinterest_rich_pins_type',
    static function ( string $type, $context ): string {
        if ( $context->is_singular() && 'recipe' === get_post_type( $context->post_id() ) ) {
            return 'recipe';
        }
        return $type;
    },
    10,
    2
);
```

### `ogc_pinterest_rich_pin_payload`

Modify the JSON-LD payload before it's serialized. Useful for products that want to add offers / price.

```php
add_filter(
    'ogc_pinterest_rich_pin_payload',
    static function ( array $payload, string $type, $context ): array {
        if ( 'product' === $type && $context->is_singular() ) {
            $payload['offers'] = [
                '@type'         => 'Offer',
                'price'         => get_post_meta( $context->post_id(), 'price', true ),
                'priceCurrency' => 'USD',
                'availability'  => 'https://schema.org/InStock',
            ];
        }
        return $payload;
    },
    10,
    3
);
```

## Rendering flags

The plugin also reads two settings at render time via the options repository — `output.strict_mode` and `output.comment_markers`. There are no separate filters for these; flip them in the Advanced settings section or programmatically by calling `Options\Repository::update()`.

## Context object

All filters receive a `Context` value object. Methods you can rely on:

- `type()` — one of `singular`, `front`, `blog`, `archive`, `author`, `date`, `search`, `not_found`
- `is_singular(): bool`
- `post_id(): ?int` — non-null on singular contexts
- `extra( string $key, mixed $fallback = null ): mixed` — archive kind (`category`, `tag`, `taxonomy`, `post_type`) and user ID

Construct contexts in tests with `Context::for_post( 42 )`, `Context::for_front()`, `Context::for_archive( 'category' )`, `Context::for_author( 7 )`, etc.
