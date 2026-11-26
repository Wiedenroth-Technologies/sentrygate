<?php
/**
 * Module registry, BlockForge detection, and admin navigation map.
 *
 * @package SentryGate
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SentryGate_Modules
 */
class SentryGate_Modules {

	/**
	 * Cached BlockForge detection result for the current request.
	 *
	 * @var bool|null
	 */
	private static ?bool $blockforge_active = null;

	/**
	 * Module definitions keyed by option suffix (without "module_").
	 *
	 * @return array<string, array{
	 *   option: string,
	 *   label: string,
	 *   desc: string,
	 *   tab: string,
	 *   recommended: bool,
	 *   requires_blockforge: bool
	 * }>
	 */
	public static function definitions(): array {
		return array(
			'interface_hardening' => array(
				'option'              => 'module_interface_hardening',
				'label'               => __( 'Basic protection', 'wiedenroth-technologies-sentrygate' ),
				'desc'                => __( 'Blocks known attack paths (XML-RPC, username guessing, file editor, version leaks).', 'wiedenroth-technologies-sentrygate' ),
				'tab'                 => 'interface',
				'recommended'         => true,
				'requires_blockforge' => false,
			),
			'debug_control'       => array(
				'option'              => 'module_debug_control',
				'label'               => __( 'Check error display', 'wiedenroth-technologies-sentrygate' ),
				'desc'                => __( 'Warns you when WordPress shows debug messages on a live site – it does not change your wp-config.php.', 'wiedenroth-technologies-sentrygate' ),
				'tab'                 => 'debug',
				'recommended'         => true,
				'requires_blockforge' => false,
			),
			'login_path'          => array(
				'option'              => 'module_login_path',
				'label'               => __( 'Hide the login URL', 'wiedenroth-technologies-sentrygate' ),
				'desc'                => __( 'Replaces /wp-login.php with a custom address. Unknown visitors see "Page not found".', 'wiedenroth-technologies-sentrygate' ),
				'tab'                 => 'login_path',
				'recommended'         => false,
				'requires_blockforge' => false,
			),
			'login_challenge'     => array(
				'option'              => 'module_login_challenge',
				'label'               => __( 'Login protection', 'wiedenroth-technologies-sentrygate' ),
				'desc'                => __( 'A check before login (symbol choice), then a session cookie. Direct POSTs without a cookie are rejected.', 'wiedenroth-technologies-sentrygate' ),
				'tab'                 => 'challenge',
				'recommended'         => true,
				'requires_blockforge' => false,
			),
			'form_challenge'      => array(
				'option'              => 'module_form_challenge',
				'label'               => __( 'Form protection', 'wiedenroth-technologies-sentrygate' ),
				'desc'                => __( 'The same symbol choice before submitting BlockForge forms.', 'wiedenroth-technologies-sentrygate' ),
				'tab'                 => 'forms',
				'recommended'         => true,
				'requires_blockforge' => true,
			),
			'two_factor'          => array(
				'option'              => 'module_two_factor',
				'label'               => __( 'Two-factor login', 'wiedenroth-technologies-sentrygate' ),
				'desc'                => __( 'An extra code via authenticator app or email. Set up under Users → Two-factor.', 'wiedenroth-technologies-sentrygate' ),
				'tab'                 => 'tfa',
				'recommended'         => false,
				'requires_blockforge' => false,
			),
			'security_headers'    => array(
				'option'              => 'module_security_headers',
				'label'               => __( 'Browser protection', 'wiedenroth-technologies-sentrygate' ),
				'desc'                => __( 'Tells the browser how the page may be loaded (for example, no embedding on other sites).', 'wiedenroth-technologies-sentrygate' ),
				'tab'                 => 'headers',
				'recommended'         => true,
				'requires_blockforge' => false,
			),
			'file_hardening'      => array(
				'option'              => 'module_file_hardening',
				'label'               => __( 'Files & server', 'wiedenroth-technologies-sentrygate' ),
				'desc'                => __( 'Shows recommendations for file permissions and upload protection. Does not write anything to the server automatically.', 'wiedenroth-technologies-sentrygate' ),
				'tab'                 => 'files',
				'recommended'         => true,
				'requires_blockforge' => false,
			),
			'audit_log'           => array(
				'option'              => 'module_audit_log',
				'label'               => __( 'Audit log', 'wiedenroth-technologies-sentrygate' ),
				'desc'                => __( 'Logs logins, lockouts, and important security events.', 'wiedenroth-technologies-sentrygate' ),
				'tab'                 => 'audit',
				'recommended'         => true,
				'requires_blockforge' => false,
			),
			'core_integrity'      => array(
				'option'              => 'module_core_integrity',
				'label'               => __( 'WordPress check', 'wiedenroth-technologies-sentrygate' ),
				'desc'                => __( 'Compares WordPress files with the original from wordpress.org. No automatic repair.', 'wiedenroth-technologies-sentrygate' ),
				'tab'                 => 'integrity',
				'recommended'         => true,
				'requires_blockforge' => false,
			),
		);
	}

	/**
	 * Modules that may be shown in the Modules UI (respects BlockForge).
	 *
	 * @return array<string, array{option: string, label: string, desc: string, tab: string, recommended: bool, requires_blockforge: bool}>
	 */
	public static function visible_definitions(): array {
		$out = array();
		foreach ( self::definitions() as $key => $meta ) {
			if ( ! empty( $meta['requires_blockforge'] ) && ! self::is_blockforge_active() ) {
				continue;
			}
			$out[ $key ] = $meta;
		}
		return $out;
	}

