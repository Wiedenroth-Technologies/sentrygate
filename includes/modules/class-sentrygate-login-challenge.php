<?php
/**
 * Login challenge: pre-login symbol gate, session cookie, honeypot, timing, rate limit.
 *
 * Fully self-hosted – no third-party requests, no image CAPTCHA.
 *
 * @package SentryGate
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SentryGate_Login_Challenge
 */
class SentryGate_Login_Challenge {

	public const TRANSIENT_PREFIX = 'sentrygate_chal_';

	public const COOKIE_GATE = 'sentrygate_gate';

	/**
	 * Max cookie lifetime (browser session ends sooner when the tab/browser closes).
	 */
	private const GATE_TTL = 12 * HOUR_IN_SECONDS;

	/**
	 * Whether this request already counted a challenge failure.
	 *
	 * @var bool
	 */
	private bool $failure_recorded = false;

	/**
	 * Register hooks.
	 *
	 * @param SentryGate_Loader $loader Loader.
	 */
	public function register( SentryGate_Loader $loader ): void {
		if ( ! SentryGate_Options::module_enabled( 'login_challenge' ) ) {
			return;
		}

		$loader->add_action( 'login_init', $this, 'gate_login', 5 );
		$loader->add_action( 'login_enqueue_scripts', $this, 'enqueue' );
		$loader->add_action( 'login_form_login', $this, 'maybe_block_locked' );
		// After password auth (20): without a gate cookie, replace success and credential errors.
		$loader->add_filter( 'authenticate', $this, 'require_gate', 25, 3 );
		$loader->add_action( 'wp_login_failed', $this, 'on_login_failed', 10, 2 );
		$loader->add_action( 'wp_login', $this, 'on_login_success', 10, 2 );
		$loader->add_action( 'wp_ajax_nopriv_sentrygate_new_challenge', $this, 'ajax_new_challenge' );
		$loader->add_action( 'wp_ajax_sentrygate_new_challenge', $this, 'ajax_new_challenge' );
		$loader->add_action( 'wp_ajax_nopriv_sentrygate_challenge_state', $this, 'ajax_challenge_state' );
		$loader->add_action( 'wp_ajax_sentrygate_challenge_state', $this, 'ajax_challenge_state' );
		$loader->add_action( 'wp_ajax_nopriv_sentrygate_complete_challenge', $this, 'ajax_complete_challenge' );
		$loader->add_action( 'wp_ajax_sentrygate_complete_challenge', $this, 'ajax_complete_challenge' );
	}

	/**
	 * Show the challenge before username/password, or pass through if the session cookie is valid.
	 */
	public function gate_login(): void {
		if ( is_user_logged_in() || $this->should_skip_action() ) {
			return;
		}

		$ip = SentryGate_IP::get();
		if ( SentryGate_Rate_Limit::is_locked( $ip ) ) {
			$this->render_locked( $ip );
		}

		if ( $this->has_valid_gate() ) {
			return;
		}

		$error = null;
		if ( isset( $_SERVER['REQUEST_METHOD'] ) && 'POST' === strtoupper( (string) $_SERVER['REQUEST_METHOD'] ) && isset( $_POST['sentrygate_gate'] ) ) {
			$error = $this->process_challenge_post( $ip );
			if ( ! is_wp_error( $error ) ) {
				$this->issue_gate_cookie();
				wp_safe_redirect( $this->continue_url() );
				exit;
			}
		}

		$this->render_challenge_page( $error instanceof WP_Error ? $error : null );
		exit;
	}

