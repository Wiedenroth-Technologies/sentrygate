<?php
/**
 * Plugin activator.
 *
 * @package SentryGate
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SentryGate_Activator
 */
class SentryGate_Activator {

	/**
	 * Run on plugin activation.
	 */
	public static function activate(): void {
		$had_settings = false !== get_option( SENTRYGATE_OPTION_KEY, false );

		self::create_tables();
		self::set_defaults();
		self::prepare_setup( $had_settings );
		self::schedule_events();

		update_option( 'sentrygate_db_version', SENTRYGATE_DB_VERSION );
		flush_rewrite_rules();
	}

	/**
	 * Create custom database tables.
	 */
	private static function create_tables(): void {
		global $wpdb;

		$charset = $wpdb->get_charset_collate();
		$audit   = $wpdb->prefix . 'sentrygate_audit_log';
		$lockout = $wpdb->prefix . 'sentrygate_lockouts';

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$sql_audit = "CREATE TABLE {$audit} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			event_type varchar(64) NOT NULL DEFAULT '',
			severity varchar(64) NOT NULL DEFAULT '',
			message text NOT NULL,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			username varchar(191) NOT NULL DEFAULT '',
			ip_address varchar(45) NOT NULL DEFAULT '',
			user_agent varchar(255) NOT NULL DEFAULT '',
			meta longtext NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY event_type (event_type),
			KEY severity (severity),
			KEY created_at (created_at),
			KEY ip_address (ip_address)
		) {$charset};";

		$sql_lockout = "CREATE TABLE {$lockout} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			ip_address varchar(45) NOT NULL DEFAULT '',
			username varchar(191) NOT NULL DEFAULT '',
			attempts int(11) NOT NULL DEFAULT 0,
			locked_until datetime NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY ip_address (ip_address),
			KEY locked_until (locked_until)
		) {$charset};";

		dbDelta( $sql_audit );
		dbDelta( $sql_lockout );
	}

	/**
	 * Persist default settings if none exist.
	 */
	private static function set_defaults(): void {
		if ( false === get_option( SENTRYGATE_OPTION_KEY ) ) {
			add_option( SENTRYGATE_OPTION_KEY, SentryGate_Options::defaults() );
		}
	}

	/**
	 * First-run setup: fresh installs open the wizard; upgrades skip it.
	 *
	 * No activation redirect for network-wide or bulk activation.
	 *
	 * @param bool $had_settings Whether settings already existed before this activation.
	 */
	private static function prepare_setup( bool $had_settings ): void {
		if ( false === get_option( 'sentrygate_setup_completed', false ) ) {
			add_option( 'sentrygate_setup_completed', $had_settings ? '1' : '0', '', false );
		}

		if ( '1' === (string) get_option( 'sentrygate_setup_completed', '0' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Activation context only.
		if ( ! empty( $_GET['networkwide'] ) || is_network_admin() ) {
			return;
		}

		// Bulk plugin activation sends multiple "checked" plugins.
		$checked = array();
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Activation context only.
		if ( isset( $_REQUEST['checked'] ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized via array_map below.
			$checked_raw = wp_unslash( $_REQUEST['checked'] );
			$checked     = is_array( $checked_raw ) ? array_map( 'sanitize_text_field', $checked_raw ) : array();
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		if ( count( $checked ) > 1 ) {
			return;
		}

		set_transient( 'sentrygate_activation_redirect', '1', 60 );
	}

	/**
	 * Schedule recurring cleanup.
	 */
	private static function schedule_events(): void {
		if ( ! wp_next_scheduled( 'sentrygate_cleanup_logs' ) ) {
			wp_schedule_event( time(), 'daily', 'sentrygate_cleanup_logs' );
		}
	}
}
