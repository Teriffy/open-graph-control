# Open Graph Control — status & roadmap

> Živý přehled k **2026-04-20**. Plugin běží na **v0.3.0**, zatím není submitnutý na wordpress.org. **v0.4** (Dynamic OG image generation) — design schválen, čeká na implementační plán.

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
| ✅ | Banner 772×250 PNG — rendered z SVG placeholderu přes `bin/make-wporg-assets.mjs` |
| ✅ | Banner 1544×500 PNG (retina) |
| ✅ | Icon 128×128 PNG |
| ✅ | Icon 256×256 PNG (retina) |
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

### v0.4 — Dynamic OG image generation (design schválen)

**Co to dělá:** server-side renderuje OG kartu 1200×630 PNG z bundled template (title + description + logo + pozadí) pro každý post / archive / author bez vlastní OG image. Pokrývá ~80 % blogů které dnes posílají site-master image nebo nic. Auto-card je dnes paywall feature u Yoast/RankMath/AIOSEO Pro — náš v0.4 ji odemkne zadarmo s pokrytím 12 platforem.

#### Architectural decisions (zafixované)

| # | Decision | Rationale |
|---|---|---|
| 1 | **Trigger:** fallback only — generuje jen když všechny earlier image chain steps vrátí null | Lowest surprise; uživatel s explicitní imagery dostane svou |
| 2 | **Timing:** render-on-save v `shutdown` hooku + WP-Cron backfill (5 postů/tick) | Predictable UX pro OG scrapery; nikdy neblokuje editor save |
| 3 | **Template:** single fixed layout, customizable colors / bg / logo only | YAGNI — multi-preset + editor jsou v0.5+ |
| 4 | **Render stack:** GD default, Imagick opt-in přes `RendererInterface` + filter | GD universally available; Imagick bez code branch |
| 5 | **Fonts:** jeden bundled font (Inter, 400 + 700 weights, SIL OFL, ~250 KB) | Predictable metrics → reliable text-fit; zero attack surface |

#### Co se vyrenderuje

```
┌─────────────────────────────────────────────────────────┐
│  [logo] SITE NAME                                       │
│                                                         │
│   How to ship a WordPress plugin in 2026                │
│   (auto-shrink 60→52→44→36px, max 3 lines + ellipsis)  │
│                                                         │
│   A practical guide to wp.org submission                │
│   (Inter Regular 28px, max 2 lines + ellipsis)         │
│                                                         │
│   example.com · April 2026                              │
└─────────────────────────────────────────────────────────┘
                                                  1200×630
```

Customizable: `bg_type` (gradient/solid/image), `bg_color`, `bg_gradient_to`, `bg_image_id`, `text_color`, `logo_id`, `show_site_name`, `show_meta_line`. Layout / pozice / fonty jsou locked.

#### Scope split

- **Plán A — Dynamic OG card rendering** (~50 tasků, ~2 týdny)
  Renderer interface + GD + Imagick + Template + Payload + CardStore + CardGenerator + Scheduler + BackfillCron + GcCron + ResolverHook + 5 REST endpointů + 4 WP-CLI commands + admin React tab + per-post/per-archive status badge + bundled Inter font + golden cards CI + perf bench

- **Plán B — Dynamic field sources** (~15 tasků, ~3–4 dny, samostatně)
  ACF + JetEngine resolvers v `ogc_resolve_title_chain` / `_description_chain` + Settings → Integrations → Dynamic field sources sub-tab s per-post-type dropdownem. Profituje karta i běžné OG meta tagy.

#### Výkonový profil (z designu)

**Per-visitor (návštěvník):**

| Metric | Bez v0.4 | S v0.4 | Delta |
|---|---|---|---|
| Single-post render (PHP) | 0.396 ms | ~0.45 ms | **+0.05 ms** (1× `file_exists()`) |
| Memory per request | baseline | baseline | **0** |
| Extra HTTP / DB | 0 | 0 | **0** |
| PNG download | — | static asset (nginx/Apache, žádný PHP) | — |

**Per-save (editor):** 0 ms perceived — render je deferred do `shutdown` hooku (po response).

**Per-render (background):**

| Resource | GD (default) | Imagick (opt-in) |
|---|---|---|
| CPU time | 150–200 ms median | 80–100 ms median |
| Memory peak | ~15 MB | ~25 MB |
| Disk write | ~50 KB PNG | ~50 KB PNG |
| Network | 0 | 0 |

**Cron load:** ~1 sec/den backfill + < 0.5 sec/den GC.

**Disk usage:**

| Site | Footprint | Notes |
|---|---|---|
| 100 postů | ~5 MB | nepoznáš |
| 1 000 postů | ~50 MB | běžný blog |
| 10 000 postů | ~500 MB | admin warning v UI |

#### Plánované test pokrytí (z designu)

