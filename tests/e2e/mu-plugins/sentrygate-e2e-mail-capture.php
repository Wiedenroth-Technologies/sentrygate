<?php
/**
 * E2E-only: fängt wp_mail ab (ähnlich WP Mail Logging / Mailpit).
 *
 * @package SentryGate
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Persist last mail for Playwright / wp-cli.
 *
 * @param array<string, mixed> $args wp_mail args.
 */
function sentrygate_e2e_store_mail( array $args ): void {
	$to = $args['to'] ?? '';
	if ( is_array( $to ) ) {
		$to = implode( ', ', array_map( 'strval', $to ) );
	}

	$message = isset( $args['message'] ) ? (string) $args['message'] : '';
	$payload = array(
		'to'          => (string) $to,
		'subject'     => isset( $args['subject'] ) ? (string) $args['subject'] : '',
		'message'     => $message,
		'captured_at' => gmdate( 'c' ),
	);

	update_option( 'sentrygate_e2e_last_mail', $payload, false );

	if ( preg_match( '/\b(\d{6})\b/', $message, $match ) ) {
		update_option( 'sentrygate_e2e_last_code', $match[1], false );
	}

	$dir = WP_CONTENT_DIR . '/uploads';
	if ( ! is_dir( $dir ) ) {
		wp_mkdir_p( $dir );
	}
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- E2E helper only.
	file_put_contents( $dir . '/sentrygate-e2e-last-mail.json', wp_json_encode( $payload ) );
}

/**
 * @param array<string, mixed> $args wp_mail args.
 * @return array<string, mixed>
 */
function sentrygate_e2e_capture_mail( array $args ): array {
	sentrygate_e2e_store_mail( $args );
	return $args;
}
add_filter( 'wp_mail', 'sentrygate_e2e_capture_mail', 1 );

/**
 * Short-circuit delivery in Docker (no real MTA) after capture via wp_mail.
 *
 * @param null|bool $return Short-circuit.
 * @param array     $atts   Mail attributes.
 * @return bool
 */
function sentrygate_e2e_force_mail_success( $return, $atts ) {
	if ( null !== $return ) {
		return (bool) $return;
	}
	if ( is_array( $atts ) ) {
		sentrygate_e2e_store_mail( $atts );
	}
	return true;
}
add_filter( 'pre_wp_mail', 'sentrygate_e2e_force_mail_success', 10, 2 );
