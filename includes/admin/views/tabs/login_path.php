<?php
/**
 * Login path settings.
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

$slug          = (string) ( $options['login_slug'] ?? 'portal' );
$login_url     = home_url( '/' . $slug . '/' );
$frontend_url  = (string) apply_filters( 'sentrygate_frontend_login_url', '', '' );
$effective_url = '' !== $frontend_url ? $frontend_url : $login_url;
?>
<form method="post" action="options.php">
	<?php settings_fields( 'sentrygate_settings_group' ); ?>

	<div class="<?php echo esc_attr( $ui->cls( 'card' ) ); ?>">
		<?php
		$ui->heading(
			__( 'Custom login URL', 'wiedenroth-technologies-sentrygate' ),
			__( 'Instead of the well-known address /wp-login.php, use your own URL. Anyone who opens the old address sees "Page not found". That makes the login harder for bots to find.', 'wiedenroth-technologies-sentrygate' ),
			__( 'Write down the new login URL. The module must be turned on under "Modules on/off".', 'wiedenroth-technologies-sentrygate' )
		);
		?>

		<div class="<?php echo esc_attr( $ui->cls( 'field' ) ); ?>">
			<label class="<?php echo esc_attr( $ui->cls( 'field__label' ) ); ?>" for="<?php echo esc_attr( SentryGate_Admin::field_id( 'login_slug' ) ); ?>"><?php esc_html_e( 'Your login name in the URL', 'wiedenroth-technologies-sentrygate' ); ?></label>
			<div class="<?php echo esc_attr( $ui->cls( 'field__control' ) ); ?>">
				<?php SentryGate_Admin::text( $ui, 'login_slug', $slug ); ?>
				<?php
				$ui->help(
					sprintf(
						/* translators: %s: login URL */
						__( 'Login is then at: %s', 'wiedenroth-technologies-sentrygate' ),
						$login_url
					)
				);
				if ( '' !== $frontend_url ) {
					$ui->help(
						sprintf(
							/* translators: 1: custom login slug, 2: frontend login URL */
							__( 'Front-end login is active: /%1$s/ and login links go there (%2$s), including captcha and 2FA – without logging in twice through the WordPress admin.', 'wiedenroth-technologies-sentrygate' ),
							esc_html( $slug ),
							esc_url( $effective_url )
						)
					);
				}
				$ui->help( __( 'Letters, numbers, and hyphens only. Avoid words like "login" or "admin".', 'wiedenroth-technologies-sentrygate' ) );
				?>
			</div>
		</div>

		<?php
		SentryGate_Admin::field_toggle(
			$ui,
			'redirect_admin',
			$options['redirect_admin'] ?? true,
			__( 'Block the admin URL', 'wiedenroth-technologies-sentrygate' ),
			__( 'Answer /wp-admin with "not found" for visitors who are not logged in', 'wiedenroth-technologies-sentrygate' ),
			__( 'Logged-in users can still reach the admin as usual.', 'wiedenroth-technologies-sentrygate' )
		);
		?>
	</div>

	<?php SentryGate_Admin::save_actions( $ui ); ?>
</form>

<?php
$ui->notice(
	'info',
	__( 'If the new address does not take effect: under Settings → Permalinks, click "Save Changes" once.', 'wiedenroth-technologies-sentrygate' )
);
$ui->notice(
	'warning',
	sprintf(
		/* translators: %s: custom login URL */
		__( 'Recovery if you forget the login URL: (1) Use the URL you wrote down (%s). (2) Rename or deactivate the plugin via FTP or a file manager – after that, /wp-login.php works again. (3) With WP-CLI: wp plugin deactivate sentrygate – or set the module_login_path setting to off in the database and save permalinks.', 'wiedenroth-technologies-sentrygate' ),
		$login_url
	),
	__( 'Recovery path', 'wiedenroth-technologies-sentrygate' )
);
?>
