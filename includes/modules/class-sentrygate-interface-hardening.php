<?php
/**
 * Interface hardening: XML-RPC, REST enumeration, file editor, version leak, REST whitelist.
 *
 * @package SentryGate
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SentryGate_Interface_Hardening
 */
class SentryGate_Interface_Hardening {

	/**
	 * Register hooks when module is enabled.
	 *
	 * @param SentryGate_Loader $loader Loader.
	 */
	public function register( SentryGate_Loader $loader ): void {
		if ( ! SentryGate_Options::module_enabled( 'interface_hardening' ) ) {
			return;
		}

		if ( SentryGate_Options::get( 'disable_xmlrpc' ) ) {
			$loader->add_filter( 'xmlrpc_enabled', $this, 'disable_xmlrpc' );
			$loader->add_filter( 'wp_headers', $this, 'remove_pingback_header' );
			$loader->add_action( 'init', $this, 'block_xmlrpc_direct', 0 );
			$loader->add_filter( 'xmlrpc_methods', $this, 'empty_xmlrpc_methods' );
		}

		if ( SentryGate_Options::get( 'block_user_enumeration' ) ) {
			$loader->add_filter( 'rest_authentication_errors', $this, 'block_rest_users', 20 );
			$loader->add_action( 'template_redirect', $this, 'block_author_enumeration' );
			$loader->add_filter( 'redirect_canonical', $this, 'stop_author_redirect', 10, 2 );
		}

		if ( SentryGate_Options::get( 'disallow_file_edit' ) ) {
			$loader->add_action( 'init', $this, 'disallow_file_edit', 1 );
		}

		if ( SentryGate_Options::get( 'remove_wp_version' ) ) {
			$loader->add_action( 'init', $this, 'remove_version_noise' );
			$loader->add_filter( 'style_loader_src', $this, 'strip_version_query', 9999 );
			$loader->add_filter( 'script_loader_src', $this, 'strip_version_query', 9999 );
			$loader->add_filter( 'the_generator', $this, 'empty_generator' );
		}

		if ( SentryGate_Options::get( 'restrict_rest_api' ) ) {
			$loader->add_filter( 'rest_pre_dispatch', $this, 'restrict_rest_routes', 10, 3 );
		}
	}

	/**
	 * Disable XML-RPC.
	 */
	public function disable_xmlrpc(): bool {
		return false;
	}

	/**
	 * Remove X-Pingback header.
	 *
	 * @param array<string, string> $headers Headers.
	 * @return array<string, string>
	 */
	public function remove_pingback_header( array $headers ): array {
		unset( $headers['X-Pingback'] );
		return $headers;
	}

