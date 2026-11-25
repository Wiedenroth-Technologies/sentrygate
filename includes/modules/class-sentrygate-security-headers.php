<?php
/**
 * Security headers module.
 *
 * @package SentryGate
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SentryGate_Security_Headers
 */
class SentryGate_Security_Headers {

	/**
	 * Register hooks.
	 *
	 * @param SentryGate_Loader $loader Loader.
	 */
	public function register( SentryGate_Loader $loader ): void {
		if ( ! SentryGate_Options::module_enabled( 'security_headers' ) ) {
			return;
		}

		$loader->add_action( 'send_headers', $this, 'send_headers' );
		$loader->add_action( 'login_init', $this, 'send_headers' );
		$loader->add_action( 'admin_init', $this, 'send_headers' );
	}

	/**
	 * Emit configured security headers.
	 */
	public function send_headers(): void {
		if ( headers_sent() ) {
			return;
		}

		if ( SentryGate_Options::get( 'header_x_frame_options' ) ) {
			$value = (string) SentryGate_Options::get( 'header_x_frame_value', 'SAMEORIGIN' );
			header( 'X-Frame-Options: ' . $value );
		}

		if ( SentryGate_Options::get( 'header_x_content_type' ) ) {
			header( 'X-Content-Type-Options: nosniff' );
		}

		if ( SentryGate_Options::get( 'header_referrer_policy' ) ) {
			$value = (string) SentryGate_Options::get( 'header_referrer_value', 'strict-origin-when-cross-origin' );
			header( 'Referrer-Policy: ' . $value );
		}

		if ( SentryGate_Options::get( 'header_hsts' ) && is_ssl() ) {
			$max = (int) SentryGate_Options::get( 'header_hsts_max_age', 31536000 );
			header( 'Strict-Transport-Security: max-age=' . $max . '; includeSubDomains' );
		}

		if ( SentryGate_Options::get( 'header_csp' ) ) {
			$policy = (string) SentryGate_Options::get( 'header_csp_policy', '' );
			$policy = trim( str_replace( array( "\r", "\n" ), ' ', $policy ) );
			$name   = SentryGate_Options::get( 'header_csp_report_only' )
				? 'Content-Security-Policy-Report-Only'
				: 'Content-Security-Policy';
			if ( $policy ) {
				header( $name . ': ' . $policy );
			}
		}

		header( 'Permissions-Policy: geolocation=(), microphone=(), camera=()' );
		header( 'X-Permitted-Cross-Domain-Policies: none' );
	}

	/**
	 * Module status.
	 *
	 * @return array{status: string, label: string, message: string}
	 */
	public static function status(): array {
		if ( ! SentryGate_Options::module_enabled( 'security_headers' ) ) {
			return array(
				'status'  => 'inactive',
				'label'   => __( 'Browser protection', 'wiedenroth-technologies-sentrygate' ),
				'message' => __( 'Module disabled.', 'wiedenroth-technologies-sentrygate' ),
			);
		}

		$csp_ro = (bool) SentryGate_Options::get( 'header_csp_report_only' );
		return array(
			'status'  => 'active',
			'label'   => __( 'Browser protection', 'wiedenroth-technologies-sentrygate' ),
			'message' => $csp_ro
				? __( 'Headers active (CSP in report-only mode).', 'wiedenroth-technologies-sentrygate' )
				: __( 'Headers active (CSP enforcing).', 'wiedenroth-technologies-sentrygate' ),
		);
	}
}
