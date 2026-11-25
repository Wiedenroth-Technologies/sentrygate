<?php
/**
 * Security headers tab.
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
?>
<form method="post" action="options.php">
	<?php settings_fields( 'sentrygate_settings_group' ); ?>

	<div class="<?php echo esc_attr( $ui->cls( 'card' ) ); ?>">
		<?php
		$ui->heading(
			__( 'Instructions for the browser', 'wiedenroth-technologies-sentrygate' ),
			__( 'These settings tell the browser what is allowed – for example, whether your page may be embedded on other sites. The defaults are enough for most sites.', 'wiedenroth-technologies-sentrygate' ),
			__( 'Turn on HSTS only if the whole site reliably runs over HTTPS. Leave CSP in report-only mode at first.', 'wiedenroth-technologies-sentrygate' )
		);
		?>

		<div class="<?php echo esc_attr( $ui->cls( 'field' ) ); ?>">
			<div class="<?php echo esc_attr( $ui->cls( 'field__label' ) ); ?>">
				<?php esc_html_e( 'Embedding on other sites', 'wiedenroth-technologies-sentrygate' ); ?>
				<div class="<?php echo esc_attr( $ui->cls( 'tech' ) ); ?>">X-Frame-Options</div>
			</div>
			<div class="<?php echo esc_attr( $ui->cls( 'field__control' ) ); ?>">
				<?php
				SentryGate_Admin::toggle(
					$ui,
					'header_x_frame_options',
					$options['header_x_frame_options'] ?? true,
					__( 'Enable protection', 'wiedenroth-technologies-sentrygate' )
				);
				?>
				<select class="<?php echo esc_attr( $ui->cls( 'select' ) ); ?>" name="<?php echo esc_attr( SentryGate_Admin::option_name( 'header_x_frame_value' ) ); ?>">
					<option value="SAMEORIGIN" <?php selected( $options['header_x_frame_value'] ?? '', 'SAMEORIGIN' ); ?>><?php esc_html_e( 'Allow on this domain only', 'wiedenroth-technologies-sentrygate' ); ?></option>
					<option value="DENY" <?php selected( $options['header_x_frame_value'] ?? '', 'DENY' ); ?>><?php esc_html_e( 'Deny everywhere', 'wiedenroth-technologies-sentrygate' ); ?></option>
				</select>
				<?php $ui->help( __( 'Protects against "clickjacking" – your page is not secretly misused inside a frame.', 'wiedenroth-technologies-sentrygate' ) ); ?>
			</div>
		</div>

		<?php
		SentryGate_Admin::field_toggle(
			$ui,
			'header_x_content_type',
			$options['header_x_content_type'] ?? true,
			__( 'Take file types seriously', 'wiedenroth-technologies-sentrygate' ),
			__( 'Browser should not guess file types (nosniff)', 'wiedenroth-technologies-sentrygate' ),
			__( 'Stops harmful files from being executed as something else.', 'wiedenroth-technologies-sentrygate' ),
			'X-Content-Type-Options'
		);
		?>

		<div class="<?php echo esc_attr( $ui->cls( 'field' ) ); ?>">
			<div class="<?php echo esc_attr( $ui->cls( 'field__label' ) ); ?>">
				<?php esc_html_e( 'Sharing the referrer address', 'wiedenroth-technologies-sentrygate' ); ?>
				<div class="<?php echo esc_attr( $ui->cls( 'tech' ) ); ?>">Referrer-Policy</div>
			</div>
			<div class="<?php echo esc_attr( $ui->cls( 'field__control' ) ); ?>">
				<?php
				SentryGate_Admin::toggle(
					$ui,
					'header_referrer_policy',
					$options['header_referrer_policy'] ?? true,
					__( 'Set a rule', 'wiedenroth-technologies-sentrygate' )
				);
				SentryGate_Admin::text( $ui, 'header_referrer_value', $options['header_referrer_value'] ?? 'strict-origin-when-cross-origin' );
				$ui->help( __( 'The default is privacy-friendly and suitable for most sites. Change it only if you know the value.', 'wiedenroth-technologies-sentrygate' ) );
				?>
			</div>
		</div>

		<div class="<?php echo esc_attr( $ui->cls( 'field' ) ); ?>">
			<div class="<?php echo esc_attr( $ui->cls( 'field__label' ) ); ?>">
				<?php esc_html_e( 'Always encrypted (HTTPS)', 'wiedenroth-technologies-sentrygate' ); ?>
				<div class="<?php echo esc_attr( $ui->cls( 'tech' ) ); ?>">Strict-Transport-Security (HSTS)</div>
			</div>
			<div class="<?php echo esc_attr( $ui->cls( 'field__control' ) ); ?>">
				<div class="<?php echo esc_attr( $ui->cls( 'stack' ) ); ?>">
					<?php
					SentryGate_Admin::toggle(
						$ui,
						'header_hsts',
						$options['header_hsts'] ?? false,
						__( 'Force the browser to use only HTTPS from now on', 'wiedenroth-technologies-sentrygate' )
					);
					?>
					<label for="<?php echo esc_attr( SentryGate_Admin::field_id( 'header_hsts_max_age' ) ); ?>" class="<?php echo esc_attr( $ui->cls( 'inline-fields' ) ); ?>">
						<?php esc_html_e( 'Lifetime in seconds', 'wiedenroth-technologies-sentrygate' ); ?>
						<?php SentryGate_Admin::number( $ui, 'header_hsts_max_age', $options['header_hsts_max_age'] ?? 31536000, 300, 63072000 ); ?>
					</label>
				</div>
				<?php $ui->help( __( 'Only with HTTPS throughout. 31536000 ≈ 1 year. Set incorrectly, it can make the site hard to reach.', 'wiedenroth-technologies-sentrygate' ) ); ?>
			</div>
		</div>

		<div class="<?php echo esc_attr( $ui->cls( 'field' ) ); ?>">
			<div class="<?php echo esc_attr( $ui->cls( 'field__label' ) ); ?>">
				<?php esc_html_e( 'What scripts and content may do', 'wiedenroth-technologies-sentrygate' ); ?>
				<div class="<?php echo esc_attr( $ui->cls( 'tech' ) ); ?>">Content-Security-Policy (CSP)</div>
			</div>
			<div class="<?php echo esc_attr( $ui->cls( 'field__control' ) ); ?>">
				<div class="<?php echo esc_attr( $ui->cls( 'stack' ) ); ?>">
					<?php
					SentryGate_Admin::toggle(
						$ui,
						'header_csp',
						$options['header_csp'] ?? true,
						__( 'Send policy', 'wiedenroth-technologies-sentrygate' )
					);
					SentryGate_Admin::toggle(
						$ui,
						'header_csp_report_only',
						$options['header_csp_report_only'] ?? true,
						__( 'Report only, do not block anything yet (recommended)', 'wiedenroth-technologies-sentrygate' )
					);
					?>
					<textarea
						class="<?php echo esc_attr( $ui->cls( 'textarea' ) ); ?>"
						rows="4"
						name="<?php echo esc_attr( SentryGate_Admin::option_name( 'header_csp_policy' ) ); ?>"
					><?php echo esc_textarea( (string) ( $options['header_csp_policy'] ?? '' ) ); ?></textarea>
				</div>
				<?php $ui->help( __( 'Test in report-only mode first. Clear the "report only" checkbox only when nothing breaks.', 'wiedenroth-technologies-sentrygate' ) ); ?>
			</div>
		</div>
	</div>

	<?php SentryGate_Admin::save_actions( $ui ); ?>
</form>
