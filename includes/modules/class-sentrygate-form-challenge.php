<?php
/**
 * Symbol challenge for public forms (BlockForge and other plugins).
 *
 * @package SentryGate
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SentryGate_Form_Challenge
 */
class SentryGate_Form_Challenge {

	/**
	 * Register hooks when the module is enabled.
	 *
	 * @param SentryGate_Loader $loader Loader.
	 */
	public function register( SentryGate_Loader $loader ): void {
		if ( ! self::is_enabled() ) {
			return;
		}

		$loader->add_action( 'wp_ajax_nopriv_sentrygate_form_challenge', $this, 'ajax_new' );
		$loader->add_action( 'wp_ajax_sentrygate_form_challenge', $this, 'ajax_new' );
	}

	/**
	 * Whether public forms must pass the symbol check.
	 */
	public static function is_enabled(): bool {
		if ( ! SentryGate_Options::module_enabled( 'form_challenge' ) ) {
			return false;
		}

		return (bool) apply_filters( 'sentrygate_form_challenge_enabled', true );
	}

	/**
	 * Frontend CSS + refresh script.
	 */
	public static function enqueue(): void {
		if ( ! self::is_enabled() ) {
			return;
		}

		$css_login = SENTRYGATE_PLUGIN_DIR . 'assets/css/login-challenge.css';
		$css_form  = SENTRYGATE_PLUGIN_DIR . 'assets/css/form-challenge.css';
		$js        = SENTRYGATE_PLUGIN_DIR . 'assets/js/form-challenge.js';

		wp_enqueue_style(
			'sentrygate-login-challenge',
			SENTRYGATE_PLUGIN_URL . 'assets/css/login-challenge.css',
			array(),
			is_readable( $css_login ) ? (string) filemtime( $css_login ) : SENTRYGATE_VERSION
		);

		wp_enqueue_style(
			'sentrygate-form-challenge',
			SENTRYGATE_PLUGIN_URL . 'assets/css/form-challenge.css',
			array( 'sentrygate-login-challenge' ),
			is_readable( $css_form ) ? (string) filemtime( $css_form ) : SENTRYGATE_VERSION
		);

		wp_enqueue_script(
			'sentrygate-form-challenge',
			SENTRYGATE_PLUGIN_URL . 'assets/js/form-challenge.js',
			array(),
			is_readable( $js ) ? (string) filemtime( $js ) : SENTRYGATE_VERSION,
			true
		);

		wp_localize_script(
			'sentrygate-form-challenge',
			'SentryGateFormChallenge',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'sentrygate_form_challenge' ),
			)
		);
	}

	/**
	 * Widget HTML for embedding inside an existing form (no nested form).
	 */
	public static function widget(): string {
		if ( ! self::is_enabled() ) {
			return '';
		}

		self::enqueue();

		$ip = SentryGate_IP::get();
		if ( SentryGate_Rate_Limit::is_locked( $ip ) ) {
			$secs = SentryGate_Rate_Limit::remaining_seconds( $ip );
			return '<p class="sentrygate-form-challenge__locked">' . esc_html(
				sprintf(
					/* translators: %d: seconds */
					__( 'Zu viele Fehlversuche. Bitte in %d Sekunden erneut versuchen.', 'wiedenroth-technologies-sentrygate' ),
					$secs
				)
			) . '</p>';
		}

		$uid   = wp_unique_id( 'sentrygate-form-' );
		$token = SentryGate_Symbol_Challenge::create();
		$data  = SentryGate_Symbol_Challenge::get( $token );
		$html  = SentryGate_Symbol_Challenge::markup(
			is_array( $data ) ? $data : array(),
			array(
				'uid'           => $uid,
				'refresh_class' => 'sentrygate-refresh',
			)
		);

		return '<div class="sentrygate-form-challenge" data-sentrygate-form-challenge>'
			. '<input type="hidden" name="sentrygate_challenge_token" value="' . esc_attr( $token ) . '" autocomplete="off" />'
			. '<div data-sentrygate-choice-wrap>' . $html . '</div>'
			. '</div>';
	}

	/**
	 * Verify a submitted token + choice. Records failures against the shared rate limit.
	 *
	 * @return true|WP_Error
	 */
	public static function verify( string $token, string $choice ): true|WP_Error {
		if ( ! self::is_enabled() ) {
			return true;
		}

		$ip = SentryGate_IP::get();
		if ( SentryGate_Rate_Limit::is_locked( $ip ) ) {
			return new WP_Error(
				'sentrygate_form_locked',
				__( 'Zu viele Fehlversuche. Bitte später erneut versuchen.', 'wiedenroth-technologies-sentrygate' ),
				array( 'status' => 429 )
			);
		}

		$result = SentryGate_Symbol_Challenge::verify( $token, $choice );
		if ( is_wp_error( $result ) ) {
			SentryGate_Rate_Limit::record_failure( $ip, '' );
			return new WP_Error(
				$result->get_error_code(),
				$result->get_error_message(),
				array( 'status' => 400 )
			);
		}

		return true;
	}

	/**
	 * AJAX: mint a fresh challenge for a form widget.
	 */
	public function ajax_new(): void {
		check_ajax_referer( 'sentrygate_form_challenge', 'nonce' );

		if ( ! self::is_enabled() ) {
			wp_send_json_error( array( 'message' => __( 'Sicherheitsprüfung ist ausgeschaltet.', 'wiedenroth-technologies-sentrygate' ) ) );
		}

		$ip = SentryGate_IP::get();
		if ( SentryGate_Rate_Limit::is_locked( $ip ) ) {
			wp_send_json_error(
				array(
					'locked'    => true,
					'remaining' => SentryGate_Rate_Limit::remaining_seconds( $ip ),
					'message'   => __( 'Zu viele Fehlversuche. Bitte später erneut versuchen.', 'wiedenroth-technologies-sentrygate' ),
				)
			);
		}

		$uid   = wp_unique_id( 'sentrygate-form-' );
		$token = SentryGate_Symbol_Challenge::create();
		$data  = SentryGate_Symbol_Challenge::get( $token );

		wp_send_json_success(
			array(
				'token' => $token,
				'html'  => SentryGate_Symbol_Challenge::markup(
					is_array( $data ) ? $data : array(),
					array(
						'uid'           => $uid,
						'refresh_class' => 'sentrygate-refresh',
					)
				),
			)
		);
	}

	/**
	 * Module status.
	 *
	 * @return array{status: string, label: string, message: string}
	 */
	public static function status(): array {
		if ( ! SentryGate_Options::module_enabled( 'form_challenge' ) ) {
			return array(
				'status'  => 'inactive',
				'label'   => __( 'Formular-Schutz', 'wiedenroth-technologies-sentrygate' ),
				'message' => __( 'Ausgeschaltet – Formulare ohne Symbolwahl.', 'wiedenroth-technologies-sentrygate' ),
			);
		}

		return array(
			'status'  => 'active',
			'label'   => __( 'Formular-Schutz', 'wiedenroth-technologies-sentrygate' ),
			'message' => __( 'Symbolwahl vor dem Absenden (BlockForge und angebundene Formulare).', 'wiedenroth-technologies-sentrygate' ),
		);
	}
}
