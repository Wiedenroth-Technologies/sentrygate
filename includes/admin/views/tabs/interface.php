<?php
/**
 * Interface hardening settings.
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

$whitelist = implode( "\n", (array) ( $options['rest_api_whitelist'] ?? array() ) );
?>
<form method="post" action="options.php">
	<?php settings_fields( 'sentrygate_settings_group' ); ?>

	<div class="<?php echo esc_attr( $ui->cls( 'card' ) ); ?>">
		<?php
		$ui->heading(
			__( 'Baseline protection for the site', 'wiedenroth-technologies-sentrygate' ),
			__( 'Here you turn off common attack paths. You can safely leave most settings enabled.', 'wiedenroth-technologies-sentrygate' ),
			__( 'Turn on "Restrict the REST API" only if you know which endpoints the site needs (for example, forms or blocks).', 'wiedenroth-technologies-sentrygate' )
		);
		?>

		<?php
		SentryGate_Admin::field_toggle(
			$ui,
			'disable_xmlrpc',
			$options['disable_xmlrpc'] ?? true,
			__( 'Block the old remote interface', 'wiedenroth-technologies-sentrygate' ),
			__( 'Disable XML-RPC', 'wiedenroth-technologies-sentrygate' ),
			__( 'An old interface often abused for password attacks. Not needed for ordinary sites.', 'wiedenroth-technologies-sentrygate' )
		);

		SentryGate_Admin::field_toggle(
			$ui,
			'block_user_enumeration',
			$options['block_user_enumeration'] ?? true,
			__( 'Protect usernames', 'wiedenroth-technologies-sentrygate' ),
			__( 'Make username guessing harder', 'wiedenroth-technologies-sentrygate' ),
			__( 'Stops attackers from reading lists of valid logins.', 'wiedenroth-technologies-sentrygate' )
		);

		SentryGate_Admin::field_toggle(
			$ui,
			'disallow_file_edit',
			$options['disallow_file_edit'] ?? true,
			__( 'Code editor in the admin', 'wiedenroth-technologies-sentrygate' ),
			__( 'Disable the theme/plugin editor', 'wiedenroth-technologies-sentrygate' ),
			__( 'If someone takes over your admin account, they cannot rewrite files directly in the browser this way.', 'wiedenroth-technologies-sentrygate' )
		);

		SentryGate_Admin::field_toggle(
			$ui,
			'remove_wp_version',
			$options['remove_wp_version'] ?? true,
			__( 'Hide the WordPress version', 'wiedenroth-technologies-sentrygate' ),
			__( 'Remove the version number from the public site', 'wiedenroth-technologies-sentrygate' ),
			__( 'Makes it harder for attackers to see which WordPress version is running.', 'wiedenroth-technologies-sentrygate' )
		);
		?>

		<div class="<?php echo esc_attr( $ui->cls( 'field' ) ); ?>">
			<span class="<?php echo esc_attr( $ui->cls( 'field__label' ) ); ?>"><?php esc_html_e( 'Interface for apps (REST)', 'wiedenroth-technologies-sentrygate' ); ?></span>
			<div class="<?php echo esc_attr( $ui->cls( 'field__control' ) ); ?>">
				<?php
				SentryGate_Admin::toggle(
					$ui,
					'restrict_rest_api',
					$options['restrict_rest_api'] ?? false,
					__( 'Allow only approved areas for guests', 'wiedenroth-technologies-sentrygate' )
				);
				$ui->help( __( 'Off by default. Enable it only if you deliberately maintain the list below.', 'wiedenroth-technologies-sentrygate' ) );
				?>
				<p class="<?php echo esc_attr( $ui->cls( 'card__section' ) ); ?>"><?php esc_html_e( 'Allowed areas (one line per entry)', 'wiedenroth-technologies-sentrygate' ); ?></p>
				<textarea
					class="<?php echo esc_attr( $ui->cls( 'textarea' ) ); ?>"
					rows="8"
					id="sentrygate_rest_whitelist"
					name="<?php echo esc_attr( SentryGate_Admin::option_name( 'rest_api_whitelist' ) ); ?>"
				><?php echo esc_textarea( $whitelist ); ?></textarea>
				<?php $ui->help( __( 'Example: /wp/v2/posts – anything that starts with this line stays reachable for guests.', 'wiedenroth-technologies-sentrygate' ) ); ?>
			</div>
		</div>
	</div>

	<?php SentryGate_Admin::save_actions( $ui ); ?>
</form>
