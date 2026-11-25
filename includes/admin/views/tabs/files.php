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
		__( 'Optionally copy this manually into the file %s. Not written automatically.', 'wiedenroth-technologies-sentrygate' ),
		$sentrygate_htaccess_path
	)
	: __( 'Optionally copy this manually into the .htaccess file in the uploads directory. Not written automatically.', 'wiedenroth-technologies-sentrygate' );
?>
<form method="post" action="options.php">
	<?php settings_fields( 'sentrygate_settings_group' ); ?>

	<div class="<?php echo esc_attr( $ui->cls( 'card' ) ); ?>">
		<?php
		$ui->heading(
			__( 'Notes about files on the server', 'wiedenroth-technologies-sentrygate' ),
			__( 'SentryGate shows recommendations and checks – it does not change file permissions or .htaccess on its own. That way nothing is rewritten in secret.', 'wiedenroth-technologies-sentrygate' ),
			__( 'Paste the .htaccess text only if your host uses Apache and you know where the file in the uploads folder is – or ask your provider.', 'wiedenroth-technologies-sentrygate' )
		);

		SentryGate_Admin::field_toggle(
			$ui,
			'show_htaccess_snippets',
			$options['show_htaccess_snippets'] ?? true,
			__( 'Upload protection', 'wiedenroth-technologies-sentrygate' ),
			__( 'Show sample text for upload protection', 'wiedenroth-technologies-sentrygate' )
		);

		SentryGate_Admin::field_toggle(
			$ui,
			'show_permission_checks',
			$options['show_permission_checks'] ?? true,
			__( 'File permissions', 'wiedenroth-technologies-sentrygate' ),
			__( 'Show the file permissions check', 'wiedenroth-technologies-sentrygate' )
		);
		?>
	</div>

	<?php SentryGate_Admin::save_actions( $ui ); ?>
</form>

<?php if ( ! empty( $options['show_htaccess_snippets'] ) ) : ?>
	<section class="<?php echo esc_attr( $ui->cls( 'card' ) ); ?>">
		<?php $ui->heading( __( 'Example: protection for uploaded files', 'wiedenroth-technologies-sentrygate' ), $sentrygate_htaccess_hint ); ?>
		<div class="<?php echo esc_attr( $ui->cls( 'snippets' ) ); ?>">
			<div class="<?php echo esc_attr( $ui->cls( 'snippet' ) ); ?>">
				<div class="<?php echo esc_attr( $ui->cls( 'snippet__head' ) ); ?>">
					<p class="<?php echo esc_attr( $ui->cls( 'snippet__title' ) ); ?>">.htaccess</p>
					<button type="button" class="<?php echo esc_attr( $ui->cls( 'btn', 'primary' ) ); ?>" data-wt-copy="<?php echo esc_attr( $sentrygate_snippet ); ?>">
						<?php esc_html_e( 'Copy', 'wiedenroth-technologies-sentrygate' ); ?>
					</button>
				</div>
				<textarea class="<?php echo esc_attr( $ui->cls( 'textarea' ) ); ?>" rows="14" readonly><?php echo esc_textarea( $sentrygate_snippet ); ?></textarea>
			</div>
		</div>
	</section>
<?php endif; ?>

<?php if ( ! empty( $options['show_permission_checks'] ) ) : ?>
	<div class="<?php echo esc_attr( $ui->cls( 'card', 'flush' ) ); ?>">
		<?php $ui->heading( __( 'File permissions – for review only', 'wiedenroth-technologies-sentrygate' ), __( '"Check" means the permissions look unusual. Do not change anything yourself if you are unsure – ask your host or agency.', 'wiedenroth-technologies-sentrygate' ) ); ?>
		<div class="<?php echo esc_attr( $ui->cls( 'table-wrap' ) ); ?>">
			<table class="<?php echo esc_attr( $ui->cls( 'table' ) ); ?>">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Path', 'wiedenroth-technologies-sentrygate' ); ?></th>
						<th><?php esc_html_e( 'Permissions', 'wiedenroth-technologies-sentrygate' ); ?></th>
						<th><?php esc_html_e( 'Status', 'wiedenroth-technologies-sentrygate' ); ?></th>
						<th><?php esc_html_e( 'Note', 'wiedenroth-technologies-sentrygate' ); ?></th>
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
									$ui->badge( 'warn', __( 'Check', 'wiedenroth-technologies-sentrygate' ) );
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
