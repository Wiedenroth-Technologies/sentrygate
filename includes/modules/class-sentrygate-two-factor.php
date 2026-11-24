<?php
/**
 * Two-factor authentication: authenticator app or email, recovery codes, trusted devices.
 *
 * @package SentryGate
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SentryGate_Two_Factor
 */
class SentryGate_Two_Factor {

	public const PAGE_SLUG = 'sentrygate-tfa';

	private const META_SECRET      = 'sentrygate_tfa_secret';
	private const META_ENABLED     = 'sentrygate_tfa_enabled';
	private const META_METHOD      = 'sentrygate_tfa_method';
	private const META_PENDING     = 'sentrygate_tfa_pending_method';
	private const META_RECOVERY    = 'sentrygate_tfa_recovery';
	private const META_PLAIN       = 'sentrygate_tfa_recovery_plain_once';
	private const META_EMAIL_CODES = 'sentrygate_tfa_email_code';
	private const META_FORCE_SETUP = 'sentrygate_tfa_force_setup';
	private const COOKIE_TRUSTED   = 'sentrygate_trusted';
	private const TRANSIENT_PENDING = 'sentrygate_tfa_pending_';
	private const TRANSIENT_EMAIL   = 'sentrygate_email_code_';

	/**
	 * Register hooks.
	 *
	 * @param SentryGate_Loader $loader Loader.
	 */
	public function register( SentryGate_Loader $loader ): void {
		if ( ! SentryGate_Options::module_enabled( 'two_factor' ) ) {
			return;
		}

		$loader->add_action( 'admin_menu', $this, 'add_setup_page' );
		$loader->add_action( 'admin_init', $this, 'handle_setup_post' );
		$loader->add_filter( 'authenticate', $this, 'intercept_login', 30, 3 );
		$loader->add_action( 'login_form_sentrygate_tfa', $this, 'handle_tfa_submit', 5 );
		$loader->add_action( 'login_form_sentrygate_tfa', $this, 'render_tfa_form', 10 );
		$loader->add_action( 'admin_init', $this, 'enforce_setup_redirect', 20 );
		$loader->add_action( 'admin_notices', $this, 'setup_admin_notice' );
		$loader->add_action( 'wp_ajax_nopriv_sentrygate_verify_tfa', $this, 'ajax_verify' );
		$loader->add_action( 'wp_ajax_sentrygate_verify_tfa', $this, 'ajax_verify' );
		$loader->add_action( 'wp_ajax_nopriv_sentrygate_tfa_email', $this, 'ajax_resend_email' );
		$loader->add_action( 'wp_ajax_sentrygate_tfa_email', $this, 'ajax_resend_email' );
	}

	/**
	 * Whether an authenticate error is the pending 2FA step (not a failed login).
	 *
	 * @param mixed $error Error from wp_authenticate.
	 */
	public static function is_pending_error( mixed $error ): bool {
		return $error instanceof WP_Error && 'sentrygate_tfa' === $error->get_error_code();
	}

	/**
	 * Payload for a pending 2FA session (frontend login UIs / deep links).
	 *
	 * @param string $token Pending token from the authenticate step.
	 * @return array{token: string, method: string, nonce: string, email_sent: bool, trust_days: int, message: string}|null
	 */
	public static function frontend_pending_state( string $token ): ?array {
		$token = sanitize_text_field( $token );
		if ( '' === $token || ! SentryGate_Options::module_enabled( 'two_factor' ) ) {
			return null;
		}

		$data = get_transient( self::TRANSIENT_PENDING . $token );
		if ( ! is_array( $data ) ) {
			return null;
		}

		$user = get_user_by( 'id', (int) ( $data['user_id'] ?? 0 ) );
		if ( ! $user ) {
			return null;
		}

		$instance = new self();
		$method   = $instance->method_for_user( $user->ID );
		if ( '' === $method ) {
			return null;
		}

		$email_sent = false;
		if ( 'email' === $method && get_transient( self::TRANSIENT_EMAIL . $user->ID ) ) {
			$email_sent = true;
		}

		return array(
			'token'      => $token,
			'method'     => $method,
			'nonce'      => wp_create_nonce( 'sentrygate_tfa_verify' ),
			'email_sent' => $email_sent,
			'trust_days' => (int) SentryGate_Options::get( 'tfa_trusted_days', 30 ),
			'message'    => 'email' === $method
				? __( 'Bitte den Code aus der E-Mail eingeben.', 'wiedenroth-technologies-sentrygate' )
				: __( 'Bitte den Code aus der Authenticator-App eingeben.', 'wiedenroth-technologies-sentrygate' ),
		);
	}

