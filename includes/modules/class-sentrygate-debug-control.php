<?php
/**
 * WP_DEBUG control – advisory dashboard warnings, no wp-config rewriting.
 *
 * @package SentryGate
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SentryGate_Debug_Control
 */
class SentryGate_Debug_Control {

	/**
	 * Register hooks.
	 *
	 * @param SentryGate_Loader $loader Loader.
	 */
	public function register( SentryGate_Loader $loader ): void {
		if ( ! SentryGate_Options::module_enabled( 'debug_control' ) ) {
			return;
		}

		$loader->add_action( 'admin_notices', $this, 'admin_notice' );
	}

	/**
	 * Show admin notice when debug constants diverge from production recommendations.
	 */
	public function admin_notice(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( ! SentryGate_Options::get( 'enforce_production_debug' ) ) {
			return;
		}

		$issues = self::collect_issues();
		if ( empty( $issues ) ) {
			return;
		}

		echo '<div class="notice notice-warning"><p><strong>' . esc_html__( 'SentryGate debug check:', 'wiedenroth-technologies-sentrygate' ) . '</strong> ';
		echo esc_html( implode( ' ', $issues ) );
		echo ' ' . esc_html__( 'wp-config.php is not overwritten automatically – please check it manually.', 'wiedenroth-technologies-sentrygate' );
		echo '</p></div>';
	}

	/**
	 * Collect configuration issues.
	 *
	 * @return array<int, string>
	 */
	public static function collect_issues(): array {
		$issues = array();

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$issues[] = __( 'WP_DEBUG is active.', 'wiedenroth-technologies-sentrygate' );
		}
		if ( defined( 'WP_DEBUG_DISPLAY' ) && WP_DEBUG_DISPLAY ) {
			$issues[] = __( 'WP_DEBUG_DISPLAY is active (errors may be publicly visible).', 'wiedenroth-technologies-sentrygate' );
		}
		if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			$log_path = is_string( WP_DEBUG_LOG ) ? WP_DEBUG_LOG : ( WP_CONTENT_DIR . '/debug.log' );
			if ( is_readable( $log_path ) ) {
				// Check if likely web-accessible under wp-content.
				$issues[] = __( 'WP_DEBUG_LOG is active – make sure the log file is not publicly reachable.', 'wiedenroth-technologies-sentrygate' );
			}
		}

		return $issues;
	}

	/**
	 * Module status.
	 *
	 * @return array{status: string, label: string, message: string}
	 */
	public static function status(): array {
		if ( ! SentryGate_Options::module_enabled( 'debug_control' ) ) {
			return array(
				'status'  => 'inactive',
				'label'   => __( 'Error display', 'wiedenroth-technologies-sentrygate' ),
				'message' => __( 'Module disabled.', 'wiedenroth-technologies-sentrygate' ),
			);
		}

		$issues = self::collect_issues();
		if ( $issues ) {
			return array(
				'status'  => 'warning',
				'label'   => __( 'Error display', 'wiedenroth-technologies-sentrygate' ),
				'message' => implode( ' ', $issues ),
			);
		}

		return array(
			'status'  => 'active',
			'label'   => __( 'Error display', 'wiedenroth-technologies-sentrygate' ),
			'message' => __( 'Debug constants match the production recommendation.', 'wiedenroth-technologies-sentrygate' ),
		);
	}
}
