<?php
/**
 * Security headers tab.
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
			__( 'Anweisungen an den Browser', 'wiedenroth-technologies-sentrygate' ),
			__( 'Diese Einstellungen sagen dem Browser, was erlaubt ist – z. B. ob Ihre Seite in fremden Websites eingebettet werden darf. Für die meisten Seiten reichen die Voreinstellungen.', 'wiedenroth-technologies-sentrygate' ),
			__( 'HSTS nur einschalten, wenn die gesamte Website zuverlässig über HTTPS läuft. CSP zuerst im Melde-Modus lassen.', 'wiedenroth-technologies-sentrygate' )
		);
		?>

		<div class="<?php echo esc_attr( $ui->cls( 'field' ) ); ?>">
			<div class="<?php echo esc_attr( $ui->cls( 'field__label' ) ); ?>">
				<?php esc_html_e( 'Einbetten in fremde Seiten', 'wiedenroth-technologies-sentrygate' ); ?>
				<div class="<?php echo esc_attr( $ui->cls( 'tech' ) ); ?>">X-Frame-Options</div>
			</div>
			<div class="<?php echo esc_attr( $ui->cls( 'field__control' ) ); ?>">
				<?php
				SentryGate_Admin::toggle(
					$ui,
					'header_x_frame_options',
					$options['header_x_frame_options'] ?? true,
					__( 'Schutz einschalten', 'wiedenroth-technologies-sentrygate' )
				);
				?>
				<select class="<?php echo esc_attr( $ui->cls( 'select' ) ); ?>" name="<?php echo esc_attr( SentryGate_Admin::option_name( 'header_x_frame_value' ) ); ?>">
					<option value="SAMEORIGIN" <?php selected( $options['header_x_frame_value'] ?? '', 'SAMEORIGIN' ); ?>><?php esc_html_e( 'Nur auf dieser Domain erlauben', 'wiedenroth-technologies-sentrygate' ); ?></option>
					<option value="DENY" <?php selected( $options['header_x_frame_value'] ?? '', 'DENY' ); ?>><?php esc_html_e( 'Überall verbieten', 'wiedenroth-technologies-sentrygate' ); ?></option>
				</select>
				<?php $ui->help( __( 'Schützt vor „Clickjacking“ – Ihre Seite wird nicht heimlich in einem Rahmen missbraucht.', 'wiedenroth-technologies-sentrygate' ) ); ?>
			</div>
		</div>

		<?php
		SentryGate_Admin::field_toggle(
			$ui,
			'header_x_content_type',
			$options['header_x_content_type'] ?? true,
			__( 'Dateitypen ernst nehmen', 'wiedenroth-technologies-sentrygate' ),
			__( 'Browser soll Dateitypen nicht raten (nosniff)', 'wiedenroth-technologies-sentrygate' ),
			__( 'Verhindert, dass schädliche Dateien als etwas anderes ausgeführt werden.', 'wiedenroth-technologies-sentrygate' ),
			'X-Content-Type-Options'
		);
		?>

		<div class="<?php echo esc_attr( $ui->cls( 'field' ) ); ?>">
			<div class="<?php echo esc_attr( $ui->cls( 'field__label' ) ); ?>">
				<?php esc_html_e( 'Weitergabe der Herkunfts-Adresse', 'wiedenroth-technologies-sentrygate' ); ?>
				<div class="<?php echo esc_attr( $ui->cls( 'tech' ) ); ?>">Referrer-Policy</div>
			</div>
			<div class="<?php echo esc_attr( $ui->cls( 'field__control' ) ); ?>">
				<?php
				SentryGate_Admin::toggle(
					$ui,
					'header_referrer_policy',
					$options['header_referrer_policy'] ?? true,
					__( 'Regel setzen', 'wiedenroth-technologies-sentrygate' )
				);
				SentryGate_Admin::text( $ui, 'header_referrer_value', $options['header_referrer_value'] ?? 'strict-origin-when-cross-origin' );
				$ui->help( __( 'Die Voreinstellung ist datensparsam und für die meisten Seiten geeignet. Nur ändern, wenn Sie den Wert kennen.', 'wiedenroth-technologies-sentrygate' ) );
				?>
			</div>
		</div>

		<div class="<?php echo esc_attr( $ui->cls( 'field' ) ); ?>">
			<div class="<?php echo esc_attr( $ui->cls( 'field__label' ) ); ?>">
				<?php esc_html_e( 'Immer verschlüsselt (HTTPS)', 'wiedenroth-technologies-sentrygate' ); ?>
				<div class="<?php echo esc_attr( $ui->cls( 'tech' ) ); ?>">Strict-Transport-Security (HSTS)</div>
			</div>
			<div class="<?php echo esc_attr( $ui->cls( 'field__control' ) ); ?>">
				<div class="<?php echo esc_attr( $ui->cls( 'stack' ) ); ?>">
					<?php
					SentryGate_Admin::toggle(
						$ui,
						'header_hsts',
						$options['header_hsts'] ?? false,
						__( 'Browser zwingen, künftig nur HTTPS zu nutzen', 'wiedenroth-technologies-sentrygate' )
					);
					?>
					<label for="<?php echo esc_attr( SentryGate_Admin::field_id( 'header_hsts_max_age' ) ); ?>" class="<?php echo esc_attr( $ui->cls( 'inline-fields' ) ); ?>">
						<?php esc_html_e( 'Gültigkeit in Sekunden', 'wiedenroth-technologies-sentrygate' ); ?>
						<?php SentryGate_Admin::number( $ui, 'header_hsts_max_age', $options['header_hsts_max_age'] ?? 31536000, 300, 63072000 ); ?>
					</label>
				</div>
				<?php $ui->help( __( 'Nur bei durchgehendem HTTPS. 31536000 ≈ 1 Jahr. Falsch gesetzt kann den Zugang erschweren.', 'wiedenroth-technologies-sentrygate' ) ); ?>
			</div>
		</div>

		<div class="<?php echo esc_attr( $ui->cls( 'field' ) ); ?>">
			<div class="<?php echo esc_attr( $ui->cls( 'field__label' ) ); ?>">
				<?php esc_html_e( 'Was Skripte und Inhalte dürfen', 'wiedenroth-technologies-sentrygate' ); ?>
				<div class="<?php echo esc_attr( $ui->cls( 'tech' ) ); ?>">Content-Security-Policy (CSP)</div>
			</div>
			<div class="<?php echo esc_attr( $ui->cls( 'field__control' ) ); ?>">
				<div class="<?php echo esc_attr( $ui->cls( 'stack' ) ); ?>">
					<?php
					SentryGate_Admin::toggle(
						$ui,
						'header_csp',
						$options['header_csp'] ?? true,
						__( 'Richtlinie senden', 'wiedenroth-technologies-sentrygate' )
					);
					SentryGate_Admin::toggle(
						$ui,
						'header_csp_report_only',
						$options['header_csp_report_only'] ?? true,
						__( 'Nur melden, noch nichts blockieren (empfohlen)', 'wiedenroth-technologies-sentrygate' )
					);
					?>
					<textarea
						class="<?php echo esc_attr( $ui->cls( 'textarea' ) ); ?>"
						rows="4"
						name="<?php echo esc_attr( SentryGate_Admin::option_name( 'header_csp_policy' ) ); ?>"
					><?php echo esc_textarea( (string) ( $options['header_csp_policy'] ?? '' ) ); ?></textarea>
				</div>
				<?php $ui->help( __( 'Zuerst im Melde-Modus testen. Erst wenn nichts kaputtgeht, den Haken „nur melden“ entfernen.', 'wiedenroth-technologies-sentrygate' ) ); ?>
			</div>
		</div>
	</div>

	<?php SentryGate_Admin::save_actions( $ui ); ?>
</form>
