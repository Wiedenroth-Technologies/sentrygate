<?php
/**
 * Main settings page shell with tabs.
 *
 * @package SentryGate
 *
 * @var SentryGate_Admin_Ui          $ui
 * @var string               $tab
 * @var array<string,string> $tabs
 * @var array<string,mixed>  $options
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$sentrygate_product_url = 'https://wiedenroth.tech/software/sentrygate';
?>
<div class="wrap <?php echo esc_attr( $ui->cls( 'app' ) ); ?>">
	<div class="wp-header-end"></div>
	<header class="<?php echo esc_attr( $ui->cls( 'header' ) ); ?>">
		<div class="<?php echo esc_attr( $ui->cls( 'header__titles' ) ); ?>">
			<img
				class="<?php echo esc_attr( $ui->cls( 'header__logo' ) ); ?>"
				src="<?php echo esc_url( SENTRYGATE_PLUGIN_URL . 'assets/images/icon.svg' ); ?>"
				alt=""
				width="50"
				height="50"
				decoding="async"
			/>
			<div class="<?php echo esc_attr( $ui->cls( 'header__copy' ) ); ?>">
				<h1 class="<?php echo esc_attr( $ui->cls( 'title' ) ); ?>"><?php echo esc_html( get_admin_page_title() ); ?></h1>
				<p class="<?php echo esc_attr( $ui->cls( 'tagline' ) ); ?>">
					<?php esc_html_e( 'Schützt Anmeldung, Formulare und die Website.', 'wiedenroth-technologies-sentrygate' ); ?>
				</p>
			</div>
		</div>
		<div class="<?php echo esc_attr( $ui->cls( 'header__actions' ) ); ?>">
			<?php $ui->inbox(); ?>
			<?php $ui->button( __( 'Dokumentation', 'wiedenroth-technologies-sentrygate' ), 'secondary', 'button', $sentrygate_product_url ); ?>
		</div>
	</header>

	<nav class="<?php echo esc_attr( $ui->cls( 'tabs' ) ); ?>" aria-label="<?php esc_attr_e( 'SentryGate Bereiche', 'wiedenroth-technologies-sentrygate' ); ?>">
		<?php foreach ( $tabs as $sentrygate_slug => $sentrygate_label ) : ?>
			<a
				class="<?php echo esc_attr( $ui->cls( 'tab', $tab === $sentrygate_slug ? 'active' : '' ) ); ?>"
				href="<?php echo esc_url( admin_url( 'admin.php?page=' . SENTRYGATE_SLUG . '&tab=' . $sentrygate_slug ) ); ?>"
				<?php echo $tab === $sentrygate_slug ? 'aria-current="page"' : ''; ?>
			>
				<?php echo esc_html( $sentrygate_label ); ?>
			</a>
		<?php endforeach; ?>
	</nav>

	<div class="<?php echo esc_attr( $ui->cls( 'content' ) ); ?>">
		<?php
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Settings API success flag after manage_options screen.
		$sentrygate_settings_updated = isset( $_GET['settings-updated'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['settings-updated'] ) ) : '';
		if ( '' !== $sentrygate_settings_updated ) {
			$ui->notice( 'success', __( 'Einstellungen gespeichert.', 'wiedenroth-technologies-sentrygate' ) );
		}

		$sentrygate_view = SENTRYGATE_PLUGIN_DIR . 'includes/admin/views/tabs/' . $tab . '.php';
		if ( is_readable( $sentrygate_view ) ) {
			include $sentrygate_view;
		} else {
			$ui->notice( 'error', __( 'Bereich nicht gefunden.', 'wiedenroth-technologies-sentrygate' ) );
		}
		?>
	</div>
</div>
