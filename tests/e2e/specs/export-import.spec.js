const { test, expect } = require( '@playwright/test' );
const fs = require( 'node:fs' );
const path = require( 'node:path' );
const { loginAsAdmin } = require( '../helpers/auth' );
const { updateSentryGateOptions, wpCli } = require( '../helpers/wp-cli' );

test.describe( 'Konfig-Export und -Import', () => {
	test.beforeEach( () => {
		updateSentryGateOptions( {
			module_login_challenge: true,
			module_two_factor: false,
			module_login_path: false,
			challenge_min_time: 0,
			header_x_frame_value: 'SAMEORIGIN',
		} );
	} );

	test( 'Export lädt JSON; Import übernimmt geänderte Einstellung', async ( { page } ) => {
		await loginAsAdmin( page );

		updateSentryGateOptions( { header_referrer_value: 'no-referrer' } );

		await page.goto( '/wp-admin/admin.php?page=wiedenroth-technologies-sentrygate&tab=export' );
		const downloadPromise = page.waitForEvent( 'download' );
		await page.getByRole( 'button', { name: /Datei herunterladen/i } ).click();
		const download = await downloadPromise;
		const tmp = path.join( test.info().outputDir, 'sentrygate-config.json' );
		fs.mkdirSync( path.dirname( tmp ), { recursive: true } );
		await download.saveAs( tmp );

		const exported = JSON.parse( fs.readFileSync( tmp, 'utf8' ) );
		expect( exported ).toHaveProperty( 'settings' );
		expect( exported.settings.header_referrer_value ).toBe( 'no-referrer' );

		updateSentryGateOptions( { header_referrer_value: 'strict-origin-when-cross-origin' } );
		const before = wpCli( [
			'eval',
			'echo (string) SentryGate_Options::get( "header_referrer_value" );',
		] );
		expect( before ).toBe( 'strict-origin-when-cross-origin' );

		await page.goto( '/wp-admin/admin.php?page=wiedenroth-technologies-sentrygate&tab=export' );
		await page.locator( 'input[name="sentrygate_import"]' ).setInputFiles( tmp );
		await Promise.all( [
			page.waitForURL( /tab=export&import=ok/, { timeout: 30_000 } ),
			page.getByRole( 'button', { name: /Datei einspielen/i } ).click(),
		] );
		await expect( page.getByRole( 'status' ).filter( { hasText: /Einstellungen wurden übernommen/i } ).first() ).toBeVisible();

		const after = wpCli( [
			'eval',
			'echo (string) SentryGate_Options::get( "header_referrer_value" );',
		] );
		expect( after ).toBe( 'no-referrer' );
	} );
} );
