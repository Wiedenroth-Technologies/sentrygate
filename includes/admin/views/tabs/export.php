<?php
/**
 * Config export / import tab.
 *
 * @package SentryGate
 *
 * @var SentryGate_Admin_Ui $ui
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:ignore WordPress.Security.NonceVerification.Recommended
$sentrygate_import = isset( $_GET['import'] ) ? sanitize_key( (string) wp_unslash( $_GET['import'] ) ) : '';
?>

<section class="<?php echo esc_attr( $ui->cls( 'card', 'intro' ) ); ?>">
	<?php
	$ui->heading(
		__( 'Back up settings or apply them to another site', 'wiedenroth-technologies-sentrygate' ),
		__( 'Export creates a file with your SentryGate settings. Import loads that file back in – handy when you want the same security standard for every client.', 'wiedenroth-technologies-sentrygate' ),
		__( 'Personal secrets (for example, user 2FA) are not in the file. They must be set up again on each site.', 'wiedenroth-technologies-sentrygate' )
	);
	?>
</section>

<?php
if ( 'ok' === $sentrygate_import ) {
	$ui->notice( 'success', __( 'Settings have been applied.', 'wiedenroth-technologies-sentrygate' ) );
} elseif ( 'fail' === $sentrygate_import ) {
	$ui->notice( 'error', __( 'Import failed – please choose a valid SentryGate JSON file.', 'wiedenroth-technologies-sentrygate' ) );
}
?>

<div class="<?php echo esc_attr( $ui->cls( 'grid' ) ); ?>">
	<section class="<?php echo esc_attr( $ui->cls( 'card' ) ); ?>">
		<?php $ui->heading( __( '1. Back up / download', 'wiedenroth-technologies-sentrygate' ), __( 'Downloads a JSON file to your computer.', 'wiedenroth-technologies-sentrygate' ) ); ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="sentrygate_export_config" />
			<?php wp_nonce_field( 'sentrygate_export_config' ); ?>
			<p class="<?php echo esc_attr( $ui->cls( 'row', 'actions' ) ); ?>">
				<?php $ui->button( __( 'Download file', 'wiedenroth-technologies-sentrygate' ), 'secondary', 'submit' ); ?>
			</p>
		</form>
	</section>

	<section class="<?php echo esc_attr( $ui->cls( 'card' ) ); ?>">
		<?php $ui->heading( __( '2. Import on this site', 'wiedenroth-technologies-sentrygate' ), __( 'Overwrites the current SentryGate settings with the selected file.', 'wiedenroth-technologies-sentrygate' ) ); ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
			<input type="hidden" name="action" value="sentrygate_import_config" />
			<?php wp_nonce_field( 'sentrygate_import_config' ); ?>
			<div class="<?php echo esc_attr( $ui->cls( 'stack' ) ); ?>">
				<input type="file" name="sentrygate_import" accept="application/json,.json" required />
				<p class="<?php echo esc_attr( $ui->cls( 'row' ) ); ?>">
					<?php $ui->button( __( 'Import file', 'wiedenroth-technologies-sentrygate' ), 'primary', 'submit' ); ?>
				</p>
			</div>
		</form>
	</section>
</div>
