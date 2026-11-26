<?php
/**
 * PSR-4-ähnlicher Autoloader für SentryGate-Klassen.
 *
 * @package SentryGate
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SentryGate_Autoloader
 */
class SentryGate_Autoloader {

	/**
	 * Class-to-file map for non-standard paths.
	 *
	 * @var array<string, string>
	 */
	private static array $map = array(
		'SentryGate'                    => 'includes/class-sentrygate.php',
		'SentryGate_Activator'          => 'includes/class-sentrygate-activator.php',
		'SentryGate_Deactivator'        => 'includes/class-sentrygate-deactivator.php',
		'SentryGate_Loader'             => 'includes/class-sentrygate-loader.php',
		'SentryGate_i18n'               => 'includes/class-sentrygate-i18n.php',
		'SentryGate_Options'            => 'includes/class-sentrygate-options.php',
		'SentryGate_Modules'            => 'includes/class-sentrygate-modules.php',
		'SentryGate_Admin'              => 'includes/admin/class-sentrygate-admin.php',
		'SentryGate_Dashboard'          => 'includes/admin/class-sentrygate-dashboard.php',
		'SentryGate_Interface_Hardening'=> 'includes/modules/class-sentrygate-interface-hardening.php',
		'SentryGate_Debug_Control'      => 'includes/modules/class-sentrygate-debug-control.php',
		'SentryGate_Login_Path'         => 'includes/modules/class-sentrygate-login-path.php',
		'SentryGate_Login_Challenge'    => 'includes/modules/class-sentrygate-login-challenge.php',
		'SentryGate_Form_Challenge'     => 'includes/modules/class-sentrygate-form-challenge.php',
		'SentryGate_Two_Factor'         => 'includes/modules/class-sentrygate-two-factor.php',
		'SentryGate_Security_Headers'   => 'includes/modules/class-sentrygate-security-headers.php',
		'SentryGate_File_Hardening'     => 'includes/modules/class-sentrygate-file-hardening.php',
		'SentryGate_Audit_Log'          => 'includes/modules/class-sentrygate-audit-log.php',
		'SentryGate_Core_Integrity'     => 'includes/modules/class-sentrygate-core-integrity.php',
		'SentryGate_TOTP'               => 'includes/helpers/class-sentrygate-totp.php',
		'SentryGate_QR'                 => 'includes/helpers/class-sentrygate-qr.php',
		'SentryGate_Rate_Limit'         => 'includes/helpers/class-sentrygate-rate-limit.php',
		'SentryGate_IP'                 => 'includes/helpers/class-sentrygate-ip.php',
		'SentryGate_Symbol_Challenge'  => 'includes/helpers/class-sentrygate-symbol-challenge.php',
	);

	/**
	 * Register the autoloader.
	 */
	public static function register(): void {
		spl_autoload_register( array( __CLASS__, 'load' ) );
	}

	/**
	 * Load a class file.
	 *
	 * @param string $class Class name.
	 */
	public static function load( string $class ): void {
		if ( ! isset( self::$map[ $class ] ) ) {
			return;
		}

		$file = SENTRYGATE_PLUGIN_DIR . self::$map[ $class ];

		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
}
