<?php
/**
 * Login challenge settings.
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
			__( 'Extra protection at login', 'wiedenroth-technologies-sentrygate' ),
			__( 'The check appears before the username and password. After you pass it, SentryGate stores an HttpOnly session cookie – you will not be asked again in this browser window. Direct POSTs without a cookie are rejected without checking the password.', 'wiedenroth-technologies-sentrygate' ),
			__( 'The symbol choice is a hurdle against simple bots, not a captcha like those from Google. Protection is stronger together with the hidden login URL.', 'wiedenroth-technologies-sentrygate' )
		);
		?>

		<div class="<?php echo esc_attr( $ui->cls( 'field' ) ); ?>">
			<label class="<?php echo esc_attr( $ui->cls( 'field__label' ) ); ?>" for="sentrygate-challenge-mode"><?php esc_html_e( 'Type of protection', 'wiedenroth-technologies-sentrygate' ); ?></label>
			<div class="<?php echo esc_attr( $ui->cls( 'field__control' ) ); ?>">
				<select class="<?php echo esc_attr( $ui->cls( 'select' ) ); ?>" id="sentrygate-challenge-mode" name="<?php echo esc_attr( SentryGate_Admin::option_name( 'challenge_mode' ) ); ?>">
					<option value="full" <?php selected( $options['challenge_mode'] ?? 'full', 'full' ); ?>><?php esc_html_e( 'Full – symbol choice before login + session cookie', 'wiedenroth-technologies-sentrygate' ); ?></option>
					<option value="lightweight" <?php selected( $options['challenge_mode'] ?? '', 'lightweight' ); ?>><?php esc_html_e( 'Light – confirm only + invisible traps + cookie', 'wiedenroth-technologies-sentrygate' ); ?></option>
				</select>
				<?php $ui->help( __( 'The cookie is signed (HMAC), HttpOnly, and lasts until the browser is closed, for at most 12 hours. It cannot be read by JavaScript.', 'wiedenroth-technologies-sentrygate' ) ); ?>
			</div>
		</div>

		<?php
		SentryGate_Admin::field_number(
			$ui,
			'challenge_min_time',
			$options['challenge_min_time'] ?? 2,
			__( 'Minimum wait time', 'wiedenroth-technologies-sentrygate' ),
			__( 'Seconds before submitting is allowed. Bots often submit forms immediately. 2 seconds is usually enough.', 'wiedenroth-technologies-sentrygate' ),
			0,
			60
		);
		?>

		<div class="<?php echo esc_attr( $ui->cls( 'field' ) ); ?>">
			<span class="<?php echo esc_attr( $ui->cls( 'field__label' ) ); ?>"><?php esc_html_e( 'Too many failed attempts', 'wiedenroth-technologies-sentrygate' ); ?></span>
			<div class="<?php echo esc_attr( $ui->cls( 'field__control' ) ); ?>">
				<div class="<?php echo esc_attr( $ui->cls( 'inline-fields' ) ); ?>">
					<label for="<?php echo esc_attr( SentryGate_Admin::field_id( 'rate_limit_max_attempts' ) ); ?>">
						<?php esc_html_e( 'Allowed attempts', 'wiedenroth-technologies-sentrygate' ); ?>
						<?php SentryGate_Admin::number( $ui, 'rate_limit_max_attempts', $options['rate_limit_max_attempts'] ?? 5, 1, 100 ); ?>
					</label>
					<label for="<?php echo esc_attr( SentryGate_Admin::field_id( 'rate_limit_window' ) ); ?>">
						<?php esc_html_e( 'Time window in seconds', 'wiedenroth-technologies-sentrygate' ); ?>
						<?php SentryGate_Admin::number( $ui, 'rate_limit_window', $options['rate_limit_window'] ?? 900, 60, 86400 ); ?>
					</label>
					<label for="<?php echo esc_attr( SentryGate_Admin::field_id( 'rate_limit_lockout' ) ); ?>">
						<?php esc_html_e( 'Lockout duration in seconds', 'wiedenroth-technologies-sentrygate' ); ?>
						<?php SentryGate_Admin::number( $ui, 'rate_limit_lockout', $options['rate_limit_lockout'] ?? 900, 60, 86400 ); ?>
					</label>
				</div>
				<?php
				SentryGate_Admin::toggle(
					$ui,
					'rate_limit_exponential',
					$options['rate_limit_exponential'] ?? true,
					__( 'Make the lockout longer on repeat', 'wiedenroth-technologies-sentrygate' )
				);
				$ui->help( __( 'Example: 5 failed attempts in 15 minutes → 15-minute pause. 900 seconds = 15 minutes.', 'wiedenroth-technologies-sentrygate' ) );
				?>
			</div>
		</div>

		<?php
		SentryGate_Admin::field_toggle(
			$ui,
			'trust_proxy_headers',
			$options['trust_proxy_headers'] ?? false,
			__( 'Site behind Cloudflare / proxy?', 'wiedenroth-technologies-sentrygate' ),
			__( 'Yes, read the real visitor IP from proxy headers', 'wiedenroth-technologies-sentrygate' ),
			__( 'Turn this on only if a trusted proxy or CDN is in front. Otherwise leave it off.', 'wiedenroth-technologies-sentrygate' )
		);
		?>
	</div>

	<?php SentryGate_Admin::save_actions( $ui ); ?>
</form>
