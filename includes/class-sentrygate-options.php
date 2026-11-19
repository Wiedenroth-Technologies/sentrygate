<?php
/**
 * Central options helper – Settings API backed.
 *
 * @package SentryGate
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SentryGate_Options
 */
class SentryGate_Options {

	/**
	 * Cached settings.
	 *
	 * @var array<string, mixed>|null
	 */
	private static ?array $cache = null;

	/**
	 * Default settings for all modules.
	 *
	 * @return array<string, mixed>
	 */
	public static function defaults(): array {
		return array(
			// Module toggles.
			'module_interface_hardening' => true,
			'module_debug_control'       => true,
			'module_login_path'          => false,
			'module_login_challenge'     => true,
			'module_form_challenge'      => true,
			'module_two_factor'          => false,
			'module_security_headers'    => true,
			'module_file_hardening'      => true,
			'module_audit_log'           => true,
			'module_core_integrity'      => true,

			// Interface hardening.
			'disable_xmlrpc'             => true,
			'block_user_enumeration'     => true,
			'disallow_file_edit'         => true,
			'remove_wp_version'          => true,
			'restrict_rest_api'          => false,
			'rest_api_whitelist'         => array(
				'/wp/v2/posts',
				'/wp/v2/pages',
				'/wp/v2/media',
				'/wp/v2/categories',
				'/wp/v2/tags',
				'/wp/v2/search',
				'/oembed/',
				'/contact-form-7/',
				'/jetpack/',
				'/wt-blockforge/',
			),

			// Debug control.
			'enforce_production_debug'   => true,

			// Login path.
			'login_slug'                 => 'portal',
			'redirect_admin'             => true,

			// Login challenge.
			'challenge_mode'             => 'full', // full | lightweight
			'challenge_min_time'         => 2,
			'rate_limit_max_attempts'    => 5,
			'rate_limit_window'          => 900,
			'rate_limit_lockout'         => 900,
			'rate_limit_exponential'     => true,
			'trust_proxy_headers'        => false,

			// Two-factor.
			'tfa_required_roles'         => array( 'administrator' ),
			'tfa_email_fallback'         => true,
			'tfa_trusted_days'           => 30,
			'tfa_recovery_count'         => 10,

			// Security headers.
			'header_x_frame_options'     => true,
			'header_x_frame_value'       => 'SAMEORIGIN',
			'header_x_content_type'      => true,
			'header_referrer_policy'     => true,
			'header_referrer_value'      => 'strict-origin-when-cross-origin',
			'header_hsts'                => false,
			'header_hsts_max_age'        => 31536000,
			'header_csp'                 => true,
			'header_csp_report_only'     => true,
			'header_csp_policy'          => "default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:; font-src 'self' data:;",

			// File hardening.
			'show_htaccess_snippets'     => true,
			'show_permission_checks'     => true,

			// Audit log.
			'audit_retention_days'       => 90,
			'audit_truncate_ip'          => false,
			'audit_notify_email'         => '',
			'audit_notify_webhook'       => '',
			'audit_lockout_threshold'    => 5,
			'audit_lockout_window'       => 600,

			// Core integrity.
			'core_check_on_dashboard'    => true,
		);
	}

	/**
	 * Get all settings merged with defaults.
	 *
	 * @return array<string, mixed>
	 */
	public static function all(): array {
		if ( null !== self::$cache ) {
			return self::$cache;
		}

		$stored      = get_option( SENTRYGATE_OPTION_KEY, array() );
		self::$cache = wp_parse_args( is_array( $stored ) ? $stored : array(), self::defaults() );

		return self::$cache;
	}

	/**
	 * Get a single option.
	 *
	 * @param string $key     Option key.
	 * @param mixed  $default Fallback.
	 * @return mixed
	 */
	public static function get( string $key, mixed $default = null ): mixed {
		$all = self::all();

		if ( array_key_exists( $key, $all ) ) {
			return $all[ $key ];
		}

		return $default;
	}

	/**
	 * Whether a module is enabled.
	 *
	 * @param string $module Module slug without prefix.
	 */
	public static function module_enabled( string $module ): bool {
		return (bool) self::get( 'module_' . $module, false );
	}

	/**
	 * Update settings (partial merge).
	 *
	 * @param array<string, mixed> $settings New values.
	 */
	public static function update( array $settings ): void {
		$merged      = wp_parse_args( $settings, self::all() );
		self::$cache = $merged;
		update_option( SENTRYGATE_OPTION_KEY, $merged );
	}

	/**
	 * Clear cache (e.g. after Settings API save).
	 */
	public static function flush_cache(): void {
		self::$cache = null;
	}

