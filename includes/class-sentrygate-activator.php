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
		self::create_tables();
		self::set_defaults();
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
	 * Schedule recurring cleanup.
	 */
	private static function schedule_events(): void {
		if ( ! wp_next_scheduled( 'sentrygate_cleanup_logs' ) ) {
			wp_schedule_event( time(), 'daily', 'sentrygate_cleanup_logs' );
		}
	}
}
