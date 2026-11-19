<?php
/**
 * File and server hardening – .htaccess snippets and permission checks (read-only).
 *
 * @package SentryGate
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SentryGate_File_Hardening
 */
class SentryGate_File_Hardening {

	/**
	 * Register hooks.
	 *
	 * @param SentryGate_Loader $loader Loader.
	 */
	public function register( SentryGate_Loader $loader ): void {
		if ( ! SentryGate_Options::module_enabled( 'file_hardening' ) ) {
			return;
		}
		// Admin UI only – no automatic chmod on foreign servers.
		unset( $loader );
	}

	/**
	 * Recommended .htaccess snippet for uploads.
	 */
	public static function htaccess_uploads_snippet(): string {
		return <<<'HTACCESS'
# SentryGate – PHP-Ausführung in Uploads blockieren
<FilesMatch "\.(?i:php|phtml|php3|php4|php5|php7|php8|phar)$">
  <IfModule mod_authz_core.c>
    Require all denied
  </IfModule>
  <IfModule !mod_authz_core.c>
    Order Deny,Allow
    Deny from all
  </IfModule>
</FilesMatch>

# Directory-Listing aus
Options -Indexes
HTACCESS;
	}

	/**
	 * Collect permission findings (display only).
	 *
	 * @return array<int, array{path: string, perms: string, ok: bool, note: string}>
	 */
	public static function permission_report(): array {
		$checks = array(
			array(
				'path' => ABSPATH,
				'max'  => 0755,
				'note' => __( 'WordPress-Root sollte nicht welt-schreibbar sein.', 'wiedenroth-technologies-sentrygate' ),
			),
			array(
				'path' => ABSPATH . 'wp-config.php',
				'max'  => 0644,
				'note' => __( 'wp-config.php idealerweise 0440/0400/0640.', 'wiedenroth-technologies-sentrygate' ),
			),
			array(
				'path' => WP_CONTENT_DIR,
				'max'  => 0755,
				'note' => __( 'wp-content sollte nicht 0777 sein.', 'wiedenroth-technologies-sentrygate' ),
			),
			array(
				'path' => WP_CONTENT_DIR . '/uploads',
				'max'  => 0755,
				'note' => __( 'Uploads-Verzeichnis prüfen.', 'wiedenroth-technologies-sentrygate' ),
			),
		);

		$report = array();
		foreach ( $checks as $check ) {
			if ( ! file_exists( $check['path'] ) ) {
				continue;
			}
			$perms = fileperms( $check['path'] ) & 0777;
			$ok    = $perms <= $check['max'] && ( $perms & 0002 ) === 0;
			// wp-config special: warn if world-readable write.
			if ( str_ends_with( $check['path'], 'wp-config.php' ) && ( $perms & 0006 ) ) {
				$ok = false;
			}
			$report[] = array(
				'path'  => $check['path'],
				'perms' => sprintf( '%04o', $perms ),
				'ok'    => $ok,
				'note'  => $check['note'],
			);
		}

		return $report;
	}

	/**
	 * Module status.
	 *
	 * @return array{status: string, label: string, message: string}
	 */
	public static function status(): array {
		if ( ! SentryGate_Options::module_enabled( 'file_hardening' ) ) {
			return array(
				'status'  => 'inactive',
				'label'   => __( 'Dateien & Server', 'wiedenroth-technologies-sentrygate' ),
				'message' => __( 'Modul deaktiviert.', 'wiedenroth-technologies-sentrygate' ),
			);
		}

		$report  = self::permission_report();
		$bad     = array_filter( $report, static fn( $r ) => ! $r['ok'] );

		if ( $bad ) {
			return array(
				'status'  => 'warning',
				'label'   => __( 'Dateien & Server', 'wiedenroth-technologies-sentrygate' ),
				'message' => __( 'Einige Dateirechte weichen von der Empfehlung ab (nur Anzeige).', 'wiedenroth-technologies-sentrygate' ),
			);
		}

		return array(
			'status'  => 'active',
			'label'   => __( 'Dateien & Server', 'wiedenroth-technologies-sentrygate' ),
			'message' => __( 'Permission-Check OK. .htaccess-Snippets im Backend verfügbar.', 'wiedenroth-technologies-sentrygate' ),
		);
	}
}
