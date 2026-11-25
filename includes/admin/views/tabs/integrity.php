<?php
/**
 * Core integrity tab.
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

if ( ! function_exists( 'sentrygate_render_integrity_result' ) ) {
	/**
	 * Render checksum result (empty / error / stats).
	 *
	 * @param SentryGate_Admin_Ui               $ui   UI helper.
	 * @param array<string, mixed>|null $last Last result.
	 */
	function sentrygate_render_integrity_result( SentryGate_Admin_Ui $ui, ?array $last ): void {
		if ( null === $last ) {
			echo '<div class="' . esc_attr( $ui->cls( 'card' ) ) . '">';
			echo '<div class="' . esc_attr( $ui->cls( 'empty' ) ) . '">';
			echo '<div class="' . esc_attr( $ui->cls( 'empty__icon' ) ) . '" aria-hidden="true">◇</div>';
			echo '<h2 class="' . esc_attr( $ui->cls( 'empty__title' ) ) . '">' . esc_html__( 'No check yet', 'wiedenroth-technologies-sentrygate' ) . '</h2>';
			echo '<p class="' . esc_attr( $ui->cls( 'empty__text' ) ) . '">';
			echo esc_html__( 'Run the check to compare the WordPress core files with the official list from wordpress.org.', 'wiedenroth-technologies-sentrygate' );
			echo '</p>';
			echo '</div></div>';
			return;
		}

		if ( ! empty( $last['error'] ) ) {
			$ui->notice( 'error', (string) $last['error'], __( 'Check failed', 'wiedenroth-technologies-sentrygate' ) );
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
		echo '<p class="' . esc_attr( $ui->cls( 'result__eyebrow' ) ) . '">' . esc_html__( 'Last result', 'wiedenroth-technologies-sentrygate' ) . '</p>';
		if ( $ok ) {
			echo '<h2 class="' . esc_attr( $ui->cls( 'result__title' ) ) . '">' . esc_html__( 'Core matches', 'wiedenroth-technologies-sentrygate' ) . '</h2>';
			echo '<p class="' . esc_attr( $ui->cls( 'result__text' ) ) . '">' . esc_html__( 'No mismatched or missing core files.', 'wiedenroth-technologies-sentrygate' ) . '</p>';
		} else {
			echo '<h2 class="' . esc_attr( $ui->cls( 'result__title' ) ) . '">' . esc_html__( 'Mismatches found', 'wiedenroth-technologies-sentrygate' ) . '</h2>';
			echo '<p class="' . esc_attr( $ui->cls( 'result__text' ) ) . '">' . esc_html__( 'SentryGate does not repair anything automatically. Create a backup and reinstall core if needed, or tell a developer.', 'wiedenroth-technologies-sentrygate' ) . '</p>';
		}
		echo '</div></div>';

		echo '<div class="' . esc_attr( $ui->cls( 'result__body' ) ) . '">';
		echo '<div class="' . esc_attr( $ui->cls( 'stats' ) ) . '">';
		SentryGate_Admin::stat( $ui, (string) $checked, __( 'Files checked', 'wiedenroth-technologies-sentrygate' ), 'shield', 'accent' );
		SentryGate_Admin::stat(
			$ui,
			(string) count( $mismatch ),
			__( 'Mismatch', 'wiedenroth-technologies-sentrygate' ),
			count( $mismatch ) ? 'warn' : 'check',
			count( $mismatch ) ? 'warn' : 'ok'
		);
		SentryGate_Admin::stat(
			$ui,
			(string) count( $missing ),
			__( 'Missing', 'wiedenroth-technologies-sentrygate' ),
			count( $missing ) ? 'x' : 'check',
			count( $missing ) ? 'off' : 'ok'
		);
		echo '</div>';

		if ( '' !== $time || '' !== $version ) {
			echo '<ul class="' . esc_attr( $ui->cls( 'result__meta' ) ) . '">';
			if ( '' !== $time ) {
				echo '<li class="' . esc_attr( $ui->cls( 'result__chip' ) ) . '">';
				echo '<span class="' . esc_attr( $ui->cls( 'result__chip-label' ) ) . '">' . esc_html__( 'Last checked', 'wiedenroth-technologies-sentrygate' ) . '</span>';
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
			sentrygate_integrity_file_card( $ui, __( 'Mismatched files', 'wiedenroth-technologies-sentrygate' ), $mismatch );
		}
		if ( $missing ) {
			sentrygate_integrity_file_card( $ui, __( 'Missing files', 'wiedenroth-technologies-sentrygate' ), $missing );
		}
	}

	/**
	 * @param SentryGate_Admin_Ui       $ui    UI helper.
	 * @param string            $title Card title.
	 * @param array<int, mixed> $files Paths.
	 */
	function sentrygate_integrity_file_card( SentryGate_Admin_Ui $ui, string $title, array $files ): void {
		echo '<div class="' . esc_attr( $ui->cls( 'card', 'flush' ) ) . '">';
		$ui->heading( $title );
		echo '<div class="' . esc_attr( $ui->cls( 'table-wrap' ) ) . '">';
		echo '<table class="' . esc_attr( $ui->cls( 'table' ) ) . '">';
		echo '<thead><tr><th scope="col">' . esc_html__( 'File', 'wiedenroth-technologies-sentrygate' ) . '</th></tr></thead><tbody>';
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
			__( 'Does WordPress still match the original?', 'wiedenroth-technologies-sentrygate' ),
			__( 'The check compares the WordPress files with the official list from wordpress.org. Differences can be a warning sign – SentryGate does not repair anything automatically.', 'wiedenroth-technologies-sentrygate' ),
			__( 'Check once after updates. If there are differences: back up, then reinstall core if needed or tell a developer.', 'wiedenroth-technologies-sentrygate' )
		);

		SentryGate_Admin::field_toggle(
			$ui,
			'core_check_on_dashboard',
			$options['core_check_on_dashboard'] ?? true,
			__( 'Show on the overview?', 'wiedenroth-technologies-sentrygate' ),
			__( 'Yes, also show the status on the overview page', 'wiedenroth-technologies-sentrygate' )
		);
		?>
	</div>

	<?php SentryGate_Admin::save_actions( $ui, __( 'Save setting', 'wiedenroth-technologies-sentrygate' ) ); ?>
</form>

<section class="<?php echo esc_attr( $ui->cls( 'card' ) ); ?>">
	<div class="<?php echo esc_attr( $ui->cls( 'check-run' ) ); ?>">
		<?php
		$ui->heading(
			__( 'Start check', 'wiedenroth-technologies-sentrygate' ),
			__( 'Takes a few seconds, depending on the server. No files are changed.', 'wiedenroth-technologies-sentrygate' )
		);
		?>
		<div class="<?php echo esc_attr( $ui->cls( 'check-run__actions' ) ); ?>">
			<button type="button" class="<?php echo esc_attr( $ui->cls( 'btn', 'primary' ) ); ?>" id="sentrygate-run-checksum">
				<?php esc_html_e( 'Check now', 'wiedenroth-technologies-sentrygate' ); ?>
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
				<?php esc_html_e( 'Wiedenroth Technologies security check', 'wiedenroth-technologies-sentrygate' ); ?>
			</p>
			<h2 class="<?php echo esc_attr( $ui->cls( 'cta__title' ) ); ?>"><?php esc_html_e( 'Scan the site from the outside', 'wiedenroth-technologies-sentrygate' ); ?></h2>
			<p class="<?php echo esc_attr( $ui->cls( 'cta__lead' ) ); ?>"><?php esc_html_e( 'The check above inspects files on this server. The security checker inspects the site from the outside: TLS, headers, login, and visible gaps.', 'wiedenroth-technologies-sentrygate' ); ?></p>
		</div>
		<div class="<?php echo esc_attr( $ui->cls( 'cta__action' ) ); ?>">
			<a class="<?php echo esc_attr( $ui->cls( 'cta__go' ) ); ?>" href="<?php echo esc_url( $cta_href ); ?>" target="_blank" rel="noopener noreferrer">
				<?php esc_html_e( 'Check site', 'wiedenroth-technologies-sentrygate' ); ?> →
			</a>
		</div>
	</div>
	<div class="<?php echo esc_attr( $ui->cls( 'cta__foot' ) ); ?>">
		<p class="<?php echo esc_attr( $ui->cls( 'cta__hint' ) ); ?>"><?php esc_html_e( 'The scan reads only publicly visible information. Not a penetration test, and no exploits. The result is a first technical assessment.', 'wiedenroth-technologies-sentrygate' ); ?></p>
		<p class="<?php echo esc_attr( $ui->cls( 'cta__note' ) ); ?>"><?php esc_html_e( 'Publicly reachable pages only. Non-invasive, about 20–40 seconds.', 'wiedenroth-technologies-sentrygate' ); ?></p>
	</div>
</aside>
