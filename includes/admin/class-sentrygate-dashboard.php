<?php
/**
 * WP Dashboard widget with traffic-light status.
 *
 * @package SentryGate
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SentryGate_Dashboard
 */
class SentryGate_Dashboard {

	/**
	 * Register dashboard widget.
	 */
	public function register_widget(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		wp_add_dashboard_widget(
			'sentrygate_status_widget',
			'SentryGate – Security status',
			array( $this, 'render_widget' )
		);
	}

	/**
	 * Collect all module statuses.
	 *
	 * @return array<int, array{status: string, label: string, message: string}>
	 */
	public static function all_statuses(): array {
		$statuses = array(
			SentryGate_Interface_Hardening::status(),
			SentryGate_Debug_Control::status(),
			SentryGate_Login_Path::status(),
			SentryGate_Login_Challenge::status(),
			SentryGate_Two_Factor::status(),
			SentryGate_Security_Headers::status(),
			SentryGate_File_Hardening::status(),
			SentryGate_Audit_Log::status(),
		);

		$form = SentryGate_Form_Challenge::status();
		if ( null !== $form ) {
			array_splice( $statuses, 4, 0, array( $form ) );
		}

		if ( SentryGate_Options::get( 'core_check_on_dashboard', true ) ) {
			$statuses[] = SentryGate_Core_Integrity::status();
		}

		return $statuses;
	}

	/**
	 * Render widget content.
	 */
	public function render_widget(): void {
		echo '<p class="description">' . esc_html__( 'Green = active, yellow = check, red = off. See details under SentryGate.', 'wiedenroth-technologies-sentrygate' ) . '</p>';
		$statuses = self::all_statuses();
		echo '<table class="widefat striped"><tbody>';
		foreach ( $statuses as $row ) {
			printf(
				'<tr><td><span class="sentrygate-ampel sentrygate-ampel--%1$s" aria-hidden="true"></span> %2$s</td><td>%3$s</td></tr>',
				esc_attr( $row['status'] ),
				esc_html( $row['label'] ),
				esc_html( $row['message'] )
			);
		}
		echo '</tbody></table>';
		echo '<p><a class="button" href="' . esc_url( admin_url( 'admin.php?page=' . SENTRYGATE_SLUG ) ) . '">' . esc_html__( 'Open all settings', 'wiedenroth-technologies-sentrygate' ) . '</a></p>';
	}
}
