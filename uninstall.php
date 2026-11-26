<?php
/**
 * Uninstall routine – removes all SentryGate data.
 *
 * @package SentryGate
 */

declare(strict_types=1);

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

delete_option( 'sentrygate_settings' );
delete_option( 'sentrygate_db_version' );
delete_option( 'sentrygate_core_checksum_cache' );
delete_option( 'sentrygate_flush_rewrite' );
delete_option( 'sentrygate_setup_completed' );

delete_site_option( 'sentrygate_settings' );
delete_site_option( 'sentrygate_db_version' );
delete_site_option( 'sentrygate_setup_completed' );

// phpcs:ignore WordPress.DB.DirectDatabaseQuery
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}sentrygate_audit_log" );
// phpcs:ignore WordPress.DB.DirectDatabaseQuery
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}sentrygate_lockouts" );

// User meta for 2FA.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery
$wpdb->query(
	"DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE 'sentrygate\_%'"
);

// Transients.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery
$wpdb->query(
	"DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_sentrygate\_%' OR option_name LIKE '\_transient\_timeout\_sentrygate\_%'"
);

$sentrygate_timestamp = wp_next_scheduled( 'sentrygate_cleanup_logs' );
if ( $sentrygate_timestamp ) {
	wp_unschedule_event( $sentrygate_timestamp, 'sentrygate_cleanup_logs' );
}

flush_rewrite_rules();
