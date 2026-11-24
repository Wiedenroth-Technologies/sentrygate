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
		__( 'Einstellungen sichern oder auf eine andere Website übernehmen', 'wiedenroth-technologies-sentrygate' ),
		__( 'Export erzeugt eine Datei mit Ihren SentryGate-Einstellungen. Import lädt so eine Datei wieder ein – praktisch, wenn Sie bei Kunden immer denselben Sicherheits-Standard setzen.', 'wiedenroth-technologies-sentrygate' ),
		__( 'Persönliche Geheimnisse (z. B. 2FA der Nutzer) sind nicht in der Datei. Die müssen pro Website neu eingerichtet werden.', 'wiedenroth-technologies-sentrygate' )
	);
	?>
</section>

<?php
if ( 'ok' === $sentrygate_import ) {
	$ui->notice( 'success', __( 'Einstellungen wurden übernommen.', 'wiedenroth-technologies-sentrygate' ) );
} elseif ( 'fail' === $sentrygate_import ) {
	$ui->notice( 'error', __( 'Import fehlgeschlagen – bitte eine gültige SentryGate-JSON-Datei wählen.', 'wiedenroth-technologies-sentrygate' ) );
}
?>

<div class="<?php echo esc_attr( $ui->cls( 'grid' ) ); ?>">
	<section class="<?php echo esc_attr( $ui->cls( 'card' ) ); ?>">
		<?php $ui->heading( __( '1. Sichern / herunterladen', 'wiedenroth-technologies-sentrygate' ), __( 'Lädt eine JSON-Datei auf Ihren Computer.', 'wiedenroth-technologies-sentrygate' ) ); ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="sentrygate_export_config" />
			<?php wp_nonce_field( 'sentrygate_export_config' ); ?>
			<p class="<?php echo esc_attr( $ui->cls( 'row', 'actions' ) ); ?>">
				<?php $ui->button( __( 'Datei herunterladen', 'wiedenroth-technologies-sentrygate' ), 'secondary', 'submit' ); ?>
			</p>
		</form>
	</section>

	<section class="<?php echo esc_attr( $ui->cls( 'card' ) ); ?>">
		<?php $ui->heading( __( '2. Auf dieser Website einspielen', 'wiedenroth-technologies-sentrygate' ), __( 'Überschreibt die aktuellen SentryGate-Einstellungen mit der gewählten Datei.', 'wiedenroth-technologies-sentrygate' ) ); ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
			<input type="hidden" name="action" value="sentrygate_import_config" />
			<?php wp_nonce_field( 'sentrygate_import_config' ); ?>
			<div class="<?php echo esc_attr( $ui->cls( 'stack' ) ); ?>">
				<input type="file" name="sentrygate_import" accept="application/json,.json" required />
				<p class="<?php echo esc_attr( $ui->cls( 'row' ) ); ?>">
					<?php $ui->button( __( 'Datei einspielen', 'wiedenroth-technologies-sentrygate' ), 'primary', 'submit' ); ?>
				</p>
			</div>
		</form>
	</section>
</div>
