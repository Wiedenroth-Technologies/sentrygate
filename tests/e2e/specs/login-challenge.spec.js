const { test, expect } = require( '@playwright/test' );
const {
	completeLoginChallenge,
	fillLoginCredentials,
	ADMIN_USER,
	ADMIN_PASSWORD,
} = require( '../helpers/auth' );
const { clearLockouts, updateSentryGateOptions } = require( '../helpers/wp-cli' );

test.describe( 'Login-Challenge', () => {
	test.beforeEach( () => {
		clearLockouts();
		updateSentryGateOptions( {
			module_login_challenge: true,
			module_login_path: false,
			module_two_factor: false,
			challenge_mode: 'full',
			challenge_min_time: 0,
			rate_limit_max_attempts: 3,
			rate_limit_lockout: 120,
			rate_limit_exponential: false,
		} );
	} );

	test( 'normale Challenge besteht und Login gelingt', async ( { page } ) => {
		await page.goto( '/wp-login.php' );
		await expect( page.locator( '#sentrygate-challenge' ) ).toBeVisible();
		await completeLoginChallenge( page );
		await fillLoginCredentials( page, { user: ADMIN_USER, password: ADMIN_PASSWORD } );
		await expect( page ).toHaveURL( /wp-admin/ );
	} );

	test( 'Lockout nach Fehlversuchen', async ( { page } ) => {
		await page.goto( '/wp-login.php' );

		// rate_limit_max_attempts=3: nach drei Fehlversuchen ist die IP gesperrt;
		// die Sperrseite erscheint beim nächsten Login-Request.
		for ( let i = 0; i < 3; i++ ) {
			await expect( page.locator( '#sentrygate-challenge' ) ).toBeVisible();
			const target = ( await page.locator( '#sentrygate-choice-target' ).innerText() ).trim();
			const wrong = page.locator( `.sentrygate-choice__item:not([aria-label="${ target }"])` ).first();
			await wrong.click();
			await page.locator( '#wp-submit' ).click();
			await expect( page.locator( '#login_error, #sentrygate-challenge' ).first() ).toBeVisible();
		}

		await page.goto( '/wp-login.php' );
		await expect( page.locator( '.message, #login_error, body' ).filter( { hasText: /Too many failed attempts/i } ).first() ).toBeVisible();
		await expect( page.locator( '#loginform' ) ).toHaveCount( 0 );
		await expect( page.locator( '#sentrygate-challenge' ) ).toHaveCount( 0 );
	} );
} );
