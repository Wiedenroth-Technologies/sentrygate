<?php
/**
 * Core integrity check against official WordPress checksums.
 * No automatic repair in v1.
 *
 * @package SentryGate
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SentryGate_Core_Integrity
 */
class SentryGate_Core_Integrity {

	/**
	 * Register hooks.
	 *
	 * @param SentryGate_Loader $loader Loader.
	 */
	public function register( SentryGate_Loader $loader ): void {
		if ( ! SentryGate_Options::module_enabled( 'core_integrity' ) ) {
			return;
		}

		$loader->add_action( 'wp_ajax_sentrygate_run_checksum', $this, 'ajax_run_checksum' );
	}

	/**
	 * Fetch checksums from api.wordpress.org and compare.
	 *
	 * @return array{ok: bool, checked: int, mismatches: array<int, string>, missing: array<int, string>, error?: string}
	 */
	public static function run_check(): array {
		global $wp_version;

		$locale = get_locale();
		$url    = add_query_arg(
			array(
				'version' => $wp_version,
				'locale'  => $locale,
			),
			'https://api.wordpress.org/core/checksums/1.0/'
		);

		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 30,
				'headers' => array( 'Accept' => 'application/json' ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'ok'         => false,
				'checked'    => 0,
				'mismatches' => array(),
				'missing'    => array(),
				'error'      => $response->get_error_message(),
			);
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );

		if ( 200 !== $code || ! is_array( $body ) || empty( $body['checksums'] ) || ! is_array( $body['checksums'] ) ) {
			return array(
				'ok'         => false,
				'checked'    => 0,
				'mismatches' => array(),
				'missing'    => array(),
				'error'      => __( 'Checksum-API lieferte keine gültigen Daten.', 'wiedenroth-technologies-sentrygate' ),
			);
		}

		$mismatches = array();
		$missing    = array();
		$checked    = 0;

		foreach ( $body['checksums'] as $file => $checksum ) {
			// Skip wp-content – not part of immutable core package comparison in practice for themes/plugins.
			if ( str_starts_with( (string) $file, 'wp-content/' ) ) {
				continue;
			}

			$path = ABSPATH . ltrim( (string) $file, '/' );
			if ( ! is_readable( $path ) ) {
				$missing[] = (string) $file;
				continue;
			}

			$hash = md5_file( $path );
			++$checked;
			if ( ! hash_equals( (string) $checksum, (string) $hash ) ) {
				$mismatches[] = (string) $file;
			}
		}

		$result = array(
			'ok'         => empty( $mismatches ) && empty( $missing ),
			'checked'    => $checked,
			'mismatches' => $mismatches,
			'missing'    => $missing,
			'version'    => $wp_version,
			'time'       => gmdate( 'c' ),
		);

		update_option( 'sentrygate_core_checksum_cache', $result, false );

		return $result;
	}

	/**
	 * Cached last result.
	 *
	 * @return array<string, mixed>|null
	 */
	public static function last_result(): ?array {
		$cached = get_option( 'sentrygate_core_checksum_cache' );
		return is_array( $cached ) ? $cached : null;
	}

	/**
	 * Human-readable timestamp for a stored check.
	 *
	 * @param mixed $time ISO time or empty.
	 */
	public static function format_time_label( mixed $time ): string {
		if ( ! is_string( $time ) || '' === $time ) {
			return '';
		}

		$ts = strtotime( $time );
		if ( false === $ts ) {
			return $time;
		}

		return wp_date( 'd.m.Y, H:i', $ts );
	}

	/**
	 * AJAX handler.
	 */
	public function ajax_run_checksum(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Keine Berechtigung.', 'wiedenroth-technologies-sentrygate' ) ), 403 );
		}
		check_ajax_referer( 'sentrygate_admin', 'nonce' );

		$result                 = self::run_check();
		$result['time_label']   = self::format_time_label( $result['time'] ?? '' );

		wp_send_json_success( $result );
	}

	/**
	 * Module status.
	 *
	 * @return array{status: string, label: string, message: string}
	 */
	public static function status(): array {
		if ( ! SentryGate_Options::module_enabled( 'core_integrity' ) ) {
			return array(
				'status'  => 'inactive',
				'label'   => __( 'WordPress-Check', 'wiedenroth-technologies-sentrygate' ),
				'message' => __( 'Modul deaktiviert.', 'wiedenroth-technologies-sentrygate' ),
			);
		}

		$last = self::last_result();
		if ( ! $last ) {
			return array(
				'status'  => 'warning',
				'label'   => __( 'WordPress-Check', 'wiedenroth-technologies-sentrygate' ),
				'message' => __( 'Noch kein Check ausgeführt.', 'wiedenroth-technologies-sentrygate' ),
			);
		}

		if ( ! empty( $last['error'] ) ) {
			return array(
				'status'  => 'warning',
				'label'   => __( 'WordPress-Check', 'wiedenroth-technologies-sentrygate' ),
				'message' => (string) $last['error'],
			);
		}

		if ( empty( $last['ok'] ) ) {
			return array(
				'status'  => 'warning',
				'label'   => __( 'WordPress-Check', 'wiedenroth-technologies-sentrygate' ),
				'message' => sprintf(
					/* translators: 1: mismatches, 2: missing */
					__( '%1$d abweichende, %2$d fehlende Dateien (keine Auto-Reparatur in v1).', 'wiedenroth-technologies-sentrygate' ),
					count( (array) ( $last['mismatches'] ?? array() ) ),
					count( (array) ( $last['missing'] ?? array() ) )
				),
			);
		}

		return array(
			'status'  => 'active',
			'label'   => __( 'WordPress-Check', 'wiedenroth-technologies-sentrygate' ),
			'message' => sprintf(
				/* translators: %d: files checked */
				__( 'Core OK (%d Dateien geprüft).', 'wiedenroth-technologies-sentrygate' ),
				(int) ( $last['checked'] ?? 0 )
			),
		);
	}
}
