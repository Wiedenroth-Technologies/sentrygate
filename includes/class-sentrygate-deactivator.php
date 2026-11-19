<?php
/**
 * Plugin deactivator.
 *
 * @package SentryGate
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SentryGate_Deactivator
 */
class SentryGate_Deactivator {

	/**
	 * Run on plugin deactivation.
	 */
	public static function deactivate(): void {
		$timestamp = wp_next_scheduled( 'sentrygate_cleanup_logs' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'sentrygate_cleanup_logs' );
		}

		flush_rewrite_rules();
	}
}
