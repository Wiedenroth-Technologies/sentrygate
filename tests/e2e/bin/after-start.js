/**
 * Lifecycle after wp-env start: Plugin aktiv, Permalinks, Mail-Capture, Test-Defaults.
 */
const { execSync } = require( 'node:child_process' );

function run( command ) {
	execSync( command, {
		stdio: 'inherit',
		env: process.env,
	} );
}

function wp( args ) {
	run( `npx wp-env run cli wp ${ args }` );
}

try {
	// MU-Plugin kommt über .wp-env.json-Mapping (wp-content/mu-plugins → tests/e2e/mu-plugins).
	// Kein cp: Quell- und Zieldatei sind im Container dieselbe gemappte Datei.
	run(
		`npx wp-env run cli bash -lc 'ls -la /var/www/html/wp-content/mu-plugins'`
	);

	wp( 'plugin activate sentrygate' );
	wp( 'rewrite structure "/%postname%/" --hard' );
	wp( 'option update blogname "SentryGate E2E"' );

	wp(
		`eval 'echo function_exists( "sentrygate_e2e_capture_mail" ) ? "mail-capture-ok" : "mail-capture-missing";'`
	);
	wp(
		`eval 'echo ( defined( "SENTRYGATE_E2E" ) && SENTRYGATE_E2E ) ? "e2e-const-ok" : "e2e-const-missing";'`
	);

	// Smoke: Core wp_mail → pre_wp_mail Capture muss 654321 speichern.
	wp(
		`eval 'delete_option("sentrygate_e2e_last_code"); wp_mail("admin@example.com","SentryGate E2E ping","Ihr Einmalcode lautet: 654321"); echo get_option("sentrygate_e2e_last_code");'`
	);

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
