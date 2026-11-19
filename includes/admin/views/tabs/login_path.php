<?php
/**
 * Login path settings.
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

$slug          = (string) ( $options['login_slug'] ?? 'portal' );
$login_url     = home_url( '/' . $slug . '/' );
$frontend_url  = (string) apply_filters( 'sentrygate_frontend_login_url', '', '' );
$effective_url = '' !== $frontend_url ? $frontend_url : $login_url;
?>
<form method="post" action="options.php">
	<?php settings_fields( 'sentrygate_settings_group' ); ?>

	<div class="<?php echo esc_attr( $ui->cls( 'card' ) ); ?>">
		<?php
		$ui->heading(
			__( 'Eigene Adresse für den Login', 'wiedenroth-technologies-sentrygate' ),
			__( 'Statt der bekannten Adresse /wp-login.php nutzen Sie eine eigene URL. Wer die alte Adresse aufruft, sieht „Seite nicht gefunden“. So finden Bots den Login schwerer.', 'wiedenroth-technologies-sentrygate' ),
			__( 'Notieren Sie sich die neue Login-Adresse. Das Modul muss unter „Module ein/aus“ eingeschaltet sein.', 'wiedenroth-technologies-sentrygate' )
		);
		?>

		<div class="<?php echo esc_attr( $ui->cls( 'field' ) ); ?>">
			<label class="<?php echo esc_attr( $ui->cls( 'field__label' ) ); ?>" for="<?php echo esc_attr( SentryGate_Admin::field_id( 'login_slug' ) ); ?>"><?php esc_html_e( 'Ihr Login-Name in der URL', 'wiedenroth-technologies-sentrygate' ); ?></label>
			<div class="<?php echo esc_attr( $ui->cls( 'field__control' ) ); ?>">
				<?php SentryGate_Admin::text( $ui, 'login_slug', $slug ); ?>
				<?php
				$ui->help(
					sprintf(
						/* translators: %s: login URL */
						__( 'Login dann unter: %s', 'wiedenroth-technologies-sentrygate' ),
						$login_url
					)
				);
				if ( '' !== $frontend_url ) {
					$ui->help(
						sprintf(
							/* translators: 1: custom login slug, 2: frontend login URL */
							__( 'Frontend-Anmeldung aktiv: /%1$s/ und Login-Links führen dorthin (%2$s), inkl. Captcha und 2FA – ohne doppeltes Einloggen über die WordPress-Oberfläche.', 'wiedenroth-technologies-sentrygate' ),
							esc_html( $slug ),
							esc_url( $effective_url )
						)
					);
				}
				$ui->help( __( 'Nur Buchstaben, Zahlen und Bindestriche. Vermeiden Sie Wörter wie „login“ oder „admin“.', 'wiedenroth-technologies-sentrygate' ) );
				?>
			</div>
		</div>

		<?php
		SentryGate_Admin::field_toggle(
			$ui,
			'redirect_admin',
			$options['redirect_admin'] ?? true,
			__( 'Backend-Adresse sperren', 'wiedenroth-technologies-sentrygate' ),
			__( '/wp-admin für Nicht-Eingeloggte mit „nicht gefunden“ beantworten', 'wiedenroth-technologies-sentrygate' ),
			__( 'Eingeloggte Nutzer können weiterhin normal ins Backend.', 'wiedenroth-technologies-sentrygate' )
		);
		?>
	</div>

	<?php SentryGate_Admin::save_actions( $ui ); ?>
</form>

<?php
$ui->notice(
	'info',
	__( 'Falls die neue Adresse nicht greift: unter Einstellungen → Permalinks einmal „Änderungen speichern“ klicken.', 'wiedenroth-technologies-sentrygate' )
);
$ui->notice(
	'warning',
	sprintf(
		/* translators: %s: custom login URL */
		__( 'Wiederherstellung, falls Sie die Login-Adresse vergessen: (1) Notierte URL nutzen (%s). (2) Plugin per FTP/Dateimanager umbenennen oder deaktivieren – danach funktioniert /wp-login.php wieder. (3) Per WP-CLI: wp plugin deactivate sentrygate – oder in der Datenbank die Einstellung module_login_path auf aus setzen und Permalinks speichern.', 'wiedenroth-technologies-sentrygate' ),
		$login_url
	),
	__( 'Wiederherstellungsweg', 'wiedenroth-technologies-sentrygate' )
);
?>