	/**
	 * Enqueue assets only on the challenge screen.
	 */
	public function enqueue(): void {
		if ( $this->has_valid_gate() || $this->should_skip_action() ) {
			return;
		}

		$mode = (string) SentryGate_Options::get( 'challenge_mode', 'full' );

		$css = SENTRYGATE_PLUGIN_DIR . 'assets/css/login-challenge.css';
		wp_enqueue_style(
			'sentrygate-login-challenge',
			SENTRYGATE_PLUGIN_URL . 'assets/css/login-challenge.css',
			array(),
			is_readable( $css ) ? (string) filemtime( $css ) : SENTRYGATE_VERSION
		);

		wp_enqueue_script(
			'sentrygate-login-challenge',
			SENTRYGATE_PLUGIN_URL . 'assets/js/login-challenge.js',
			array(),
			SENTRYGATE_VERSION,
			true
		);

		$token = $this->create_challenge();

		wp_localize_script(
			'sentrygate-login-challenge',
			'SentryGateChallenge',
			array(
				'mode'    => $mode,
				'token'   => $token,
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'sentrygate_challenge' ),
				'i18n'    => array(
					'prompt'  => __( 'Wählen Sie das Symbol:', 'wiedenroth-technologies-sentrygate' ),
					'refresh' => __( 'Andere Symbole', 'wiedenroth-technologies-sentrygate' ),
					'locked'  => __( 'Zu viele Fehlversuche. Bitte später erneut versuchen.', 'wiedenroth-technologies-sentrygate' ),
				),
			)
		);
	}

	/**
	 * Reject login attempts that skip the gate (direct POST of credentials).
	 *
	 * @param WP_User|WP_Error|null $user     User.
	 * @param string                $username Username.
	 * @param string                $password Password.
	 * @return WP_User|WP_Error|null
	 */
	public function require_gate( $user, string $username, string $password ) {
		if ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) {
			return $user;
		}
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return $user;
		}
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return $user;
		}

		if ( '' === $username && '' === $password ) {
			return $user;
		}

		if ( $this->has_valid_gate() ) {
			return $user;
		}

		return new WP_Error(
			'sentrygate_gate',
			__( '<strong>Fehler:</strong> Bitte zuerst die Sicherheitsprüfung abschließen.', 'wiedenroth-technologies-sentrygate' )
		);
	}

	/**
	 * Block login form rendering if locked.
	 */
	public function maybe_block_locked(): void {
		$ip = SentryGate_IP::get();
		if ( SentryGate_Rate_Limit::is_locked( $ip ) ) {
			$this->render_locked( $ip );
		}
	}

	/**
	 * Record failed password attempts.
	 *
	 * @param string          $username Username.
	 * @param WP_Error|null   $error    Auth error.
	 */
	public function on_login_failed( string $username, mixed $error = null ): void {
		if ( $this->failure_recorded ) {
			return;
		}
		if ( class_exists( 'SentryGate_Two_Factor' ) && SentryGate_Two_Factor::is_pending_error( $error ) ) {
			return;
		}
		SentryGate_Rate_Limit::record_failure( SentryGate_IP::get(), $username );
	}

	/**
	 * Clear rate limit on success.
	 *
	 * @param string  $user_login Login.
	 * @param WP_User $user       User.
	 */
	public function on_login_success( string $user_login, WP_User $user ): void {
		unset( $user_login, $user );
		SentryGate_Rate_Limit::clear( SentryGate_IP::get() );
	}

	/**
	 * Create a new challenge token + server-side secrets in transient.
	 */
	public function create_challenge(): string {
		$token = SentryGate_Symbol_Challenge::create();
		$GLOBALS['sentrygate_challenge_token'] = $token;

		return $token;
	}

	/**
	 * AJAX: mint a new challenge and return markup.
	 */
	public function ajax_new_challenge(): void {
		check_ajax_referer( 'sentrygate_challenge', 'nonce' );
		$token = $this->create_challenge();
		$data  = get_transient( self::TRANSIENT_PREFIX . $token );

		ob_start();
		$this->render_choice_markup( is_array( $data ) ? $data : array() );
		$html = ob_get_clean();

		wp_send_json_success(
			array(
				'token' => $token,
				'html'  => is_string( $html ) ? $html : '',
			)
		);
	}

	/**
	 * AJAX: frontend bootstrap for MemberGate (and other custom login UIs).
	 */
	public function ajax_challenge_state(): void {
		check_ajax_referer( 'sentrygate_challenge', 'nonce' );

		$ip = SentryGate_IP::get();
		if ( SentryGate_Rate_Limit::is_locked( $ip ) ) {
			wp_send_json_success(
				array(
					'active'    => true,
					'passed'    => false,
					'locked'    => true,
					'remaining' => SentryGate_Rate_Limit::remaining_seconds( $ip ),
				)
			);
		}

		if ( $this->has_valid_gate() ) {
			wp_send_json_success(
				array(
					'active' => true,
					'passed' => true,
					'locked' => false,
				)
			);
		}

		$mode  = (string) SentryGate_Options::get( 'challenge_mode', 'full' );
		$token = $this->create_challenge();
		$data  = get_transient( self::TRANSIENT_PREFIX . $token );
		$html  = '';
		if ( 'full' === $mode ) {
			ob_start();
			$this->render_choice_markup( is_array( $data ) ? $data : array() );
			$html = (string) ob_get_clean();
		}

		wp_send_json_success(
			array(
				'active'         => true,
				'passed'         => false,
				'locked'         => false,
				'mode'           => $mode,
				'token'          => $token,
				'html'           => $html,
				'completeNonce'  => wp_create_nonce( 'sentrygate_gate' ),
				'minTime'        => (int) SentryGate_Options::get( 'challenge_min_time', 2 ),
			)
		);
	}

	/**
	 * AJAX: validate the challenge and issue the gate cookie (no wp-login.php).
	 */
	public function ajax_complete_challenge(): void {
		check_ajax_referer( 'sentrygate_gate', 'nonce' );

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

		if ( $this->has_valid_gate() ) {
			wp_send_json_success( array( 'passed' => true ) );
		}

		$result = $this->process_challenge_post( $ip, true );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error(
				array(
					'message' => wp_strip_all_tags( $result->get_error_message() ),
				)
			);
		}

		$this->issue_gate_cookie();
		wp_send_json_success( array( 'passed' => true ) );
	}

	/**
	 * Module status.
	 *
	 * @return array{status: string, label: string, message: string}
	 */
	public static function status(): array {
		if ( ! SentryGate_Options::module_enabled( 'login_challenge' ) ) {
			return array(
				'status'  => 'inactive',
				'label'   => __( 'Login-Schutz', 'wiedenroth-technologies-sentrygate' ),
				'message' => __( 'Ausgeschaltet – Anmeldung ohne Zusatzschutz gegen Bots.', 'wiedenroth-technologies-sentrygate' ),
			);
		}

		$mode = (string) SentryGate_Options::get( 'challenge_mode', 'full' );
		$msg  = 'lightweight' === $mode
			? __( 'Prüfung vor dem Login (Honeypot + Timing), danach Sitzungscookie.', 'wiedenroth-technologies-sentrygate' )
			: __( 'Symbolwahl vor dem Login, danach Sitzungscookie. Direct-POSTs ohne Cookie werden abgewiesen.', 'wiedenroth-technologies-sentrygate' );

		return array(
			'status'  => 'active',
			'label'   => __( 'Login-Schutz', 'wiedenroth-technologies-sentrygate' ),
			'message' => $msg,
		);
	}

	/**
	 * Whether the current login action should skip the gate.
	 */
	private function should_skip_action(): bool {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$action = isset( $_REQUEST['action'] ) ? sanitize_key( (string) wp_unslash( $_REQUEST['action'] ) ) : 'login';
		if ( '' === $action ) {
			$action = 'login';
		}

		$skip = array(
			'logout',
			'loggedout',
			'rp',
			'resetpass',
			'postpass',
			'confirmaction',
			'confirm_admin_email',
			'checkemail',
			'sentrygate_tfa',
		);

		return in_array( $action, $skip, true );
	}

	/**
	 * Valid HMAC session cookie?
	 */
	public function has_valid_gate(): bool {
		if ( empty( $_COOKIE[ self::COOKIE_GATE ] ) ) {
			return false;
		}

		$raw   = sanitize_text_field( wp_unslash( (string) $_COOKIE[ self::COOKIE_GATE ] ) );
		$parts = explode( '|', $raw );
		if ( 4 !== count( $parts ) ) {
			return false;
		}

		[ $version, $exp, $nonce, $sig ] = $parts;
		if ( '1' !== $version || (int) $exp < time() || ! preg_match( '/^[a-f0-9]{32}$/', $nonce ) ) {
			return false;
		}

		$payload  = $version . '|' . $exp . '|' . $nonce;
		$expected = hash_hmac( 'sha256', $payload, $this->gate_secret() );

		return hash_equals( $expected, $sig );
	}

	/**
	 * Issue an HttpOnly session cookie (browser session; HMAC max. 12h).
	 */
	private function issue_gate_cookie(): void {
		$exp     = time() + self::GATE_TTL;
		$nonce   = bin2hex( random_bytes( 16 ) );
		$payload = '1|' . $exp . '|' . $nonce;
		$value   = $payload . '|' . hash_hmac( 'sha256', $payload, $this->gate_secret() );

		$path   = defined( 'COOKIEPATH' ) ? (string) COOKIEPATH : '/';
		$domain = defined( 'COOKIE_DOMAIN' ) ? (string) COOKIE_DOMAIN : '';

		setcookie(
			self::COOKIE_GATE,
			$value,
			array(
				'expires'  => 0,
				'path'     => $path !== '' ? $path : '/',
				'domain'   => $domain,
				'secure'   => is_ssl(),
				'httponly' => true,
				'samesite' => 'Lax',
			)
		);

		$_COOKIE[ self::COOKIE_GATE ] = $value;
	}

	/**
	 * Secret for the gate HMAC.
	 */
	private function gate_secret(): string {
		return wp_salt( 'auth' ) . '|sentrygate_gate';
	}

	/**
	 * Validate the challenge POST. True on success, WP_Error otherwise.
	 *
	 * @param string $ip                    Client IP.
	 * @param bool   $nonce_already_checked Skip form nonce (AJAX already verified).
	 * @return true|WP_Error
	 */
	private function process_challenge_post( string $ip, bool $nonce_already_checked = false ): true|WP_Error {
		if ( ! $nonce_already_checked ) {
			if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( (string) $_POST['_wpnonce'] ) ), 'sentrygate_gate' ) ) {
				$this->record_challenge_failure( $ip, '' );
				return new WP_Error( 'sentrygate_nonce', __( 'Sicherheitsprüfung ungültig. Bitte erneut versuchen.', 'wiedenroth-technologies-sentrygate' ) );
			}
		}

		$honeypot = isset( $_POST['sentrygate_website'] ) ? (string) wp_unslash( $_POST['sentrygate_website'] ) : '';
		if ( '' !== trim( $honeypot ) ) {
			$this->record_challenge_failure( $ip, '' );
			return new WP_Error( 'sentrygate_bot', __( 'Sicherheitsprüfung fehlgeschlagen.', 'wiedenroth-technologies-sentrygate' ) );
		}

		$loaded_at = isset( $_POST['sentrygate_loaded_at'] ) ? absint( $_POST['sentrygate_loaded_at'] ) : 0;
		$min_time  = (int) SentryGate_Options::get( 'challenge_min_time', 2 );
		if ( $loaded_at > 0 && ( time() - $loaded_at ) < $min_time ) {
			$this->record_challenge_failure( $ip, '' );
			return new WP_Error( 'sentrygate_timing', __( 'Bitte etwas langsamer – Formular zu schnell abgesendet.', 'wiedenroth-technologies-sentrygate' ) );
		}

		$mode = (string) SentryGate_Options::get( 'challenge_mode', 'full' );
		if ( 'lightweight' === $mode ) {
			return true;
		}

		$token  = isset( $_POST['sentrygate_challenge_token'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['sentrygate_challenge_token'] ) ) : '';
		$choice = isset( $_POST['sentrygate_choice'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['sentrygate_choice'] ) ) : '';
		$result = SentryGate_Symbol_Challenge::verify( $token, $choice );
		if ( is_wp_error( $result ) ) {
			$this->record_challenge_failure( $ip, '' );
			return $result;
		}

		return true;
	}

	/**
	 * Standalone challenge page (no username/password fields).
	 *
	 * @param WP_Error|null $error Error from a failed POST.
	 */
	private function render_challenge_page( ?WP_Error $error ): void {
		$wp_error = $error instanceof WP_Error ? $error : new WP_Error();
		$message  = '<p class="message">' . esc_html__( 'Bitte bestätigen Sie kurz, dass Sie kein Bot sind. Danach erscheint die Anmeldung.', 'wiedenroth-technologies-sentrygate' ) . '</p>';

		login_header( __( 'Sicherheitsprüfung', 'wiedenroth-technologies-sentrygate' ), $message, $wp_error );

		$mode  = (string) SentryGate_Options::get( 'challenge_mode', 'full' );
		$token = isset( $GLOBALS['sentrygate_challenge_token'] ) ? (string) $GLOBALS['sentrygate_challenge_token'] : '';
		$data  = $token ? get_transient( self::TRANSIENT_PREFIX . $token ) : false;
		?>
		<form name="sentrygate-gateform" id="sentrygate-gateform" action="<?php echo esc_url( site_url( 'wp-login.php', 'login_post' ) ); ?>" method="post">
			<input type="hidden" name="sentrygate_gate" value="1" />
			<?php wp_nonce_field( 'sentrygate_gate' ); ?>
			<?php $this->hidden_context_fields(); ?>

			<div id="sentrygate-challenge" class="sentrygate-challenge" data-mode="<?php echo esc_attr( $mode ); ?>">
				<div class="sentrygate-hp" aria-hidden="true">
					<label for="sentrygate_website"><?php esc_html_e( 'Website', 'wiedenroth-technologies-sentrygate' ); ?></label>
					<input type="text" name="sentrygate_website" id="sentrygate_website" value="" tabindex="-1" autocomplete="off" />
				</div>
				<input type="hidden" name="sentrygate_challenge_token" id="sentrygate_challenge_token" value="<?php echo esc_attr( $token ); ?>" />
				<input type="hidden" name="sentrygate_loaded_at" id="sentrygate_loaded_at" value="<?php echo esc_attr( (string) time() ); ?>" />

				<?php if ( 'full' === $mode ) : ?>
					<div id="sentrygate-choice-wrap">
						<?php $this->render_choice_markup( is_array( $data ) ? $data : array() ); ?>
					</div>
				<?php else : ?>
					<p class="sentrygate-choice__prompt"><?php esc_html_e( 'Klicken Sie auf Weiter, um zur Anmeldung zu gelangen.', 'wiedenroth-technologies-sentrygate' ); ?></p>
				<?php endif; ?>
			</div>

			<p class="submit">
				<button type="submit" name="wp-submit" id="wp-submit" class="button button-primary button-large">
					<?php esc_html_e( 'Weiter zur Anmeldung', 'wiedenroth-technologies-sentrygate' ); ?>
				</button>
			</p>
		</form>
		<?php
		login_footer();
	}

	/**
	 * Keep redirect_to / reauth / interim-login across the gate.
	 */
	private function hidden_context_fields(): void {
		$keys = array( 'redirect_to', 'reauth', 'interim-login' );
		foreach ( $keys as $key ) {
			if ( empty( $_REQUEST[ $key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				continue;
			}
			printf(
				'<input type="hidden" name="%1$s" value="%2$s" />',
				esc_attr( $key ),
				esc_attr( sanitize_text_field( wp_unslash( (string) $_REQUEST[ $key ] ) ) ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			);
		}
	}

	/**
	 * URL to the real login form after a successful gate.
	 */
	private function continue_url(): string {
		$url = site_url( 'wp-login.php', 'login' );
		$keys = array( 'redirect_to', 'reauth', 'interim-login' );
		$args = array();
		foreach ( $keys as $key ) {
			if ( empty( $_REQUEST[ $key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				continue;
			}
			$args[ $key ] = sanitize_text_field( wp_unslash( (string) $_REQUEST[ $key ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}
		return empty( $args ) ? $url : add_query_arg( $args, $url );
	}

	/**
	 * Locked-out screen.
	 *
	 * @param string $ip Client IP.
	 */
	private function render_locked( string $ip ): void {
		$secs = SentryGate_Rate_Limit::remaining_seconds( $ip );
		login_header(
			__( 'Gesperrt', 'wiedenroth-technologies-sentrygate' ),
			'<p class="message">' . esc_html(
				sprintf(
					/* translators: %d: seconds */
					__( 'Zu viele Fehlversuche. Bitte in %d Sekunden erneut versuchen.', 'wiedenroth-technologies-sentrygate' ),
					$secs
				)
			) . '</p>'
		);
		login_footer();
		exit;
	}

	/**
	 * Record a challenge failure once per request.
	 *
	 * @param string $ip       IP.
	 * @param string $username Username.
	 */
	private function record_challenge_failure( string $ip, string $username ): void {
		SentryGate_Rate_Limit::record_failure( $ip, $username );
		$this->failure_recorded = true;
	}

	/**
	 * Symbol buttons / radios for the current challenge.
	 *
	 * @param array<string, mixed> $data Challenge payload.
	 */
	private function render_choice_markup( array $data ): void {
		SentryGate_Symbol_Challenge::render_markup( $data );
	}
}
