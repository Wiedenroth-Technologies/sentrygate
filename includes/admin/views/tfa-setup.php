<?php
/**
 * Dedicated two-factor setup page (not the WP profile).
 *
 * @package SentryGate
 *
 * @var Wt_Admin_Ui        $ui
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
			<h1 class="<?php echo esc_attr( $ui->cls( 'title' ) ); ?>"><?php esc_html_e( 'Zwei-Faktor', 'wiedenroth-technologies-sentrygate' ); ?></h1>
			<p class="<?php echo esc_attr( $ui->cls( 'tagline' ) ); ?>">
				<?php
				if ( $is_other ) {
					printf(
						/* translators: %s: user login */
						esc_html__( 'Einrichtung für %s. Wählen Sie Authenticator-App oder E-Mail.', 'wiedenroth-technologies-sentrygate' ),
						esc_html( $user->user_login )
					);
				} else {
					esc_html_e( 'Zweiter Schritt nach dem Passwort: Authenticator-App oder E-Mail – eine Methode reicht.', 'wiedenroth-technologies-sentrygate' );
				}
				?>
			</p>
		</div>
	</header>

	<div class="<?php echo esc_attr( $ui->cls( 'content' ) ); ?>">
		<?php
		if ( 'bad_code' === $error ) {
			$ui->notice( 'error', __( 'Der Code ist ungültig oder abgelaufen. Bitte erneut versuchen.', 'wiedenroth-technologies-sentrygate' ), __( 'Bestätigung fehlgeschlagen', 'wiedenroth-technologies-sentrygate' ) );
		} elseif ( 'no_email' === $error ) {
			$ui->notice( 'error', __( 'Für dieses Konto ist keine gültige E-Mail-Adresse hinterlegt, oder E-Mail ist abgeschaltet.', 'wiedenroth-technologies-sentrygate' ), __( 'E-Mail nicht möglich', 'wiedenroth-technologies-sentrygate' ) );
		}

		if ( 'ok' === $notice ) {
			$ui->notice( 'success', __( 'Zwei-Faktor ist aktiv. Bewahren Sie die Notfall-Codes sicher auf – sie werden nur einmal angezeigt.', 'wiedenroth-technologies-sentrygate' ), __( 'Einrichtung abgeschlossen', 'wiedenroth-technologies-sentrygate' ) );
		} elseif ( 'email' === $notice ) {
			$ui->notice(
				'info',
				sprintf(
					/* translators: %s: email address */
					__( 'Ein Code wurde an %s gesendet. Er ist 10 Minuten gültig.', 'wiedenroth-technologies-sentrygate' ),
					$user->user_email
				)
			);
		} elseif ( 'disabled' === $notice ) {
			$ui->notice( 'success', __( 'Zwei-Faktor ist ausgeschaltet.', 'wiedenroth-technologies-sentrygate' ) );
		} elseif ( 'codes' === $notice ) {
			$ui->notice( 'warning', __( 'Alte Notfall-Codes sind ungültig. Die neuen Codes unten jetzt speichern.', 'wiedenroth-technologies-sentrygate' ) );
		}

		if ( $recovery_once ) :
			?>
			<div class="<?php echo esc_attr( $ui->cls( 'notice', 'warning' ) ); ?>" role="status">
				<p class="<?php echo esc_attr( $ui->cls( 'notice__title' ) ); ?>"><?php esc_html_e( 'Notfall-Codes – nur einmal sichtbar', 'wiedenroth-technologies-sentrygate' ); ?></p>
				<p class="<?php echo esc_attr( $ui->cls( 'notice__text' ) ); ?>"><?php esc_html_e( 'Jeder Code gilt einmal, falls App oder E-Mail nicht erreichbar sind.', 'wiedenroth-technologies-sentrygate' ); ?></p>
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
						__( 'Aktiver Schutz', 'wiedenroth-technologies-sentrygate' ),
						'email' === $method
							? sprintf(
								/* translators: %s: email */
								__( 'Anmeldungen brauchen einen Code per E-Mail an %s.', 'wiedenroth-technologies-sentrygate' ),
								$user->user_email
							)
							: __( 'Anmeldungen brauchen einen Code aus der Authenticator-App.', 'wiedenroth-technologies-sentrygate' )
					);
					$ui->badge( 'ok', 'email' === $method ? __( 'E-Mail aktiv', 'wiedenroth-technologies-sentrygate' ) : __( 'App aktiv', 'wiedenroth-technologies-sentrygate' ) );
					?>
					<div class="<?php echo esc_attr( $ui->cls( 'row' ) . ' ' . $ui->cls( 'tfa-actions' ) ); ?>">
						<button type="submit" name="sentrygate_tfa_action" value="regen_recovery" class="<?php echo esc_attr( $ui->cls( 'btn', 'secondary' ) ); ?>">
							<?php esc_html_e( 'Neue Notfall-Codes', 'wiedenroth-technologies-sentrygate' ); ?>
						</button>
						<button type="submit" name="sentrygate_tfa_action" value="disable" class="<?php echo esc_attr( $ui->cls( 'btn', 'danger' ) ); ?>">
							<?php esc_html_e( 'Zwei-Faktor ausschalten', 'wiedenroth-technologies-sentrygate' ); ?>
						</button>
					</div>
				</section>
			<?php endif; ?>

			<?php if ( $show_chooser ) : ?>
				<section class="<?php echo esc_attr( $ui->cls( 'card' ) ); ?>">
					<?php
					$ui->heading(
						$enabled ? __( 'Methode wechseln', 'wiedenroth-technologies-sentrygate' ) : __( 'Methode wählen', 'wiedenroth-technologies-sentrygate' ),
						__( 'Nur eine Methode ist aktiv. Die andere können Sie später hier umstellen.', 'wiedenroth-technologies-sentrygate' ),
						__( 'Die App ist unabhängiger vom Postfach. E-Mail braucht eine erreichbare Adresse im Konto.', 'wiedenroth-technologies-sentrygate' )
					);
					?>
					<div class="<?php echo esc_attr( $ui->cls( 'choice-grid' ) ); ?>">
						<button type="submit" name="sentrygate_tfa_action" value="choose_app" class="<?php echo esc_attr( $ui->cls( 'tile' ) ); ?>">
							<span class="<?php echo esc_attr( $ui->cls( 'tile__title' ) ); ?>"><?php esc_html_e( 'Authenticator-App', 'wiedenroth-technologies-sentrygate' ); ?></span>
							<span class="<?php echo esc_attr( $ui->cls( 'tile__text' ) ); ?>"><?php esc_html_e( 'Google Authenticator, Authy, 1Password und ähnliche Apps. QR-Code scannen, dann einmal bestätigen.', 'wiedenroth-technologies-sentrygate' ); ?></span>
						</button>
						<?php if ( $email_allowed ) : ?>
							<button type="submit" name="sentrygate_tfa_action" value="choose_email" class="<?php echo esc_attr( $ui->cls( 'tile' ) ); ?>">
								<span class="<?php echo esc_attr( $ui->cls( 'tile__title' ) ); ?>"><?php esc_html_e( 'E-Mail', 'wiedenroth-technologies-sentrygate' ); ?></span>
								<span class="<?php echo esc_attr( $ui->cls( 'tile__text' ) ); ?>">
									<?php
									printf(
										/* translators: %s: email */
										esc_html__( 'Bei jeder Anmeldung ein Code an %s. 10 Minuten gültig.', 'wiedenroth-technologies-sentrygate' ),
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
						__( 'Authenticator-App einrichten', 'wiedenroth-technologies-sentrygate' ),
						__( 'QR-Code in der App scannen oder das Geheimnis manuell eintragen. Danach den aktuellen 6-stelligen Code hier bestätigen.', 'wiedenroth-technologies-sentrygate' )
					);
					?>
					<?php if ( $qr_svg ) : ?>
						<div class="<?php echo esc_attr( $ui->cls( 'qr' ) ); ?>"><?php echo $qr_svg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- generated SVG ?></div>
					<?php else : ?>
						<?php $ui->notice( 'warning', __( 'QR konnte nicht erzeugt werden. Bitte das Geheimnis manuell in der App eintragen.', 'wiedenroth-technologies-sentrygate' ) ); ?>
					<?php endif; ?>
					<p class="<?php echo esc_attr( $ui->cls( 'card__text' ) ); ?>"><?php esc_html_e( 'Geheimnis', 'wiedenroth-technologies-sentrygate' ); ?></p>
					<code class="<?php echo esc_attr( $ui->cls( 'secret' ) ); ?>"><?php echo esc_html( $secret ); ?></code>
					<?php if ( $uri ) : ?>
						<p class="<?php echo esc_attr( $ui->cls( 'card__text' ) ); ?>">
							<a href="<?php echo esc_url( $uri ); ?>"><?php esc_html_e( 'otpauth-Link öffnen', 'wiedenroth-technologies-sentrygate' ); ?></a>
						</p>
					<?php endif; ?>
					<div class="<?php echo esc_attr( $ui->cls( 'inline-fields' ) . ' ' . $ui->cls( 'tfa-actions' ) ); ?>">
						<label for="sentrygate_tfa_confirm"><?php esc_html_e( 'Bestätigungscode', 'wiedenroth-technologies-sentrygate' ); ?></label>
						<input type="text" name="sentrygate_tfa_confirm" id="sentrygate_tfa_confirm" class="<?php echo esc_attr( $ui->cls( 'input' ) ); ?>" inputmode="numeric" autocomplete="one-time-code" />
					</div>
					<div class="<?php echo esc_attr( $ui->cls( 'row' ) . ' ' . $ui->cls( 'tfa-actions' ) ); ?>">
						<button type="submit" name="sentrygate_tfa_action" value="confirm_app" class="<?php echo esc_attr( $ui->cls( 'btn', 'primary' ) ); ?>">
							<?php esc_html_e( 'App bestätigen', 'wiedenroth-technologies-sentrygate' ); ?>
						</button>
						<button type="submit" name="sentrygate_tfa_action" value="cancel_setup" class="<?php echo esc_attr( $ui->cls( 'btn', 'ghost' ) ); ?>">
							<?php esc_html_e( 'Abbrechen', 'wiedenroth-technologies-sentrygate' ); ?>
						</button>
					</div>
				</section>
			<?php endif; ?>

			<?php if ( 'email' === $pending ) : ?>
				<section class="<?php echo esc_attr( $ui->cls( 'card' ) ); ?>">
					<?php
					$ui->heading(
						__( 'E-Mail einrichten', 'wiedenroth-technologies-sentrygate' ),
						sprintf(
							/* translators: %s: email */
							__( 'Geben Sie den Code ein, der an %s gesendet wurde.', 'wiedenroth-technologies-sentrygate' ),
							$user->user_email
						)
					);
					?>
					<div class="<?php echo esc_attr( $ui->cls( 'inline-fields' ) ); ?>">
						<label for="sentrygate_tfa_confirm"><?php esc_html_e( 'Bestätigungscode', 'wiedenroth-technologies-sentrygate' ); ?></label>
						<input type="text" name="sentrygate_tfa_confirm" id="sentrygate_tfa_confirm" class="<?php echo esc_attr( $ui->cls( 'input' ) ); ?>" inputmode="numeric" autocomplete="one-time-code" />
					</div>
					<div class="<?php echo esc_attr( $ui->cls( 'row' ) . ' ' . $ui->cls( 'tfa-actions' ) ); ?>">
						<button type="submit" name="sentrygate_tfa_action" value="confirm_email" class="<?php echo esc_attr( $ui->cls( 'btn', 'primary' ) ); ?>">
							<?php esc_html_e( 'E-Mail bestätigen', 'wiedenroth-technologies-sentrygate' ); ?>
						</button>
						<button type="submit" name="sentrygate_tfa_action" value="send_setup_email" class="<?php echo esc_attr( $ui->cls( 'btn', 'secondary' ) ); ?>">
							<?php esc_html_e( 'Code erneut senden', 'wiedenroth-technologies-sentrygate' ); ?>
						</button>
						<button type="submit" name="sentrygate_tfa_action" value="cancel_setup" class="<?php echo esc_attr( $ui->cls( 'btn', 'ghost' ) ); ?>">
							<?php esc_html_e( 'Abbrechen', 'wiedenroth-technologies-sentrygate' ); ?>
						</button>
					</div>
				</section>
			<?php endif; ?>
		</form>
	</div>
</div>
