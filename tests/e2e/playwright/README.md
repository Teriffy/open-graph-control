# E2E tests

Playwright smoke suite that runs against a `wp-env` WordPress instance.

## Quick start

```bash
npx wp-env start
npx playwright install chromium --with-deps   # first time only
npm run e2e
```

Admin auth defaults to wp-env's `admin` / `password`. Override via env vars:

```bash
WP_BASE_URL=http://localhost:8888 WP_ADMIN_USER=... WP_ADMIN_PASS=... npm run e2e
```

## Specs

| Spec | What it verifies |
|---|---|
| `01-activate.spec.ts` | Plugin activates cleanly + "Open Graph Control" menu item appears + React settings root renders |
| `02-settings-save.spec.ts` | Site name edit persists across reload (GET /settings → POST /settings → GET /settings roundtrip) |
| `03-metabox-override.spec.ts` | Per-post title override surfaces in rendered `og:title` on the published post |
| `04-frontend-tags.spec.ts` | Homepage emits the comment markers + canonical `og:title` / `og:type` tags |

## Writing a new spec

Use the helpers in `helpers.ts`:

```ts
import { test, expect } from '@playwright/test';
import { login, gotoSettings } from './helpers';

test( 'my scenario', async ( { page } ) => {
    await login( page );
    await gotoSettings( page );
    // ...
} );
```

## CI (not wired yet)

The Playwright run isn't part of GitHub Actions CI because it requires Docker to spin up `wp-env`. To add it later:

```yaml
e2e:
  runs-on: ubuntu-latest
  steps:
    - uses: actions/checkout@v4
    - uses: actions/setup-node@v4
      with: { node-version: '24' }
    - run: npm install --no-audit --no-fund
    - run: npx playwright install chromium --with-deps
    - run: npx wp-env start
    - run: npm run e2e
```
