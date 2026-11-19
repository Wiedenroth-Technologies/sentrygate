<?php
/**
 * Monitoring & audit log with optional notifications.
 *
 * @package SentryGate
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SentryGate_Audit_Log
 */
class SentryGate_Audit_Log {

	/**
	 * Register hooks.
	 *
	 * @param SentryGate_Loader $loader Loader.
	 */
	public function register( SentryGate_Loader $loader ): void {
		if ( ! SentryGate_Options::module_enabled( 'audit_log' ) ) {
			return;
		}

		$loader->add_action( 'wp_login', $this, 'on_login_success', 10, 2 );
		$loader->add_action( 'wp_login_failed', $this, 'on_login_failed', 10, 2 );
		$loader->add_action( 'user_register', $this, 'on_user_register' );
		$loader->add_action( 'set_user_role', $this, 'on_set_role', 10, 3 );
		$loader->add_action( 'activated_plugin', $this, 'on_plugin_activate' );
		$loader->add_action( 'deactivated_plugin', $this, 'on_plugin_deactivate' );
		$loader->add_action( 'switch_theme', $this, 'on_theme_switch', 10, 3 );
		$loader->add_action( 'upgrader_process_complete', $this, 'on_upgrade', 10, 2 );
	}

	/**
	 * Write an audit entry.
	 *
	 * @param string               $event_type Event type.
	 * @param string               $severity   info|warning|critical.
	 * @param string               $message    Message.
	 * @param array<string, mixed> $meta       Meta.
	 * @param int                  $user_id    User ID.
	 * @param string               $username   Username.
	 */
	public static function log(
		string $event_type,
		string $severity,
		string $message,
		array $meta = array(),
		int $user_id = 0,
		string $username = ''
	): void {
		if ( ! SentryGate_Options::module_enabled( 'audit_log' ) && 'login_lockout' !== $event_type ) {
			return;
		}

		global $wpdb;

		$ip = SentryGate_IP::maybe_truncate(
			SentryGate_IP::get(),
			(bool) SentryGate_Options::get( 'audit_truncate_ip', false )
		);

		$ua = isset( $_SERVER['HTTP_USER_AGENT'] )
			? substr( sanitize_text_field( wp_unslash( (string) $_SERVER['HTTP_USER_AGENT'] ) ), 0, 255 )
			: '';

		$table = $wpdb->prefix . 'sentrygate_audit_log';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert(
			$table,
			array(
				'event_type' => sanitize_key( $event_type ),
				'severity'   => sanitize_key( $severity ),
				'message'    => sanitize_text_field( $message ),
				'user_id'    => $user_id,
				'username'   => sanitize_user( $username ),
				'ip_address' => $ip,
				'user_agent' => $ua,
				'meta'       => wp_json_encode( $meta ),
				'created_at' => gmdate( 'Y-m-d H:i:s' ),
			),
			array( '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( 'critical' === $severity ) {
			self::maybe_notify( $event_type, $message, $meta );
		}

		if ( 'login_lockout' === $event_type ) {
			self::maybe_notify_lockout_burst();
		}
	}

	/**
	 * Successful login.
	 *
	 * @param string  $user_login Login.
	 * @param WP_User $user       User.
	 */
	public function on_login_success( string $user_login, WP_User $user ): void {
		self::log(
			'login_success',
			'info',
			sprintf(
				/* translators: %s: username */
				__( 'Erfolgreicher Login: %s', 'wiedenroth-technologies-sentrygate' ),
				$user_login
			),
			array(),
			(int) $user->ID,
			$user_login
		);
	}

	/**
	 * Failed login.
	 *
	 * @param string        $username Username.
	 * @param WP_Error|null $error    Auth error.
	 */
	public function on_login_failed( string $username, mixed $error = null ): void {
		if ( class_exists( 'SentryGate_Two_Factor' ) && SentryGate_Two_Factor::is_pending_error( $error ) ) {
			return;
		}

		self::log(
			'login_failed',
			'warning',
			sprintf(
				/* translators: %s: username */
				__( 'Fehlgeschlagener Login: %s', 'wiedenroth-technologies-sentrygate' ),
				$username
			),
			array(),
			0,
			$username
		);
	}

	/**
	 * New user registration – flag admins.
	 *
	 * @param int $user_id User ID.
	 */
	public function on_user_register( int $user_id ): void {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return;
		}

		$is_admin = user_can( $user, 'manage_options' );
		self::log(
			$is_admin ? 'admin_user_created' : 'user_created',
			$is_admin ? 'critical' : 'info',
			sprintf(
				/* translators: %s: username */
				__( 'Neuer Benutzer: %s', 'wiedenroth-technologies-sentrygate' ),
				$user->user_login
			),
			array( 'roles' => $user->roles ),
			$user_id,
			$user->user_login
		);
	}

