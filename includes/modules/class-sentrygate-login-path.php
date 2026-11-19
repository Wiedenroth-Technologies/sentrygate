<?php
/**
 * Custom login path (Hide Login style) – self-implemented.
 *
 * @package SentryGate
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SentryGate_Login_Path
 */
class SentryGate_Login_Path {

	/**
	 * Whether the current request is the custom login.
	 *
	 * @var bool
	 */
	private bool $is_custom_login = false;

	/**
	 * Register hooks.
	 *
	 * @param SentryGate_Loader $loader Loader.
	 */
	public function register( SentryGate_Loader $loader ): void {
		if ( ! SentryGate_Options::module_enabled( 'login_path' ) ) {
			return;
		}

		$loader->add_action( 'init', $this, 'add_rewrite' );
		$loader->add_filter( 'query_vars', $this, 'query_vars' );
		$loader->add_action( 'wp_loaded', $this, 'handle_wp_loaded', 1 );
		$loader->add_filter( 'site_url', $this, 'filter_site_url', 10, 4 );
		$loader->add_filter( 'network_site_url', $this, 'filter_site_url', 10, 3 );
		$loader->add_filter( 'wp_redirect', $this, 'filter_wp_redirect' );
		$loader->add_action( 'login_init', $this, 'login_init', 1 );
		$loader->add_filter( 'login_url', $this, 'filter_login_url', 10, 3 );
		$loader->add_filter( 'logout_url', $this, 'filter_logout_url', 10, 2 );
		$loader->add_filter( 'lostpassword_url', $this, 'filter_lostpassword_url', 10, 2 );
		$loader->add_filter( 'register_url', $this, 'filter_register_url' );
	}

	/**
	 * Login slug.
	 */
	private function slug(): string {
		$slug = (string) SentryGate_Options::get( 'login_slug', 'portal' );
		return sanitize_title( $slug ) ?: 'portal';
	}

	/**
	 * Add rewrite rule for custom slug.
	 */
	public function add_rewrite(): void {
		$slug = $this->slug();
		add_rewrite_rule( '^' . preg_quote( $slug, '/' ) . '/?$', 'index.php?sentrygate_login=1', 'top' );
	}

	/**
	 * Register query var.
	 *
	 * @param array<int, string> $vars Vars.
	 * @return array<int, string>
	 */
	public function query_vars( array $vars ): array {
		$vars[] = 'sentrygate_login';
		return $vars;
	}

	/**
	 * Early request handling: serve custom login, 404 original paths.
	 */
	public function handle_wp_loaded(): void {
		// Keep admin-ajax.php and REST login endpoints reachable.
		if ( wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}

		$request = $this->request_path();
		$slug    = $this->slug();

		// Custom slug → frontend login (e.g. MemberGate) or wp-login.php.
		if ( $request === $slug || $request === $slug . '/' ) {
			if ( $this->maybe_redirect_frontend_login() ) {
				return;
			}
			$this->is_custom_login = true;
			$this->load_login();
		}

		// Block wp-login.php and wp-admin for guests with 404.
		if ( $this->is_login_request() || $this->is_admin_request() ) {
			if ( is_user_logged_in() ) {
				return;
			}

			// Allow logout / post-auth actions only via custom slug (already handled).
			$this->serve_404();
		}
	}

