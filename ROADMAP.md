# Open Graph Control — status & roadmap

> Živý přehled k **2026-04-19**. Plugin běží na **v0.3.0**, zatím není submitnutý na wordpress.org.
> Lokální barevná verze: [`docs/roadmap.html`](docs/roadmap.html).

| | |
|---|---|
| **Verze** | v0.3.0 |
| **Release** | [v0.3.0 on GitHub](https://github.com/Teriffy/open-graph-control/releases/tag/v0.3.0) |
| **Minimum PHP** | 8.1 |
| **Minimum WP** | 6.2 |
| **License** | GPL-2.0-or-later |

---

## Čísla v kostce

| | |
|---|---|
| **218** | PHPUnit tests |
| **429** | assertions |
| **18 + 12** | Playwright tests (fixture + WP E2E) |
| **PHPStan L8** | enforced v CI |
| **0.047 ms** | front-page render (mean, 500 iterations) |
| **0.396 ms** | single-post render (mean, full resolver chain) |
| **12** | platform-specific tag streams |
| **7** | SEO plugin integrací s takeover |

---

## Co je hotovo

### Backend

- ✅ **12 platform classes** — Facebook · X/Twitter · LinkedIn · iMessage · Threads · Mastodon · Bluesky · WhatsApp · Discord · Pinterest · Telegram · Slack
- ✅ **6 resolverů s filterable fallback chains** (title, description, image, type, URL, locale) přes `ogc_resolve_{field}_chain`/`_value` hooky
- ✅ **Pinterest Rich Pins JSON-LD** (Article / Product / Recipe) — chráněno `JSON_HEX_TAG` proti stored XSS
- ✅ **Per-post overrides** (v0.2) — meta box s živým preview pro všech 12 platforem
- ✅ **Per-archive & per-author overrides** (v0.3) — OG title/description/image/exclude na edit screen category, tag, custom taxonomy, author
- ✅ **7 SEO plugin integrací s takeover** — Yoast · Rank Math · AIOSEO · SEOPress · Jetpack · TSF · Slim SEO
- ✅ **Output cache** — transient-based, per-archive klíčování, invalidace přes save_post/update_option/term_meta/user_meta
- ✅ **3 auto-registered image sizes** — landscape 1200×630, square 600×600, Pinterest 1000×1500
- ✅ **Bulk image regeneration** — WP-Cron batch walker
- ✅ **REST API** — 13 endpointů pod `open-graph-control/v1`
- ✅ **WP-CLI** — `wp ogc tags|validate|regenerate|bench`

### Admin UI (React)

- ✅ **11 sekcí nastavení** — Overview · Site defaults · Platforms · Post types · Archive overrides · Images · Fallback chains · Integrations · Security · Debug · Import/Export · Advanced
- ✅ **Per-post meta box** — Base + X/Twitter + Pinterest + Per-platform taby, live preview, inline validation
- ✅ **Archive editor** na term/user edit screens — 4 pole (title/description/image/exclude) s aria-live save feedback
- ✅ **Central audit table** — Settings → Archive overrides: search, kind filter, Edit → do WP-native edit screen
- ✅ **MediaUpload picker** — master image + per-platform overrides
- ✅ **SEO conflict notice** — one-time admin_notices banner, takeover/keep choice
- ✅ **Import/Export JSON + Reset to defaults**
- ✅ **Security panel** — defensive posture, OWASP coverage, audit trail, disclosure
- ✅ **Mobile wp-admin (≤782px) responsive** — metabox stacks, nav chips wrap, 44px touch targets

### Bezpečnost & kvalita

- ✅ **Stored XSS JSON-LD fix** — Pinterest Rich Pins payload (`JSON_HEX_TAG` + `str_replace('</','<\/')` + scheme filter). CVSS 8.1, commit [`3c96aa0`](https://github.com/Teriffy/open-graph-control/commit/3c96aa0), v0.2.1
- ✅ **Design token system** — žádné inline hex, WCAG-AAA kontrast na badges, `prefers-reduced-motion` podpora
- ✅ **@axe-core/playwright v CI** — zero violations na settings shell, Security, Archive overrides, term edit screen
- ✅ **SECURITY.md + GHSA draft** — responsible disclosure, OWASP Top 10 coverage; draft v [`.wordpress-org/GHSA-draft.md`](.wordpress-org/GHSA-draft.md)
- ✅ **PHPStan level 8 + WPCS** v CI
- ✅ **plugin-check-action** proti built zipu na každém push
- ✅ **Performance benchmark** — in-process `wp ogc bench` CLI, sub-millisecond, dokumentováno

### Release & distribuce

- ✅ **v0.2.0** — feature-complete developer preview
- ✅ **v0.2.1** — security release
- ✅ **v0.3.0** — per-archive & per-author overrides
- ✅ **Release workflow (GitHub Actions)** — tag → build zip → GitHub Release (SVN push připravený, čeká na credentials)
- ✅ **wp.org screenshots 1–5** v `.wordpress-org/`, regenerovatelné přes `bin/make-screenshots.sh`

---

## Co potřebujeme pro publikaci na wordpress.org

### Před submission

| Status | Položka |
|---|---|
| ✅ | `readme.txt` v wp.org formátu (description, FAQ, changelog, upgrade notice, 5 screenshots, stable tag 0.3.0) |
| ✅ | GPL-2.0-or-later licence |
| ✅ | Plugin-check passing v CI |
| ✅ | Žádné phone-home ani externí HTTP (ověřeno auditem) |
| ✅ | Capability checks + nonces všude |
| ✅ | Screenshot files 1–5 PNG v `.wordpress-org/` |
| ⏳ | **Banner 772×250 PNG** (dnes jen SVG placeholder) |
| ⏳ | **Banner 1544×500 PNG** (retina) |
| ⏳ | **Icon 128×128 PNG** |
| ⏳ | **Icon 256×256 PNG** (retina) |
| ⏳ | **GitHub Security Advisory publikace** (draft hotový v `.wordpress-org/GHSA-draft.md`) |
| ⏳ | **wordpress.org účet** (pokud ještě není) |

### Submission & review

| Status | Položka |
|---|---|
| ⏳ | Submit přes [wordpress.org/plugins/developers/add/](https://wordpress.org/plugins/developers/add/) — upload `open-graph-control-0.3.0.zip` |
| ⏳ | Reviewer feedback (1–14 dní) — možných pár kol back-and-forth |
| ⏳ | Approval email + SVN credentials |

### Po approval

| Status | Položka |
|---|---|
| ⏳ | GitHub repo secrets — `WP_SVN_USERNAME` + `WP_SVN_PASSWORD` (application password) |
| ⏳ | Re-tag nebo nový patch release — workflow auto-pushne na wp.org SVN |
| ⏳ | wp.org plugin page live (~15 min po SVN pushi) |

> **Doporučený postup pro minimum třecí plochy:** než submitnu, udělej 4 chybějící PNG — plugin bez nich vypadá v adresáři jako kostra. Pak publikuj GHSA a submit. Review tým uvidí bezpečnostní kulturu (SECURITY.md, GHSA, audit trail) a schválení bude přímočaré.

---

## Roadmapa — co nás čeká dál

### v0.4 — Dynamic OG image generation (Project B)

- 🔲 **Server-side render OG obrázku z template** — title + description + logo + pozadí → PNG, když uživatel nemá vlastní. Pokrývá 80 % blogů co dnes posílají site-master image nebo nic.
- 🔲 **Volba stack** — PHP GD (vestavěné) vs Imagick vs headless Chromium. Potřebuje vlastní brainstorm.
- 🔲 **Template editor UI** — bg upload, font picker, position tweaks, preview
- 🔲 **Disk cache + invalidace** — renderované obrázky v uploads/
- 🔲 **Font management** — hostované v pluginu, žádné dynamic fetchování za běhu
- 🔲 **Size variants pipeline** — jeden template → 3 sizes automaticky

**Odhad:** 5+ dní práce. Vlastní spec + plán potřebný před implementací.

### v0.3.x — drobné doplňky (backlog)

- 🔲 Bulk actions na Archive overrides table (Clear all / Set image pro 40+ kategorií)
- 🔲 Date archive support (`/2026/04/`)
- 🔲 Custom post type archive overrides (`/products/` když je CPT s archive enabled)
- 🔲 Per-platform overrides pro archives
- 🔲 Gutenberg sidebar panel jako alternativa k classic metaboxu

### v1.0+ — větší kroky

- 🔲 Multisite support (network-wide settings + per-site overrides)
- 🔲 WordPress block pro embed OG preview card v post contentu
- 🔲 Analytics integrace (CTR z Facebook/Twitter proti OG variantám)

---

## Doporučený další krok

1. **Vygeneruj 4 PNG assets** (2 banner, 2 icon) — ~1 hodina designéra / Figma self-made
2. **Publikuj GitHub Security Advisory** — zkopíruj z [`.wordpress-org/GHSA-draft.md`](.wordpress-org/GHSA-draft.md) do [form](https://github.com/Teriffy/open-graph-control/security/advisories/new) — 5 minut
3. **Submit na wordpress.org/plugins/developers/add/** — upload `open-graph-control-0.3.0.zip` — 5 minut
4. **Čekání 1–14 dní** na review (mezitím brainstorm v0.4)
5. **Po approval:** přidej SVN credentials do GH secrets → příští `git tag` půjde auto na wp.org — 10 minut

> Reviewer ocení, když GHSA je už publikovaný před submissionem — ukazuje zralou security kulturu.

---

_Generováno 2026-04-19. Lokální interaktivní verze s light/dark mode: [`docs/roadmap.html`](docs/roadmap.html)._
