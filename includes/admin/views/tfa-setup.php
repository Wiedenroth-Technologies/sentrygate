<?php
/**
 * Dedicated two-factor setup page (not the WP profile).
 *
 * @package SentryGate
 *
 * @var SentryGate_Admin_Ui        $ui
 * @var WP_User            $user
 * @var bool               $enabled
 * @var string             $method
 * @var string             $pending
 * @var bool               $email_allowed
 * @var array<int, string> $recovery_once
 * @var string             $secret
 * @var string             $uri
 * @var string             $qr_svg
 * @var string             $notice
 * @var string             $error
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$form_action  = SentryGate_Two_Factor::setup_url( $user->ID );
$is_other     = get_current_user_id() !== $user->ID;
$show_chooser = '' === $pending;
?>
<div class="wrap <?php echo esc_attr( $ui->cls( 'app' ) ); ?>">
	<div class="wp-header-end"></div>
	<header class="<?php echo esc_attr( $ui->cls( 'header' ) ); ?>">
		<div class="<?php echo esc_attr( $ui->cls( 'header__titles' ) ); ?>">
			<h1 class="<?php echo esc_attr( $ui->cls( 'title' ) ); ?>"><?php esc_html_e( 'Two-factor', 'wiedenroth-technologies-sentrygate' ); ?></h1>
			<p class="<?php echo esc_attr( $ui->cls( 'tagline' ) ); ?>">
				<?php
				if ( $is_other ) {
					printf(
						/* translators: %s: user login */
						esc_html__( 'Setup for %s. Choose an authenticator app or email.', 'wiedenroth-technologies-sentrygate' ),
						esc_html( $user->user_login )
					);
				} else {
					esc_html_e( 'Second step after the password: authenticator app or email – one method is enough.', 'wiedenroth-technologies-sentrygate' );
				}
				?>
			</p>
		</div>
	</header>

	<div class="<?php echo esc_attr( $ui->cls( 'content' ) ); ?>">
		<?php
		if ( 'bad_code' === $error ) {
			$ui->notice( 'error', __( 'The code is invalid or expired. Please try again.', 'wiedenroth-technologies-sentrygate' ), __( 'Confirmation failed', 'wiedenroth-technologies-sentrygate' ) );
		} elseif ( 'no_email' === $error ) {
			$ui->notice( 'error', __( 'This account has no valid email address, or email is turned off.', 'wiedenroth-technologies-sentrygate' ), __( 'Email not available', 'wiedenroth-technologies-sentrygate' ) );
		}

		if ( 'ok' === $notice ) {
			$ui->notice( 'success', __( 'Two-factor is active. Keep the emergency codes safe – they are shown only once.', 'wiedenroth-technologies-sentrygate' ), __( 'Setup complete', 'wiedenroth-technologies-sentrygate' ) );
		} elseif ( 'email' === $notice ) {
			$ui->notice(
				'info',
				sprintf(
					/* translators: %s: email address */
					__( 'A code was sent to %s. It is valid for 10 minutes.', 'wiedenroth-technologies-sentrygate' ),
					$user->user_email
				)
			);
		} elseif ( 'disabled' === $notice ) {
			$ui->notice( 'success', __( 'Two-factor is turned off.', 'wiedenroth-technologies-sentrygate' ) );
		} elseif ( 'codes' === $notice ) {
			$ui->notice( 'warning', __( 'The old emergency codes are invalid. Save the new codes below now.', 'wiedenroth-technologies-sentrygate' ) );
		}

		if ( $recovery_once ) :
			?>
			<div class="<?php echo esc_attr( $ui->cls( 'notice', 'warning' ) ); ?>" role="status">
				<p class="<?php echo esc_attr( $ui->cls( 'notice__title' ) ); ?>"><?php esc_html_e( 'Emergency codes – shown only once', 'wiedenroth-technologies-sentrygate' ); ?></p>
				<p class="<?php echo esc_attr( $ui->cls( 'notice__text' ) ); ?>"><?php esc_html_e( 'Each code works once if the app or email cannot be reached.', 'wiedenroth-technologies-sentrygate' ); ?></p>
				<ul class="<?php echo esc_attr( $ui->cls( 'codes' ) ); ?>">
					<?php foreach ( $recovery_once as $code ) : ?>
						<li><code><?php echo esc_html( $code ); ?></code></li>
					<?php endforeach; ?>
				</ul>
			</div>
			<?php
		endif;
		?>

		<form method="post" action="<?php echo esc_url( $form_action ); ?>">
			<?php wp_nonce_field( 'sentrygate_tfa_setup', 'sentrygate_tfa_nonce' ); ?>
			<input type="hidden" name="page" value="<?php echo esc_attr( SentryGate_Two_Factor::PAGE_SLUG ); ?>" />
			<?php if ( $is_other ) : ?>
				<input type="hidden" name="user_id" value="<?php echo esc_attr( (string) $user->ID ); ?>" />
			<?php endif; ?>

			<?php if ( $enabled && $show_chooser ) : ?>
				<section class="<?php echo esc_attr( $ui->cls( 'card' ) ); ?>">
					<?php
					$ui->heading(
						__( 'Active protection', 'wiedenroth-technologies-sentrygate' ),
						'email' === $method
							? sprintf(
								/* translators: %s: email */
								__( 'Logins need a code by email to %s.', 'wiedenroth-technologies-sentrygate' ),
								$user->user_email
							)
							: __( 'Logins need a code from the authenticator app.', 'wiedenroth-technologies-sentrygate' )
					);
					$ui->badge( 'ok', 'email' === $method ? __( 'Email active', 'wiedenroth-technologies-sentrygate' ) : __( 'App active', 'wiedenroth-technologies-sentrygate' ) );
					?>
					<div class="<?php echo esc_attr( $ui->cls( 'row' ) . ' ' . $ui->cls( 'tfa-actions' ) ); ?>">
						<button type="submit" name="sentrygate_tfa_action" value="regen_recovery" class="<?php echo esc_attr( $ui->cls( 'btn', 'secondary' ) ); ?>">
							<?php esc_html_e( 'New emergency codes', 'wiedenroth-technologies-sentrygate' ); ?>
						</button>
						<button type="submit" name="sentrygate_tfa_action" value="disable" class="<?php echo esc_attr( $ui->cls( 'btn', 'danger' ) ); ?>">
							<?php esc_html_e( 'Turn off two-factor', 'wiedenroth-technologies-sentrygate' ); ?>
						</button>
					</div>
				</section>
			<?php endif; ?>

			<?php if ( $show_chooser ) : ?>
				<section class="<?php echo esc_attr( $ui->cls( 'card' ) ); ?>">
					<?php
					$ui->heading(
						$enabled ? __( 'Change method', 'wiedenroth-technologies-sentrygate' ) : __( 'Choose method', 'wiedenroth-technologies-sentrygate' ),
						__( 'Only one method is active. You can switch to the other one here later.', 'wiedenroth-technologies-sentrygate' ),
						__( 'The app does not depend on the inbox. Email needs a reachable address on the account.', 'wiedenroth-technologies-sentrygate' )
					);
					?>
					<div class="<?php echo esc_attr( $ui->cls( 'choice-grid' ) ); ?>">
						<button type="submit" name="sentrygate_tfa_action" value="choose_app" class="<?php echo esc_attr( $ui->cls( 'tile' ) ); ?>">
							<span class="<?php echo esc_attr( $ui->cls( 'tile__title' ) ); ?>"><?php esc_html_e( 'Authenticator app', 'wiedenroth-technologies-sentrygate' ); ?></span>
							<span class="<?php echo esc_attr( $ui->cls( 'tile__text' ) ); ?>"><?php esc_html_e( 'Google Authenticator, Authy, 1Password, and similar apps. Scan the QR code, then confirm once.', 'wiedenroth-technologies-sentrygate' ); ?></span>
						</button>
						<?php if ( $email_allowed ) : ?>
							<button type="submit" name="sentrygate_tfa_action" value="choose_email" class="<?php echo esc_attr( $ui->cls( 'tile' ) ); ?>">
								<span class="<?php echo esc_attr( $ui->cls( 'tile__title' ) ); ?>"><?php esc_html_e( 'Email', 'wiedenroth-technologies-sentrygate' ); ?></span>
								<span class="<?php echo esc_attr( $ui->cls( 'tile__text' ) ); ?>">
									<?php
									printf(
										/* translators: %s: email */
										esc_html__( 'A code to %s at every login. Valid for 10 minutes.', 'wiedenroth-technologies-sentrygate' ),
										esc_html( $user->user_email )
									);
									?>
								</span>
							</button>
						<?php endif; ?>
					</div>
				</section>
			<?php endif; ?>

			<?php if ( 'app' === $pending ) : ?>
				<section class="<?php echo esc_attr( $ui->cls( 'card' ) ); ?>">
					<?php
					$ui->heading(
						__( 'Set up authenticator app', 'wiedenroth-technologies-sentrygate' ),
						__( 'Scan the QR code in the app or enter the secret manually. Then confirm the current 6-digit code here.', 'wiedenroth-technologies-sentrygate' )
					);
					?>
					<?php if ( $qr_svg ) : ?>
						<div class="<?php echo esc_attr( $ui->cls( 'qr' ) ); ?>"><?php echo $qr_svg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- offline-generated SVG from integer module geometry only ?></div>
					<?php else : ?>
						<?php $ui->notice( 'warning', __( 'The QR code could not be generated. Please enter the secret in the app manually.', 'wiedenroth-technologies-sentrygate' ) ); ?>
					<?php endif; ?>
					<p class="<?php echo esc_attr( $ui->cls( 'card__text' ) ); ?>"><?php esc_html_e( 'Secret', 'wiedenroth-technologies-sentrygate' ); ?></p>
					<code class="<?php echo esc_attr( $ui->cls( 'secret' ) ); ?>"><?php echo esc_html( $secret ); ?></code>
					<?php if ( $uri ) : ?>
						<p class="<?php echo esc_attr( $ui->cls( 'card__text' ) ); ?>">
							<a href="<?php echo esc_url( $uri ); ?>"><?php esc_html_e( 'Open otpauth link', 'wiedenroth-technologies-sentrygate' ); ?></a>
						</p>
					<?php endif; ?>
					<div class="<?php echo esc_attr( $ui->cls( 'inline-fields' ) . ' ' . $ui->cls( 'tfa-actions' ) ); ?>">
						<label for="sentrygate_tfa_confirm"><?php esc_html_e( 'Confirmation code', 'wiedenroth-technologies-sentrygate' ); ?></label>
						<input type="text" name="sentrygate_tfa_confirm" id="sentrygate_tfa_confirm" class="<?php echo esc_attr( $ui->cls( 'input' ) ); ?>" inputmode="numeric" autocomplete="one-time-code" />
					</div>
					<div class="<?php echo esc_attr( $ui->cls( 'row' ) . ' ' . $ui->cls( 'tfa-actions' ) ); ?>">
						<button type="submit" name="sentrygate_tfa_action" value="confirm_app" class="<?php echo esc_attr( $ui->cls( 'btn', 'primary' ) ); ?>">
							<?php esc_html_e( 'Confirm app', 'wiedenroth-technologies-sentrygate' ); ?>
						</button>
						<button type="submit" name="sentrygate_tfa_action" value="cancel_setup" class="<?php echo esc_attr( $ui->cls( 'btn', 'ghost' ) ); ?>">
							<?php esc_html_e( 'Cancel', 'wiedenroth-technologies-sentrygate' ); ?>
						</button>
					</div>
				</section>
			<?php endif; ?>

			<?php if ( 'email' === $pending ) : ?>
				<section class="<?php echo esc_attr( $ui->cls( 'card' ) ); ?>">
					<?php
					$ui->heading(
						__( 'Set up email', 'wiedenroth-technologies-sentrygate' ),
						sprintf(
							/* translators: %s: email */
							__( 'Enter the code that was sent to %s.', 'wiedenroth-technologies-sentrygate' ),
							$user->user_email
						)
					);
					?>
					<div class="<?php echo esc_attr( $ui->cls( 'inline-fields' ) ); ?>">
						<label for="sentrygate_tfa_confirm"><?php esc_html_e( 'Confirmation code', 'wiedenroth-technologies-sentrygate' ); ?></label>
						<input type="text" name="sentrygate_tfa_confirm" id="sentrygate_tfa_confirm" class="<?php echo esc_attr( $ui->cls( 'input' ) ); ?>" inputmode="numeric" autocomplete="one-time-code" />
					</div>
					<div class="<?php echo esc_attr( $ui->cls( 'row' ) . ' ' . $ui->cls( 'tfa-actions' ) ); ?>">
						<button type="submit" name="sentrygate_tfa_action" value="confirm_email" class="<?php echo esc_attr( $ui->cls( 'btn', 'primary' ) ); ?>">
							<?php esc_html_e( 'Confirm email', 'wiedenroth-technologies-sentrygate' ); ?>
						</button>
						<button type="submit" name="sentrygate_tfa_action" value="send_setup_email" class="<?php echo esc_attr( $ui->cls( 'btn', 'secondary' ) ); ?>">
							<?php esc_html_e( 'Resend code', 'wiedenroth-technologies-sentrygate' ); ?>
						</button>
						<button type="submit" name="sentrygate_tfa_action" value="cancel_setup" class="<?php echo esc_attr( $ui->cls( 'btn', 'ghost' ) ); ?>">
							<?php esc_html_e( 'Cancel', 'wiedenroth-technologies-sentrygate' ); ?>
						</button>
					</div>
				</section>
			<?php endif; ?>
		</form>
	</div>
</div>
