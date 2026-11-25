const { test, expect } = require( '@playwright/test' );
const { loginAsAdmin } = require( '../helpers/auth' );
const { wpCli } = require( '../helpers/wp-cli' );

test.describe( 'Activation / deactivation', () => {
	test.afterAll( () => {
		wpCli( [ 'plugin', 'activate', 'sentrygate' ] );
	} );

	test( 'Plugin lässt sich deaktivieren und wieder aktivieren', async ( { page } ) => {
		await loginAsAdmin( page );
		await page.goto( '/wp-admin/plugins.php' );

		const deactivate = page.locator( '#deactivate-sentrygate, a[href*="action=deactivate"][href*="sentrygate"]' ).first();
		await expect( deactivate ).toBeVisible();
		await deactivate.click();
		await expect( page.locator( '#activate-sentrygate, a[href*="action=activate"][href*="sentrygate"]' ).first() ).toBeVisible();

		const activate = page.locator( '#activate-sentrygate, a[href*="action=activate"][href*="sentrygate"]' ).first();
		await activate.click();
		await expect( page.locator( '#deactivate-sentrygate, a[href*="action=deactivate"][href*="sentrygate"]' ).first() ).toBeVisible();

		const status = wpCli( [ 'plugin', 'get', 'sentrygate', '--field=status' ] );
		expect( status ).toBe( 'active' );
	} );
} );
