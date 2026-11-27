<?php
/**
 * Form protection settings (BlockForge only).
 *
 * @package SentryGate
 *
 * @var SentryGate_Admin_Ui $ui
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
			__( 'Form protection', 'wiedenroth-technologies-sentrygate' ),
			__( 'The same symbol choice as at login, right in the BlockForge form before submitting. No cookie; each submission needs a fresh check.', 'wiedenroth-technologies-sentrygate' ),
			__( 'BlockForge forms use this automatically. Other plugins can use SentryGate_Form_Challenge::widget() and ::verify().', 'wiedenroth-technologies-sentrygate' )
		);

		$ui->badge( 'ok', __( 'BlockForge detected', 'wiedenroth-technologies-sentrygate' ) );

		SentryGate_Admin::field_toggle(
			$ui,
			'module_form_challenge',
			$options['module_form_challenge'] ?? true,
			__( 'Symbol choice in forms', 'wiedenroth-technologies-sentrygate' ),
			__( 'Enable', 'wiedenroth-technologies-sentrygate' ),
			__( 'Without this module, BlockForge forms are submitted without the SentryGate symbol check (honeypot and wait time in BlockForge still apply).', 'wiedenroth-technologies-sentrygate' )
		);
		?>
	</div>

	<?php SentryGate_Admin::save_actions( $ui ); ?>
</form>
