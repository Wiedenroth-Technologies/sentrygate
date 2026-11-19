<?php
/**
 * Core integrity tab.
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

if ( ! function_exists( 'sentrygate_render_integrity_result' ) ) {
	/**
	 * Render checksum result (empty / error / stats).
	 *
	 * @param Wt_Admin_Ui               $ui   UI helper.
	 * @param array<string, mixed>|null $last Last result.
	 */
	function sentrygate_render_integrity_result( Wt_Admin_Ui $ui, ?array $last ): void {
		if ( null === $last ) {
			echo '<div class="' . esc_attr( $ui->cls( 'card' ) ) . '">';
			echo '<div class="' . esc_attr( $ui->cls( 'empty' ) ) . '">';
			echo '<div class="' . esc_attr( $ui->cls( 'empty__icon' ) ) . '" aria-hidden="true">◇</div>';
			echo '<h2 class="' . esc_attr( $ui->cls( 'empty__title' ) ) . '">' . esc_html__( 'Noch keine Prüfung', 'wiedenroth-technologies-sentrygate' ) . '</h2>';
			echo '<p class="' . esc_attr( $ui->cls( 'empty__text' ) ) . '">';
			echo esc_html__( 'Starten Sie den Check, um die WordPress-Core-Dateien mit der offiziellen Liste von wordpress.org zu vergleichen.', 'wiedenroth-technologies-sentrygate' );
			echo '</p>';
			echo '</div></div>';
			return;
		}

		if ( ! empty( $last['error'] ) ) {
			$ui->notice( 'error', (string) $last['error'], __( 'Prüfung fehlgeschlagen', 'wiedenroth-technologies-sentrygate' ) );
			return;
		}

		$checked  = (int) ( $last['checked'] ?? 0 );
		$mismatch = (array) ( $last['mismatches'] ?? array() );
		$missing  = (array) ( $last['missing'] ?? array() );
		$ok       = ! empty( $last['ok'] ) && empty( $mismatch ) && empty( $missing );
		$time     = SentryGate_Core_Integrity::format_time_label( $last['time'] ?? '' );
		$version  = (string) ( $last['version'] ?? '' );
		$tone     = $ok ? 'ok' : 'warn';

		echo '<section class="' . esc_attr( $ui->cls( 'card', 'flush result' ) ) . '">';
		echo '<div class="' . esc_attr( $ui->cls( 'result__banner', $tone ) ) . '">';
		echo '<span class="' . esc_attr( $ui->cls( 'result__icon' ) ) . '" aria-hidden="true">' . SentryGate_Admin::stat_svg( $ok ? 'check' : 'warn' ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- inline SVG.
		echo '<div class="' . esc_attr( $ui->cls( 'result__copy' ) ) . '">';
		echo '<p class="' . esc_attr( $ui->cls( 'result__eyebrow' ) ) . '">' . esc_html__( 'Letztes Ergebnis', 'wiedenroth-technologies-sentrygate' ) . '</p>';
		if ( $ok ) {
			echo '<h2 class="' . esc_attr( $ui->cls( 'result__title' ) ) . '">' . esc_html__( 'Core stimmt überein', 'wiedenroth-technologies-sentrygate' ) . '</h2>';
			echo '<p class="' . esc_attr( $ui->cls( 'result__text' ) ) . '">' . esc_html__( 'Keine abweichenden oder fehlenden Core-Dateien.', 'wiedenroth-technologies-sentrygate' ) . '</p>';
		} else {
			echo '<h2 class="' . esc_attr( $ui->cls( 'result__title' ) ) . '">' . esc_html__( 'Abweichungen gefunden', 'wiedenroth-technologies-sentrygate' ) . '</h2>';
			echo '<p class="' . esc_attr( $ui->cls( 'result__text' ) ) . '">' . esc_html__( 'SentryGate repariert nichts automatisch. Backup anlegen und Core ggf. neu installieren oder Entwickler informieren.', 'wiedenroth-technologies-sentrygate' ) . '</p>';
		}
		echo '</div></div>';

		echo '<div class="' . esc_attr( $ui->cls( 'result__body' ) ) . '">';
		echo '<div class="' . esc_attr( $ui->cls( 'stats' ) ) . '">';
		SentryGate_Admin::stat( $ui, (string) $checked, __( 'Dateien geprüft', 'wiedenroth-technologies-sentrygate' ), 'shield', 'accent' );
		SentryGate_Admin::stat(
			$ui,
			(string) count( $mismatch ),
			__( 'Abweichend', 'wiedenroth-technologies-sentrygate' ),
			count( $mismatch ) ? 'warn' : 'check',
			count( $mismatch ) ? 'warn' : 'ok'
		);
		SentryGate_Admin::stat(
			$ui,
			(string) count( $missing ),
			__( 'Fehlend', 'wiedenroth-technologies-sentrygate' ),
			count( $missing ) ? 'x' : 'check',
			count( $missing ) ? 'off' : 'ok'
		);
		echo '</div>';

		if ( '' !== $time || '' !== $version ) {
			echo '<ul class="' . esc_attr( $ui->cls( 'result__meta' ) ) . '">';
			if ( '' !== $time ) {
				echo '<li class="' . esc_attr( $ui->cls( 'result__chip' ) ) . '">';
				echo '<span class="' . esc_attr( $ui->cls( 'result__chip-label' ) ) . '">' . esc_html__( 'Zuletzt geprüft', 'wiedenroth-technologies-sentrygate' ) . '</span>';
				echo '<span class="' . esc_attr( $ui->cls( 'result__chip-value' ) ) . '">' . esc_html( $time ) . '</span>';
				echo '</li>';
			}
			if ( '' !== $version ) {
				echo '<li class="' . esc_attr( $ui->cls( 'result__chip' ) ) . '">';
				echo '<span class="' . esc_attr( $ui->cls( 'result__chip-label' ) ) . '">' . esc_html__( 'WordPress', 'wiedenroth-technologies-sentrygate' ) . '</span>';
				echo '<span class="' . esc_attr( $ui->cls( 'result__chip-value' ) ) . '">' . esc_html( $version ) . '</span>';
				echo '</li>';
			}
			echo '</ul>';
		}
		echo '</div></section>';

		if ( $ok ) {
			return;
		}

		if ( $mismatch ) {
			sentrygate_integrity_file_card( $ui, __( 'Abweichende Dateien', 'wiedenroth-technologies-sentrygate' ), $mismatch );
		}
		if ( $missing ) {
			sentrygate_integrity_file_card( $ui, __( 'Fehlende Dateien', 'wiedenroth-technologies-sentrygate' ), $missing );
		}
	}

	/**
	 * @param Wt_Admin_Ui       $ui    UI helper.
	 * @param string            $title Card title.
	 * @param array<int, mixed> $files Paths.
	 */
	function sentrygate_integrity_file_card( Wt_Admin_Ui $ui, string $title, array $files ): void {
		echo '<div class="' . esc_attr( $ui->cls( 'card', 'flush' ) ) . '">';
		$ui->heading( $title );
		echo '<div class="' . esc_attr( $ui->cls( 'table-wrap' ) ) . '">';
		echo '<table class="' . esc_attr( $ui->cls( 'table' ) ) . '">';
		echo '<thead><tr><th scope="col">' . esc_html__( 'Datei', 'wiedenroth-technologies-sentrygate' ) . '</th></tr></thead><tbody>';
		foreach ( $files as $file ) {
			echo '<tr><td><code class="' . esc_attr( $ui->cls( 'file' ) ) . '">' . esc_html( (string) $file ) . '</code></td></tr>';
		}
		echo '</tbody></table></div></div>';
	}
}

