<?php
/**
 * First-run setup wizard.
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

// phpcs:ignore WordPress.Security.NonceVerification.Recommended
$step = isset( $_GET['step'] ) ? sanitize_key( (string) wp_unslash( $_GET['step'] ) ) : 'welcome';
$allowed_steps = SentryGate_Options::wizard_step_slugs();
if ( ! in_array( $step, $allowed_steps, true ) ) {
	$step = 'welcome';
}

$step_labels = array(
	'welcome'    => __( 'Welcome', 'wiedenroth-technologies-sentrygate' ),
	'modules'    => __( 'Modules', 'wiedenroth-technologies-sentrygate' ),
	'settings'   => __( 'Settings', 'wiedenroth-technologies-sentrygate' ),
	'two_factor' => __( 'Two-factor', 'wiedenroth-technologies-sentrygate' ),
	'done'       => __( 'Done', 'wiedenroth-technologies-sentrygate' ),
);

$steps = array();
foreach ( $allowed_steps as $slug ) {
	if ( isset( $step_labels[ $slug ] ) ) {
		$steps[ $slug ] = $step_labels[ $slug ];
	}
}

$post_url   = admin_url( 'admin-post.php' );
$skip_url   = wp_nonce_url( admin_url( 'admin-post.php?action=sentrygate_skip_setup' ), 'sentrygate_skip_setup' );
$overview   = admin_url( 'admin.php?page=' . SENTRYGATE_SLUG );
$tfa_setup  = admin_url( 'users.php?page=' . SentryGate_Two_Factor::PAGE_SLUG );
$visible    = SentryGate_Modules::visible_definitions();
$blockforge = SentryGate_Modules::is_blockforge_active();
$include_tfa = SentryGate_Options::module_enabled( 'two_factor' );
?>

<div class="<?php echo esc_attr( $ui->cls( 'card' ) ); ?>">
	<?php
	$ui->heading(
		__( 'Setup', 'wiedenroth-technologies-sentrygate' ),
		__( 'A short walkthrough for the most important protection modules. You can skip anytime and change everything later.', 'wiedenroth-technologies-sentrygate' )
	);
	?>

	<ol class="<?php echo esc_attr( $ui->cls( 'steps' ) ); ?>">
		<?php
		$step_index = 0;
		foreach ( $steps as $slug => $label ) :
			++$step_index;
			$mod = '';
			if ( $slug === $step ) {
				$mod = 'current';
			} elseif ( SentryGate_Options::is_setup_step_done( $slug ) ) {
				$mod = 'done';
			}
			?>
			<li class="<?php echo esc_attr( $ui->cls( 'step', $mod ) ); ?>">
				<span class="<?php echo esc_attr( $ui->cls( 'step__num' ) ); ?>"><?php echo esc_html( (string) $step_index ); ?></span>
				<span class="<?php echo esc_attr( $ui->cls( 'step__title' ) ); ?>"><?php echo esc_html( $label ); ?></span>
			</li>
		<?php endforeach; ?>
	</ol>
</div>

<?php if ( 'welcome' === $step ) : ?>
	<div class="<?php echo esc_attr( $ui->cls( 'card' ) ); ?>">
		<?php
		$ui->heading(
			__( 'Welcome to SentryGate', 'wiedenroth-technologies-sentrygate' ),
			__( 'SentryGate protects login, forms (with BlockForge), and common WordPress attack paths. Recommended defaults are already selected – confirm or adjust them in the next steps.', 'wiedenroth-technologies-sentrygate' )
		);
		?>
		<form method="post" action="<?php echo esc_url( $post_url ); ?>">
			<input type="hidden" name="action" value="sentrygate_setup_step" />
			<input type="hidden" name="sentrygate_setup_step" value="welcome" />
			<input type="hidden" name="sentrygate_setup_next" value="modules" />
			<?php wp_nonce_field( 'sentrygate_setup_step' ); ?>
			<p class="<?php echo esc_attr( $ui->cls( 'actions' ) ); ?>">
				<?php $ui->button( __( 'Start setup', 'wiedenroth-technologies-sentrygate' ), 'primary', 'submit' ); ?>
				<a class="<?php echo esc_attr( $ui->cls( 'btn', 'ghost' ) ); ?>" href="<?php echo esc_url( $skip_url ); ?>">
					<?php esc_html_e( 'Skip setup', 'wiedenroth-technologies-sentrygate' ); ?>
				</a>
			</p>
		</form>
	</div>
<?php endif; ?>

<?php if ( 'modules' === $step ) : ?>
	<div class="<?php echo esc_attr( $ui->cls( 'card' ) ); ?>">
		<?php
		$ui->heading(
			__( 'Choose modules', 'wiedenroth-technologies-sentrygate' ),
			__( 'Turn on the building blocks you want. Recommended ones are preselected. Form protection only appears when BlockForge is active.', 'wiedenroth-technologies-sentrygate' )
		);
		?>

		<?php if ( ! $blockforge ) : ?>
			<?php
			$ui->notice(
				'info',
				__( 'BlockForge is not active, so form protection is hidden for now. Install and activate BlockForge later to enable it – saved settings stay.', 'wiedenroth-technologies-sentrygate' )
			);
			?>
		<?php endif; ?>

		<form method="post" action="<?php echo esc_url( $post_url ); ?>">
			<input type="hidden" name="action" value="sentrygate_setup_step" />
			<input type="hidden" name="sentrygate_setup_step" value="modules" />
			<input type="hidden" name="sentrygate_setup_next" value="settings" />
			<?php wp_nonce_field( 'sentrygate_setup_step' ); ?>

			<div class="<?php echo esc_attr( $ui->cls( 'module-grid' ) ); ?>">
				<?php foreach ( $visible as $key => $meta ) : ?>
					<?php
					$checked = array_key_exists( $meta['option'], $options )
						? (bool) $options[ $meta['option'] ]
						: (bool) $meta['recommended'];
					?>
					<div class="<?php echo esc_attr( $ui->cls( 'module-card' ) ); ?>">
						<div class="<?php echo esc_attr( $ui->cls( 'module-card__head' ) ); ?>">
							<strong class="<?php echo esc_attr( $ui->cls( 'module-card__title' ) ); ?>"><?php echo esc_html( $meta['label'] ); ?></strong>
							<?php if ( $meta['recommended'] ) : ?>
								<?php $ui->badge( 'ok', __( 'Recommended', 'wiedenroth-technologies-sentrygate' ) ); ?>
							<?php endif; ?>
						</div>
						<p class="<?php echo esc_attr( $ui->cls( 'module-card__desc' ) ); ?>"><?php echo esc_html( $meta['desc'] ); ?></p>
						<?php SentryGate_Admin::toggle( $ui, $meta['option'], $checked, __( 'Enable', 'wiedenroth-technologies-sentrygate' ) ); ?>
					</div>
				<?php endforeach; ?>
			</div>

			<p class="<?php echo esc_attr( $ui->cls( 'actions' ) ); ?>">
				<?php $ui->button( __( 'Continue', 'wiedenroth-technologies-sentrygate' ), 'primary', 'submit' ); ?>
				<a class="<?php echo esc_attr( $ui->cls( 'btn', 'ghost' ) ); ?>" href="<?php echo esc_url( $skip_url ); ?>">
					<?php esc_html_e( 'Skip setup', 'wiedenroth-technologies-sentrygate' ); ?>
				</a>
			</p>
		</form>
	</div>
<?php endif; ?>

<?php if ( 'settings' === $step ) : ?>
	<div class="<?php echo esc_attr( $ui->cls( 'card' ) ); ?>">
		<?php
		$ui->heading(
			__( 'Important settings', 'wiedenroth-technologies-sentrygate' ),
			__( 'Fine-tune a few defaults for the modules you enabled. You can change everything later in each section.', 'wiedenroth-technologies-sentrygate' )
		);
		?>

		<form method="post" action="<?php echo esc_url( $post_url ); ?>">
			<input type="hidden" name="action" value="sentrygate_setup_step" />
			<input type="hidden" name="sentrygate_setup_step" value="settings" />
			<input type="hidden" name="sentrygate_setup_next" value="<?php echo esc_attr( $include_tfa ? 'two_factor' : 'done' ); ?>" />
			<?php wp_nonce_field( 'sentrygate_setup_step' ); ?>

			<?php if ( SentryGate_Options::module_enabled( 'login_challenge' ) ) : ?>
				<div class="<?php echo esc_attr( $ui->cls( 'field' ) ); ?>">
					<label class="<?php echo esc_attr( $ui->cls( 'field__label' ) ); ?>" for="sentrygate-challenge-mode"><?php esc_html_e( 'Login protection type', 'wiedenroth-technologies-sentrygate' ); ?></label>
					<div class="<?php echo esc_attr( $ui->cls( 'field__control' ) ); ?>">
						<select class="<?php echo esc_attr( $ui->cls( 'select' ) ); ?>" id="sentrygate-challenge-mode" name="<?php echo esc_attr( SentryGate_Admin::option_name( 'challenge_mode' ) ); ?>">
							<option value="full" <?php selected( $options['challenge_mode'] ?? 'full', 'full' ); ?>><?php esc_html_e( 'Full – symbol choice + session cookie (recommended)', 'wiedenroth-technologies-sentrygate' ); ?></option>
							<option value="lightweight" <?php selected( $options['challenge_mode'] ?? '', 'lightweight' ); ?>><?php esc_html_e( 'Light – confirm only + traps + cookie', 'wiedenroth-technologies-sentrygate' ); ?></option>
						</select>
					</div>
				</div>
			<?php endif; ?>

			<?php if ( SentryGate_Options::module_enabled( 'login_path' ) ) : ?>
				<div class="<?php echo esc_attr( $ui->cls( 'field' ) ); ?>">
					<label class="<?php echo esc_attr( $ui->cls( 'field__label' ) ); ?>" for="<?php echo esc_attr( SentryGate_Admin::field_id( 'login_slug' ) ); ?>"><?php esc_html_e( 'Custom login path', 'wiedenroth-technologies-sentrygate' ); ?></label>
					<div class="<?php echo esc_attr( $ui->cls( 'field__control' ) ); ?>">
						<?php SentryGate_Admin::text( $ui, 'login_slug', $options['login_slug'] ?? 'portal' ); ?>
						<?php $ui->help( __( 'Write this URL down. The default /wp-login.php will show “Page not found”.', 'wiedenroth-technologies-sentrygate' ) ); ?>
					</div>
				</div>
			<?php endif; ?>

			<?php if ( SentryGate_Options::module_enabled( 'security_headers' ) ) : ?>
				<?php
				SentryGate_Admin::field_toggle(
					$ui,
					'header_csp_report_only',
					$options['header_csp_report_only'] ?? true,
					__( 'Content Security Policy', 'wiedenroth-technologies-sentrygate' ),
					__( 'Start in report-only mode (recommended)', 'wiedenroth-technologies-sentrygate' ),
					__( 'Browsers report issues without blocking content. Switch to enforce later when ready.', 'wiedenroth-technologies-sentrygate' )
				);
				?>
			<?php endif; ?>

			<?php if ( SentryGate_Options::module_enabled( 'audit_log' ) ) : ?>
				<?php
				SentryGate_Admin::field_number(
					$ui,
					'audit_retention_days',
					$options['audit_retention_days'] ?? 90,
					__( 'Audit log retention (days)', 'wiedenroth-technologies-sentrygate' ),
					__( 'Older entries are removed automatically. 90 days suits most sites.', 'wiedenroth-technologies-sentrygate' ),
					7,
					730
				);
				?>
			<?php endif; ?>

			<?php
			$has_fields = SentryGate_Options::module_enabled( 'login_challenge' )
				|| SentryGate_Options::module_enabled( 'login_path' )
				|| SentryGate_Options::module_enabled( 'security_headers' )
				|| SentryGate_Options::module_enabled( 'audit_log' );
			if ( ! $has_fields ) :
				$ui->help( __( 'No extra settings for the modules you chose. Continue to finish.', 'wiedenroth-technologies-sentrygate' ) );
			endif;
			?>

			<p class="<?php echo esc_attr( $ui->cls( 'actions' ) ); ?>">
				<?php $ui->button( __( 'Continue', 'wiedenroth-technologies-sentrygate' ), 'primary', 'submit' ); ?>
				<a class="<?php echo esc_attr( $ui->cls( 'btn', 'ghost' ) ); ?>" href="<?php echo esc_url( $skip_url ); ?>">
					<?php esc_html_e( 'Skip setup', 'wiedenroth-technologies-sentrygate' ); ?>
				</a>
			</p>
		</form>
	</div>
<?php endif; ?>

<?php if ( 'two_factor' === $step ) : ?>
	<div class="<?php echo esc_attr( $ui->cls( 'card' ) ); ?>">
		<?php
		$ui->heading(
			__( 'Protect your own account', 'wiedenroth-technologies-sentrygate' ),
			__( 'Two-factor is on for required roles. Set up your authenticator app or email method now so you can still sign in.', 'wiedenroth-technologies-sentrygate' ),
			__( 'Open Users → Two-factor (or the button below) after setup if you prefer to do this later.', 'wiedenroth-technologies-sentrygate' )
		);
		?>
		<form method="post" action="<?php echo esc_url( $post_url ); ?>">
			<input type="hidden" name="action" value="sentrygate_setup_step" />
			<input type="hidden" name="sentrygate_setup_step" value="two_factor" />
			<input type="hidden" name="sentrygate_setup_next" value="done" />
			<?php wp_nonce_field( 'sentrygate_setup_step' ); ?>
			<p class="<?php echo esc_attr( $ui->cls( 'actions' ) ); ?>">
				<?php $ui->button( __( 'Finish setup', 'wiedenroth-technologies-sentrygate' ), 'primary', 'submit' ); ?>
				<a class="<?php echo esc_attr( $ui->cls( 'btn', 'secondary' ) ); ?>" href="<?php echo esc_url( $tfa_setup ); ?>">
					<?php esc_html_e( 'Open two-factor setup', 'wiedenroth-technologies-sentrygate' ); ?>
				</a>
				<a class="<?php echo esc_attr( $ui->cls( 'btn', 'ghost' ) ); ?>" href="<?php echo esc_url( $skip_url ); ?>">
					<?php esc_html_e( 'Skip setup', 'wiedenroth-technologies-sentrygate' ); ?>
				</a>
			</p>
		</form>
	</div>
<?php endif; ?>

<?php if ( 'done' === $step ) : ?>
	<div class="<?php echo esc_attr( $ui->cls( 'card' ) ); ?>">
		<?php
		$ui->heading(
			__( 'Setup complete', 'wiedenroth-technologies-sentrygate' ),
			__( 'Here is what is active. Open any section from the overview to fine-tune.', 'wiedenroth-technologies-sentrygate' )
		);
		?>

		<div class="<?php echo esc_attr( $ui->cls( 'table-wrap' ) ); ?>">
			<table class="<?php echo esc_attr( $ui->cls( 'table' ) ); ?>">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Status', 'wiedenroth-technologies-sentrygate' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Module', 'wiedenroth-technologies-sentrygate' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Notes', 'wiedenroth-technologies-sentrygate' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( SentryGate_Modules::visible_definitions() as $meta ) : ?>
						<?php $on = ! empty( $options[ $meta['option'] ] ); ?>
						<tr>
							<td><?php SentryGate_Admin::status_badge( $ui, $on ? 'active' : 'inactive' ); ?></td>
							<td><strong><?php echo esc_html( $meta['label'] ); ?></strong></td>
							<td><?php echo esc_html( $meta['desc'] ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>

		<p class="<?php echo esc_attr( $ui->cls( 'actions' ) ); ?>">
			<a class="<?php echo esc_attr( $ui->cls( 'btn', 'primary' ) ); ?>" href="<?php echo esc_url( $overview ); ?>">
				<?php esc_html_e( 'Go to overview', 'wiedenroth-technologies-sentrygate' ); ?>
			</a>
		</p>
	</div>
<?php endif; ?>
