<?php
/**
 * Module enable/disable tab.
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

$modules = array(
	'module_interface_hardening' => array(
		'label' => __( 'Basis-Schutz', 'wiedenroth-technologies-sentrygate' ),
		'desc'  => __( 'Sperrt bekannte Angriffswege (XML-RPC, Benutzer-Erraten, Datei-Editor, Versionslecks).', 'wiedenroth-technologies-sentrygate' ),
	),
	'module_debug_control'       => array(
		'label' => __( 'Fehleranzeige prüfen', 'wiedenroth-technologies-sentrygate' ),
		'desc'  => __( 'Warnt, wenn WordPress im Live-Betrieb Debug-Meldungen zeigt – ändert Ihre wp-config.php nicht.', 'wiedenroth-technologies-sentrygate' ),
	),
	'module_login_path'          => array(
		'label' => __( 'Login-Adresse verstecken', 'wiedenroth-technologies-sentrygate' ),
		'desc'  => __( 'Ersetzt /wp-login.php durch eine eigene Adresse. Unbekannte Besucher sehen „Seite nicht gefunden“.', 'wiedenroth-technologies-sentrygate' ),
	),
	'module_login_challenge'     => array(
		'label' => __( 'Login-Schutz', 'wiedenroth-technologies-sentrygate' ),
		'desc'  => __( 'Prüfung vor dem Login (Symbolwahl), danach Sitzungscookie. Direct-POSTs ohne Cookie werden abgewiesen.', 'wiedenroth-technologies-sentrygate' ),
	),
	'module_form_challenge'      => array(
		'label' => __( 'Formular-Schutz', 'wiedenroth-technologies-sentrygate' ),
		'desc'  => __( 'Dieselbe Symbolwahl vor dem Absenden von BlockForge-Formularen und anderen angebundenen Formularen.', 'wiedenroth-technologies-sentrygate' ),
	),
	'module_two_factor'          => array(
		'label' => __( 'Zwei-Faktor-Login', 'wiedenroth-technologies-sentrygate' ),
		'desc'  => __( 'Zusätzlicher Code per Authenticator-App oder E-Mail. Einrichtung unter Benutzer → Zwei-Faktor.', 'wiedenroth-technologies-sentrygate' ),
	),
	'module_security_headers'    => array(
		'label' => __( 'Browser-Schutz', 'wiedenroth-technologies-sentrygate' ),
		'desc'  => __( 'Sagt dem Browser, wie die Seite geladen werden darf (z. B. kein Einbetten in fremde Seiten).', 'wiedenroth-technologies-sentrygate' ),
	),
	'module_file_hardening'      => array(
		'label' => __( 'Dateien & Server', 'wiedenroth-technologies-sentrygate' ),
		'desc'  => __( 'Zeigt Empfehlungen zu Dateirechten und Upload-Schutz. Schreibt nichts automatisch auf den Server.', 'wiedenroth-technologies-sentrygate' ),
	),
	'module_audit_log'           => array(
		'label' => __( 'Ereignis-Protokoll', 'wiedenroth-technologies-sentrygate' ),
		'desc'  => __( 'Protokolliert Logins, Sperren und wichtige Sicherheitsereignisse.', 'wiedenroth-technologies-sentrygate' ),
	),
	'module_core_integrity'      => array(
		'label' => __( 'WordPress-Check', 'wiedenroth-technologies-sentrygate' ),
		'desc'  => __( 'Vergleicht WordPress-Dateien mit dem Original von wordpress.org. Keine automatische Reparatur.', 'wiedenroth-technologies-sentrygate' ),
	),
);
?>
<form method="post" action="options.php">
	<?php settings_fields( 'sentrygate_settings_group' ); ?>

	<div class="<?php echo esc_attr( $ui->cls( 'card' ) ); ?>">
		<?php
		$ui->heading(
			__( 'Module sind die Schutz-Bausteine', 'wiedenroth-technologies-sentrygate' ),
			__( 'Schalten Sie hier ein, was auf dieser Website aktiv sein soll. Ausgeschaltete Module tun nichts – ihre Einstellungen bleiben gespeichert.', 'wiedenroth-technologies-sentrygate' ),
			__( 'Für die meisten Kunden-Websites: Basis-Schutz, Login-Schutz, Browser-Schutz und Ereignis-Protokoll anlassen.', 'wiedenroth-technologies-sentrygate' )
		);
		?>

		<?php foreach ( $modules as $key => $meta ) : ?>
			<?php
			SentryGate_Admin::field_toggle(
				$ui,
				$key,
				$options[ $key ] ?? false,
				$meta['label'],
				__( 'Einschalten', 'wiedenroth-technologies-sentrygate' ),
				$meta['desc']
			);
			?>
		<?php endforeach; ?>
	</div>

	<?php SentryGate_Admin::save_actions( $ui, __( 'Module speichern', 'wiedenroth-technologies-sentrygate' ) ); ?>
</form>