	/**
	 * Hard-block direct xmlrpc.php access.
	 */
	public function block_xmlrpc_direct(): void {
		if ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) {
			status_header( 403 );
			wp_die(
				esc_html__( 'XML-RPC ist auf dieser Website deaktiviert.', 'wiedenroth-technologies-sentrygate' ),
				esc_html__( 'Zugriff verweigert', 'wiedenroth-technologies-sentrygate' ),
				array( 'response' => 403 )
			);
		}

		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( (string) $_SERVER['REQUEST_URI'] ) ) : '';
		if ( false !== stripos( $request_uri, 'xmlrpc.php' ) ) {
			status_header( 403 );
			nocache_headers();
			exit;
		}
	}

	/**
	 * Empty XML-RPC method map.
	 *
	 * @param array<string, mixed> $methods Methods.
	 * @return array<string, mixed>
	 */
	public function empty_xmlrpc_methods( array $methods ): array {
		return array();
	}

	/**
	 * Block REST user enumeration for anonymous requests.
	 *
	 * @param mixed $result Previous result.
	 * @return mixed
	 */
	public function block_rest_users( mixed $result ): mixed {
		if ( ! empty( $result ) ) {
			return $result;
		}

		if ( is_user_logged_in() ) {
			return $result;
		}

		$rest_route = $GLOBALS['wp']->query_vars['rest_route'] ?? '';
		if ( ! is_string( $rest_route ) ) {
			$rest_route = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( (string) $_SERVER['REQUEST_URI'] ) ) : '';
		}

		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( (string) $_SERVER['REQUEST_URI'] ) ) : '';
		if ( preg_match( '#/wp/v2/users(/|$)#', $rest_route ) || preg_match( '#/wp/v2/users(/|$)#', $request_uri ) ) {
			return new WP_Error(
				'sentrygate_rest_users_blocked',
				__( 'Benutzer-Enumeration über die REST-API ist deaktiviert.', 'wiedenroth-technologies-sentrygate' ),
				array( 'status' => 403 )
			);
		}

		return $result;
	}

	/**
	 * Block ?author=N enumeration with 403.
	 */
	public function block_author_enumeration(): void {
		if ( is_admin() || is_user_logged_in() ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$author_raw = isset( $_GET['author'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['author'] ) ) : '';
		if ( '' !== $author_raw && (string) absint( $author_raw ) === $author_raw && absint( $author_raw ) > 0 ) {
			status_header( 403 );
			nocache_headers();
			wp_die(
				esc_html__( 'Benutzer-Enumeration ist deaktiviert.', 'wiedenroth-technologies-sentrygate' ),
				esc_html__( 'Zugriff verweigert', 'wiedenroth-technologies-sentrygate' ),
				array( 'response' => 403 )
			);
		}
	}

	/**
	 * Prevent canonical redirects that leak usernames via author archives.
	 *
	 * @param string|false $redirect Redirect URL.
	 * @param string       $requested Requested URL.
	 * @return string|false
	 */
	public function stop_author_redirect( $redirect, string $requested ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['author'] ) ) {
			return false;
		}
		return $redirect;
	}

	/**
	 * Define DISALLOW_FILE_EDIT if not already set.
	 */
	public function disallow_file_edit(): void {
		if ( ! defined( 'DISALLOW_FILE_EDIT' ) ) {
			define( 'DISALLOW_FILE_EDIT', true );
		}
	}

	/**
	 * Remove version meta and generator.
	 */
	public function remove_version_noise(): void {
		remove_action( 'wp_head', 'wp_generator' );
		add_filter( 'the_generator', '__return_empty_string' );
	}

	/**
	 * Strip ?ver= from asset URLs.
	 *
	 * @param string $src Source URL.
	 */
	public function strip_version_query( string $src ): string {
		// Im Editor muss ?ver= bleiben, sonst hält der Browser alte Block-Skripte fest.
		if ( is_admin() ) {
			return $src;
		}
		if ( str_contains( $src, 'ver=' ) ) {
			$src = remove_query_arg( 'ver', $src );
		}
		return $src;
	}

	/**
	 * Empty generator string.
	 */
	public function empty_generator(): string {
		return '';
	}

	/**
	 * Restrict anonymous REST routes to whitelist.
	 *
	 * @param mixed            $result  Response.
	 * @param WP_REST_Server   $server  Server.
	 * @param WP_REST_Request  $request Request.
	 * @return mixed
	 */
	public function restrict_rest_routes( mixed $result, $server, $request ): mixed {
		unset( $server );

		if ( is_user_logged_in() || current_user_can( 'edit_posts' ) ) {
			return $result;
		}

		$route = $request->get_route();
		if ( str_starts_with( (string) $route, '/wt-blockforge/' ) ) {
			return $result;
		}

		$whitelist = (array) SentryGate_Options::get( 'rest_api_whitelist', array() );

		foreach ( $whitelist as $allowed ) {
			$allowed = (string) $allowed;
			if ( '' === $allowed ) {
				continue;
			}
			// Prefix match only – avoid overly broad substring matches.
			if ( str_starts_with( $route, $allowed ) ) {
				return $result;
			}
		}

		// Always allow namespace index lightly for discovery of allowed routes? Prefer deny.
		return new WP_Error(
			'sentrygate_rest_restricted',
			__( 'Diese REST-Route ist für anonyme Nutzer gesperrt.', 'wiedenroth-technologies-sentrygate' ),
			array( 'status' => 401 )
		);
	}

	/**
	 * Module status for dashboard.
	 *
	 * @return array{status: string, label: string, message: string}
	 */
	public static function status(): array {
		if ( ! SentryGate_Options::module_enabled( 'interface_hardening' ) ) {
			return array(
				'status'  => 'inactive',
				'label'   => __( 'Basis-Schutz', 'wiedenroth-technologies-sentrygate' ),
				'message' => __( 'Ausgeschaltet – bekannte Angriffswege sind nicht blockiert.', 'wiedenroth-technologies-sentrygate' ),
			);
		}

		$warnings = array();
		if ( ! SentryGate_Options::get( 'disable_xmlrpc' ) ) {
			$warnings[] = __( 'XML-RPC ist nicht deaktiviert.', 'wiedenroth-technologies-sentrygate' );
		}
		if ( ! SentryGate_Options::get( 'block_user_enumeration' ) ) {
			$warnings[] = __( 'User-Enumeration ist nicht blockiert.', 'wiedenroth-technologies-sentrygate' );
		}

		if ( $warnings ) {
			return array(
				'status'  => 'warning',
				'label'   => __( 'Basis-Schutz', 'wiedenroth-technologies-sentrygate' ),
				'message' => implode( ' ', $warnings ),
			);
		}

		return array(
			'status'  => 'active',
			'label'   => __( 'Basis-Schutz', 'wiedenroth-technologies-sentrygate' ),
			'message' => __( 'XML-RPC, Enumeration und Versionsleaks sind gehärtet.', 'wiedenroth-technologies-sentrygate' ),
		);
	}
}