	/**
	 * Sanitize full settings array from Settings API.
	 *
	 * @param mixed $input Raw input.
	 * @return array<string, mixed>
	 */
	public static function sanitize( mixed $input ): array {
		$defaults = self::defaults();
		$current  = self::all();
		$input    = is_array( $input ) ? $input : array();
		$output   = $current;

		$bool_keys = array(
			'module_interface_hardening',
			'module_debug_control',
			'module_login_path',
			'module_login_challenge',
			'module_form_challenge',
			'module_two_factor',
			'module_security_headers',
			'module_file_hardening',
			'module_audit_log',
			'module_core_integrity',
			'disable_xmlrpc',
			'block_user_enumeration',
			'disallow_file_edit',
			'remove_wp_version',
			'restrict_rest_api',
			'enforce_production_debug',
			'redirect_admin',
			'rate_limit_exponential',
			'tfa_email_fallback',
			'trust_proxy_headers',
			'header_x_frame_options',
			'header_x_content_type',
			'header_referrer_policy',
			'header_hsts',
			'header_csp',
			'header_csp_report_only',
			'show_htaccess_snippets',
			'show_permission_checks',
			'audit_truncate_ip',
			'core_check_on_dashboard',
		);

		foreach ( $bool_keys as $key ) {
			// Checkboxes: absent means false when form submitted for that section.
			if ( array_key_exists( $key, $input ) ) {
				$output[ $key ] = (bool) $input[ $key ];
			}
		}

		// Explicit module toggles from dashboard may send 0/1.
		foreach ( $defaults as $key => $default_value ) {
			if ( ! str_starts_with( $key, 'module_' ) ) {
				continue;
			}
			if ( isset( $input[ $key ] ) ) {
				$output[ $key ] = (bool) (int) $input[ $key ];
			}
		}

		if ( isset( $input['rest_api_whitelist'] ) ) {
			$lines = is_array( $input['rest_api_whitelist'] )
				? $input['rest_api_whitelist']
				: preg_split( '/[\r\n,]+/', (string) $input['rest_api_whitelist'] );
			$output['rest_api_whitelist'] = array_values(
				array_filter(
					array_map(
						static function ( $line ) {
							return sanitize_text_field( trim( (string) $line ) );
						},
						(array) $lines
					)
				)
			);
		}

		if ( isset( $input['login_slug'] ) ) {
			$slug = sanitize_title( (string) $input['login_slug'] );
			$output['login_slug'] = $slug !== '' && ! in_array( $slug, array( 'wp-admin', 'wp-login', 'admin', 'login' ), true )
				? $slug
				: $defaults['login_slug'];
		}

		if ( isset( $input['challenge_mode'] ) ) {
			$output['challenge_mode'] = in_array( $input['challenge_mode'], array( 'full', 'lightweight' ), true )
				? $input['challenge_mode']
				: 'full';
		}

		$int_keys = array(
			'challenge_min_time'      => array( 0, 60 ),
			'rate_limit_max_attempts' => array( 1, 100 ),
			'rate_limit_window'       => array( 60, 86400 ),
			'rate_limit_lockout'      => array( 60, 86400 ),
			'tfa_trusted_days'        => array( 1, 365 ),
			'tfa_recovery_count'      => array( 8, 10 ),
			'header_hsts_max_age'     => array( 300, 63072000 ),
			'audit_retention_days'    => array( 7, 730 ),
			'audit_lockout_threshold' => array( 1, 100 ),
			'audit_lockout_window'    => array( 60, 86400 ),
		);

		foreach ( $int_keys as $key => $range ) {
			if ( isset( $input[ $key ] ) ) {
				$output[ $key ] = max( $range[0], min( $range[1], (int) $input[ $key ] ) );
			}
		}

		if ( isset( $input['header_x_frame_value'] ) ) {
			$val = strtoupper( sanitize_text_field( (string) $input['header_x_frame_value'] ) );
			$output['header_x_frame_value'] = in_array( $val, array( 'DENY', 'SAMEORIGIN' ), true ) ? $val : 'SAMEORIGIN';
		}

		if ( isset( $input['header_referrer_value'] ) ) {
			$allowed = array(
				'no-referrer',
				'no-referrer-when-downgrade',
				'origin',
				'origin-when-cross-origin',
				'same-origin',
				'strict-origin',
				'strict-origin-when-cross-origin',
				'unsafe-url',
			);
			$val = sanitize_text_field( (string) $input['header_referrer_value'] );
			$output['header_referrer_value'] = in_array( $val, $allowed, true ) ? $val : $defaults['header_referrer_value'];
		}

		if ( isset( $input['header_csp_policy'] ) ) {
			// Newlines aus CSP entfernen (Header-Injection über Admin-Einstellung).
			$csp = sanitize_textarea_field( (string) $input['header_csp_policy'] );
			$output['header_csp_policy'] = trim( str_replace( array( "\r", "\n" ), ' ', $csp ) );
		}

		if ( isset( $input['tfa_required_roles'] ) || ! empty( $input['tfa_roles_section'] ) ) {
			$roles    = isset( $input['tfa_required_roles'] ) && is_array( $input['tfa_required_roles'] ) ? $input['tfa_required_roles'] : array();
			$wp_roles = wp_roles()->get_names();
			$output['tfa_required_roles'] = array_values(
				array_filter(
					array_map( 'sanitize_key', $roles ),
					static function ( $role ) use ( $wp_roles ) {
						return isset( $wp_roles[ $role ] );
					}
				)
			);
		}

		if ( isset( $input['audit_notify_email'] ) ) {
			$email = sanitize_email( (string) $input['audit_notify_email'] );
			$output['audit_notify_email'] = is_email( $email ) ? $email : '';
		}

		if ( isset( $input['audit_notify_webhook'] ) ) {
			$url = esc_url_raw( (string) $input['audit_notify_webhook'] );
			$output['audit_notify_webhook'] = $url;
		}

		self::$cache = null;

		/**
		 * Fires after settings sanitization (e.g. flush rewrite for login slug).
		 *
		 * @param array<string, mixed> $output Sanitized settings.
		 * @param array<string, mixed> $current Previous settings.
		 */
		do_action( 'sentrygate_settings_sanitized', $output, $current );

		return $output;
	}

	/**
	 * Export configuration as JSON-serializable array (no secrets).
	 *
	 * @return array<string, mixed>
	 */
	public static function export(): array {
		$data = self::all();
		// Never export challenge pool attachment IDs that may be site-specific secrets context – keep them.
		return array(
			'version'    => SENTRYGATE_VERSION,
			'exported_at'=> gmdate( 'c' ),
			'settings'   => $data,
		);
	}
}
