<?php
/**
 * Audit log settings & recent entries.
 *
 * @package SentryGate
 *
 * @var SentryGate_Admin_Ui         $ui
 * @var array<string,mixed> $options
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$entries = SentryGate_Options::module_enabled( 'audit_log' ) ? SentryGate_Audit_Log::recent( 40 ) : array();
?>
<form method="post" action="options.php">
	<?php settings_fields( 'sentrygate_settings_group' ); ?>

	<div class="<?php echo esc_attr( $ui->cls( 'card' ) ); ?>">
		<?php
		$ui->heading(
			__( 'What happened on the site', 'wiedenroth-technologies-sentrygate' ),
			__( 'Here you can see logins, failed attempts, and lockouts. Useful when you want to check whether someone is rattling the door – without complicated server logs.', 'wiedenroth-technologies-sentrygate' ),
			__( 'Fill in an email or webhook only if you want to be notified when there are many lockouts.', 'wiedenroth-technologies-sentrygate' )
		);

		SentryGate_Admin::field_number(
			$ui,
			'audit_retention_days',
			$options['audit_retention_days'] ?? 90,
			__( 'How long to keep?', 'wiedenroth-technologies-sentrygate' ),
			__( 'Days. Older entries are deleted automatically. 90 days is a good compromise.', 'wiedenroth-technologies-sentrygate' ),
			7,
			730
		);

		SentryGate_Admin::field_toggle(
			$ui,
			'audit_truncate_ip',
			$options['audit_truncate_ip'] ?? false,
			__( 'Shorten IP addresses?', 'wiedenroth-technologies-sentrygate' ),
			__( 'Yes, mask the last segment (privacy-friendly)', 'wiedenroth-technologies-sentrygate' ),
			__( 'Helps with privacy. But it makes it harder to pinpoint an attacker.', 'wiedenroth-technologies-sentrygate' )
		);
		?>

		<div class="<?php echo esc_attr( $ui->cls( 'field' ) ); ?>">
			<span class="<?php echo esc_attr( $ui->cls( 'field__label' ) ); ?>"><?php esc_html_e( 'Alert on many lockouts', 'wiedenroth-technologies-sentrygate' ); ?></span>
			<div class="<?php echo esc_attr( $ui->cls( 'field__control' ) ); ?>">
				<div class="<?php echo esc_attr( $ui->cls( 'inline-fields' ) ); ?>">
					<label for="<?php echo esc_attr( SentryGate_Admin::field_id( 'audit_notify_email' ) ); ?>">
						<?php esc_html_e( 'Email address', 'wiedenroth-technologies-sentrygate' ); ?>
						<?php SentryGate_Admin::text( $ui, 'audit_notify_email', $options['audit_notify_email'] ?? '', 'email' ); ?>
					</label>
					<label for="<?php echo esc_attr( SentryGate_Admin::field_id( 'audit_notify_webhook' ) ); ?>">
						<?php esc_html_e( 'Webhook URL (optional)', 'wiedenroth-technologies-sentrygate' ); ?>
						<?php SentryGate_Admin::text( $ui, 'audit_notify_webhook', $options['audit_notify_webhook'] ?? '', 'url' ); ?>
					</label>
					<label for="<?php echo esc_attr( SentryGate_Admin::field_id( 'audit_lockout_threshold' ) ); ?>">
						<?php esc_html_e( 'Alert after this many lockouts', 'wiedenroth-technologies-sentrygate' ); ?>
						<?php SentryGate_Admin::number( $ui, 'audit_lockout_threshold', $options['audit_lockout_threshold'] ?? 5, 1, 100 ); ?>
					</label>
					<label for="<?php echo esc_attr( SentryGate_Admin::field_id( 'audit_lockout_window' ) ); ?>">
						<?php esc_html_e( '… within this many seconds', 'wiedenroth-technologies-sentrygate' ); ?>
						<?php SentryGate_Admin::number( $ui, 'audit_lockout_window', $options['audit_lockout_window'] ?? 600, 60, 86400 ); ?>
					</label>
				</div>
				<?php $ui->help( __( 'Leave empty = no extra notification. 600 seconds = 10 minutes.', 'wiedenroth-technologies-sentrygate' ) ); ?>
			</div>
		</div>
	</div>

	<?php SentryGate_Admin::save_actions( $ui ); ?>
</form>

<?php if ( ! SentryGate_Options::module_enabled( 'audit_log' ) ) : ?>
	<div class="<?php echo esc_attr( $ui->cls( 'card' ) ); ?>">
		<div class="<?php echo esc_attr( $ui->cls( 'empty' ) ); ?>">
			<div class="<?php echo esc_attr( $ui->cls( 'empty__icon' ) ); ?>" aria-hidden="true">◇</div>
			<h2 class="<?php echo esc_attr( $ui->cls( 'empty__title' ) ); ?>"><?php esc_html_e( 'Module is off', 'wiedenroth-technologies-sentrygate' ); ?></h2>
			<p class="<?php echo esc_attr( $ui->cls( 'empty__text' ) ); ?>">
				<?php esc_html_e( 'Turn it on under "Modules on/off" so events are recorded.', 'wiedenroth-technologies-sentrygate' ); ?>
			</p>
			<?php $ui->button( __( 'Open modules', 'wiedenroth-technologies-sentrygate' ), 'primary', 'button', admin_url( 'admin.php?page=' . SENTRYGATE_SLUG . '&tab=modules' ) ); ?>
		</div>
	</div>
<?php elseif ( empty( $entries ) ) : ?>
	<div class="<?php echo esc_attr( $ui->cls( 'card' ) ); ?>">
		<div class="<?php echo esc_attr( $ui->cls( 'empty' ) ); ?>">
			<div class="<?php echo esc_attr( $ui->cls( 'empty__icon' ) ); ?>" aria-hidden="true">◇</div>
			<h2 class="<?php echo esc_attr( $ui->cls( 'empty__title' ) ); ?>"><?php esc_html_e( 'No entries yet', 'wiedenroth-technologies-sentrygate' ); ?></h2>
			<p class="<?php echo esc_attr( $ui->cls( 'empty__text' ) ); ?>">
				<?php esc_html_e( 'Either it was quiet, or the module was only just turned on.', 'wiedenroth-technologies-sentrygate' ); ?>
			</p>
		</div>
	</div>
<?php else : ?>
	<div class="<?php echo esc_attr( $ui->cls( 'card', 'flush' ) ); ?>">
		<?php $ui->heading( __( 'Recent events', 'wiedenroth-technologies-sentrygate' ) ); ?>
		<div class="<?php echo esc_attr( $ui->cls( 'table-wrap' ) ); ?>">
			<table class="<?php echo esc_attr( $ui->cls( 'table' ) ); ?>">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Date', 'wiedenroth-technologies-sentrygate' ); ?></th>
						<th><?php esc_html_e( 'Type', 'wiedenroth-technologies-sentrygate' ); ?></th>
						<th><?php esc_html_e( 'Level', 'wiedenroth-technologies-sentrygate' ); ?></th>
						<th><?php esc_html_e( 'What happened', 'wiedenroth-technologies-sentrygate' ); ?></th>
						<th><?php esc_html_e( 'IP', 'wiedenroth-technologies-sentrygate' ); ?></th>
						<th><?php esc_html_e( 'User', 'wiedenroth-technologies-sentrygate' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $entries as $entry ) : ?>
						<tr>
							<td><?php echo esc_html( SentryGate_Audit_Log::format_created_at( (string) $entry->created_at ) ); ?></td>
							<td><code><?php echo esc_html( (string) $entry->event_type ); ?></code></td>
							<td><?php echo esc_html( (string) $entry->severity ); ?></td>
							<td><?php echo esc_html( (string) $entry->message ); ?></td>
							<td><?php echo esc_html( (string) $entry->ip_address ); ?></td>
							<td><?php echo esc_html( (string) $entry->username ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	</div>
<?php endif; ?>