	/**
	 * Role change – especially elevation to admin.
	 *
	 * @param int    $user_id User ID.
	 * @param string $role    New role.
	 * @param array  $old     Old roles.
	 */
	public function on_set_role( int $user_id, string $role, $old = array() ): void {
		$user     = get_userdata( $user_id );
		$critical = in_array( $role, array( 'administrator' ), true );
		self::log(
			'role_change',
			$critical ? 'critical' : 'warning',
			sprintf(
				/* translators: 1: user, 2: role */
				__( 'Rollenwechsel: %1$s → %2$s', 'wiedenroth-technologies-sentrygate' ),
				$user ? $user->user_login : (string) $user_id,
				$role
			),
			array( 'old' => $old, 'new' => $role ),
			$user_id,
			$user ? $user->user_login : ''
		);
	}

	/**
	 * Plugin activated.
	 *
	 * @param string $plugin Plugin path.
	 */
	public function on_plugin_activate( string $plugin ): void {
		self::log(
			'plugin_activate',
			'warning',
			sprintf(
				/* translators: %s: plugin */
				__( 'Plugin aktiviert: %s', 'wiedenroth-technologies-sentrygate' ),
				$plugin
			),
			array( 'plugin' => $plugin ),
			get_current_user_id()
		);
	}

	/**
	 * Plugin deactivated.
	 *
	 * @param string $plugin Plugin path.
	 */
	public function on_plugin_deactivate( string $plugin ): void {
		self::log(
			'plugin_deactivate',
			'info',
			sprintf(
				/* translators: %s: plugin */
				__( 'Plugin deaktiviert: %s', 'wiedenroth-technologies-sentrygate' ),
				$plugin
			),
			array( 'plugin' => $plugin ),
			get_current_user_id()
		);
	}

	/**
	 * Theme switch.
	 *
	 * @param string $new_name New theme name.
	 * @param mixed  $new      New theme.
	 * @param mixed  $old      Old theme.
	 */
	public function on_theme_switch( string $new_name, $new = null, $old = null ): void {
		unset( $new, $old );
		self::log(
			'theme_switch',
			'warning',
			sprintf(
				/* translators: %s: theme */
				__( 'Theme gewechselt: %s', 'wiedenroth-technologies-sentrygate' ),
				$new_name
			),
			array(),
			get_current_user_id()
		);
	}

	/**
	 * Core/plugin updates.
	 *
	 * @param mixed $upgrader Upgrader.
	 * @param array $options  Options.
	 */
	public function on_upgrade( $upgrader, array $options ): void {
		unset( $upgrader );
		$type = $options['type'] ?? 'unknown';
		self::log(
			'update_' . sanitize_key( (string) $type ),
			'warning',
			sprintf(
				/* translators: %s: update type */
				__( 'Update abgeschlossen: %s', 'wiedenroth-technologies-sentrygate' ),
				(string) $type
			),
			$options,
			get_current_user_id()
		);
	}

	/**
	 * Notify via email/webhook for critical events.
	 *
	 * @param string               $event_type Event.
	 * @param string               $message    Message.
	 * @param array<string, mixed> $meta       Meta.
	 */
	private static function maybe_notify( string $event_type, string $message, array $meta ): void {
		$email = (string) SentryGate_Options::get( 'audit_notify_email', '' );
		if ( $email && is_email( $email ) ) {
			wp_mail(
				$email,
				sprintf(
					/* translators: 1: site, 2: event */
					__( '[%1$s] SentryGate Alarm: %2$s', 'wiedenroth-technologies-sentrygate' ),
					wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
					$event_type
				),
				$message . "\n\n" . wp_json_encode( $meta )
			);
		}

		$webhook = (string) SentryGate_Options::get( 'audit_notify_webhook', '' );
		if ( $webhook ) {
			wp_remote_post(
				$webhook,
				array(
					'timeout' => 5,
					'headers' => array( 'Content-Type' => 'application/json' ),
					'body'    => wp_json_encode(
						array(
							'event'   => $event_type,
							'message' => $message,
							'meta'    => $meta,
							'site'    => home_url(),
							'time'    => gmdate( 'c' ),
						)
					),
				)
			);
		}
	}

