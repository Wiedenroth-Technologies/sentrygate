<?php
/**
 * Module enable/disable tab.
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

$modules = SentryGate_Modules::visible_definitions();
?>
<form method="post" action="options.php">
	<?php settings_fields( 'sentrygate_settings_group' ); ?>

	<div class="<?php echo esc_attr( $ui->cls( 'card' ) ); ?>">
		<?php
		$ui->heading(
			__( 'Modules are the protection building blocks', 'wiedenroth-technologies-sentrygate' ),
			__( 'Here, turn on what should be active on this site. Turned-off modules do nothing – their settings stay saved. Navigation only shows sections for modules that are on.', 'wiedenroth-technologies-sentrygate' ),
			__( 'For most client sites: leave Basic protection, Login protection, Browser protection, and Audit log on.', 'wiedenroth-technologies-sentrygate' )
		);
		?>

		<?php if ( ! SentryGate_Modules::is_blockforge_active() ) : ?>
			<?php
			$ui->notice(
				'info',
				__( 'Form protection appears here when BlockForge is installed and active. Saved form-protection settings are kept.', 'wiedenroth-technologies-sentrygate' ),
				__( 'BlockForge not detected', 'wiedenroth-technologies-sentrygate' )
			);
			?>
		<?php endif; ?>

		<div class="<?php echo esc_attr( $ui->cls( 'module-grid' ) ); ?>">
			<?php foreach ( $modules as $key => $meta ) : ?>
				<?php
				$enabled = ! empty( $options[ $meta['option'] ] );
				$status  = $enabled ? 'active' : 'inactive';
				?>
				<div class="<?php echo esc_attr( $ui->cls( 'module-card', $enabled ? 'on' : 'off' ) ); ?>">
					<div class="<?php echo esc_attr( $ui->cls( 'module-card__head' ) ); ?>">
						<strong class="<?php echo esc_attr( $ui->cls( 'module-card__title' ) ); ?>"><?php echo esc_html( $meta['label'] ); ?></strong>
						<?php SentryGate_Admin::status_badge( $ui, $status ); ?>
					</div>
					<p class="<?php echo esc_attr( $ui->cls( 'module-card__desc' ) ); ?>"><?php echo esc_html( $meta['desc'] ); ?></p>
					<?php
					SentryGate_Admin::toggle(
						$ui,
						$meta['option'],
						$enabled,
						__( 'Enable', 'wiedenroth-technologies-sentrygate' )
					);
					?>
				</div>
			<?php endforeach; ?>
		</div>
	</div>

	<?php SentryGate_Admin::save_actions( $ui, __( 'Save modules', 'wiedenroth-technologies-sentrygate' ) ); ?>
</form>
