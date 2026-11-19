<?php
/**
 * Audit log settings & recent entries.
 *
 * @package SentryGate
 *
 * @var Wt_Admin_Ui         $ui
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
			__( 'Was auf der Website passiert ist', 'wiedenroth-technologies-sentrygate' ),
			__( 'Hier sieht man Logins, Fehlversuche und Sperren. Nützlich, wenn Sie prüfen wollen, ob jemand an der Tür rüttelt – ohne komplizierte Server-Logs.', 'wiedenroth-technologies-sentrygate' ),
			__( 'E-Mail oder Webhook nur ausfüllen, wenn Sie bei vielen Sperren benachrichtigt werden wollen.', 'wiedenroth-technologies-sentrygate' )
		);

		SentryGate_Admin::field_number(
			$ui,
			'audit_retention_days',
			$options['audit_retention_days'] ?? 90,
			__( 'Wie lange speichern?', 'wiedenroth-technologies-sentrygate' ),
			__( 'Tage. Ältere Einträge werden automatisch gelöscht. 90 Tage sind ein guter Kompromiss.', 'wiedenroth-technologies-sentrygate' ),
			7,
			730
		);

		SentryGate_Admin::field_toggle(
			$ui,
			'audit_truncate_ip',
			$options['audit_truncate_ip'] ?? false,
			__( 'IP-Adressen kürzen?', 'wiedenroth-technologies-sentrygate' ),
			__( 'Ja, letzte Stelle unkenntlich machen (datensparsam)', 'wiedenroth-technologies-sentrygate' ),
			__( 'Hilft bei Datenschutz. Erschwert aber die genaue Zuordnung eines Angreifers.', 'wiedenroth-technologies-sentrygate' )
		);
		?>

		<div class="<?php echo esc_attr( $ui->cls( 'field' ) ); ?>">
			<span class="<?php echo esc_attr( $ui->cls( 'field__label' ) ); ?>"><?php esc_html_e( 'Alarm bei vielen Sperren', 'wiedenroth-technologies-sentrygate' ); ?></span>
			<div class="<?php echo esc_attr( $ui->cls( 'field__control' ) ); ?>">
				<div class="<?php echo esc_attr( $ui->cls( 'inline-fields' ) ); ?>">
					<label for="<?php echo esc_attr( SentryGate_Admin::field_id( 'audit_notify_email' ) ); ?>">
						<?php esc_html_e( 'E-Mail-Adresse', 'wiedenroth-technologies-sentrygate' ); ?>
						<?php SentryGate_Admin::text( $ui, 'audit_notify_email', $options['audit_notify_email'] ?? '', 'email' ); ?>
					</label>
					<label for="<?php echo esc_attr( SentryGate_Admin::field_id( 'audit_notify_webhook' ) ); ?>">
						<?php esc_html_e( 'Webhook-Adresse (optional)', 'wiedenroth-technologies-sentrygate' ); ?>
						<?php SentryGate_Admin::text( $ui, 'audit_notify_webhook', $options['audit_notify_webhook'] ?? '', 'url' ); ?>
					</label>
					<label for="<?php echo esc_attr( SentryGate_Admin::field_id( 'audit_lockout_threshold' ) ); ?>">
						<?php esc_html_e( 'Alarm ab so vielen Sperren', 'wiedenroth-technologies-sentrygate' ); ?>
						<?php SentryGate_Admin::number( $ui, 'audit_lockout_threshold', $options['audit_lockout_threshold'] ?? 5, 1, 100 ); ?>
					</label>
					<label for="<?php echo esc_attr( SentryGate_Admin::field_id( 'audit_lockout_window' ) ); ?>">
						<?php esc_html_e( '… innerhalb so vieler Sekunden', 'wiedenroth-technologies-sentrygate' ); ?>
						<?php SentryGate_Admin::number( $ui, 'audit_lockout_window', $options['audit_lockout_window'] ?? 600, 60, 86400 ); ?>
					</label>
				</div>
				<?php $ui->help( __( 'Leer lassen = keine Extra-Benachrichtigung. 600 Sekunden = 10 Minuten.', 'wiedenroth-technologies-sentrygate' ) ); ?>
			</div>
		</div>
	</div>

	<?php SentryGate_Admin::save_actions( $ui ); ?>
</form>

<?php if ( ! SentryGate_Options::module_enabled( 'audit_log' ) ) : ?>
	<div class="<?php echo esc_attr( $ui->cls( 'card' ) ); ?>">
		<div class="<?php echo esc_attr( $ui->cls( 'empty' ) ); ?>">
			<div class="<?php echo esc_attr( $ui->cls( 'empty__icon' ) ); ?>" aria-hidden="true">◇</div>
			<h2 class="<?php echo esc_attr( $ui->cls( 'empty__title' ) ); ?>"><?php esc_html_e( 'Modul ist aus', 'wiedenroth-technologies-sentrygate' ); ?></h2>
			<p class="<?php echo esc_attr( $ui->cls( 'empty__text' ) ); ?>">
				<?php esc_html_e( 'Unter „Module ein/aus“ einschalten, damit Ereignisse erfasst werden.', 'wiedenroth-technologies-sentrygate' ); ?>
			</p>
			<?php $ui->button( __( 'Module öffnen', 'wiedenroth-technologies-sentrygate' ), 'primary', 'button', admin_url( 'admin.php?page=' . SENTRYGATE_SLUG . '&tab=modules' ) ); ?>
		</div>
	</div>
<?php elseif ( empty( $entries ) ) : ?>
	<div class="<?php echo esc_attr( $ui->cls( 'card' ) ); ?>">
		<div class="<?php echo esc_attr( $ui->cls( 'empty' ) ); ?>">
			<div class="<?php echo esc_attr( $ui->cls( 'empty__icon' ) ); ?>" aria-hidden="true">◇</div>
			<h2 class="<?php echo esc_attr( $ui->cls( 'empty__title' ) ); ?>"><?php esc_html_e( 'Noch keine Einträge', 'wiedenroth-technologies-sentrygate' ); ?></h2>
			<p class="<?php echo esc_attr( $ui->cls( 'empty__text' ) ); ?>">
				<?php esc_html_e( 'Entweder war es ruhig, oder das Modul ist erst frisch aktiv.', 'wiedenroth-technologies-sentrygate' ); ?>
			</p>
		</div>
	</div>
<?php else : ?>
	<div class="<?php echo esc_attr( $ui->cls( 'card', 'flush' ) ); ?>">
		<?php $ui->heading( __( 'Letzte Ereignisse', 'wiedenroth-technologies-sentrygate' ) ); ?>
		<div class="<?php echo esc_attr( $ui->cls( 'table-wrap' ) ); ?>">
			<table class="<?php echo esc_attr( $ui->cls( 'table' ) ); ?>">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Datum', 'wiedenroth-technologies-sentrygate' ); ?></th>
						<th><?php esc_html_e( 'Art', 'wiedenroth-technologies-sentrygate' ); ?></th>
						<th><?php esc_html_e( 'Stufe', 'wiedenroth-technologies-sentrygate' ); ?></th>
						<th><?php esc_html_e( 'Was passiert ist', 'wiedenroth-technologies-sentrygate' ); ?></th>
						<th><?php esc_html_e( 'IP', 'wiedenroth-technologies-sentrygate' ); ?></th>
						<th><?php esc_html_e( 'Benutzer', 'wiedenroth-technologies-sentrygate' ); ?></th>
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
