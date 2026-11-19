<?php
/**
 * E2E-only: fängt wp_mail ab (ähnlich WP Mail Logging / Mailpit), speichert die letzte Mail.
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
	$payload = array(
		'to'          => $args['to'] ?? '',
		'subject'     => isset( $args['subject'] ) ? (string) $args['subject'] : '',
		'message'     => isset( $args['message'] ) ? (string) $args['message'] : '',
		'headers'     => $args['headers'] ?? array(),
		'captured_at' => gmdate( 'c' ),
	);

	update_option( 'sentrygate_e2e_last_mail', $payload, false );

	return $args;
}
add_filter( 'wp_mail', 'sentrygate_e2e_capture_mail', 9999 );
