<?php
/**
 * Rate limiting and lockout handling.
 *
 * @package SentryGate
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SentryGate_Rate_Limit
 */
class SentryGate_Rate_Limit {

	/**
	 * Check if an IP is currently locked out.
	 *
	 * @param string $ip IP address.
	 */
	public static function is_locked( string $ip ): bool {
		global $wpdb;

		$table = $wpdb->prefix . 'sentrygate_lockouts';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT locked_until FROM %i WHERE ip_address = %s AND locked_until IS NOT NULL AND locked_until > UTC_TIMESTAMP() ORDER BY locked_until DESC LIMIT 1',
				$table,
				$ip
			)
		);

		return null !== $row;
	}

	/**
	 * Remaining lockout seconds.
	 *
	 * @param string $ip IP address.
	 */
	public static function remaining_seconds( string $ip ): int {
		global $wpdb;

		$table = $wpdb->prefix . 'sentrygate_lockouts';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$until = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT locked_until FROM %i WHERE ip_address = %s AND locked_until IS NOT NULL AND locked_until > UTC_TIMESTAMP() ORDER BY locked_until DESC LIMIT 1',
				$table,
				$ip
			)
		);

		if ( ! $until ) {
			return 0;
		}

		return max( 0, strtotime( $until . ' UTC' ) - time() );
	}

	/**
	 * Record a failed attempt; lock if threshold exceeded.
	 *
	 * @param string $ip       IP address.
	 * @param string $username Attempted username.
	 * @return bool True if newly locked.
	 */
	public static function record_failure( string $ip, string $username = '' ): bool {
		global $wpdb;

		$table      = $wpdb->prefix . 'sentrygate_lockouts';
		$max        = (int) SentryGate_Options::get( 'rate_limit_max_attempts', 5 );
		$window     = (int) SentryGate_Options::get( 'rate_limit_window', 900 );
		$base_lock  = (int) SentryGate_Options::get( 'rate_limit_lockout', 900 );
		$exponential = (bool) SentryGate_Options::get( 'rate_limit_exponential', true );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE ip_address = %s ORDER BY id DESC LIMIT 1',
				$table,
				$ip
			),
			ARRAY_A
		);

		$now = gmdate( 'Y-m-d H:i:s' );

		if ( ! $row || ( strtotime( $row['updated_at'] . ' UTC' ) < ( time() - $window ) ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->insert(
				$table,
				array(
					'ip_address'   => $ip,
					'username'     => sanitize_user( $username ),
					'attempts'     => 1,
					'locked_until' => null,
					'created_at'   => $now,
					'updated_at'   => $now,
				),
				array( '%s', '%s', '%d', '%s', '%s', '%s' )
			);
			return false;
		}

		$attempts = (int) $row['attempts'] + 1;
		$locked   = null;
		$is_lock  = false;

		if ( $attempts >= $max ) {
			$multiplier = $exponential ? (int) pow( 2, max( 0, (int) floor( ( $attempts - $max ) / $max ) ) ) : 1;
			$multiplier = min( $multiplier, 16 );
			$lock_secs  = $base_lock * $multiplier;
			$locked     = gmdate( 'Y-m-d H:i:s', time() + $lock_secs );
			$is_lock    = true;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			$table,
			array(
				'username'     => sanitize_user( $username ),
				'attempts'     => $attempts,
				'locked_until' => $locked,
				'updated_at'   => $now,
			),
			array( 'id' => (int) $row['id'] ),
			array( '%s', '%d', '%s', '%s' ),
			array( '%d' )
		);

		if ( $is_lock && class_exists( 'SentryGate_Audit_Log' ) ) {
			SentryGate_Audit_Log::log(
				'login_lockout',
				'critical',
				sprintf(
					/* translators: 1: IP, 2: username */
					__( 'Login lockout for IP %1$s (user: %2$s).', 'wiedenroth-technologies-sentrygate' ),
					$ip,
					$username ?: '-'
				),
				array(
					'ip'       => $ip,
					'username' => $username,
					'attempts' => $attempts,
				)
			);
		}

		return $is_lock;
	}

	/**
	 * Clear failures for IP after successful auth.
	 *
	 * @param string $ip IP address.
	 */
	public static function clear( string $ip ): void {
		global $wpdb;

		$table = $wpdb->prefix . 'sentrygate_lockouts';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete( $table, array( 'ip_address' => $ip ), array( '%s' ) );
	}
}
