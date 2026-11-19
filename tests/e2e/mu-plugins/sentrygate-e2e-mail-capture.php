<?php
/**
 * E2E-only: fängt E-Mail-OTP und wp_mail ab (ähnlich Mailpit / WP Mail Logging).
 *
 * @package SentryGate
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Persist OTP / mail payload for Playwright / wp-cli.
 *
 * @param string               $code    Optional OTP.
 * @param array<string, mixed> $mail    Optional wp_mail args.
 */
function sentrygate_e2e_store( string $code = '', array $mail = array() ): void {
	if ( '' !== $code ) {
		update_option( 'sentrygate_e2e_last_code', $code, false );
	}

	if ( $mail ) {
		$to = $mail['to'] ?? '';
		if ( is_array( $to ) ) {
			$to = implode( ', ', array_map( 'strval', $to ) );
		}
		$message = isset( $mail['message'] ) ? (string) $mail['message'] : '';
		$payload = array(
			'to'          => (string) $to,
			'subject'     => isset( $mail['subject'] ) ? (string) $mail['subject'] : '',
			'message'     => $message,
			'captured_at' => gmdate( 'c' ),
		);
		update_option( 'sentrygate_e2e_last_mail', $payload, false );

		if ( '' === $code && preg_match( '/\b(\d{6})\b/', $message, $match ) ) {
			update_option( 'sentrygate_e2e_last_code', $match[1], false );
		}
	}
}

/**
 * Primary capture: plaintext OTP from SentryGate (independent of MTA).
 *
 * @param string  $code Code.
 * @param WP_User $user User.
 */
function sentrygate_e2e_on_otp( string $code, $user ): void {
	unset( $user );
	sentrygate_e2e_store( $code );
}
add_action( 'sentrygate_email_otp_generated', 'sentrygate_e2e_on_otp', 10, 2 );

/**
 * @param array<string, mixed> $args wp_mail args.
 * @return array<string, mixed>
 */
function sentrygate_e2e_capture_mail( array $args ): array {
	sentrygate_e2e_store( '', $args );
	return $args;
}
add_filter( 'wp_mail', 'sentrygate_e2e_capture_mail', 1 );

/**
 * @param null|bool $return Short-circuit.
 * @param array     $atts   Mail attributes.
 * @return bool
 */
function sentrygate_e2e_force_mail_success( $return, $atts ) {
	if ( null !== $return ) {
		return (bool) $return;
	}
	if ( is_array( $atts ) ) {
		sentrygate_e2e_store( '', $atts );
	}
	return true;
}
add_filter( 'pre_wp_mail', 'sentrygate_e2e_force_mail_success', 10, 2 );
