/**
 * Lifecycle after wp-env start: Plugin aktiv, Permalinks, Test-Defaults.
 */
const { execSync } = require( 'node:child_process' );

function wp( args ) {
	execSync( `npx wp-env run cli wp ${ args }`, {
		stdio: 'inherit',
		env: process.env,
	} );
}

try {
	wp( 'plugin activate sentrygate' );
	wp( 'rewrite structure "/%postname%/" --hard' );
	wp( 'option update blogname "SentryGate E2E"' );
	wp(
		`eval 'echo function_exists( "sentrygate_e2e_capture_mail" ) ? "mail-capture-ok" : "mail-capture-missing";'`
	);
	// Smoke-Test: Capture speichert Code in Option.
	wp(
		`eval 'wp_mail( "admin@example.com", "SentryGate E2E ping", "Ihr Einmalcode lautet: 654321" ); echo get_option("sentrygate_e2e_last_code");'`
	);
	// Challenge-Mindestzeit für deterministische, schnelle Tests.
	const patch = Buffer.from(
		JSON.stringify( {
			challenge_min_time: 0,
			rate_limit_max_attempts: 3,
			rate_limit_lockout: 120,
			rate_limit_exponential: false,
		} ),
		'utf8'
	).toString( 'base64' );
	wp(
		`eval 'SentryGate_Options::update( json_decode( base64_decode( "${ patch }" ), true ) ); SentryGate_Options::flush_cache(); echo "ok";'`
	);
	console.log( 'SentryGate E2E afterStart: ready' );
} catch ( error ) {
	console.error( 'afterStart failed:', error.message || error );
	process.exit( 1 );
}