$last = SentryGate_Core_Integrity::last_result();
?>
<form method="post" action="options.php">
	<?php settings_fields( 'sentrygate_settings_group' ); ?>

	<div class="<?php echo esc_attr( $ui->cls( 'card' ) ); ?>">
		<?php
		$ui->heading(
			__( 'Stimmt WordPress noch mit dem Original überein?', 'wiedenroth-technologies-sentrygate' ),
			__( 'Der Check vergleicht die WordPress-Dateien mit der offiziellen Liste von wordpress.org. Abweichungen können ein Hinweis sein – SentryGate repariert nichts automatisch.', 'wiedenroth-technologies-sentrygate' ),
			__( 'Nach Updates einmal prüfen. Bei Abweichungen: Backup, dann Core ggf. neu installieren oder Entwickler informieren.', 'wiedenroth-technologies-sentrygate' )
		);

		SentryGate_Admin::field_toggle(
			$ui,
			'core_check_on_dashboard',
			$options['core_check_on_dashboard'] ?? true,
			__( 'In der Übersicht zeigen?', 'wiedenroth-technologies-sentrygate' ),
			__( 'Ja, Status auch auf der Übersichtsseite anzeigen', 'wiedenroth-technologies-sentrygate' )
		);
		?>
	</div>

	<?php SentryGate_Admin::save_actions( $ui, __( 'Einstellung speichern', 'wiedenroth-technologies-sentrygate' ) ); ?>
