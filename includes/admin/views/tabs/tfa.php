<?php
/**
 * Two-factor settings tab.
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

$roles    = wp_roles()->get_names();
$selected = (array) ( $options['tfa_required_roles'] ?? array() );
?>
<form method="post" action="options.php">
	<?php settings_fields( 'sentrygate_settings_group' ); ?>

	<div class="<?php echo esc_attr( $ui->cls( 'card' ) ); ?>">
		<?php
		$ui->heading(
			__( 'Second step after the password', 'wiedenroth-technologies-sentrygate' ),
			__( 'Even if the password is stolen, the attacker also needs a code. Each user chooses an authenticator app or email on their own "Two-factor" page.', 'wiedenroth-technologies-sentrygate' ),
			__( 'Be sure to mark administrators as a required role.', 'wiedenroth-technologies-sentrygate' )
		);
		?>

		<div class="<?php echo esc_attr( $ui->cls( 'field' ) ); ?>">
			<span class="<?php echo esc_attr( $ui->cls( 'field__label' ) ); ?>"><?php esc_html_e( 'Who must use two-factor?', 'wiedenroth-technologies-sentrygate' ); ?></span>
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
				<?php $ui->help( __( 'Marked roles are sent to the setup page after login until a method is active.', 'wiedenroth-technologies-sentrygate' ) ); ?>
			</div>
		</div>

		<?php
		SentryGate_Admin::field_toggle(
			$ui,
			'tfa_email_fallback',
			$options['tfa_email_fallback'] ?? true,
			__( 'Allow email as a method?', 'wiedenroth-technologies-sentrygate' ),
			__( 'Yes, users may choose email instead of an app', 'wiedenroth-technologies-sentrygate' ),
			__( 'Without this option, only the authenticator app remains. Email is not a second method alongside the app; it is a choice between the two.', 'wiedenroth-technologies-sentrygate' )
		);

		SentryGate_Admin::field_number(
			$ui,
			'tfa_trusted_days',
			$options['tfa_trusted_days'] ?? 30,
			__( 'Remember device', 'wiedenroth-technologies-sentrygate' ),
			__( 'Days without another code. Users can choose "Trust this device" at login. Do not check it on a shared computer.', 'wiedenroth-technologies-sentrygate' ),
			1,
			365
		);

		SentryGate_Admin::field_number(
			$ui,
			'tfa_recovery_count',
			$options['tfa_recovery_count'] ?? 10,
			__( 'Emergency codes', 'wiedenroth-technologies-sentrygate' ),
			__( 'Number of new codes (8–10). One-time backup codes in case the phone or app is gone. Keep them safe.', 'wiedenroth-technologies-sentrygate' ),
			8,
			10
		);
		?>
	</div>

	<?php SentryGate_Admin::save_actions( $ui ); ?>
</form>

<div class="<?php echo esc_attr( $ui->cls( 'notice', 'info shop' ) ); ?>">
	<div class="<?php echo esc_attr( $ui->cls( 'notice__body' ) ); ?>">
		<p class="<?php echo esc_attr( $ui->cls( 'notice__title' ) ); ?>"><?php esc_html_e( 'Separate setup page', 'wiedenroth-technologies-sentrygate' ); ?></p>
		<p class="<?php echo esc_attr( $ui->cls( 'notice__text' ) ); ?>"><?php esc_html_e( 'Not in the profile: Users → Two-factor. Choose an app or email there, confirm, done.', 'wiedenroth-technologies-sentrygate' ); ?></p>
	</div>
	<div class="<?php echo esc_attr( $ui->cls( 'notice__actions' ) ); ?>">
		<?php $ui->button( __( 'My two-factor page', 'wiedenroth-technologies-sentrygate' ), 'primary', 'button', SentryGate_Two_Factor::setup_url() ); ?>
	</div>
</div>