	/**
	 * Dedicated setup page under Users / Profile.
	 */
	public function add_setup_page(): void {
		add_users_page(
			__( 'Zwei-Faktor', 'wiedenroth-technologies-sentrygate' ),
			__( 'Zwei-Faktor', 'wiedenroth-technologies-sentrygate' ),
			'read',
			self::PAGE_SLUG,
			array( $this, 'render_setup_page' )
		);
	}

	/**
	 * Admin URL for the setup screen.
	 *
	 * @param int $user_id Optional other user.
	 */
	public static function setup_url( int $user_id = 0 ): string {
		$url     = admin_url( 'users.php?page=' . self::PAGE_SLUG );
		$current = get_current_user_id();
		if ( $user_id > 0 && $user_id !== $current ) {
			$url = add_query_arg( 'user_id', $user_id, $url );
		}

		return $url;
	}

	/**
	 * Handle setup form POST (PRG).
	 */
	public function handle_setup_post(): void {
		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_text_field( wp_unslash( (string) $_SERVER['REQUEST_METHOD'] ) ) ) : '';
		if ( 'POST' !== $method ) {
			return;
		}

		$page = isset( $_POST['page'] ) ? sanitize_key( (string) wp_unslash( $_POST['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( self::PAGE_SLUG !== $page ) {
			return;
		}

		if ( ! isset( $_POST['sentrygate_tfa_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( (string) $_POST['sentrygate_tfa_nonce'] ) ), 'sentrygate_tfa_setup' ) ) {
			wp_die( esc_html__( 'Sicherheitsprüfung fehlgeschlagen.', 'wiedenroth-technologies-sentrygate' ) );
		}

		$user = $this->setup_user_from_request( true );
		if ( ! $user ) {
			wp_die( esc_html__( 'Keine Berechtigung.', 'wiedenroth-technologies-sentrygate' ) );
		}

		$action = isset( $_POST['sentrygate_tfa_action'] ) ? sanitize_key( (string) wp_unslash( $_POST['sentrygate_tfa_action'] ) ) : '';
		$back   = self::setup_url( $user->ID );

		switch ( $action ) {
			case 'choose_app':
				update_user_meta( $user->ID, self::META_PENDING, 'app' );
				$this->ensure_secret( $user->ID );
				wp_safe_redirect( $back );
				exit;

			case 'choose_email':
				if ( ! $this->email_method_allowed() ) {
					wp_safe_redirect( add_query_arg( 'tfa_err', 'no_email', $back ) );
					exit;
				}
				if ( '' === $user->user_email || ! is_email( $user->user_email ) ) {
					wp_safe_redirect( add_query_arg( 'tfa_err', 'no_email', $back ) );
					exit;
				}
				update_user_meta( $user->ID, self::META_PENDING, 'email' );
				$this->send_email_code( $user );
				wp_safe_redirect( add_query_arg( 'setup', 'email', $back ) );
				exit;

			case 'send_setup_email':
				if ( 'email' !== $this->pending_method( $user->ID ) && 'email' !== $this->method_for_user( $user->ID ) ) {
					wp_safe_redirect( $back );
					exit;
				}
				$this->send_email_code( $user );
				wp_safe_redirect( add_query_arg( 'setup', 'email', $back ) );
				exit;

			case 'confirm_app':
				$confirm = isset( $_POST['sentrygate_tfa_confirm'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['sentrygate_tfa_confirm'] ) ) : '';
				$secret  = (string) get_user_meta( $user->ID, self::META_SECRET, true );
				if ( '' === $confirm || ! $secret || ! SentryGate_TOTP::verify( $secret, $confirm ) ) {
					wp_safe_redirect( add_query_arg( 'tfa_err', 'bad_code', $back ) );
					exit;
				}
				$this->activate_method( $user->ID, 'app' );
				wp_safe_redirect( add_query_arg( 'setup', 'ok', $back ) );
				exit;

			case 'confirm_email':
				$confirm = isset( $_POST['sentrygate_tfa_confirm'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['sentrygate_tfa_confirm'] ) ) : '';
				if ( ! $this->verify_email_code( $user, $confirm ) ) {
					wp_safe_redirect( add_query_arg( 'tfa_err', 'bad_code', $back ) );
					exit;
				}
				$this->activate_method( $user->ID, 'email' );
				wp_safe_redirect( add_query_arg( 'setup', 'ok', $back ) );
				exit;

			case 'cancel_setup':
				delete_user_meta( $user->ID, self::META_PENDING );
				wp_safe_redirect( add_query_arg( 'setup', 'cancelled', $back ) );
				exit;

			case 'disable':
				update_user_meta( $user->ID, self::META_ENABLED, 0 );
				delete_user_meta( $user->ID, self::META_METHOD );
				delete_user_meta( $user->ID, self::META_PENDING );
				delete_user_meta( $user->ID, self::META_RECOVERY );
				delete_user_meta( $user->ID, self::META_PLAIN );
				wp_safe_redirect( add_query_arg( 'setup', 'disabled', $back ) );
				exit;

			case 'regen_recovery':
				if ( ! (bool) get_user_meta( $user->ID, self::META_ENABLED, true ) ) {
					wp_safe_redirect( $back );
					exit;
				}
				$this->maybe_generate_recovery( $user->ID, true );
				wp_safe_redirect( add_query_arg( 'setup', 'codes', $back ) );
				exit;
		}

		wp_safe_redirect( $back );
		exit;
	}

	/**
	 * Render the dedicated setup screen.
	 */
	public function render_setup_page(): void {
		$user = $this->setup_user_from_request( false );
		if ( ! $user ) {
			wp_die( esc_html__( 'Keine Berechtigung.', 'wiedenroth-technologies-sentrygate' ) );
		}

		$ui            = SentryGate::instance()->ui();
		$enabled       = (bool) get_user_meta( $user->ID, self::META_ENABLED, true );
		$method        = $this->method_for_user( $user->ID );
		$pending       = $this->pending_method( $user->ID );
		$email_allowed = $this->email_method_allowed();
		$recovery_once = get_user_meta( $user->ID, self::META_PLAIN, true );
		$secret        = '';
		$uri           = '';
		$qr_svg        = '';

		if ( 'app' === $pending ) {
			$secret = $this->ensure_secret( $user->ID );
			$uri    = SentryGate_TOTP::provisioning_uri( $secret, $user->user_email, wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) );
			$qr_svg = SentryGate_QR::svg( $uri, 4 );
		}

		if ( is_array( $recovery_once ) && $recovery_once ) {
			delete_user_meta( $user->ID, self::META_PLAIN );
		} else {
			$recovery_once = array();
		}

		$notice = isset( $_GET['setup'] ) ? sanitize_key( (string) wp_unslash( $_GET['setup'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$error  = isset( $_GET['tfa_err'] ) ? sanitize_key( (string) wp_unslash( $_GET['tfa_err'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		include SENTRYGATE_PLUGIN_DIR . 'includes/admin/views/tfa-setup.php';
	}

	/**
	 * User being set up (self or editable other).
	 *
	 * @param bool $from_post Read user_id from POST.
	 */
	private function setup_user_from_request( bool $from_post ): ?WP_User {
		$source = $from_post ? $_POST : $_GET; // phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.NonceVerification.Missing
		$id     = get_current_user_id();

		if ( ! empty( $source['user_id'] ) ) {
			$requested = absint( wp_unslash( $source['user_id'] ) );
			if ( $requested > 0 && current_user_can( 'edit_user', $requested ) ) {
				$id = $requested;
			} elseif ( $requested !== get_current_user_id() ) {
				return null;
			}
		}

		if ( $id !== get_current_user_id() && ! current_user_can( 'edit_user', $id ) ) {
			return null;
		}

		$user = get_user_by( 'id', $id );
		return $user instanceof WP_User ? $user : null;
	}

	/**
	 * @param int $user_id User ID.
	 */
	private function ensure_secret( int $user_id ): string {
		$secret = (string) get_user_meta( $user_id, self::META_SECRET, true );
		if ( '' === $secret ) {
			$secret = SentryGate_TOTP::generate_secret();
			update_user_meta( $user_id, self::META_SECRET, $secret );
		}

		return $secret;
	}

	/**
	 * @param int    $user_id User ID.
	 * @param string $method  app|email.
	 */
	private function activate_method( int $user_id, string $method ): void {
		update_user_meta( $user_id, self::META_ENABLED, 1 );
		update_user_meta( $user_id, self::META_METHOD, $method );
		delete_user_meta( $user_id, self::META_PENDING );
		delete_user_meta( $user_id, self::META_FORCE_SETUP );
		if ( 'email' === $method ) {
			delete_user_meta( $user_id, self::META_SECRET );
		}
		$this->maybe_generate_recovery( $user_id, false );
	}

	/**
	 * @param int $user_id User ID.
	 */
	private function pending_method( int $user_id ): string {
		$pending = (string) get_user_meta( $user_id, self::META_PENDING, true );
		return in_array( $pending, array( 'app', 'email' ), true ) ? $pending : '';
	}

	/**
	 * Active method, including legacy TOTP-only setups.
	 *
	 * @param int $user_id User ID.
	 */
	private function method_for_user( int $user_id ): string {
		$method = (string) get_user_meta( $user_id, self::META_METHOD, true );
		if ( in_array( $method, array( 'app', 'email' ), true ) ) {
			return $method;
		}

		if ( (bool) get_user_meta( $user_id, self::META_ENABLED, true ) && (string) get_user_meta( $user_id, self::META_SECRET, true ) ) {
			return 'app';
		}

		return '';
	}

	private function email_method_allowed(): bool {
		return (bool) SentryGate_Options::get( 'tfa_email_fallback', true );
	}

	/**
	 * Generate and store hashed recovery codes; expose plaintext once.
	 *
	 * @param int  $user_id User ID.
	 * @param bool $force   Force regenerate.
	 */
	private function maybe_generate_recovery( int $user_id, bool $force ): void {
		$existing = get_user_meta( $user_id, self::META_RECOVERY, true );
		if ( ! $force && is_array( $existing ) && $existing ) {
			return;
		}

		$count  = (int) SentryGate_Options::get( 'tfa_recovery_count', 10 );
		$plain  = SentryGate_TOTP::generate_recovery_codes( $count );
		$hashed = array();
		foreach ( $plain as $code ) {
			$hashed[] = SentryGate_TOTP::hash_recovery_code( $code );
		}
		update_user_meta( $user_id, self::META_RECOVERY, $hashed );
		update_user_meta( $user_id, self::META_PLAIN, $plain );
	}

	/**
	 * After password auth, require 2FA step when enabled/required.
	 *
	 * @param WP_User|WP_Error|null $user     User.
	 * @param string                $username Username.
	 * @param string                $password Password.
	 * @return WP_User|WP_Error|null
	 */
	public function intercept_login( $user, string $username, string $password ) {
		unset( $username, $password );

		if ( ! ( $user instanceof WP_User ) ) {
			return $user;
		}

		if ( $this->is_trusted_device( $user->ID ) ) {
			return $user;
		}

		$required = $this->is_required_for_user( $user );
		$enabled  = (bool) get_user_meta( $user->ID, self::META_ENABLED, true );

		if ( ! $enabled && ! $required ) {
			return $user;
		}

		if ( ! $enabled && $required ) {
			update_user_meta( $user->ID, self::META_FORCE_SETUP, 1 );
			return $user;
		}

		$token = bin2hex( random_bytes( 16 ) );
		set_transient(
			self::TRANSIENT_PENDING . $token,
			array(
				'user_id'  => $user->ID,
				// phpcs:ignore WordPress.Security.NonceVerification.Missing -- runs during authenticate after core login POST.
				'remember' => isset( $_POST['rememberme'] ) && '' !== sanitize_text_field( wp_unslash( (string) $_POST['rememberme'] ) ),
			),
			10 * MINUTE_IN_SECONDS
		);

		$method     = $this->method_for_user( $user->ID );
		$email_sent = false;
		if ( 'email' === $method && ! get_transient( self::TRANSIENT_EMAIL . $user->ID ) ) {
			$this->send_email_code( $user );
			$email_sent = true;
		}

		if ( wp_doing_ajax() ) {
			return new WP_Error(
				'sentrygate_tfa',
				'email' === $method
					? __( 'Bitte den Code aus der E-Mail eingeben.', 'wiedenroth-technologies-sentrygate' )
					: __( 'Bitte den Code aus der Authenticator-App eingeben.', 'wiedenroth-technologies-sentrygate' ),
				array(
					'token'      => $token,
					'method'     => $method,
					'email_sent' => $email_sent,
					'nonce'      => wp_create_nonce( 'sentrygate_tfa_verify' ),
					'trust_days' => (int) SentryGate_Options::get( 'tfa_trusted_days', 30 ),
				)
			);
		}

		$redirect = add_query_arg(
			array(
				'action' => 'sentrygate_tfa',
				'token'  => $token,
			),
			wp_login_url()
		);

		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * AJAX: verify 2FA without wp-login.php.
	 */
	public function ajax_verify(): void {
		check_ajax_referer( 'sentrygate_tfa_verify', 'nonce' );

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

		$token = $this->request_token();
		$data  = $token ? get_transient( self::TRANSIENT_PENDING . $token ) : false;
		if ( ! is_array( $data ) ) {
			wp_send_json_error( array( 'message' => __( 'Sitzung abgelaufen. Bitte erneut anmelden.', 'wiedenroth-technologies-sentrygate' ) ) );
		}

		$user = get_user_by( 'id', (int) $data['user_id'] );
		if ( ! $user ) {
			wp_send_json_error( array( 'message' => __( 'Sitzung abgelaufen. Bitte erneut anmelden.', 'wiedenroth-technologies-sentrygate' ) ) );
		}

		$code = isset( $_POST['code'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['code'] ) ) : '';
		if ( ! $this->verify_any_code( $user, $code ) ) {
			SentryGate_Rate_Limit::record_failure( $ip, $user->user_login );
			wp_send_json_error( array( 'message' => __( 'Code ungültig oder abgelaufen.', 'wiedenroth-technologies-sentrygate' ) ) );
		}

		delete_transient( self::TRANSIENT_PENDING . $token );

		if ( isset( $_POST['trust_device'] ) && '' !== sanitize_text_field( wp_unslash( (string) $_POST['trust_device'] ) ) ) {
			$this->set_trusted_device( $user->ID );
		}

		$remember = ! empty( $data['remember'] );
		wp_set_current_user( $user->ID );
		wp_set_auth_cookie( $user->ID, $remember );
		do_action( 'wp_login', $user->user_login, $user );

		$requested = $this->requested_redirect();
		$redirect  = apply_filters( 'login_redirect', $requested, $requested, $user );
		if ( ! is_string( $redirect ) || '' === $redirect ) {
			$redirect = $requested;
		}

		wp_send_json_success(
			array(
				'redirect' => $this->safe_redirect_url( $redirect ),
			)
		);
	}

	/**
	 * AJAX: resend email OTP for a pending TFA session.
	 */
	public function ajax_resend_email(): void {
		check_ajax_referer( 'sentrygate_tfa_verify', 'nonce' );

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

		$token = $this->request_token();
		$data  = $token ? get_transient( self::TRANSIENT_PENDING . $token ) : false;
		if ( ! is_array( $data ) ) {
			wp_send_json_error( array( 'message' => __( 'Sitzung abgelaufen. Bitte erneut anmelden.', 'wiedenroth-technologies-sentrygate' ) ) );
		}

		$user = get_user_by( 'id', (int) $data['user_id'] );
		if ( ! $user || 'email' !== $this->method_for_user( $user->ID ) ) {
			wp_send_json_error( array( 'message' => __( 'Code konnte nicht gesendet werden.', 'wiedenroth-technologies-sentrygate' ) ) );
		}

		if ( ! $this->send_email_code( $user ) ) {
			wp_send_json_error( array( 'message' => __( 'Bitte kurz warten, bevor Sie erneut einen Code anfordern.', 'wiedenroth-technologies-sentrygate' ) ) );
		}

		wp_send_json_success(
			array(
				'message' => __( 'Ein Code wurde per E-Mail gesendet (10 Minuten gültig).', 'wiedenroth-technologies-sentrygate' ),
			)
		);
	}

	/**
	 * Redirect target from the current request.
	 */
	private function requested_redirect(): string {
		$raw = isset( $_REQUEST['redirect_to'] ) ? esc_url_raw( wp_unslash( (string) $_REQUEST['redirect_to'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( '' === $raw ) {
			return home_url( '/' );
		}

		return $this->safe_redirect_url( $raw );
	}

	/**
	 * @param string $url Candidate URL.
	 */
	private function safe_redirect_url( string $url ): string {
		$safe = wp_validate_redirect( $url, false );
		return is_string( $safe ) && '' !== $safe ? $safe : home_url( '/' );
	}

	/**
	 * Current TFA session token from GET or POST.
	 */
	private function request_token(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.NonceVerification.Missing
		$raw = isset( $_REQUEST['token'] ) ? sanitize_text_field( wp_unslash( (string) $_REQUEST['token'] ) ) : '';
		return $raw;
	}

	/**
	 * Render TFA challenge form.
	 */
	public function render_tfa_form(): void {
		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_text_field( wp_unslash( (string) $_SERVER['REQUEST_METHOD'] ) ) ) : '';
		if ( 'POST' === $method ) {
			return;
		}

		$token = $this->request_token();
		$data  = $token ? get_transient( self::TRANSIENT_PENDING . $token ) : false;

		if ( ! is_array( $data ) ) {
			wp_safe_redirect( wp_login_url() );
			exit;
		}

		$user = get_user_by( 'id', (int) $data['user_id'] );
		if ( ! $user ) {
			wp_safe_redirect( wp_login_url() );
			exit;
		}

		$method     = $this->method_for_user( $user->ID );
		$error      = isset( $_GET['error'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$email_sent = isset( $_GET['email_sent'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( 'email' === $method && ! $email_sent && ! get_transient( self::TRANSIENT_EMAIL . $user->ID ) ) {
			$this->send_email_code( $user );
			$email_sent = true;
		}

		$form_action = add_query_arg(
			array(
				'action' => 'sentrygate_tfa',
				'token'  => $token,
			),
			wp_login_url()
		);

		login_header( __( 'Zwei-Faktor-Authentifizierung', 'wiedenroth-technologies-sentrygate' ) );

		if ( $error ) {
			echo '<div id="login_error">' . esc_html__( 'Code ungültig oder abgelaufen.', 'wiedenroth-technologies-sentrygate' ) . '</div>';
		}
		if ( $email_sent && 'email' === $method ) {
			echo '<p class="message">' . esc_html__( 'Ein Code wurde per E-Mail gesendet (10 Minuten gültig).', 'wiedenroth-technologies-sentrygate' ) . '</p>';
		}
		?>
		<form name="sentrygate_tfa_form" id="sentrygate_tfa_form" action="<?php echo esc_url( $form_action ); ?>" method="post">
			<?php wp_nonce_field( 'sentrygate_tfa_verify', 'sentrygate_tfa_nonce' ); ?>
			<input type="hidden" name="token" value="<?php echo esc_attr( $token ); ?>" />
			<p>
				<label for="sentrygate_tfa_code">
					<?php
					if ( 'email' === $method ) {
						esc_html_e( 'Code aus der E-Mail', 'wiedenroth-technologies-sentrygate' );
					} else {
						esc_html_e( 'Code aus der Authenticator-App', 'wiedenroth-technologies-sentrygate' );
					}
					?>
				</label>
				<input type="text" name="sentrygate_tfa_code" id="sentrygate_tfa_code" class="input" size="20" autocomplete="one-time-code" inputmode="numeric" autofocus />
			</p>
			<p class="description">
				<?php
				if ( 'email' === $method ) {
					esc_html_e( 'E-Mail-Code oder Notfall-Code eingeben.', 'wiedenroth-technologies-sentrygate' );
				} else {
					esc_html_e( 'App-Code oder Notfall-Code eingeben.', 'wiedenroth-technologies-sentrygate' );
				}
				?>
			</p>
			<?php if ( 'email' === $method ) : ?>
				<p><button type="submit" name="sentrygate_send_email" value="1" class="button"><?php esc_html_e( 'Code erneut senden', 'wiedenroth-technologies-sentrygate' ); ?></button></p>
			<?php endif; ?>
			<p>
				<label>
					<input type="checkbox" name="sentrygate_trust_device" value="1" />
					<?php
					printf(
						/* translators: %d: days */
						esc_html__( 'Dieses Gerät für %d Tage vertrauen', 'wiedenroth-technologies-sentrygate' ),
						(int) SentryGate_Options::get( 'tfa_trusted_days', 30 )
					);
					?>
				</label>
			</p>
			<p class="submit">
				<input type="submit" name="wp-submit" id="wp-submit" class="button button-primary button-large" value="<?php esc_attr_e( 'Bestätigen', 'wiedenroth-technologies-sentrygate' ); ?>" />
			</p>
		</form>
		<?php
		login_footer();
		exit;
	}

	/**
	 * Handle TFA form POST.
	 */
	public function handle_tfa_submit(): void {
		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_text_field( wp_unslash( (string) $_SERVER['REQUEST_METHOD'] ) ) ) : '';
		if ( 'POST' !== $method ) {
			return;
		}

		if ( ! isset( $_POST['sentrygate_tfa_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( (string) $_POST['sentrygate_tfa_nonce'] ) ), 'sentrygate_tfa_verify' ) ) {
			wp_die( esc_html__( 'Sicherheitsprüfung fehlgeschlagen.', 'wiedenroth-technologies-sentrygate' ) );
		}

		$token = $this->request_token();
		$data  = $token ? get_transient( self::TRANSIENT_PENDING . $token ) : false;
		if ( ! is_array( $data ) ) {
			wp_safe_redirect( wp_login_url() );
			exit;
		}

		$user_id = (int) $data['user_id'];
		$user    = get_user_by( 'id', $user_id );
		if ( ! $user ) {
			wp_safe_redirect( wp_login_url() );
			exit;
		}

		$back = add_query_arg(
			array(
				'action' => 'sentrygate_tfa',
				'token'  => $token,
			),
			wp_login_url()
		);

		$send_email = isset( $_POST['sentrygate_send_email'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['sentrygate_send_email'] ) ) : '';
		if ( '' !== $send_email && 'email' === $this->method_for_user( $user->ID ) ) {
			if ( ! $this->send_email_code( $user ) ) {
				wp_safe_redirect( add_query_arg( 'error', '1', $back ) );
				exit;
			}
			wp_safe_redirect( add_query_arg( 'email_sent', '1', $back ) );
			exit;
		}

		$code = isset( $_POST['sentrygate_tfa_code'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['sentrygate_tfa_code'] ) ) : '';
		$ok   = $this->verify_any_code( $user, $code );

		if ( ! $ok ) {
			SentryGate_Rate_Limit::record_failure( SentryGate_IP::get(), $user->user_login );
			wp_safe_redirect( add_query_arg( 'error', '1', $back ) );
			exit;
		}

		delete_transient( self::TRANSIENT_PENDING . $token );

		$trust_device = isset( $_POST['sentrygate_trust_device'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['sentrygate_trust_device'] ) ) : '';
		if ( '' !== $trust_device ) {
			$this->set_trusted_device( $user_id );
		}

		wp_set_current_user( $user_id );
		wp_set_auth_cookie( $user_id, ! empty( $data['remember'] ) );
		do_action( 'wp_login', $user->user_login, $user );

		$redirect_to = admin_url();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- redirect after verified TFA nonce above.
		$candidate = isset( $_REQUEST['redirect_to'] ) ? esc_url_raw( wp_unslash( (string) $_REQUEST['redirect_to'] ) ) : '';
		if ( $candidate && wp_validate_redirect( $candidate, false ) ) {
			$redirect_to = $candidate;
		}

		wp_safe_redirect( $redirect_to );
		exit;
	}

	/**
	 * Verify according to the user's chosen method, plus recovery codes.
	 *
	 * @param WP_User $user User.
	 * @param string  $code Code.
	 */
	private function verify_any_code( WP_User $user, string $code ): bool {
		$code = trim( $code );
		if ( '' === $code ) {
			return false;
		}

		$method = $this->method_for_user( $user->ID );

		if ( 'app' === $method ) {
			$secret = (string) get_user_meta( $user->ID, self::META_SECRET, true );
			if ( $secret && SentryGate_TOTP::verify( $secret, $code ) ) {
				return true;
			}
		}

		if ( 'email' === $method && $this->verify_email_code( $user, $code ) ) {
			return true;
		}

		$recovery = get_user_meta( $user->ID, self::META_RECOVERY, true );
		if ( is_array( $recovery ) ) {
			$try = SentryGate_TOTP::hash_recovery_code( $code );
			foreach ( $recovery as $i => $hash ) {
				if ( hash_equals( (string) $hash, $try ) ) {
					unset( $recovery[ $i ] );
					update_user_meta( $user->ID, self::META_RECOVERY, array_values( $recovery ) );
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * @param WP_User $user User.
	 * @param string  $code Plain code.
	 */
	private function verify_email_code( WP_User $user, string $code ): bool {
		$code = trim( $code );
		if ( '' === $code ) {
			return false;
		}

		$email_hash = (string) get_user_meta( $user->ID, self::META_EMAIL_CODES, true );
		$email_live = (bool) get_transient( self::TRANSIENT_EMAIL . $user->ID );
		if ( $email_live && $email_hash && hash_equals( $email_hash, hash( 'sha256', $code ) ) ) {
			delete_user_meta( $user->ID, self::META_EMAIL_CODES );
			delete_transient( self::TRANSIENT_EMAIL . $user->ID );
			return true;
		}

		if ( $email_hash && ! $email_live ) {
			delete_user_meta( $user->ID, self::META_EMAIL_CODES );
		}

		return false;
	}

	/**
	 * Send email OTP (with short cooldown against mail flooding).
	 *
	 * @param WP_User $user User.
	 * @return bool False when cooldown is active.
	 */
	private function send_email_code( WP_User $user ): bool {
		$cooldown_key = 'sentrygate_email_cd_' . $user->ID;
		if ( get_transient( $cooldown_key ) ) {
			return false;
		}

		$code = (string) random_int( 100000, 999999 );
		update_user_meta( $user->ID, self::META_EMAIL_CODES, hash( 'sha256', $code ) );
		set_transient( self::TRANSIENT_EMAIL . $user->ID, 1, 10 * MINUTE_IN_SECONDS );
		set_transient( $cooldown_key, 1, MINUTE_IN_SECONDS );

		wp_mail(
			$user->user_email,
			sprintf(
				/* translators: %s: site name */
				__( '[%s] Ihr Anmeldecode', 'wiedenroth-technologies-sentrygate' ),
				wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES )
			),
			sprintf(
				/* translators: %s: code */
				__( "Ihr Einmalcode lautet: %s\n\nDer Code ist 10 Minuten gültig.", 'wiedenroth-technologies-sentrygate' ),
				$code
			)
		);

		return true;
	}

	/**
	 * Whether 2FA is required for user roles.
	 *
	 * @param WP_User $user User.
	 */
	private function is_required_for_user( WP_User $user ): bool {
		$required = (array) SentryGate_Options::get( 'tfa_required_roles', array() );
		foreach ( (array) $user->roles as $role ) {
			if ( in_array( $role, $required, true ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Trusted device cookie check.
	 *
	 * @param int $user_id User ID.
	 */
	private function is_trusted_device( int $user_id ): bool {
		if ( empty( $_COOKIE[ self::COOKIE_TRUSTED ] ) ) {
			return false;
		}
		$raw   = sanitize_text_field( wp_unslash( (string) $_COOKIE[ self::COOKIE_TRUSTED ] ) );
		$parts = explode( '|', $raw );
		if ( 3 !== count( $parts ) ) {
			return false;
		}
		[ $uid, $exp, $sig ] = $parts;
		if ( (int) $uid !== $user_id || (int) $exp < time() ) {
			return false;
		}
		$expected = hash_hmac( 'sha256', $uid . '|' . $exp, wp_salt( 'auth' ) );
		return hash_equals( $expected, $sig );
	}

	/**
	 * Set trusted device cookie.
	 *
	 * @param int $user_id User ID.
	 */
	private function set_trusted_device( int $user_id ): void {
		$days  = (int) SentryGate_Options::get( 'tfa_trusted_days', 30 );
		$exp   = time() + ( $days * DAY_IN_SECONDS );
		$sig   = hash_hmac( 'sha256', $user_id . '|' . $exp, wp_salt( 'auth' ) );
		$value = $user_id . '|' . $exp . '|' . $sig;
		setcookie(
			self::COOKIE_TRUSTED,
			$value,
			array(
				'expires'  => $exp,
				'path'     => COOKIEPATH,
				'domain'   => COOKIE_DOMAIN,
				'secure'   => is_ssl(),
				'httponly' => true,
				'samesite' => 'Lax',
			)
		);
	}

	/**
	 * Force required roles to finish 2FA on the setup page.
	 */
	public function enforce_setup_redirect(): void {
		if ( ! is_user_logged_in() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}

		$user = wp_get_current_user();
		if ( ! $user || ! $user->ID ) {
			return;
		}

		if ( (bool) get_user_meta( $user->ID, self::META_ENABLED, true ) ) {
			delete_user_meta( $user->ID, self::META_FORCE_SETUP );
			return;
		}

		if ( ! $this->is_required_for_user( $user ) && ! get_user_meta( $user->ID, self::META_FORCE_SETUP, true ) ) {
			return;
		}

		$page = isset( $_GET['page'] ) ? sanitize_key( (string) wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( self::PAGE_SLUG === $page ) {
			return;
		}

		$action = isset( $_REQUEST['action'] ) ? sanitize_key( (string) wp_unslash( $_REQUEST['action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( in_array( $action, array( 'logout', 'upload-plugin', 'upload-theme' ), true ) ) {
			return;
		}

		wp_safe_redirect( self::setup_url() );
		exit;
	}

	/**
	 * Admin notice when role requires 2FA but not enabled.
	 */
	public function setup_admin_notice(): void {
		$user = wp_get_current_user();
		if ( ! $user || ! $user->ID ) {
			return;
		}

		$page = isset( $_GET['page'] ) ? sanitize_key( (string) wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( self::PAGE_SLUG === $page ) {
			return;
		}

		if ( ! $this->is_required_for_user( $user ) ) {
			return;
		}
		if ( (bool) get_user_meta( $user->ID, self::META_ENABLED, true ) ) {
			return;
		}

		echo '<div class="notice notice-error"><p>';
		echo esc_html__( 'SentryGate: Für Ihre Rolle ist Zwei-Faktor-Authentifizierung Pflicht. Bitte wählen Sie Authenticator-App oder E-Mail.', 'wiedenroth-technologies-sentrygate' );
		echo ' <a href="' . esc_url( self::setup_url() ) . '">' . esc_html__( 'Jetzt einrichten', 'wiedenroth-technologies-sentrygate' ) . '</a>';
		echo '</p></div>';
	}

	/**
	 * Module status.
	 *
	 * @return array{status: string, label: string, message: string}
	 */
	public static function status(): array {
		if ( ! SentryGate_Options::module_enabled( 'two_factor' ) ) {
			return array(
				'status'  => 'inactive',
				'label'   => __( 'Zwei-Faktor', 'wiedenroth-technologies-sentrygate' ),
				'message' => __( 'Ausgeschaltet – nur Passwort, kein zweiter Code.', 'wiedenroth-technologies-sentrygate' ),
			);
		}

		return array(
			'status'  => 'active',
			'label'   => __( 'Zwei-Faktor', 'wiedenroth-technologies-sentrygate' ),
			'message' => __( 'App oder E-Mail, Einrichtung unter Benutzer → Zwei-Faktor.', 'wiedenroth-technologies-sentrygate' ),
		);
	}
}
