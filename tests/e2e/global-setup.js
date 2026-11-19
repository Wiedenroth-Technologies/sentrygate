const { execFileSync } = require( 'node:child_process' );

/**
 * Ensure WordPress/wp-env is reachable before the suite starts.
 */
module.exports = async function globalSetup() {
	const base = process.env.WP_BASE_URL || 'http://localhost:8888';
	const res = await fetch( base + '/' );
	if ( ! res.ok ) {
		throw new Error( `WordPress nicht erreichbar unter ${ base } (HTTP ${ res.status }). Bitte npm run env:start.` );
	}

	try {
		execFileSync( 'npx', [ 'wp-env', 'run', 'cli', 'wp', 'plugin', 'activate', 'sentrygate' ], {
			stdio: 'pipe',
			env: process.env,
		} );
	} catch ( error ) {
		const msg = error && error.stderr ? String( error.stderr ) : String( error );
		throw new Error( `SentryGate konnte nicht aktiviert werden: ${ msg }` );
	}
};
