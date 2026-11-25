<?php
/**
 * Module enable/disable tab.
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

$modules = array(
	'module_interface_hardening' => array(
		'label' => __( 'Basic protection', 'wiedenroth-technologies-sentrygate' ),
		'desc'  => __( 'Blocks known attack paths (XML-RPC, username guessing, file editor, version leaks).', 'wiedenroth-technologies-sentrygate' ),
	),
	'module_debug_control'       => array(
		'label' => __( 'Check error display', 'wiedenroth-technologies-sentrygate' ),
		'desc'  => __( 'Warns you when WordPress shows debug messages on a live site – it does not change your wp-config.php.', 'wiedenroth-technologies-sentrygate' ),
	),
	'module_login_path'          => array(
		'label' => __( 'Hide the login URL', 'wiedenroth-technologies-sentrygate' ),
		'desc'  => __( 'Replaces /wp-login.php with a custom address. Unknown visitors see "Page not found".', 'wiedenroth-technologies-sentrygate' ),
	),
	'module_login_challenge'     => array(
		'label' => __( 'Login protection', 'wiedenroth-technologies-sentrygate' ),
		'desc'  => __( 'A check before login (symbol choice), then a session cookie. Direct POSTs without a cookie are rejected.', 'wiedenroth-technologies-sentrygate' ),
	),
	'module_form_challenge'      => array(
		'label' => __( 'Form protection', 'wiedenroth-technologies-sentrygate' ),
		'desc'  => __( 'The same symbol choice before submitting BlockForge forms and other connected forms.', 'wiedenroth-technologies-sentrygate' ),
	),
	'module_two_factor'          => array(
		'label' => __( 'Two-factor login', 'wiedenroth-technologies-sentrygate' ),
		'desc'  => __( 'An extra code via authenticator app or email. Set up under Users → Two-factor.', 'wiedenroth-technologies-sentrygate' ),
	),
	'module_security_headers'    => array(
		'label' => __( 'Browser protection', 'wiedenroth-technologies-sentrygate' ),
		'desc'  => __( 'Tells the browser how the page may be loaded (for example, no embedding on other sites).', 'wiedenroth-technologies-sentrygate' ),
	),
	'module_file_hardening'      => array(
		'label' => __( 'Files & server', 'wiedenroth-technologies-sentrygate' ),
		'desc'  => __( 'Shows recommendations for file permissions and upload protection. Does not write anything to the server automatically.', 'wiedenroth-technologies-sentrygate' ),
	),
	'module_audit_log'           => array(
		'label' => __( 'Audit log', 'wiedenroth-technologies-sentrygate' ),
		'desc'  => __( 'Logs logins, lockouts, and important security events.', 'wiedenroth-technologies-sentrygate' ),
	),
	'module_core_integrity'      => array(
		'label' => __( 'WordPress check', 'wiedenroth-technologies-sentrygate' ),
		'desc'  => __( 'Compares WordPress files with the original from wordpress.org. No automatic repair.', 'wiedenroth-technologies-sentrygate' ),
	),
);
?>
<form method="post" action="options.php">
	<?php settings_fields( 'sentrygate_settings_group' ); ?>

	<div class="<?php echo esc_attr( $ui->cls( 'card' ) ); ?>">
		<?php
		$ui->heading(
			__( 'Modules are the protection building blocks', 'wiedenroth-technologies-sentrygate' ),
			__( 'Here, turn on what should be active on this site. Turned-off modules do nothing – their settings stay saved.', 'wiedenroth-technologies-sentrygate' ),
			__( 'For most client sites: leave Basic protection, Login protection, Browser protection, and Audit log on.', 'wiedenroth-technologies-sentrygate' )
		);
		?>

		<?php foreach ( $modules as $key => $meta ) : ?>
			<?php
			SentryGate_Admin::field_toggle(
				$ui,
				$key,
				$options[ $key ] ?? false,
				$meta['label'],
				__( 'Enable', 'wiedenroth-technologies-sentrygate' ),
				$meta['desc']
			);
			?>
		<?php endforeach; ?>
	</div>

	<?php SentryGate_Admin::save_actions( $ui, __( 'Save modules', 'wiedenroth-technologies-sentrygate' ) ); ?>
</form>
