// @ts-check
const { defineConfig, devices } = require( '@playwright/test' );

const baseURL = process.env.WP_BASE_URL || 'http://localhost:8888';

/**
 * Deterministische E2E-Konfiguration: nur Chromium, keine festen Sleeps,
 * Trace nur bei Fehlern.
 */
module.exports = defineConfig( {
	testDir: './tests/e2e/specs',
	globalSetup: require.resolve( './tests/e2e/global-setup.js' ),
	fullyParallel: false,
	forbidOnly: !! process.env.CI,
	retries: process.env.CI ? 1 : 0,
	workers: 1,
	timeout: 90_000,
	expect: {
		timeout: 15_000,
	},
	reporter: process.env.CI
		? [ [ 'github' ], [ 'html', { open: 'never', outputFolder: 'tests/e2e/playwright-report' } ], [ 'list' ] ]
		: [ [ 'list' ], [ 'html', { open: 'never', outputFolder: 'tests/e2e/playwright-report' } ] ],
	outputDir: 'tests/e2e/test-results',
	use: {
		baseURL,
		browserName: 'chromium',
		...devices[ 'Desktop Chrome' ],
		trace: 'retain-on-failure',
		screenshot: 'only-on-failure',
		video: 'off',
		actionTimeout: 15_000,
		navigationTimeout: 30_000,
	},
	projects: [
		{
			name: 'chromium',
			use: { ...devices[ 'Desktop Chrome' ] },
		},
	],
} );