	/**
	 * Whether BlockForge is active and available for form protection.
	 *
	 * Detection order (matches Wiedenroth sibling plugins such as ConsentGate):
	 * 1. BLOCKFORGE_* constants
	 * 2. BlockForge class or blockforge() helper
	 * 3. Known plugin basenames via is_plugin_active()
	 * 4. Filter `sentrygate_is_blockforge_active` for tests / overrides
	 */
	public static function is_blockforge_active(): bool {
		if ( null !== self::$blockforge_active ) {
			return self::$blockforge_active;
		}

		$detected = false;

		if ( defined( 'BLOCKFORGE_VERSION' ) || defined( 'BLOCKFORGE_PLUGIN_FILE' ) || defined( 'BLOCKFORGE_SLUG' ) ) {
			$detected = true;
		} elseif ( class_exists( 'BlockForge', false ) ) {
			$detected = true;
		} elseif ( function_exists( 'blockforge' ) ) {
			$detected = true;
		} else {
			$basenames = array(
				'wiedenroth-technologies-blockforge/wiedenroth-technologies-blockforge.php',
				'wiedenroth-technologies-blockforge/blockforge.php',
				'wt-blockforge/wt-blockforge.php',
				'wt-blockforge/blockforge.php',
				'blockforge/blockforge.php',
			);

			if ( ! function_exists( 'is_plugin_active' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}

			foreach ( $basenames as $basename ) {
				if ( is_plugin_active( $basename ) ) {
					$detected = true;
					break;
				}
			}
		}

		/**
		 * Filter whether BlockForge is treated as active for SentryGate.
		 *
		 * @param bool $detected Detection result before the filter.
		 */
		self::$blockforge_active = (bool) apply_filters( 'sentrygate_is_blockforge_active', $detected );

		return self::$blockforge_active;
	}

	/**
	 * Reset cached BlockForge detection (tests).
	 */
	public static function flush_blockforge_cache(): void {
		self::$blockforge_active = null;
	}

	/**
	 * Whether a module is available in this environment (dependency check).
	 *
	 * @param string $module Module slug without prefix.
	 */
	public static function is_available( string $module ): bool {
		$defs = self::definitions();
		if ( ! isset( $defs[ $module ] ) ) {
			return false;
		}
		if ( ! empty( $defs[ $module ]['requires_blockforge'] ) ) {
			return self::is_blockforge_active();
		}
		return true;
	}

	/**
	 * Whether a module is available and switched on.
	 *
	 * @param string $module Module slug without prefix.
	 */
	public static function is_effectively_enabled( string $module ): bool {
		if ( ! self::is_available( $module ) ) {
			return false;
		}
		return SentryGate_Options::module_enabled( $module );
	}

	/**
	 * Admin tabs that should appear in navigation (label keyed by tab slug).
	 *
	 * Overview / modules / export are always present. Module tabs follow enablement.
	 *
	 * @return array<string, string>
	 */
	public static function navigation_tabs(): array {
		$tabs = array(
			'dashboard' => __( 'Overview', 'wiedenroth-technologies-sentrygate' ),
			'modules'   => __( 'Modules on/off', 'wiedenroth-technologies-sentrygate' ),
		);

		$tab_labels = array(
			'interface'  => __( 'Basic protection', 'wiedenroth-technologies-sentrygate' ),
			'debug'      => __( 'Error display', 'wiedenroth-technologies-sentrygate' ),
			'login_path' => __( 'Login URL', 'wiedenroth-technologies-sentrygate' ),
			'challenge'  => __( 'Bot protection', 'wiedenroth-technologies-sentrygate' ),
			'forms'      => __( 'Form protection', 'wiedenroth-technologies-sentrygate' ),
			'tfa'        => __( 'Two-factor', 'wiedenroth-technologies-sentrygate' ),
			'headers'    => __( 'Browser protection', 'wiedenroth-technologies-sentrygate' ),
			'files'      => __( 'Files & server', 'wiedenroth-technologies-sentrygate' ),
			'audit'      => __( 'Audit log', 'wiedenroth-technologies-sentrygate' ),
			'integrity'  => __( 'WordPress check', 'wiedenroth-technologies-sentrygate' ),
		);

		foreach ( self::definitions() as $module => $meta ) {
			$tab = $meta['tab'];
			if ( ! isset( $tab_labels[ $tab ] ) ) {
				continue;
			}
			if ( ! self::is_effectively_enabled( $module ) ) {
				continue;
			}
			$tabs[ $tab ] = $tab_labels[ $tab ];
		}

		$tabs['export'] = __( 'Backup / transfer', 'wiedenroth-technologies-sentrygate' );

		return $tabs;
	}

	/**
	 * Whether a settings tab may be viewed right now.
	 *
	 * @param string $tab Tab slug.
	 */
	public static function is_tab_accessible( string $tab ): bool {
		$always = array( 'dashboard', 'modules', 'export', 'setup' );
		if ( in_array( $tab, $always, true ) ) {
			return true;
		}

		$tabs = self::navigation_tabs();
		return isset( $tabs[ $tab ] );
	}

	/**
	 * Module key for a settings tab, or empty string.
	 *
	 * @param string $tab Tab slug.
	 */
	public static function module_for_tab( string $tab ): string {
		foreach ( self::definitions() as $module => $meta ) {
			if ( $meta['tab'] === $tab ) {
				return $module;
			}
		}
		return '';
	}
}
