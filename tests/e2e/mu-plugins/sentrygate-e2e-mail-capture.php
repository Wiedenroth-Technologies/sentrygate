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
 * Store the most recent wp_mail payload for Playwright/wp-cli retrieval.
 *
 * @param array<string, mixed> $args wp_mail args.
 * @return array<string, mixed>
 */
function sentrygate_e2e_capture_mail( array $args ): array {
	$to = $args['to'] ?? '';
	if ( is_array( $to ) ) {
		$to = implode( ', ', $to );
	}

	$payload = array(
		'to'          => (string) $to,
		'subject'     => isset( $args['subject'] ) ? (string) $args['subject'] : '',
		'message'     => isset( $args['message'] ) ? (string) $args['message'] : '',
		'headers'     => $args['headers'] ?? array(),
		'captured_at' => gmdate( 'c' ),
	);

	update_option( 'sentrygate_e2e_last_mail', $payload, false );

	return $args;
}
add_filter( 'wp_mail', 'sentrygate_e2e_capture_mail', 9999 );

/**
 * Ensure mail is treated as sent in local/wp-env (no real SMTP).
 *
 * @param bool|null $return Short-circuit value.
 * @param array     $atts   Mail attributes.
 * @return bool|null
 */
function sentrygate_e2e_force_mail_success( $return, $atts ) {
	if ( null !== $return ) {
		return $return;
	}
	if ( is_array( $atts ) ) {
		sentrygate_e2e_capture_mail( $atts );
	}
	return true;
}
add_filter( 'pre_wp_mail', 'sentrygate_e2e_force_mail_success', 10, 2 );
