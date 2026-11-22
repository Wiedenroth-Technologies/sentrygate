const { expect } = require( '@playwright/test' );
const { TOTP } = require( 'otpauth' );

const ADMIN_USER = process.env.WP_ADMIN_USER || 'admin';
const ADMIN_PASSWORD = process.env.WP_ADMIN_PASSWORD || 'password';

/**
 * Complete the SentryGate login challenge by matching prompt ↔ aria-label.
 *
 * @param {import('@playwright/test').Page} page
 */
async function completeLoginChallenge( page ) {
	const challenge = page.locator( '#sentrygate-challenge' );
	if ( ! ( await challenge.count() ) ) {
		return;
	}

	const mode = await challenge.getAttribute( 'data-mode' );
	if ( mode === 'full' ) {
		const target = page.locator( '#sentrygate-choice-target' );
		await expect( target ).toBeVisible();
		const label = ( await target.innerText() ).trim();
		await page.locator( `.sentrygate-choice__item[aria-label="${ label }"]` ).click();
	}

	await page.locator( '#wp-submit' ).click();
	await expect( page.locator( '#loginform, #sentrygate_tfa_form' ) ).toBeVisible();
}

/**
 * Fill wp-login credentials (after challenge).
 *
 * @param {import('@playwright/test').Page} page
 * @param {{ user?: string, password?: string }} [creds]
 */
async function fillLoginCredentials( page, creds = {} ) {
	await expect( page.locator( '#loginform' ) ).toBeVisible();
	await page.locator( '#user_login' ).fill( creds.user || ADMIN_USER );
	await page.locator( '#user_pass' ).fill( creds.password || ADMIN_PASSWORD );
	await page.locator( '#wp-submit' ).click();
}

/**
 * Full login through challenge → credentials (no 2FA expected).
 *
 * @param {import('@playwright/test').Page} page
 * @param {{ path?: string, user?: string, password?: string }} [opts]
 */
async function loginAsAdmin( page, opts = {} ) {
	const path = opts.path || '/wp-login.php';
	await page.goto( path );
	await completeLoginChallenge( page );
	await fillLoginCredentials( page, opts );
	await expect( page ).toHaveURL( /wp-admin/ );
}

/**
 * Compute current TOTP for a Base32 secret (RFC 6238, 30s/6 digits/SHA1).
 *
 * @param {string} secret
 * @returns {string}
 */
function totpCode( secret ) {
	const totp = new TOTP( {
		secret: secret.replace( /\s+/g, '' ),
		algorithm: 'SHA1',
		digits: 6,
		period: 30,
	} );
	return totp.generate();
}

/**
 * Submit the SentryGate TFA form.
 *
 * @param {import('@playwright/test').Page} page
 * @param {string} code
 */
async function submitTfaCode( page, code ) {
	await expect( page.locator( '#sentrygate_tfa_form' ) ).toBeVisible();
	await page.locator( '#sentrygate_tfa_code' ).fill( code );
	await page.locator( '#wp-submit' ).click();
}

module.exports = {
	ADMIN_USER,
	ADMIN_PASSWORD,
	completeLoginChallenge,
	fillLoginCredentials,
	loginAsAdmin,
	totpCode,
	submitTfaCode,
};