	/**
	 * When another plugin exposes a frontend login UI, send the custom slug there
	 * (avoids WordPress login → frontend 2FA / second credential step).
	 *
	 * Interim login stays on wp-login.php (admin modal iframe).
	 *
	 * @return bool True when a redirect was issued.
	 */
	private function maybe_redirect_frontend_login(): bool {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! empty( $_REQUEST['interim-login'] ) ) {
			return false;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$action = isset( $_REQUEST['action'] ) ? sanitize_key( (string) wp_unslash( $_REQUEST['action'] ) ) : 'login';
		// Keep native wp-login actions on WordPress UI; only login + SentryGate 2FA go frontend.
		$frontend_actions = array( '', 'login', 'sentrygate_tfa' );
		if ( ! in_array( $action, $frontend_actions, true ) ) {
			return false;
		}

		$frontend = $this->frontend_login_url();
		if ( '' === $frontend ) {
			return false;
		}

		$args = array();
		$keep = array( 'action', 'token', 'reauth', 'error', 'email_sent', 'redirect_to' );
		foreach ( $keep as $key ) {
			if ( empty( $_REQUEST[ $key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				continue;
			}
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized per key below.
			$value        = wp_unslash( (string) $_REQUEST[ $key ] );
			$args[ $key ] = 'redirect_to' === $key
				? esc_url_raw( $value )
				: sanitize_text_field( $value );
		}

		$url = empty( $args ) ? $frontend : add_query_arg( $args, $frontend );
		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Optional frontend login URL from compatible plugins (MemberGate etc.).
	 *
	 * @param string $redirect Optional redirect_to.
	 */
	private function frontend_login_url( string $redirect = '' ): string {
		/**
		 * Allow a sibling plugin to own the public login screen.
		 *
		 * Return a full URL, or empty string to keep the WordPress login UI.
		 *
		 * @param string $url      Frontend login URL or ''.
		 * @param string $redirect Optional redirect after login.
		 */
		$url = apply_filters( 'sentrygate_frontend_login_url', '', $redirect );
		if ( ! is_string( $url ) || '' === $url ) {
			return '';
		}

		$safe = esc_url_raw( $url );
		return is_string( $safe ) && '' !== $safe ? $safe : '';
	}

	/**
	 * Protect login_init for direct wp-login.php hits.
	 */
	public function login_init(): void {
		if ( $this->is_custom_login ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$action = isset( $_REQUEST['action'] ) ? sanitize_key( (string) wp_unslash( $_REQUEST['action'] ) ) : '';

		// Allow logout confirmation when already authenticated via cookie mid-request.
		if ( is_user_logged_in() && in_array( $action, array( 'logout', 'loggedout' ), true ) ) {
			return;
		}

		if ( ! $this->is_custom_login && $this->is_login_request() ) {
			$this->serve_404();
		}
	}

	/**
	 * Replace wp-login.php URLs with custom slug.
	 *
	 * @param string      $url     URL.
	 * @param string      $path    Path.
	 * @param string|null $scheme  Scheme.
	 * @param int|null    $blog_id Blog ID.
	 */
	public function filter_site_url( string $url, string $path = '', $scheme = null, $blog_id = null ): string {
		unset( $scheme, $blog_id );

		if ( str_contains( $url, 'wp-login.php' ) ) {
			$url = $this->rewrite_login_url( $url );
		}

		return $url;
	}

	/**
	 * Filter redirects that point to wp-login.php.
	 *
	 * @param string $location Location.
	 */
	public function filter_wp_redirect( string $location ): string {
		if ( str_contains( $location, 'wp-login.php' ) ) {
			return $this->rewrite_login_url( $location );
		}
		return $location;
	}

	/**
	 * Filter login_url.
	 *
	 * @param string $login_url    Login URL.
	 * @param string $redirect     Redirect.
	 * @param bool   $force_reauth Force reauth.
	 */
	public function filter_login_url( string $login_url, string $redirect = '', bool $force_reauth = false ): string {
		unset( $login_url );

		$frontend = $this->frontend_login_url( $redirect );
		if ( '' !== $frontend ) {
			$url = $frontend;
		} else {
			$url = home_url( '/' . $this->slug() . '/', 'login' );
			if ( $redirect ) {
				$url = add_query_arg( 'redirect_to', $redirect, $url );
			}
		}
		if ( $force_reauth ) {
			$url = add_query_arg( 'reauth', '1', $url );
		}
		return $url;
	}

	/**
	 * Filter logout URL.
	 *
	 * @param string $logout_url Logout URL.
	 * @param string $redirect   Redirect.
	 */
	public function filter_logout_url( string $logout_url, string $redirect = '' ): string {
		return $this->rewrite_login_url( $logout_url );
	}

	/**
	 * Filter lost password URL.
	 *
	 * @param string $url      URL.
	 * @param string $redirect Redirect.
	 */
	public function filter_lostpassword_url( string $url, string $redirect = '' ): string {
		return $this->rewrite_login_url( $url );
	}

	/**
	 * Filter register URL.
	 *
	 * @param string $url URL.
	 */
	public function filter_register_url( string $url ): string {
		return $this->rewrite_login_url( $url );
	}

	/**
	 * Rewrite a URL containing wp-login.php.
	 *
	 * @param string $url URL.
	 */
	private function rewrite_login_url( string $url ): string {
		$parsed = wp_parse_url( $url );
		$query  = array();
		if ( ! empty( $parsed['query'] ) ) {
			parse_str( $parsed['query'], $query );
		}

		$base = home_url( '/' . $this->slug() . '/', 'login' );
		return empty( $query ) ? $base : add_query_arg( $query, $base );
	}

	/**
	 * Load wp-login.php in place.
	 */
	private function load_login(): void {
		global $user_login, $error, $interim_login, $action, $redirect_to, $user_email;

		$user_login = '';
		$error      = '';

		// Emulate login request environment.
		$_SERVER['PHP_SELF'] = '/wp-login.php';

		// Make wp-login.php think this is a proper request.
		if ( ! defined( 'SENTRYGATE_CUSTOM_LOGIN' ) ) {
			define( 'SENTRYGATE_CUSTOM_LOGIN', true );
		}

		require ABSPATH . 'wp-login.php';
		exit;
	}

	/**
	 * Serve a genuine 404.
	 */
	private function serve_404(): void {
		global $wp_query;

		status_header( 404 );
		nocache_headers();

		if ( isset( $wp_query ) && is_object( $wp_query ) ) {
			$wp_query->set_404();
		}

		/*
		 * This 404 is a public template. If the request started in wp-admin,
		 * is_admin() is still true, so WordPress unhooks the emoji callback
		 * from the admin hook and then runs the deprecated front callback.
		 */
		remove_action( 'wp_print_styles', 'print_emoji_styles' );

		$template = get_404_template();
		if ( $template ) {
			include $template;
		} else {
			echo esc_html__( 'Seite nicht gefunden.', 'wiedenroth-technologies-sentrygate' );
		}
		exit;
	}

	/**
	 * Normalized request path relative to home.
	 */
	private function request_path(): string {
		$uri  = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( (string) $_SERVER['REQUEST_URI'] ) ) : '/';
		$path = (string) wp_parse_url( $uri, PHP_URL_PATH );
		$home = (string) wp_parse_url( home_url(), PHP_URL_PATH );
		$home = untrailingslashit( $home );

		if ( $home && str_starts_with( $path, $home ) ) {
			$path = substr( $path, strlen( $home ) );
		}

		return trim( $path, '/' );
	}

	/**
	 * Is current request wp-login.php?
	 */
	private function is_login_request(): bool {
		$script = isset( $_SERVER['SCRIPT_NAME'] ) ? sanitize_text_field( wp_unslash( (string) $_SERVER['SCRIPT_NAME'] ) ) : '';
		$uri    = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( (string) $_SERVER['REQUEST_URI'] ) ) : '';
		return str_ends_with( $script, 'wp-login.php' ) || str_contains( $uri, 'wp-login.php' );
	}

	/**
	 * Is current request wp-admin (not ajax/cron)?
	 */
	private function is_admin_request(): bool {
		if ( ! SentryGate_Options::get( 'redirect_admin' ) ) {
			return false;
		}
		if ( wp_doing_ajax() || wp_doing_cron() ) {
			return false;
		}
		$request = $this->request_path();
		return str_starts_with( $request, 'wp-admin' );
	}

	/**
	 * Module status.
	 *
	 * @return array{status: string, label: string, message: string}
	 */
	public static function status(): array {
		if ( ! SentryGate_Options::module_enabled( 'login_path' ) ) {
			return array(
				'status'  => 'inactive',
				'label'   => __( 'Login-Adresse', 'wiedenroth-technologies-sentrygate' ),
				'message' => __( 'Ausgeschaltet – Login liegt unter der Standard-Adresse.', 'wiedenroth-technologies-sentrygate' ),
			);
		}

		$slug     = (string) SentryGate_Options::get( 'login_slug', 'portal' );
		$frontend = (string) apply_filters( 'sentrygate_frontend_login_url', '', '' );
		if ( '' !== $frontend ) {
			return array(
				'status'  => 'active',
				'label'   => __( 'Login-Adresse', 'wiedenroth-technologies-sentrygate' ),
				'message' => sprintf(
					/* translators: %s: custom login slug */
					__( '/%s/ leitet auf die Frontend-Anmeldung weiter – Originalpfade liefern 404.', 'wiedenroth-technologies-sentrygate' ),
					esc_html( $slug )
				),
			);
		}

		return array(
			'status'  => 'active',
			'label'   => __( 'Login-Adresse', 'wiedenroth-technologies-sentrygate' ),
			/* translators: %s: custom login slug */
			'message' => sprintf( __( 'Login unter /%s/ – Originalpfade liefern 404.', 'wiedenroth-technologies-sentrygate' ), esc_html( $slug ) ),
		);
	}
}
