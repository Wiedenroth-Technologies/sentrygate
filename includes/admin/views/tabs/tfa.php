<?php
/**
 * Two-factor settings tab.
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

$roles    = wp_roles()->get_names();
$selected = (array) ( $options['tfa_required_roles'] ?? array() );
?>
<form method="post" action="options.php">
	<?php settings_fields( 'sentrygate_settings_group' ); ?>

	<div class="<?php echo esc_attr( $ui->cls( 'card' ) ); ?>">
		<?php
		$ui->heading(
			__( 'Zweiter Schritt nach dem Passwort', 'wiedenroth-technologies-sentrygate' ),
			__( 'Selbst wenn das Passwort gestohlen wird, braucht der Angreifer zusätzlich einen Code. Jeder Nutzer wählt auf der eigenen Seite „Zwei-Faktor“ zwischen Authenticator-App und E-Mail.', 'wiedenroth-technologies-sentrygate' ),
			__( 'Für Administratoren unbedingt als Pflicht-Rolle markieren.', 'wiedenroth-technologies-sentrygate' )
		);
		?>

		<div class="<?php echo esc_attr( $ui->cls( 'field' ) ); ?>">
			<span class="<?php echo esc_attr( $ui->cls( 'field__label' ) ); ?>"><?php esc_html_e( 'Wer muss Zwei-Faktor nutzen?', 'wiedenroth-technologies-sentrygate' ); ?></span>
			<div class="<?php echo esc_attr( $ui->cls( 'field__control' ) ); ?>">
				<input type="hidden" name="<?php echo esc_attr( SentryGate_Admin::option_name( 'tfa_roles_section' ) ); ?>" value="1" />
				<div class="<?php echo esc_attr( $ui->cls( 'check-list' ) ); ?>">
					<?php foreach ( $roles as $slug => $name ) : ?>
						<label>
							<input type="checkbox" name="<?php echo esc_attr( SentryGate_Admin::option_name( 'tfa_required_roles' ) ); ?>[]" value="<?php echo esc_attr( $slug ); ?>" <?php checked( in_array( $slug, $selected, true ) ); ?> />
							<?php echo esc_html( translate_user_role( $name ) ); ?>
						</label>
					<?php endforeach; ?>
				</div>
				<?php $ui->help( __( 'Markierte Rollen werden nach dem Login zur Einrichtungsseite geleitet, bis eine Methode aktiv ist.', 'wiedenroth-technologies-sentrygate' ) ); ?>
			</div>
		</div>

		<?php
		SentryGate_Admin::field_toggle(
			$ui,
			'tfa_email_fallback',
			$options['tfa_email_fallback'] ?? true,
			__( 'E-Mail als Methode erlauben?', 'wiedenroth-technologies-sentrygate' ),
			__( 'Ja, Nutzer dürfen E-Mail statt einer App wählen', 'wiedenroth-technologies-sentrygate' ),
			__( 'Ohne diese Option bleibt nur die Authenticator-App. Es ist keine zweite Login-Methode parallel, sondern eine Wahl.', 'wiedenroth-technologies-sentrygate' )
		);

		SentryGate_Admin::field_number(
			$ui,
			'tfa_trusted_days',
			$options['tfa_trusted_days'] ?? 30,
			__( 'Gerät merken', 'wiedenroth-technologies-sentrygate' ),
			__( 'Tage ohne erneuten Code. Nutzer können beim Login „Dieses Gerät vertrauen“ wählen. Auf fremden Rechnern nicht anhaken.', 'wiedenroth-technologies-sentrygate' ),
			1,
			365
		);

		SentryGate_Admin::field_number(
			$ui,
			'tfa_recovery_count',
			$options['tfa_recovery_count'] ?? 10,
			__( 'Notfall-Codes', 'wiedenroth-technologies-sentrygate' ),
			__( 'Anzahl neuer Codes (8–10). Einmalig anzeigbare Backup-Codes, falls Handy oder App weg ist. Sicher aufbewahren.', 'wiedenroth-technologies-sentrygate' ),
			8,
			10
		);
		?>
	</div>

	<?php SentryGate_Admin::save_actions( $ui ); ?>
</form>

<div class="<?php echo esc_attr( $ui->cls( 'notice', 'info shop' ) ); ?>">
	<div class="<?php echo esc_attr( $ui->cls( 'notice__body' ) ); ?>">
		<p class="<?php echo esc_attr( $ui->cls( 'notice__title' ) ); ?>"><?php esc_html_e( 'Eigene Einrichtungsseite', 'wiedenroth-technologies-sentrygate' ); ?></p>
		<p class="<?php echo esc_attr( $ui->cls( 'notice__text' ) ); ?>"><?php esc_html_e( 'Nicht im Profil: Benutzer → Zwei-Faktor. Dort App oder E-Mail wählen, bestätigen, fertig.', 'wiedenroth-technologies-sentrygate' ); ?></p>
	</div>
	<div class="<?php echo esc_attr( $ui->cls( 'notice__actions' ) ); ?>">
		<?php $ui->button( __( 'Meine Zwei-Faktor-Seite', 'wiedenroth-technologies-sentrygate' ), 'primary', 'button', SentryGate_Two_Factor::setup_url() ); ?>
	</div>
</div>