| Vrstva | Stávající | +v0.4 | Po dokončení |
|---|---|---|---|
| **PHPUnit** | 218 testů, 429 assertions | **+~60** (Payload, Template, CardStore, Scheduler, ResolverHook, BackfillCron, GdRenderer) | ~278 testů |
| **Playwright fixture** | 18 | **+2** (card-template-settings, field-sources-settings → plán B) | 20 |
| **Playwright WP E2E** | 12 | **+2** (card-generation, field-source-takeover → plán B) | 14 |
| **A11y (axe-core)** | settings shell + Security + Archive | **+1 surface** (Card template tab) | + |
| **Golden cards** | — | **NEW** image-diff job s pinned Docker (Ubuntu + GD + Inter), 5 % tolerance | — |
| **PHPStan L8** | enforced v CI | enforced | enforced |

**Performance bench targets** (rozšíření `wp ogc bench`):

| Measurement | Target |
|---|---|
| Card render (GD, 1200×630) | < 200 ms median |
| Card render (Imagick) | < 100 ms median |
| Resolver chain w/ ACF step | < 1 ms median |

#### Co plán **NENÍ** (out of scope, → v0.5+)

- Multi-template presety (Bold / Editorial / Minimal / Vivid)
- Drag-and-drop / position-tweakable editor
- Square (600×600) + Pinterest (1000×1500) auto-render
- User-uploaded fonty
- Headless Chromium / Node-based rendering (zakázáno wp.org pravidly)
- ACF repeater traversal, JetEngine dynamic-tag macros
- Pods / MetaBox / CMB2 field sources

#### Status

- ✅ **Brainstorm dokončen** (5 architectural decisions zafixovaných)
- ✅ **Design spec napsaný + reviewer-approved** (lokální `docs/superpowers/specs/2026-04-20-dynamic-og-image-design.md`)
- ⏳ **Implementační plán** — A first, B následně
- ⏳ **Implementace plán A** — ~2 týdny, ~50 commitů (TDD per task)
- ⏳ **Implementace plán B** — ~3–4 dny, ~15 commitů
- ⏳ **Tag v0.4.0** — po A; B případně 0.4.1 nebo 0.5.0

### v0.5 — Setup wizard (onboarding)

**Co to dělá:** První-aktivační wizard, který novému uživateli během 60 sekund vybere nejpodstatnější nastavení bez nutnosti prolítnout 14 settings sekcí. Cílí na ~80 % uživatelů, kteří jinak jdou do Settings → Overview, kouknou a zavřou záložku. Klíčem je: pokud nevyplní ani site defaults + platform toggle + card opt-in, plugin je de facto silent — a proto je wizard guardrail, ne nadstavba.

#### Flow (5 kroků, všechny skip-able)

| # | Krok | Co se ptá | Hodnota pro uživatele |
|---|---|---|---|
| 1 | **Welcome** | Co OGC dělá, seznam 12 platforem | Nastaví očekávání, link na docs |
| 2 | **SEO conflict** | (jen když detekovaný) Yoast/RankMath/AIOSEO/… → takeover? | Přesně jeden CTA, žádné hádání |
| 3 | **Site defaults** | Site name (z bloginfo), master image upload, default type | Odbavuje 90 % OG tagů na front-page + archivech |
| 4 | **Platforms** | Toggle seznam — které z 12 emitujeme (default: vše zapnuto) | Uživatel z B2B může rychle vypnout Discord/iMessage/TikTok |
| 5 | **Dynamic cards** | Opt-in checkbox: "Auto-render OG card pro posty bez obrázku" | Jeden klik odblokuje v0.4 feature |
| 6 | **Done** | CTA: "Otevři testovací preview" + "Přejdi do Settings" | Uzavírá loop — uživatel vidí výsledek, ne blank state |

#### Architectural decisions (draft, nutno brainstormit)

| # | Decision | Rationale |
|---|---|---|
| 1 | **Trigger:** activation hook nastaví `ogc_wizard_pending` transient; `admin_notices` na všech screens kromě wizard samotného | Nenucené, ale viditelné |
| 2 | **UI:** samostatná stránka pod `admin.php?page=ogc-wizard` se skrytým menu; React komponenta sdílí `@wordpress/components` + existující MediaPicker | Konzistence s rest of admin, zero new JS deps |
| 3 | **Persistence:** používá stávající `ogc_settings` option — wizard nepíše nic nového, jen předvyplňuje existující pole | Nulová migrace; uživatel může cokoli z wizardu později přepsat v plných Settings |
| 4 | **Skip-ability:** každý krok má "Skip" + "Zpět" + "Použít výchozí"; na konci dismiss flag `ogc_wizard_dismissed` aby se už neotevíral | Uživatel ≠ rukojmí |
| 5 | **No data leak:** wizard nikdy nepošle telemetrii; žádný phone-home check | wp.org compliance |

#### Out of scope (→ v0.6+)

- Video tutorial embed (externí hosting = wp.org submission risk)
- Gamifikovaný progress s badges (overkill pro B2B tool)
- Multi-language wizard conversations (i18n ano, chatbot ne)

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

_Generováno 2026-04-19, aktualizováno 2026-04-20 o v0.4 design._
