<?php
/**
 * Interface hardening settings.
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

$whitelist = implode( "\n", (array) ( $options['rest_api_whitelist'] ?? array() ) );
?>
<form method="post" action="options.php">
	<?php settings_fields( 'sentrygate_settings_group' ); ?>

	<div class="<?php echo esc_attr( $ui->cls( 'card' ) ); ?>">
		<?php
		$ui->heading(
			__( 'Grundschutz für die Website', 'wiedenroth-technologies-sentrygate' ),
			__( 'Hier schalten Sie häufige Angriffswege ab. Die meisten Einstellungen können Sie ruhig aktiviert lassen.', 'wiedenroth-technologies-sentrygate' ),
			__( '„REST-API einschränken“ nur einschalten, wenn Sie wissen, welche Schnittstellen die Website braucht (z. B. Formulare oder Blöcke).', 'wiedenroth-technologies-sentrygate' )
		);
		?>

		<?php
		SentryGate_Admin::field_toggle(
			$ui,
			'disable_xmlrpc',
			$options['disable_xmlrpc'] ?? true,
			__( 'Alte Fernsteuerung sperren', 'wiedenroth-technologies-sentrygate' ),
			__( 'XML-RPC deaktivieren', 'wiedenroth-technologies-sentrygate' ),
			__( 'Alte Schnittstelle, die oft für Passwort-Angriffe missbraucht wird. Für normale Websites nicht nötig.', 'wiedenroth-technologies-sentrygate' )
		);

		SentryGate_Admin::field_toggle(
			$ui,
			'block_user_enumeration',
			$options['block_user_enumeration'] ?? true,
			__( 'Benutzer-Namen schützen', 'wiedenroth-technologies-sentrygate' ),
			__( 'Erraten von Benutzernamen erschweren', 'wiedenroth-technologies-sentrygate' ),
			__( 'Verhindert, dass Angreifer Listen gültiger Logins auslesen.', 'wiedenroth-technologies-sentrygate' )
		);

		SentryGate_Admin::field_toggle(
			$ui,
			'disallow_file_edit',
			$options['disallow_file_edit'] ?? true,
			__( 'Code-Editor im Backend', 'wiedenroth-technologies-sentrygate' ),
			__( 'Theme-/Plugin-Editor ausschalten', 'wiedenroth-technologies-sentrygate' ),
			__( 'Wenn jemand Ihr Admin-Konto übernimmt, kann er so nicht direkt Dateien im Browser umschreiben.', 'wiedenroth-technologies-sentrygate' )
		);

		SentryGate_Admin::field_toggle(
			$ui,
			'remove_wp_version',
			$options['remove_wp_version'] ?? true,
			__( 'WordPress-Version verstecken', 'wiedenroth-technologies-sentrygate' ),
			__( 'Versionsnummer aus der öffentlichen Seite entfernen', 'wiedenroth-technologies-sentrygate' ),
			__( 'Erschwert Angreifern zu sehen, welche WordPress-Version läuft.', 'wiedenroth-technologies-sentrygate' )
		);
		?>

		<div class="<?php echo esc_attr( $ui->cls( 'field' ) ); ?>">
			<span class="<?php echo esc_attr( $ui->cls( 'field__label' ) ); ?>"><?php esc_html_e( 'Schnittstelle für Apps (REST)', 'wiedenroth-technologies-sentrygate' ); ?></span>
			<div class="<?php echo esc_attr( $ui->cls( 'field__control' ) ); ?>">
				<?php
				SentryGate_Admin::toggle(
					$ui,
					'restrict_rest_api',
					$options['restrict_rest_api'] ?? false,
					__( 'Nur freigegebene Bereiche für Gäste erlauben', 'wiedenroth-technologies-sentrygate' )
				);
				$ui->help( __( 'Standardmäßig aus. Nur aktivieren, wenn Sie die Liste unten bewusst pflegen.', 'wiedenroth-technologies-sentrygate' ) );
				?>
				<p class="<?php echo esc_attr( $ui->cls( 'card__section' ) ); ?>"><?php esc_html_e( 'Erlaubte Bereiche (eine Zeile pro Eintrag)', 'wiedenroth-technologies-sentrygate' ); ?></p>
				<textarea
					class="<?php echo esc_attr( $ui->cls( 'textarea' ) ); ?>"
					rows="8"
					id="sentrygate_rest_whitelist"
					name="<?php echo esc_attr( SentryGate_Admin::option_name( 'rest_api_whitelist' ) ); ?>"
				><?php echo esc_textarea( $whitelist ); ?></textarea>
				<?php $ui->help( __( 'Beispiel: /wp/v2/posts – alles, was mit dieser Zeile beginnt, bleibt für Gäste erreichbar.', 'wiedenroth-technologies-sentrygate' ) ); ?>
			</div>
		</div>
	</div>

	<?php SentryGate_Admin::save_actions( $ui ); ?>
</form>
