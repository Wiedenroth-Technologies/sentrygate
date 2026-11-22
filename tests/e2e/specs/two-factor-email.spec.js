const { test, expect } = require( '@playwright/test' );
const {
	loginAsAdmin,
	completeLoginChallenge,
	fillLoginCredentials,
	submitTfaCode,
	ADMIN_USER,
	ADMIN_PASSWORD,
} = require( '../helpers/auth' );
const {
	updateSentryGateOptions,
	resetUserTfa,
	adminUserId,
	clearLockouts,
	clearLastMail,
	waitForMailCode,
	mailCaptureLoaded,
} = require( '../helpers/wp-cli' );

test.describe( '2FA E-Mail', () => {
	test.beforeEach( () => {
		expect( mailCaptureLoaded() ).toBe( true );
		clearLockouts();
		clearLastMail();
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
		clearLastMail();
	} );

	test( 'Einrichtung und Login mit Code aus abgefangener Mail', async ( { page } ) => {
		await loginAsAdmin( page );
		await page.goto( '/wp-admin/users.php?page=sentrygate-tfa' );

		clearLastMail();
		await page.locator( 'button[name="sentrygate_tfa_action"][value="choose_email"]' ).click();
		await expect( page.locator( '#sentrygate_tfa_confirm' ) ).toBeVisible();
		await expect( page.getByRole( 'heading', { name: /E-Mail einrichten/i } ) ).toBeVisible();

		// OTP aus abgefangener Mail (MU-Plugin: pre_wp_mail / wp_mail), kein Produktiv-Hook.
		const setupCode = waitForMailCode( { timeoutMs: 25_000 } );
		await page.locator( '#sentrygate_tfa_confirm' ).fill( setupCode );
		await page.locator( 'button[name="sentrygate_tfa_action"][value="confirm_email"]' ).click();
		await expect( page.getByRole( 'status' ).filter( { hasText: /Einrichtung abgeschlossen|Zwei-Faktor ist aktiv/i } ).first() ).toBeVisible();

		await page.context().clearCookies();
		// Cooldown + Capture leeren, sonst sendet Login keinen neuen Code (1-Min-Cooldown nach Setup).
		clearLastMail();

		await page.goto( '/wp-login.php' );
		await completeLoginChallenge( page );
		await fillLoginCredentials( page, { user: ADMIN_USER, password: ADMIN_PASSWORD } );

		await expect( page.locator( '#sentrygate_tfa_form' ) ).toBeVisible();
		const loginCode = waitForMailCode( { timeoutMs: 25_000 } );
		await submitTfaCode( page, loginCode );
		await expect( page ).toHaveURL( /wp-admin/ );
	} );
} );
