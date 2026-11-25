<?php
/**
 * Debug control tab.
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

$issues = SentryGate_Debug_Control::collect_issues();
?>
<form method="post" action="options.php">
	<?php settings_fields( 'sentrygate_settings_group' ); ?>

	<div class="<?php echo esc_attr( $ui->cls( 'card' ) ); ?>">
		<?php
		$ui->heading(
			__( 'Error display on the live site', 'wiedenroth-technologies-sentrygate' ),
			__( 'On a live site, visitors should not see technical error messages. SentryGate warns you if debug switches are still on – it does not change your server file wp-config.php automatically.', 'wiedenroth-technologies-sentrygate' ),
			__( 'If warnings appear here, ask a developer to turn off WP_DEBUG on the live site.', 'wiedenroth-technologies-sentrygate' )
		);

		SentryGate_Admin::field_toggle(
			$ui,
			'enforce_production_debug',
			$options['enforce_production_debug'] ?? true,
			__( 'Monitor the live site', 'wiedenroth-technologies-sentrygate' ),
			__( 'Show warnings and suppress visible errors on the front end', 'wiedenroth-technologies-sentrygate' ),
			__( 'Safe: nothing in the WordPress configuration is permanently overwritten.', 'wiedenroth-technologies-sentrygate' )
		);
		?>
	</div>

	<?php SentryGate_Admin::save_actions( $ui ); ?>
</form>

<section class="<?php echo esc_attr( $ui->cls( 'card' ) ); ?>">
	<?php $ui->heading( __( 'Current status', 'wiedenroth-technologies-sentrygate' ) ); ?>

	<?php if ( empty( $issues ) ) : ?>
		<?php $ui->notice( 'success', __( 'Everything looks set for production – no warning needed.', 'wiedenroth-technologies-sentrygate' ) ); ?>
	<?php else : ?>
		<div class="<?php echo esc_attr( $ui->cls( 'notice', 'warning' ) ); ?>" role="status">
			<p class="<?php echo esc_attr( $ui->cls( 'notice__title' ) ); ?>"><?php esc_html_e( 'Please check:', 'wiedenroth-technologies-sentrygate' ); ?></p>
			<ul class="<?php echo esc_attr( $ui->cls( 'setup-list' ) ); ?>">
				<?php foreach ( $issues as $issue ) : ?>
					<li><?php echo esc_html( $issue ); ?></li>
				<?php endforeach; ?>
			</ul>
		</div>
	<?php endif; ?>
</section>
