import { defineConfig, devices } from '@playwright/test';

/**
 * Playwright config for Open Graph Control E2E.
 *
 * Runs against a `wp-env` instance. Start with:
 *   npx wp-env start
 *
 * Then run:
 *   npm run e2e
 *
 * The wp-env admin user defaults are admin / password.
 */
export default defineConfig({
	testDir: './tests/e2e/playwright',
	timeout: 30_000,
	expect: { timeout: 5_000 },
	fullyParallel: false,
	retries: process.env.CI ? 2 : 0,
	reporter: process.env.CI ? 'github' : 'list',
	use: {
		baseURL: process.env.WP_BASE_URL || 'http://localhost:8888',
		trace: 'on-first-retry',
		screenshot: 'only-on-failure',
		video: 'retain-on-failure',
	},
	projects: [
		{
			name: 'chromium',
			use: { ...devices[ 'Desktop Chrome' ] },
		},
	],
	webServer: process.env.CI
		? undefined
		: {
				command: 'npx wp-env start',
				url: 'http://localhost:8888',
				timeout: 120_000,
				reuseExistingServer: true,
		  },
});
