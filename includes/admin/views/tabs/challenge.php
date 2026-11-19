<?php
/**
 * Login challenge settings.
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
?>
<form method="post" action="options.php">
	<?php settings_fields( 'sentrygate_settings_group' ); ?>

	<div class="<?php echo esc_attr( $ui->cls( 'card' ) ); ?>">
		<?php
		$ui->heading(
			__( 'Zusatzschutz beim Anmelden', 'wiedenroth-technologies-sentrygate' ),
			__( 'Die Prüfung erscheint vor Benutzername und Passwort. Nach dem Bestehen speichert SentryGate ein HttpOnly-Sitzungscookie – in diesem Browserfenster nicht noch einmal. Direct-POSTs ohne Cookie werden abgewiesen, das Passwort wird dabei nicht geprüft.', 'wiedenroth-technologies-sentrygate' ),
			__( 'Die Symbolwahl ist eine Hürde gegen einfache Bots, kein Captcha wie bei Google. Stärker wird der Schutz zusammen mit der versteckten Login-Adresse.', 'wiedenroth-technologies-sentrygate' )
		);
		?>

		<div class="<?php echo esc_attr( $ui->cls( 'field' ) ); ?>">
			<label class="<?php echo esc_attr( $ui->cls( 'field__label' ) ); ?>" for="sentrygate-challenge-mode"><?php esc_html_e( 'Art des Schutzes', 'wiedenroth-technologies-sentrygate' ); ?></label>
			<div class="<?php echo esc_attr( $ui->cls( 'field__control' ) ); ?>">
				<select class="<?php echo esc_attr( $ui->cls( 'select' ) ); ?>" id="sentrygate-challenge-mode" name="<?php echo esc_attr( SentryGate_Admin::option_name( 'challenge_mode' ) ); ?>">
					<option value="full" <?php selected( $options['challenge_mode'] ?? 'full', 'full' ); ?>><?php esc_html_e( 'Voll – Symbolwahl vor dem Login + Sitzungscookie', 'wiedenroth-technologies-sentrygate' ); ?></option>
					<option value="lightweight" <?php selected( $options['challenge_mode'] ?? '', 'lightweight' ); ?>><?php esc_html_e( 'Leicht – nur Bestätigen + unsichtbare Fallen + Cookie', 'wiedenroth-technologies-sentrygate' ); ?></option>
				</select>
				<?php $ui->help( __( 'Das Cookie ist signiert (HMAC), HttpOnly und gilt bis zum Schließen des Browsers, längstens 12 Stunden. Es ist nicht per JavaScript lesbar.', 'wiedenroth-technologies-sentrygate' ) ); ?>
			</div>
		</div>

		<?php
		SentryGate_Admin::field_number(
			$ui,
			'challenge_min_time',
			$options['challenge_min_time'] ?? 2,
			__( 'Mindestwartezeit', 'wiedenroth-technologies-sentrygate' ),
			__( 'Sekunden bevor Absenden erlaubt ist. Bots senden Formulare oft sofort ab. 2 Sekunden reichen meist.', 'wiedenroth-technologies-sentrygate' ),
			0,
			60
		);
		?>

		<div class="<?php echo esc_attr( $ui->cls( 'field' ) ); ?>">
			<span class="<?php echo esc_attr( $ui->cls( 'field__label' ) ); ?>"><?php esc_html_e( 'Zu viele Fehlversuche', 'wiedenroth-technologies-sentrygate' ); ?></span>
			<div class="<?php echo esc_attr( $ui->cls( 'field__control' ) ); ?>">
				<div class="<?php echo esc_attr( $ui->cls( 'inline-fields' ) ); ?>">
					<label for="<?php echo esc_attr( SentryGate_Admin::field_id( 'rate_limit_max_attempts' ) ); ?>">
						<?php esc_html_e( 'Erlaubte Versuche', 'wiedenroth-technologies-sentrygate' ); ?>
						<?php SentryGate_Admin::number( $ui, 'rate_limit_max_attempts', $options['rate_limit_max_attempts'] ?? 5, 1, 100 ); ?>
					</label>
					<label for="<?php echo esc_attr( SentryGate_Admin::field_id( 'rate_limit_window' ) ); ?>">
						<?php esc_html_e( 'Zeitraum in Sekunden', 'wiedenroth-technologies-sentrygate' ); ?>
						<?php SentryGate_Admin::number( $ui, 'rate_limit_window', $options['rate_limit_window'] ?? 900, 60, 86400 ); ?>
					</label>
					<label for="<?php echo esc_attr( SentryGate_Admin::field_id( 'rate_limit_lockout' ) ); ?>">
						<?php esc_html_e( 'Sperrdauer in Sekunden', 'wiedenroth-technologies-sentrygate' ); ?>
						<?php SentryGate_Admin::number( $ui, 'rate_limit_lockout', $options['rate_limit_lockout'] ?? 900, 60, 86400 ); ?>
					</label>
				</div>
				<?php
				SentryGate_Admin::toggle(
					$ui,
					'rate_limit_exponential',
					$options['rate_limit_exponential'] ?? true,
					__( 'Sperre bei Wiederholung länger machen', 'wiedenroth-technologies-sentrygate' )
				);
				$ui->help( __( 'Beispiel: 5 Fehlversuche in 15 Minuten → 15 Minuten Pause. 900 Sekunden = 15 Minuten.', 'wiedenroth-technologies-sentrygate' ) );
				?>
			</div>
		</div>

		<?php
		SentryGate_Admin::field_toggle(
			$ui,
			'trust_proxy_headers',
			$options['trust_proxy_headers'] ?? false,
			__( 'Website hinter Cloudflare / Proxy?', 'wiedenroth-technologies-sentrygate' ),
			__( 'Ja, echte Besucher-IP aus Proxy-Headern lesen', 'wiedenroth-technologies-sentrygate' ),
			__( 'Nur einschalten, wenn ein vertrauenswürdiger Proxy/CDN davor sitzt. Sonst lieber aus lassen.', 'wiedenroth-technologies-sentrygate' )
		);
		?>
	</div>

	<div class="<?php echo esc_attr( $ui->cls( 'card' ) ); ?>">
		<?php
		$ui->heading(
			__( 'Formulare', 'wiedenroth-technologies-sentrygate' ),
			__( 'Dieselbe Symbolwahl wie beim Login, direkt im Formular vor dem Absenden. Kein Cookie, jeder Versand braucht eine frische Prüfung.', 'wiedenroth-technologies-sentrygate' ),
			__( 'BlockForge-Formulare nutzen das automatisch. Andere Plugins können SentryGate_Form_Challenge::widget() und ::verify() nutzen.', 'wiedenroth-technologies-sentrygate' )
		);

		SentryGate_Admin::field_toggle(
			$ui,
			'module_form_challenge',
			$options['module_form_challenge'] ?? true,
			__( 'Symbolwahl in Formularen', 'wiedenroth-technologies-sentrygate' ),
			__( 'Einschalten', 'wiedenroth-technologies-sentrygate' ),
			__( 'Ohne dieses Modul oder ohne SentryGate senden Formulare wie bisher (Honeypot und Wartezeit bleiben im Formular-Plugin).', 'wiedenroth-technologies-sentrygate' )
		);
		?>
	</div>

	<?php SentryGate_Admin::save_actions( $ui ); ?>
</form>
