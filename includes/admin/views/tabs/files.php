<?php
/**
 * File hardening tab – snippets & permission report.
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

$sentrygate_report        = SentryGate_File_Hardening::permission_report();
$sentrygate_snippet       = SentryGate_File_Hardening::htaccess_uploads_snippet();
$sentrygate_htaccess_path = SentryGate_File_Hardening::uploads_htaccess_path();
$sentrygate_htaccess_hint = '' !== $sentrygate_htaccess_path
	? sprintf(
		/* translators: %s: absolute path to uploads .htaccess */
		__( 'Optional manuell in die Datei %s kopieren. Nicht automatisch geschrieben.', 'wiedenroth-technologies-sentrygate' ),
		$sentrygate_htaccess_path
	)
	: __( 'Optional manuell in die .htaccess-Datei im Uploads-Verzeichnis kopieren. Nicht automatisch geschrieben.', 'wiedenroth-technologies-sentrygate' );
?>
<form method="post" action="options.php">
	<?php settings_fields( 'sentrygate_settings_group' ); ?>

	<div class="<?php echo esc_attr( $ui->cls( 'card' ) ); ?>">
		<?php
		$ui->heading(
			__( 'Hinweise zu Dateien auf dem Server', 'wiedenroth-technologies-sentrygate' ),
			__( 'SentryGate zeigt Empfehlungen und Prüfungen – es ändert Dateirechte und .htaccess nicht von allein. So bleibt nichts „heimlich“ umgeschrieben.', 'wiedenroth-technologies-sentrygate' ),
			__( 'Den .htaccess-Text nur einfügen, wenn Ihr Hosting Apache nutzt und Sie wissen, wo die Upload-Ordner-Datei liegt – oder Ihren Dienstleister fragen.', 'wiedenroth-technologies-sentrygate' )
		);

		SentryGate_Admin::field_toggle(
			$ui,
			'show_htaccess_snippets',
			$options['show_htaccess_snippets'] ?? true,
			__( 'Upload-Schutz', 'wiedenroth-technologies-sentrygate' ),
			__( 'Beispiel-Text für Upload-Schutz zeigen', 'wiedenroth-technologies-sentrygate' )
		);

		SentryGate_Admin::field_toggle(
			$ui,
			'show_permission_checks',
			$options['show_permission_checks'] ?? true,
			__( 'Dateirechte', 'wiedenroth-technologies-sentrygate' ),
			__( 'Prüfung der Dateirechte zeigen', 'wiedenroth-technologies-sentrygate' )
		);
		?>
	</div>

	<?php SentryGate_Admin::save_actions( $ui ); ?>
</form>

<?php if ( ! empty( $options['show_htaccess_snippets'] ) ) : ?>
	<section class="<?php echo esc_attr( $ui->cls( 'card' ) ); ?>">
		<?php $ui->heading( __( 'Beispiel: Schutz für hochgeladene Dateien', 'wiedenroth-technologies-sentrygate' ), $sentrygate_htaccess_hint ); ?>
		<div class="<?php echo esc_attr( $ui->cls( 'snippets' ) ); ?>">
			<div class="<?php echo esc_attr( $ui->cls( 'snippet' ) ); ?>">
				<div class="<?php echo esc_attr( $ui->cls( 'snippet__head' ) ); ?>">
					<p class="<?php echo esc_attr( $ui->cls( 'snippet__title' ) ); ?>">.htaccess</p>
					<button type="button" class="<?php echo esc_attr( $ui->cls( 'btn', 'primary' ) ); ?>" data-wt-copy="<?php echo esc_attr( $sentrygate_snippet ); ?>">
						<?php esc_html_e( 'Kopieren', 'wiedenroth-technologies-sentrygate' ); ?>
					</button>
				</div>
				<textarea class="<?php echo esc_attr( $ui->cls( 'textarea' ) ); ?>" rows="14" readonly><?php echo esc_textarea( $sentrygate_snippet ); ?></textarea>
			</div>
		</div>
	</section>
<?php endif; ?>

<?php if ( ! empty( $options['show_permission_checks'] ) ) : ?>
	<div class="<?php echo esc_attr( $ui->cls( 'card', 'flush' ) ); ?>">
		<?php $ui->heading( __( 'Dateirechte – nur zur Kontrolle', 'wiedenroth-technologies-sentrygate' ), __( '„Prüfen“ heißt: Rechte wirken ungewöhnlich. Ändern Sie nichts selbst, wenn Sie unsicher sind – Hosting oder Agentur fragen.', 'wiedenroth-technologies-sentrygate' ) ); ?>
		<div class="<?php echo esc_attr( $ui->cls( 'table-wrap' ) ); ?>">
			<table class="<?php echo esc_attr( $ui->cls( 'table' ) ); ?>">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Pfad', 'wiedenroth-technologies-sentrygate' ); ?></th>
						<th><?php esc_html_e( 'Rechte', 'wiedenroth-technologies-sentrygate' ); ?></th>
						<th><?php esc_html_e( 'Status', 'wiedenroth-technologies-sentrygate' ); ?></th>
						<th><?php esc_html_e( 'Hinweis', 'wiedenroth-technologies-sentrygate' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $sentrygate_report as $sentrygate_row ) : ?>
						<tr>
							<td><code><?php echo esc_html( $sentrygate_row['path'] ); ?></code></td>
							<td><?php echo esc_html( $sentrygate_row['perms'] ); ?></td>
							<td>
								<?php
								if ( $sentrygate_row['ok'] ) {
									$ui->badge( 'ok', __( 'OK', 'wiedenroth-technologies-sentrygate' ) );
								} else {
									$ui->badge( 'warn', __( 'Prüfen', 'wiedenroth-technologies-sentrygate' ) );
								}
								?>
							</td>
							<td><?php echo esc_html( $sentrygate_row['note'] ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	</div>
<?php endif; ?>