</form>

<section class="<?php echo esc_attr( $ui->cls( 'card' ) ); ?>">
	<div class="<?php echo esc_attr( $ui->cls( 'check-run' ) ); ?>">
		<?php
		$ui->heading(
			__( 'Prüfung starten', 'wiedenroth-technologies-sentrygate' ),
			__( 'Dauert je nach Server ein paar Sekunden. Es werden keine Dateien verändert.', 'wiedenroth-technologies-sentrygate' )
		);
		?>
		<div class="<?php echo esc_attr( $ui->cls( 'check-run__actions' ) ); ?>">
			<button type="button" class="<?php echo esc_attr( $ui->cls( 'btn', 'primary' ) ); ?>" id="sentrygate-run-checksum">
				<?php esc_html_e( 'Jetzt prüfen', 'wiedenroth-technologies-sentrygate' ); ?>
			</button>
			<p id="sentrygate-checksum-status" class="<?php echo esc_attr( $ui->cls( 'check-status' ) ); ?>" aria-live="polite"></p>
		</div>
	</div>
</section>

<div id="sentrygate-checksum-result" class="<?php echo esc_attr( $ui->cls( 'check-result' ) ); ?>">
	<?php sentrygate_render_integrity_result( $ui, $last ); ?>
</div>

<?php
$cta_href = add_query_arg(
	'url',
	home_url( '/' ),
	'https://wiedenroth.tech/tools/sicherheits-checker'
);
?>
<aside class="<?php echo esc_attr( $ui->cls( 'cta' ) ); ?>">
	<div class="<?php echo esc_attr( $ui->cls( 'cta__row' ) ); ?>">
		<div class="<?php echo esc_attr( $ui->cls( 'cta__copy' ) ); ?>">
			<p class="<?php echo esc_attr( $ui->cls( 'cta__badge' ) ); ?>">
				<span class="<?php echo esc_attr( $ui->cls( 'cta__badge-icon' ) ); ?>" aria-hidden="true">
					<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/></svg>
				</span>
				<?php esc_html_e( 'Wiedenroth Technologies Sicherheits-Check', 'wiedenroth-technologies-sentrygate' ); ?>
			</p>
			<h2 class="<?php echo esc_attr( $ui->cls( 'cta__title' ) ); ?>"><?php esc_html_e( 'Website von außen scannen', 'wiedenroth-technologies-sentrygate' ); ?></h2>
			<p class="<?php echo esc_attr( $ui->cls( 'cta__lead' ) ); ?>"><?php esc_html_e( 'Der Check oben prüft Dateien auf diesem Server. Der Sicherheits-Checker prüft die Website von außen: TLS, Header, Login und sichtbare Lücken.', 'wiedenroth-technologies-sentrygate' ); ?></p>
		</div>
		<div class="<?php echo esc_attr( $ui->cls( 'cta__action' ) ); ?>">
			<a class="<?php echo esc_attr( $ui->cls( 'cta__go' ) ); ?>" href="<?php echo esc_url( $cta_href ); ?>" target="_blank" rel="noopener noreferrer">
				<?php esc_html_e( 'Seite prüfen', 'wiedenroth-technologies-sentrygate' ); ?> →
			</a>
		</div>
	</div>
	<div class="<?php echo esc_attr( $ui->cls( 'cta__foot' ) ); ?>">
		<p class="<?php echo esc_attr( $ui->cls( 'cta__hint' ) ); ?>"><?php esc_html_e( 'Der Scan liest nur öffentlich sichtbare Informationen. Kein Penetrationstest, keine Exploits. Das Ergebnis ist eine technische Ersteinschätzung.', 'wiedenroth-technologies-sentrygate' ); ?></p>
		<p class="<?php echo esc_attr( $ui->cls( 'cta__note' ) ); ?>"><?php esc_html_e( 'Nur öffentlich erreichbare Seiten. Nicht-invasiv, etwa 20–40 Sekunden.', 'wiedenroth-technologies-sentrygate' ); ?></p>
	</div>
</aside>
