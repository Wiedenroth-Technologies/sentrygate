const { test, expect } = require( '@playwright/test' );
const {
	loginAsAdmin,
	completeLoginChallenge,
	fillLoginCredentials,
	totpCode,
	submitTfaCode,
	ADMIN_USER,
	ADMIN_PASSWORD,
} = require( '../helpers/auth' );
const {
	updateSentryGateOptions,
	resetUserTfa,
	adminUserId,
	clearLockouts,
} = require( '../helpers/wp-cli' );

test.describe( '2FA TOTP (Authenticator app)', () => {
	test.beforeEach( () => {
		clearLockouts();
		resetUserTfa( adminUserId() );
		updateSentryGateOptions( {
			module_login_challenge: true,
			module_login_path: false,
			module_two_factor: true,
			tfa_email_fallback: true,
			challenge_min_time: 0,
		} );
	} );

	test.afterEach( () => {
		resetUserTfa( adminUserId() );
		updateSentryGateOptions( { module_two_factor: false } );
	} );

	test( 'Einrichtung und Login mit berechnetem TOTP', async ( { page } ) => {
		await loginAsAdmin( page );
		await page.goto( '/wp-admin/users.php?page=sentrygate-tfa' );

		await page.locator( 'button[name="sentrygate_tfa_action"][value="choose_app"]' ).click();
		await expect( page.locator( 'code.wiedenroth-technologies-sentrygate-wt-secret, code[class*="-wt-secret"]' ) ).toBeVisible();

		const secret = ( await page.locator( 'code.wiedenroth-technologies-sentrygate-wt-secret, code[class*="-wt-secret"]' ).innerText() ).trim();
		expect( secret.length ).toBeGreaterThan( 10 );

		const code = totpCode( secret );
		await page.locator( '#sentrygate_tfa_confirm' ).fill( code );
		await page.locator( 'button[name="sentrygate_tfa_action"][value="confirm_app"]' ).click();
		await expect( page.getByRole( 'status' ).filter( { hasText: /Setup complete|Two-factor is active/i } ).first() ).toBeVisible();

		await page.context().clearCookies();
		await page.goto( '/wp-login.php' );
		await completeLoginChallenge( page );
		await fillLoginCredentials( page, { user: ADMIN_USER, password: ADMIN_PASSWORD } );

		await expect( page.locator( '#sentrygate_tfa_form' ) ).toBeVisible();
		await submitTfaCode( page, totpCode( secret ) );
		await expect( page ).toHaveURL( /wp-admin/ );
	} );
} );
