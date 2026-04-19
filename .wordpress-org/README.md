# WordPress.org assets

Files in this directory are uploaded to the WP.org SVN `assets/` subtree (separate from `trunk/` / `tags/`). They don't ship inside the plugin zip and the `bin/make-dist.sh` script already excludes the whole `.wordpress-org/` folder.

## Required before first submission

| File | Dimensions | Purpose |
|---|---|---|
| `banner-772x250.png` | 772 × 250 | Plugin page banner |
| `banner-1544x500.png` | 1544 × 500 | Retina banner |
| `icon-128x128.png` | 128 × 128 | Directory icon |
| `icon-256x256.png` | 256 × 256 | Retina directory icon |
| `screenshot-1.png` | ≥ 1200 × 900 (any ratio) | First screenshot in order listed in `readme.txt`'s `== Screenshots ==` section |
| `screenshot-2.png` | — | Second screenshot |
| `screenshot-3.png` | — | Third screenshot |
| `screenshot-4.png` | — | Fourth screenshot |

## How to capture screenshots

Spin up a dev WP with the plugin activated:

```bash
npx wp-env start
# open http://localhost:8888/wp-admin, user: admin, password: password
```

1. **screenshot-1.png** — Frontend page source showing emitted OG tags. Use the "Debug / Test" panel: pick "Front page", click "Render tags", and screenshot the resulting table.
2. **screenshot-2.png** — Settings → Platforms section with a few platform cards expanded to show per-platform fields.
3. **screenshot-3.png** — Post edit screen with the meta box open on the Base tab, live preview rendered on the right, inline warning badges visible.
4. **screenshot-4.png** — Activate Yoast alongside the plugin and capture the admin notice banner.

## Notes

- PNG only. WebP and SVG are rejected by the WP.org asset pipeline.
- Keep file sizes under ~200 KB each; use `pngquant` if needed.
- The banner and icon should use the plugin's brand colors. For now the "theme color" is `#2271b1` (WordPress admin blue); align with that until we have a proper mark.
