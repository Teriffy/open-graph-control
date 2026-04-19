import { defineConfig, devices } from '@playwright/test';

/**
 * Two suites share this config:
 *
 *  1. Fixture specs (fixtures-*.spec.ts)
 *     — Run against static HTML pages in tests/e2e/fixtures/.
 *     — No WordPress / Docker required.
 *     — Cover preview card visual output and interactive affordances.
 *
 *  2. WP specs (01-…04-…spec.ts)
 *     — Require a live wp-env instance (Docker). Default: http://localhost:8888.
 *     — Start with `npx wp-env start` then `OGC_E2E_WP=1 npm run e2e`.
 *
 * Without OGC_E2E_WP set, the WP specs are ignored and only the fixture
 * specs run, so CI and developers without Docker can still exercise the
 * React output.
 */
const runWpSuite = Boolean( process.env.OGC_E2E_WP );

export default defineConfig( {
	testDir: './tests/e2e/playwright',
	timeout: 30_000,
	expect: { timeout: 5_000, toHaveScreenshot: { maxDiffPixelRatio: 0.02 } },
	fullyParallel: false,
	workers: runWpSuite ? 1 : undefined,
	retries: process.env.CI ? 2 : 0,
	reporter: process.env.CI ? 'github' : 'list',
	testIgnore: [
		...( runWpSuite ? [ '**/fixtures-*.spec.ts' ] : [ '**/0[0-9]-*.spec.ts' ] ),
		...( process.env.OGC_E2E_SNAPSHOT ? [] : [ '**/fixtures-snapshot.spec.ts' ] ),
	],
	use: {
		baseURL: runWpSuite
			? process.env.WP_BASE_URL || 'http://localhost:8888'
			: 'http://localhost:4321',
		trace: 'on-first-retry',
		screenshot: 'only-on-failure',
	},
	projects: [
		{
			name: 'chromium',
			use: { ...devices[ 'Desktop Chrome' ] },
		},
	],
	webServer: runWpSuite
		? {
				command: 'npx wp-env start',
				url: 'http://localhost:8888',
				timeout: 120_000,
				reuseExistingServer: true,
		  }
		: {
				command: 'npx http-server tests/e2e/fixtures -p 4321 -s',
				url: 'http://localhost:4321/preview.html',
				timeout: 10_000,
				reuseExistingServer: ! process.env.CI,
		  },
} );
