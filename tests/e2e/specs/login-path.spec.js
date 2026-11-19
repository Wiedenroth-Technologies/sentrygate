const { test, expect } = require( '@playwright/test' );
const {
	completeLoginChallenge,
	fillLoginCredentials,
	ADMIN_USER,
	ADMIN_PASSWORD,
} = require( '../helpers/auth' );
const { updateSentryGateOptions, wpCli, clearLockouts } = require( '../helpers/wp-cli' );

const SLUG = 'sg-e2e-portal';

test.describe( 'Eigener Login-Pfad', () => {
	test.beforeEach( () => {
		clearLockouts();
		updateSentryGateOptions( {
			module_login_path: true,
			module_login_challenge: true,
			module_two_factor: false,
			login_slug: SLUG,
			redirect_admin: true,
			challenge_min_time: 0,
		} );
		wpCli( [ 'rewrite', 'flush', '--hard' ] );
	} );

	test.afterEach( () => {
		updateSentryGateOptions( {
			module_login_path: false,
			login_slug: 'portal',
		} );
		wpCli( [ 'rewrite', 'flush', '--hard' ] );
	} );

	test( 'wp-login.php liefert 404; Custom-Slug funktioniert; Wiederherstellung per Deaktivierung', async ( {
		page,
		request,
	} ) => {
		const blocked = await request.get( '/wp-login.php', { maxRedirects: 0 } );
		expect( blocked.status() ).toBe( 404 );

		await page.goto( `/${ SLUG }/` );
		await expect( page.locator( '#sentrygate-challenge, #loginform' ).first() ).toBeVisible();
		await completeLoginChallenge( page );
		await fillLoginCredentials( page, { user: ADMIN_USER, password: ADMIN_PASSWORD } );
		await expect( page ).toHaveURL( /wp-admin/ );

		// Dokumentierter Wiederherstellungsweg: Modul aus / Plugin deaktivieren stellt wp-login wieder her.
		updateSentryGateOptions( { module_login_path: false } );
		wpCli( [ 'rewrite', 'flush', '--hard' ] );

		const restored = await request.get( '/wp-login.php' );
		expect( restored.status() ).toBeLessThan( 400 );
		await page.context().clearCookies();
		await page.goto( '/wp-login.php' );
		await expect( page.locator( '#sentrygate-challenge, #loginform' ).first() ).toBeVisible();
	} );
} );
