const { execFileSync } = require( 'node:child_process' );

/**
 * @param {number} ms
 */
function sleepSync( ms ) {
	const end = Date.now() + ms;
	while ( Date.now() < end ) {
		/* busy-wait: short intervals only */
	}
}

/**
 * Run a wp-cli command inside wp-env.
 *
 * @param {string[]} args wp args after `wp`.
 * @param {{ json?: boolean }} [opts]
 * @returns {string|object|null}
 */
function wpCli( args, opts = {} ) {
	const result = execFileSync(
		'npx',
		[ 'wp-env', 'run', 'cli', 'wp', ...args ],
		{
			encoding: 'utf8',
			env: process.env,
			stdio: [ 'ignore', 'pipe', 'pipe' ],
			maxBuffer: 10 * 1024 * 1024,
		}
	);
	const out = ( result || '' ).trim();
	if ( opts.json ) {
		return out ? JSON.parse( out ) : null;
	}
	return out;
}

/**
 * Merge SentryGate options via PHP eval (Base64-JSON, typed).
 *
 * @param {Record<string, unknown>} patch
 */
function updateSentryGateOptions( patch ) {
	const b64 = Buffer.from( JSON.stringify( patch ), 'utf8' ).toString( 'base64' );
	wpCli( [
		'eval',
		`SentryGate_Options::update( json_decode( base64_decode( '${ b64 }' ), true ) ); SentryGate_Options::flush_cache(); echo '1';`,
	] );
}

/**
 * Clear rate-limit rows for a clean lockout scenario.
 */
function clearLockouts() {
	wpCli( [
		'eval',
		'global $wpdb; $wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}sentrygate_lockouts" ); echo "1";',
	] );
}

/**
 * Reset 2FA meta for a user.
 *
 * @param {number|string} userId
 */
function resetUserTfa( userId ) {
	const id = Number( userId );
	wpCli( [
		'eval',
		`$uid=${ id };` +
			`$keys=array('sentrygate_tfa_secret','sentrygate_tfa_enabled','sentrygate_tfa_method','sentrygate_tfa_pending_method','sentrygate_tfa_recovery','sentrygate_tfa_recovery_plain_once','sentrygate_tfa_email_code','sentrygate_tfa_force_setup');` +
			`foreach($keys as $k){delete_user_meta($uid,$k);}` +
			`delete_transient('sentrygate_email_code_'.$uid);` +
			`delete_transient('sentrygate_email_cd_'.$uid);` +
			`echo '1';`,
	] );
}

/**
 * Last line of wp-cli stdout (wp-env may prepend noise).
 *
 * @param {string} raw
 * @returns {string}
 */
function lastStdoutLine( raw ) {
	const lines = String( raw || '' )
		.split( /\r?\n/ )
		.map( ( line ) => line.trim() )
		.filter( Boolean );
	return lines.length ? lines[ lines.length - 1 ] : '';
}

/**
 * Last captured mail from the E2E mu-plugin.
 *
 * @returns {{ to?: string, subject?: string, message?: string } | null}
 */
function getLastMail() {
	try {
		const raw = /** @type {string} */ (
			wpCli( [
				'eval',
				'echo wp_json_encode( get_option( "sentrygate_e2e_last_mail" ) );',
			] )
		);
		const json = lastStdoutLine( raw );
		if ( ! json || json === 'false' || json === 'null' ) {
			return null;
		}
		return JSON.parse( json );
	} catch {
		return null;
	}
}

/**
 * Last captured 6-digit code (dedicated option).
 *
 * @returns {string}
 */
function getLastMailCode() {
	try {
		const raw = /** @type {string} */ (
			wpCli( [
				'eval',
				'echo (string) get_option( "sentrygate_e2e_last_code", "" );',
			] )
		);
		const code = lastStdoutLine( raw );
		return /^\d{6}$/.test( code ) ? code : '';
	} catch {
		return '';
	}
}

/**
 * Clear captured mail.
 */
function clearLastMail() {
	try {
		wpCli( [
			'eval',
			'delete_option("sentrygate_e2e_last_mail"); delete_option("sentrygate_e2e_last_code"); ' +
				'@unlink( WP_CONTENT_DIR . "/uploads/sentrygate-e2e-last-mail.json" ); echo "1";',
		] );
	} catch {
		/* ignore */
	}
}

/**
 * Extract 6-digit code from last captured mail.
 *
 * @param {{ timeoutMs?: number, intervalMs?: number }} [opts]
 * @returns {string}
 */
function waitForMailCode( opts = {} ) {
	const timeoutMs = opts.timeoutMs ?? 20_000;
	const intervalMs = opts.intervalMs ?? 400;
	const started = Date.now();
	let last = null;
	while ( Date.now() - started < timeoutMs ) {
		const direct = getLastMailCode();
		if ( direct ) {
			return direct;
		}
		last = getLastMail();
		const message = last && typeof last.message === 'string' ? last.message : '';
		const match = message.match( /\b(\d{6})\b/ );
		if ( match ) {
			return match[ 1 ];
		}
		sleepSync( intervalMs );
	}
	throw new Error(
		'Kein 6-stelliger Code in sentrygate_e2e_last_mail gefunden. Letzter Stand: ' +
			JSON.stringify( { code: getLastMailCode(), mail: last } )
	);
}
/**
 * Whether the E2E mail-capture MU-plugin is loaded.
 *
 * @returns {boolean}
 */
function mailCaptureLoaded() {
	try {
		return wpCli( [
			'eval',
			'echo function_exists( "sentrygate_e2e_capture_mail" ) ? "1" : "0";',
		] ) === '1';
	} catch {
		return false;
	}
}

/**
 * Admin user ID (default admin).
 *
 * @returns {number}
 */
function adminUserId() {
	const out = wpCli( [ 'user', 'get', 'admin', '--field=ID' ] );
	return Number( out );
}

module.exports = {
	wpCli,
	updateSentryGateOptions,
	clearLockouts,
	resetUserTfa,
	getLastMail,
	getLastMailCode,
	clearLastMail,
	waitForMailCode,
	mailCaptureLoaded,
	adminUserId,
	sleepSync,
};