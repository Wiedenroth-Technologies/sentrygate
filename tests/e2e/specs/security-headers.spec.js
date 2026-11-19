const { test, expect } = require( '@playwright/test' );
const { updateSentryGateOptions } = require( '../helpers/wp-cli' );

test.describe( 'Security-Header (Frontend)', () => {
	test.beforeEach( () => {
		updateSentryGateOptions( {
			module_security_headers: true,
			header_x_frame_options: true,
			header_x_frame_value: 'SAMEORIGIN',
			header_x_content_type: true,
			header_referrer_policy: true,
			header_referrer_value: 'strict-origin-when-cross-origin',
			header_csp: true,
			header_csp_report_only: true,
			header_hsts: false,
		} );
	} );

	test( 'Homepage sendet konfigurierte Schutz-Header', async ( { request } ) => {
		const response = await request.get( '/' );
		expect( response.ok() ).toBeTruthy();

		const headers = response.headers();
		expect( headers[ 'x-frame-options' ]?.toLowerCase() ).toBe( 'sameorigin' );
		expect( headers[ 'x-content-type-options' ]?.toLowerCase() ).toBe( 'nosniff' );
		expect( headers[ 'referrer-policy' ]?.toLowerCase() ).toBe( 'strict-origin-when-cross-origin' );
		expect( headers[ 'permissions-policy' ] ).toBeTruthy();
		expect( headers[ 'x-permitted-cross-domain-policies' ]?.toLowerCase() ).toBe( 'none' );

		const csp =
			headers[ 'content-security-policy-report-only' ] ||
			headers[ 'content-security-policy' ];
		expect( csp ).toBeTruthy();
		expect( csp ).toMatch( /default-src/i );
	} );
} );