	/**
	 * Notify when many lockouts occur in a short window.
	 */
	private static function maybe_notify_lockout_burst(): void {
		global $wpdb;

		$threshold = (int) SentryGate_Options::get( 'audit_lockout_threshold', 5 );
		$window    = (int) SentryGate_Options::get( 'audit_lockout_window', 600 );
		$table     = $wpdb->prefix . 'sentrygate_audit_log';
		$since     = gmdate( 'Y-m-d H:i:s', time() - $window );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE event_type = %s AND created_at >= %s",
				'login_lockout',
				$since
			)
		);

		if ( $count >= $threshold ) {
			$cache_key = 'sentrygate_lockout_burst_notified';
			if ( get_transient( $cache_key ) ) {
				return;
			}
			set_transient( $cache_key, 1, $window );
			self::maybe_notify(
				'lockout_burst',
				sprintf(
					/* translators: 1: count, 2: seconds */
					__( '%1$d Lockouts innerhalb von %2$d Sekunden.', 'wiedenroth-technologies-sentrygate' ),
					$count,
					$window
				),
				array( 'count' => $count, 'window' => $window )
			);
		}
	}

	/**
	 * Cleanup expired log rows (cron).
	 */
	public static function cleanup_expired(): void {
		global $wpdb;

		$days  = (int) SentryGate_Options::get( 'audit_retention_days', 90 );
		$table = $wpdb->prefix . 'sentrygate_audit_log';
		$cut   = gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE created_at < %s", $cut ) );

		$lockouts = $wpdb->prefix . 'sentrygate_lockouts';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$lockouts} WHERE updated_at < %s AND (locked_until IS NULL OR locked_until < UTC_TIMESTAMP())",
				$cut
			)
		);
	}

	/**
	 * Fetch recent entries for admin UI.
	 *
	 * @param int $limit Limit.
	 * @return array<int, object>
	 */
	/**
	 * Format a UTC datetime with the site timezone and WP date/time format.
	 *
	 * @param string $utc MySQL UTC datetime.
	 */
	public static function format_created_at( string $utc ): string {
		$utc = trim( $utc );
		if ( '' === $utc ) {
			return '';
		}

		$ts = strtotime( $utc . ' UTC' );
		if ( false === $ts ) {
			return $utc;
		}

		$format = trim( (string) get_option( 'date_format' ) . ' ' . (string) get_option( 'time_format' ) );
		if ( '' === $format ) {
			$format = 'Y-m-d H:i';
		}

		$label = wp_date( $format, $ts );
		return is_string( $label ) && '' !== $label ? $label : $utc;
	}

	/**
	 * Recent entries.
	 *
	 * @param int $limit Limit.
	 * @return array<int, object>
	 */
	public static function recent( int $limit = 50 ): array {
		global $wpdb;

		$table = $wpdb->prefix . 'sentrygate_audit_log';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} ORDER BY id DESC LIMIT %d",
				$limit
			)
		);

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Module status.
	 *
	 * @return array{status: string, label: string, message: string}
	 */
	public static function status(): array {
		if ( ! SentryGate_Options::module_enabled( 'audit_log' ) ) {
			return array(
				'status'  => 'inactive',
				'label'   => __( 'Ereignis-Protokoll', 'wiedenroth-technologies-sentrygate' ),
				'message' => __( 'Modul deaktiviert – keine Nachvollziehbarkeit kritischer Events.', 'wiedenroth-technologies-sentrygate' ),
			);
		}

		return array(
			'status'  => 'active',
			'label'   => __( 'Ereignis-Protokoll', 'wiedenroth-technologies-sentrygate' ),
			'message' => sprintf(
				/* translators: %d: retention days */
				__( 'Logging aktiv, Aufbewahrung %d Tage.', 'wiedenroth-technologies-sentrygate' ),
				(int) SentryGate_Options::get( 'audit_retention_days', 90 )
			),
		);
	}
}
