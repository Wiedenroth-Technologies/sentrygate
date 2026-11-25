<?php
/**
 * E2E-only: fängt ausgehende Mail über Core-Hooks ab (wie Mailpit / WP Mail Logging).
 *
 * Kein Eingriff in SentryGate-Produktivcode – nur pre_wp_mail / phpmailer_init / wp_mail.
 *
 * @package SentryGate
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Persist mail payload + extracted 6-digit OTP for Playwright.
 *
 * @param array<string, mixed> $atts wp_mail attributes.
 */
function sentrygate_e2e_persist_mail_atts( array $atts ): void {
	$to = $atts['to'] ?? '';
	if ( is_array( $to ) ) {
		$to = implode( ', ', array_map( 'strval', $to ) );
	}

	$message = isset( $atts['message'] ) ? (string) $atts['message'] : '';
	$payload = array(
		'to'          => (string) $to,
		'subject'     => isset( $atts['subject'] ) ? (string) $atts['subject'] : '',
		'message'     => $message,
		'captured_at' => gmdate( 'c' ),
	);

	update_option( 'sentrygate_e2e_last_mail', $payload, false );

	$code = '';
	if ( preg_match( '/(\d{6})/', $message, $match ) ) {
		$code = $match[1];
		update_option( 'sentrygate_e2e_last_code', $code, false );
	}

	// Also write a file (fallback if option reads are delayed in CLI).
	$upload = wp_upload_dir( null, false );
	if ( empty( $upload['error'] ) && ! empty( $upload['basedir'] ) ) {
		$dir = trailingslashit( (string) $upload['basedir'] );
		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
		}
		$file_payload = $payload;
		$file_payload['code'] = $code;
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- E2E-only MU-Plugin.
		@file_put_contents( $dir . 'sentrygate-e2e-last-mail.json', wp_json_encode( $file_payload ) );
	}
}

/**
 * @param array<string, mixed> $args wp_mail args.
 * @return array<string, mixed>
 */
function sentrygate_e2e_capture_mail( array $args ): array {
	sentrygate_e2e_persist_mail_atts( $args );
	return $args;
}
add_filter( 'wp_mail', 'sentrygate_e2e_capture_mail', 1 );

/**
 * Short-circuit delivery in Docker (kein MTA) – Capture aus $atts, dann Erfolg melden.
 *
 * @param null|bool            $return Short-circuit value.
 * @param array<string, mixed> $atts   Mail attributes (nach wp_mail-Filter).
 * @return bool
 */
function sentrygate_e2e_pre_wp_mail( $return, $atts ) {
	if ( null !== $return ) {
		return (bool) $return;
	}
	if ( is_array( $atts ) ) {
		sentrygate_e2e_persist_mail_atts( $atts );
	}
	return true;
}
add_filter( 'pre_wp_mail', 'sentrygate_e2e_pre_wp_mail', 10, 2 );

/**
 * Fallback, falls pre_wp_mail nicht greift und PHPMailer doch gebaut wird.
 *
 * @param object $phpmailer PHPMailer instance.
 */
function sentrygate_e2e_phpmailer_init( $phpmailer ): void {
	if ( ! is_object( $phpmailer ) || ! isset( $phpmailer->Body ) ) {
		return;
	}
	sentrygate_e2e_persist_mail_atts(
		array(
			'to'      => '',
			'subject' => isset( $phpmailer->Subject ) ? (string) $phpmailer->Subject : '',
			'message' => (string) $phpmailer->Body,
		)
	);
}
add_action( 'phpmailer_init', 'sentrygate_e2e_phpmailer_init' );
