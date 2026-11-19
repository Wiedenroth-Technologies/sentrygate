<?php
/**
 * Main plugin bootstrap.
 *
 * @package SentryGate
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SentryGate
 */
final class SentryGate {

	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Hook loader.
	 *
	 * @var SentryGate_Loader
	 */
	private SentryGate_Loader $loader;

	/**
	 * Shared admin UI helper.
	 *
	 * @var Wt_Admin_Ui
	 */
	private Wt_Admin_Ui $ui;

	/**
	 * Get singleton.
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->loader = new SentryGate_Loader();
		$this->ui     = new Wt_Admin_Ui(
			SENTRYGATE_SLUG,
			SENTRYGATE_PLUGIN_DIR . 'assets/css/wt-admin.css',
			SENTRYGATE_PLUGIN_URL . 'assets/js/wt-admin.js',
			SENTRYGATE_VERSION
		);
		$this->define_admin_hooks();
		$this->define_module_hooks();
		$this->define_cron_hooks();
	}

	/**
	 * Prevent cloning.
	 */
	private function __clone() {}

	/**
	 * Prevent unserialize.
	 *
	 * @throws \Exception Always.
	 */
	public function __wakeup(): void {
		throw new \Exception( 'Cannot unserialize singleton.' );
	}

	/**
	 * Register admin hooks.
	 */
	private function define_admin_hooks(): void {
		$i18n = new SentryGate_i18n();
		$this->loader->add_action( 'plugins_loaded', $i18n, 'load_plugin_textdomain' );

		$this->ui->register_admin_hooks();

		$admin = new SentryGate_Admin( $this->ui );
		$this->loader->add_action( 'admin_menu', $admin, 'add_menu' );
		$this->loader->add_action( 'admin_init', $admin, 'register_settings' );
		$this->loader->add_action( 'admin_enqueue_scripts', $admin, 'enqueue_assets' );
		$this->loader->add_action( 'in_admin_footer', $admin, 'render_wp_footer_brand' );
		$this->loader->add_action( 'admin_post_sentrygate_export_config', $admin, 'handle_export' );
		$this->loader->add_action( 'admin_post_sentrygate_import_config', $admin, 'handle_import' );
		$this->loader->add_filter( 'plugin_action_links_' . SENTRYGATE_PLUGIN_BASENAME, $admin, 'action_links' );

		$dashboard = new SentryGate_Dashboard();
		$this->loader->add_action( 'wp_dashboard_setup', $dashboard, 'register_widget' );
	}

	/**
	 * Register feature modules (each self-registers based on options).
	 */
	private function define_module_hooks(): void {
		$modules = array(
			new SentryGate_Interface_Hardening(),
			new SentryGate_Debug_Control(),
			new SentryGate_Login_Path(),
			new SentryGate_Login_Challenge(),
			new SentryGate_Form_Challenge(),
			new SentryGate_Two_Factor(),
			new SentryGate_Security_Headers(),
			new SentryGate_File_Hardening(),
			new SentryGate_Audit_Log(),
			new SentryGate_Core_Integrity(),
		);

		foreach ( $modules as $module ) {
			if ( method_exists( $module, 'register' ) ) {
				$module->register( $this->loader );
			}
		}
	}

	/**
	 * Cron cleanup.
	 */
	private function define_cron_hooks(): void {
		$this->loader->add_action( 'sentrygate_cleanup_logs', 'SentryGate_Audit_Log', 'cleanup_expired' );
		$this->loader->add_action( 'sentrygate_settings_sanitized', $this, 'maybe_flush_rewrites', 10, 2 );
	}

	/**
	 * Flush rewrite rules when login slug changes.
	 *
	 * @param array<string, mixed> $new New settings.
	 * @param array<string, mixed> $old Old settings.
	 */
	public function maybe_flush_rewrites( array $new, array $old ): void {
		$slug_changed = ( $new['login_slug'] ?? '' ) !== ( $old['login_slug'] ?? '' );
		$mod_changed  = (bool) ( $new['module_login_path'] ?? false ) !== (bool) ( $old['module_login_path'] ?? false );

		if ( $slug_changed || $mod_changed ) {
			update_option( 'sentrygate_flush_rewrite', 1 );
		}
	}

	/**
	 * Run the loader.
	 */
	public function run(): void {
		$this->loader->run();

		add_action(
			'init',
			static function (): void {
				if ( get_option( 'sentrygate_flush_rewrite' ) ) {
					flush_rewrite_rules();
					delete_option( 'sentrygate_flush_rewrite' );
				}
			},
			99
		);
	}

	/**
	 * Expose loader for modules.
	 */
	public function get_loader(): SentryGate_Loader {
		return $this->loader;
	}

	/**
	 * Expose the UI helper.
	 */
	public function ui(): Wt_Admin_Ui {
		return $this->ui;
	}
}
