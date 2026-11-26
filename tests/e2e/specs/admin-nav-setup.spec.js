const { test, expect } = require( '@playwright/test' );
const { loginAsAdmin } = require( '../helpers/auth' );
const { wpCli, updateSentryGateOptions } = require( '../helpers/wp-cli' );

test.describe( 'Admin navigation and setup', () => {
	test.beforeEach( () => {
		wpCli( [ 'option', 'update', 'sentrygate_setup_completed', '1' ] );
		updateSentryGateOptions( {
			module_two_factor: false,
			module_login_path: false,
		} );
	} );

	test( 'Hides module tabs when modules are off and shows them when on', async ( { page } ) => {
		await loginAsAdmin( page );
		await page.goto( '/wp-admin/admin.php?page=wiedenroth-technologies-sentrygate' );

		const nav = page.locator( '.wiedenroth-technologies-sentrygate-wt-tabs' );
		await expect( nav.getByRole( 'link', { name: 'Overview' } ) ).toBeVisible();
		await expect( nav.getByRole( 'link', { name: 'Modules on/off' } ) ).toBeVisible();
		await expect( nav.getByRole( 'link', { name: 'Two-factor' } ) ).toHaveCount( 0 );
		await expect( nav.getByRole( 'link', { name: 'Login URL' } ) ).toHaveCount( 0 );
		await expect( nav.getByRole( 'link', { name: 'Form protection' } ) ).toHaveCount( 0 );

		await page.goto(
			'/wp-admin/admin.php?page=wiedenroth-technologies-sentrygate&tab=tfa'
		);
		await expect( page.getByText( 'Section unavailable' ) ).toBeVisible();
		await expect( page.getByRole( 'heading', { name: 'Status' } ) ).toBeVisible();

		updateSentryGateOptions( { module_two_factor: true } );
		await page.goto( '/wp-admin/admin.php?page=wiedenroth-technologies-sentrygate' );
		await expect( nav.getByRole( 'link', { name: 'Two-factor' } ) ).toBeVisible();
	} );

	test( 'Setup wizard is reachable from overview and can be skipped', async ( { page } ) => {
		await loginAsAdmin( page );
		await page.goto( '/wp-admin/admin.php?page=wiedenroth-technologies-sentrygate' );

		await page.getByRole( 'button', { name: 'Run setup again' } ).click();
		await expect( page ).toHaveURL( /tab=setup/ );
		await expect( page.getByRole( 'heading', { name: 'Welcome to SentryGate' } ) ).toBeVisible();

		await page.getByRole( 'link', { name: 'Skip setup' } ).click();
		await expect( page ).toHaveURL( /page=wiedenroth-technologies-sentrygate/ );
		await expect( page ).not.toHaveURL( /tab=setup/ );

		const completed = wpCli( [ 'option', 'get', 'sentrygate_setup_completed' ] );
		expect( completed.trim() ).toBe( '1' );
	} );

	test( 'Form protection stays hidden without BlockForge', async ( { page } ) => {
		await loginAsAdmin( page );
		await page.goto(
			'/wp-admin/admin.php?page=wiedenroth-technologies-sentrygate&tab=modules'
		);

		await expect( page.getByText( 'BlockForge not detected' ) ).toBeVisible();
		await expect( page.getByText( 'Form protection', { exact: true } ) ).toHaveCount( 0 );

		await page.goto(
			'/wp-admin/admin.php?page=wiedenroth-technologies-sentrygate&tab=forms'
		);
		await expect( page.getByText( 'Section unavailable' ) ).toBeVisible();
	} );
} );
