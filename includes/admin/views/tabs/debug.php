<?php
/**
 * Debug control tab.
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

$issues = SentryGate_Debug_Control::collect_issues();
?>
<form method="post" action="options.php">
	<?php settings_fields( 'sentrygate_settings_group' ); ?>

	<div class="<?php echo esc_attr( $ui->cls( 'card' ) ); ?>">
		<?php
		$ui->heading(
			__( 'Fehleranzeige auf der Live-Website', 'wiedenroth-technologies-sentrygate' ),
			__( 'Im Live-Betrieb sollten Besucher keine technischen Fehlermeldungen sehen. SentryGate warnt Sie, wenn Debug-Schalter noch aktiv sind – es ändert Ihre Server-Datei wp-config.php nicht automatisch.', 'wiedenroth-technologies-sentrygate' ),
			__( 'Wenn hier Warnungen stehen: Entwickler bitten, WP_DEBUG auf der Live-Seite auszuschalten.', 'wiedenroth-technologies-sentrygate' )
		);

		SentryGate_Admin::field_toggle(
			$ui,
			'enforce_production_debug',
			$options['enforce_production_debug'] ?? true,
			__( 'Live-Betrieb überwachen', 'wiedenroth-technologies-sentrygate' ),
			__( 'Warnungen anzeigen und sichtbare Fehler im Frontend dämpfen', 'wiedenroth-technologies-sentrygate' ),
			__( 'Sicher: Es wird nichts dauerhaft in der WordPress-Konfiguration überschrieben.', 'wiedenroth-technologies-sentrygate' )
		);
		?>
	</div>

	<?php SentryGate_Admin::save_actions( $ui ); ?>
</form>

<section class="<?php echo esc_attr( $ui->cls( 'card' ) ); ?>">
	<?php $ui->heading( __( 'Aktueller Stand', 'wiedenroth-technologies-sentrygate' ) ); ?>

	<?php if ( empty( $issues ) ) : ?>
		<?php $ui->notice( 'success', __( 'Alles wirkt produktiv eingestellt – keine Warnung nötig.', 'wiedenroth-technologies-sentrygate' ) ); ?>
	<?php else : ?>
		<div class="<?php echo esc_attr( $ui->cls( 'notice', 'warning' ) ); ?>" role="status">
			<p class="<?php echo esc_attr( $ui->cls( 'notice__title' ) ); ?>"><?php esc_html_e( 'Bitte prüfen:', 'wiedenroth-technologies-sentrygate' ); ?></p>
			<ul class="<?php echo esc_attr( $ui->cls( 'setup-list' ) ); ?>">
				<?php foreach ( $issues as $issue ) : ?>
					<li><?php echo esc_html( $issue ); ?></li>
				<?php endforeach; ?>
			</ul>
		</div>
	<?php endif; ?>
</section>
