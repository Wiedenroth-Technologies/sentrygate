const { test, expect } = require( '@playwright/test' );
const { loginAsAdmin } = require( '../helpers/auth' );
const { wpCli, updateSentryGateOptions, adminUserId, resetUserTfa } = require( '../helpers/wp-cli' );

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
		await expect( nav.getByRole( 'link', { name: 'Login URL' } ) ).toHaveCount( 0 );
		await expect( nav.getByRole( 'link', { name: 'Form protection' } ) ).toHaveCount( 0 );

		await page.goto(
			'/wp-admin/admin.php?page=wiedenroth-technologies-sentrygate&tab=login_path'
		);
		await expect( page ).toHaveURL( /module_off=1/ );
		await expect( page ).not.toHaveURL( /tab=login_path/ );
		await expect( page.getByText( 'Section unavailable' ) ).toBeVisible();
		await expect(
			page.getByRole( 'heading', { name: 'Status', exact: true } )
		).toBeVisible();

		// Prefer login_path over two_factor: enabling 2FA redirects admins without a method to Users → Two-factor.
		updateSentryGateOptions( { module_login_path: true } );
		await page.goto( '/wp-admin/admin.php?page=wiedenroth-technologies-sentrygate' );
		await expect(
			page
				.locator( '.wiedenroth-technologies-sentrygate-wt-tabs' )
				.getByRole( 'link', { name: 'Login URL' } )
		).toBeVisible();
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

	test( 'Setup step bar omits Two-factor when inactive and ignores skipped URL jumps', async ( {
		page,
	} ) => {
		wpCli( [ 'option', 'update', 'sentrygate_setup_completed', '0' ] );
		wpCli( [ 'eval', 'delete_option("sentrygate_setup_progress"); echo "1";' ] );
		updateSentryGateOptions( { module_two_factor: false } );

		await loginAsAdmin( page );
		await page.goto(
			'/wp-admin/admin.php?page=wiedenroth-technologies-sentrygate&tab=setup&step=done'
		);

		await expect( page ).toHaveURL( /tab=setup/ );
		await expect( page ).toHaveURL( /step=welcome/ );
		await expect( page.getByRole( 'heading', { name: 'Welcome to SentryGate' } ) ).toBeVisible();

		const bar = page.locator( '.wiedenroth-technologies-sentrygate-wt-steps' );
		const titles = bar.locator( '.wiedenroth-technologies-sentrygate-wt-step__title' );
		await expect( titles ).toHaveCount( 4 );
		await expect( titles.nth( 0 ) ).toHaveText( 'Welcome' );
		await expect( titles.nth( 1 ) ).toHaveText( 'Modules' );
		await expect( titles.nth( 2 ) ).toHaveText( 'Settings' );
		await expect( titles.nth( 3 ) ).toHaveText( 'Done' );
		await expect( bar.getByText( 'Two-factor' ) ).toHaveCount( 0 );

		const nums = bar.locator( '.wiedenroth-technologies-sentrygate-wt-step__num' );
		await expect( nums.nth( 0 ) ).toHaveText( '1' );
		await expect( nums.nth( 1 ) ).toHaveText( '2' );
		await expect( nums.nth( 2 ) ).toHaveText( '3' );
		await expect( nums.nth( 3 ) ).toHaveText( '4' );

		await expect(
			bar.locator( '.wiedenroth-technologies-sentrygate-wt-step--done' )
		).toHaveCount( 0 );
		await expect(
			bar.locator( '.wiedenroth-technologies-sentrygate-wt-step--current' )
		).toHaveCount( 1 );

		await page.goto(
			'/wp-admin/admin.php?page=wiedenroth-technologies-sentrygate&tab=setup&step=settings'
		);
		await expect( page ).toHaveURL( /step=welcome/ );
		await expect(
			page.locator( '.wiedenroth-technologies-sentrygate-wt-step--done' )
		).toHaveCount( 0 );

		wpCli( [ 'option', 'update', 'sentrygate_setup_completed', '1' ] );
	} );

	test( 'Setup step bar includes Two-factor when the module is on', async ( { page } ) => {
		// Log in while 2FA is still off (beforeEach), then enable the module for the step bar only.
		await loginAsAdmin( page );

		const uid = adminUserId();
		wpCli( [ 'option', 'update', 'sentrygate_setup_completed', '0' ] );
		wpCli( [ 'eval', 'delete_option("sentrygate_setup_progress"); echo "1";' ] );
		// Avoid force-redirect to Users → Two-factor while asserting the wizard chrome.
		wpCli( [
			'eval',
			`update_user_meta(${ uid },'sentrygate_tfa_enabled','1');` +
				`update_user_meta(${ uid },'sentrygate_tfa_method','email'); echo '1';`,
		] );
		updateSentryGateOptions( { module_two_factor: true } );

		await page.goto(
			'/wp-admin/admin.php?page=wiedenroth-technologies-sentrygate&tab=setup&step=welcome'
		);

		const bar = page.locator( '.wiedenroth-technologies-sentrygate-wt-steps' );
		const titles = bar.locator( '.wiedenroth-technologies-sentrygate-wt-step__title' );
		await expect( titles ).toHaveCount( 5 );
		await expect( titles.nth( 3 ) ).toHaveText( 'Two-factor' );
		await expect( titles.nth( 4 ) ).toHaveText( 'Done' );
		await expect( bar.locator( '.wiedenroth-technologies-sentrygate-wt-step__num' ).nth( 4 ) ).toHaveText(
			'5'
		);

		updateSentryGateOptions( { module_two_factor: false } );
		resetUserTfa( uid );
		wpCli( [ 'option', 'update', 'sentrygate_setup_completed', '1' ] );
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
		await expect( page ).toHaveURL( /module_off=1/ );
		await expect( page.getByText( 'Section unavailable' ) ).toBeVisible();
	} );
} );
