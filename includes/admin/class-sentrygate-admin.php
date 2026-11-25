<?php
/**
 * Admin menu, Settings API, config export/import.
 *
 * @package SentryGate
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SentryGate_Admin
 */
class SentryGate_Admin {

	/**
	 * Shared UI helper.
	 *
	 * @var SentryGate_Admin_Ui
	 */
	private SentryGate_Admin_Ui $ui;

	/**
	 * @param SentryGate_Admin_Ui $ui UI helper.
	 */
	public function __construct( SentryGate_Admin_Ui $ui ) {
		$this->ui = $ui;
	}

	/**
	 * Add top-level menu.
	 */
	public function add_menu(): void {
		SentryGate_Admin_Ui::ensure_menu_separator();

		add_menu_page(
			'SentryGate',
			'SentryGate',
			'manage_options',
			SENTRYGATE_SLUG,
			array( $this, 'render_page' ),
			$this->menu_icon(),
			SentryGate_Admin_Ui::MENU_POSITION
		);
	}

	/**
	 * Base64 menu icon.
	 */
	private function menu_icon(): string {
		$path = SENTRYGATE_PLUGIN_DIR . 'assets/images/icon-menu.svg';
		if ( ! is_readable( $path ) ) {
			return 'dashicons-shield';
		}

		$svg = file_get_contents( $path );
		if ( ! is_string( $svg ) || '' === $svg ) {
			return 'dashicons-shield';
		}

		return 'data:image/svg+xml;base64,' . base64_encode( $svg );
	}

	/**
	 * Register Settings API.
	 */
	public function register_settings(): void {
		register_setting(
			'sentrygate_settings_group',
			SENTRYGATE_OPTION_KEY,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( 'SentryGate_Options', 'sanitize' ),
				'default'           => SentryGate_Options::defaults(),
			)
		);
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Hook suffix.
	 */
	public function enqueue_assets( string $hook ): void {
		if ( 'index.php' === $hook ) {
			wp_enqueue_style(
				'sentrygate-dashboard-widget',
				SENTRYGATE_PLUGIN_URL . 'assets/css/admin.css',
				array(),
				SENTRYGATE_VERSION
			);
		}

		if ( ! str_contains( $hook, SENTRYGATE_SLUG ) ) {
			return;
		}

		$this->ui->enqueue();
		$this->ui->enqueue_extra_css( SENTRYGATE_PLUGIN_DIR . 'assets/css/admin-extra.css' );

		wp_enqueue_script(
			'sentrygate-admin',
			SENTRYGATE_PLUGIN_URL . 'assets/js/admin.js',
			array( $this->ui->handle() ),
			SENTRYGATE_VERSION,
			true
		);

		wp_localize_script(
			'sentrygate-admin',
			'SentryGateAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'sentrygate_admin' ),
				'i18n'    => array(
					'running'        => __( 'Checking…', 'wiedenroth-technologies-sentrygate' ),
					'done'           => __( 'Check complete', 'wiedenroth-technologies-sentrygate' ),
					'error'          => __( 'The check failed.', 'wiedenroth-technologies-sentrygate' ),
					'statChecked'    => __( 'Files checked', 'wiedenroth-technologies-sentrygate' ),
					'statMismatch'   => __( 'Mismatch', 'wiedenroth-technologies-sentrygate' ),
					'statMissing'    => __( 'Missing', 'wiedenroth-technologies-sentrygate' ),
					'okTitle'        => __( 'Core matches', 'wiedenroth-technologies-sentrygate' ),
					'okText'         => __( 'No mismatched or missing core files.', 'wiedenroth-technologies-sentrygate' ),
					'warnTitle'      => __( 'Mismatches found', 'wiedenroth-technologies-sentrygate' ),
					'warnText'       => __( 'SentryGate does not repair anything automatically. Create a backup and reinstall core if needed, or tell a developer.', 'wiedenroth-technologies-sentrygate' ),
					'mismatchFiles'  => __( 'Mismatched files', 'wiedenroth-technologies-sentrygate' ),
					'missingFiles'   => __( 'Missing files', 'wiedenroth-technologies-sentrygate' ),
					'fileCol'        => __( 'File', 'wiedenroth-technologies-sentrygate' ),
					'lastTitle'      => __( 'Last result', 'wiedenroth-technologies-sentrygate' ),
					'emptyTitle'     => __( 'No check yet', 'wiedenroth-technologies-sentrygate' ),
					'emptyText'      => __( 'Run the check to compare the WordPress core files with the official list from wordpress.org.', 'wiedenroth-technologies-sentrygate' ),
					'metaChecked'    => __( 'Last checked', 'wiedenroth-technologies-sentrygate' ),
					'metaWp'         => __( 'WordPress', 'wiedenroth-technologies-sentrygate' ),
				),
			)
		);
	}

	/**
	 * Plugin action links.
	 *
	 * @param array<int, string> $links Links.
	 * @return array<int, string>
	 */
	public function action_links( array $links ): array {
		$url = admin_url( 'admin.php?page=' . SENTRYGATE_SLUG );
		array_unshift(
			$links,
			'<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings', 'wiedenroth-technologies-sentrygate' ) . '</a>'
		);
		return $links;
	}

	/**
	 * Wiedenroth-Footer im WordPress-#wpfooter.
	 */
	public function render_wp_footer_brand(): void {
		if ( ! $this->ui->is_own_screen() ) {
			return;
		}

		$product_url = 'https://wiedenroth.tech/software/sentrygate';
		$impressum   = 'https://wiedenroth.tech/impressum';
		$datenschutz = 'https://wiedenroth.tech/software/sentrygate/datenschutz';
		$brand_url   = 'https://wiedenroth.tech';
		$brand_img   = '';

		$avif = SENTRYGATE_PLUGIN_DIR . 'assets/images/wiedenroth-technologies.avif';
		$png  = SENTRYGATE_PLUGIN_DIR . 'assets/images/wiedenroth-technologies.png';

		if ( is_readable( $avif ) ) {
			$brand_img = SENTRYGATE_PLUGIN_URL . 'assets/images/wiedenroth-technologies.avif';
		} elseif ( is_readable( $png ) ) {
			$brand_img = SENTRYGATE_PLUGIN_URL . 'assets/images/wiedenroth-technologies.png';
		}

		$this->ui->footer(
			array(
				__( 'Imprint', 'wiedenroth-technologies-sentrygate' )   => $impressum,
				__( 'Privacy', 'wiedenroth-technologies-sentrygate' ) => $datenschutz,
				'SentryGate'                      => $product_url,
			),
			$brand_url,
			$brand_img
		);
	}

	/**
	 * Render settings page with tabs.
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		SentryGate_Options::flush_cache();

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$tab = isset( $_GET['tab'] ) ? sanitize_key( (string) wp_unslash( $_GET['tab'] ) ) : 'dashboard';

		$tabs = array(
			'dashboard'  => __( 'Overview', 'wiedenroth-technologies-sentrygate' ),
			'modules'    => __( 'Modules on/off', 'wiedenroth-technologies-sentrygate' ),
			'interface'  => __( 'Basic protection', 'wiedenroth-technologies-sentrygate' ),
			'debug'      => __( 'Error display', 'wiedenroth-technologies-sentrygate' ),
			'login_path' => __( 'Login URL', 'wiedenroth-technologies-sentrygate' ),
			'challenge'  => __( 'Bot protection', 'wiedenroth-technologies-sentrygate' ),
			'tfa'        => __( 'Two-factor', 'wiedenroth-technologies-sentrygate' ),
			'headers'    => __( 'Browser protection', 'wiedenroth-technologies-sentrygate' ),
			'files'      => __( 'Files & server', 'wiedenroth-technologies-sentrygate' ),
			'audit'      => __( 'Audit log', 'wiedenroth-technologies-sentrygate' ),
			'integrity'  => __( 'WordPress check', 'wiedenroth-technologies-sentrygate' ),
			'export'     => __( 'Backup / transfer', 'wiedenroth-technologies-sentrygate' ),
		);

		if ( ! isset( $tabs[ $tab ] ) ) {
			$tab = 'dashboard';
		}

		$ui      = $this->ui;
		$options = SentryGate_Options::all();

		include SENTRYGATE_PLUGIN_DIR . 'includes/admin/views/settings-page.php';
	}

	/**
	 * Export config as JSON download.
	 */
	public function handle_export(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission.', 'wiedenroth-technologies-sentrygate' ) );
		}

		check_admin_referer( 'sentrygate_export_config' );

		$data = SentryGate_Options::export();
		$json = wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );

		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=sentrygate-config-' . gmdate( 'Ymd-His' ) . '.json' );
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON download with application/json Content-Type.
		echo $json ? $json : '{}';
		exit;
	}

	/**
	 * Import config from JSON upload.
	 */
	public function handle_import(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission.', 'wiedenroth-technologies-sentrygate' ) );
		}

		check_admin_referer( 'sentrygate_import_config' );

		if ( empty( $_FILES['sentrygate_import']['tmp_name'] ) || ! is_string( $_FILES['sentrygate_import']['tmp_name'] ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=' . SENTRYGATE_SLUG . '&tab=export&import=fail' ) );
			exit;
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- validated via is_uploaded_file().
		$tmp = wp_normalize_path( wp_unslash( $_FILES['sentrygate_import']['tmp_name'] ) );
		if ( ! is_uploaded_file( $tmp ) || ! is_readable( $tmp ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=' . SENTRYGATE_SLUG . '&tab=export&import=fail' ) );
			exit;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- verified upload tmp path.
		$raw  = file_get_contents( $tmp );
		$data = json_decode( is_string( $raw ) ? $raw : '', true );

		if ( ! is_array( $data ) || empty( $data['settings'] ) || ! is_array( $data['settings'] ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=' . SENTRYGATE_SLUG . '&tab=export&import=fail' ) );
			exit;
		}

		$sanitized = SentryGate_Options::sanitize( $data['settings'] );
		update_option( SENTRYGATE_OPTION_KEY, $sanitized );
		SentryGate_Options::flush_cache();

		wp_safe_redirect( admin_url( 'admin.php?page=' . SENTRYGATE_SLUG . '&tab=export&import=ok' ) );
		exit;
	}

	/**
	 * Settings API input name.
	 *
	 * @param string $name Option key.
	 */
	public static function option_name( string $name ): string {
		return SENTRYGATE_OPTION_KEY . '[' . $name . ']';
	}

	/**
	 * Stable HTML id for an option field.
	 *
	 * @param string $name Option key.
	 */
	public static function field_id( string $name ): string {
		return 'sentrygate-' . str_replace( '_', '-', sanitize_key( $name ) );
	}

	/**
	 * Toggle with hidden 0 so unchecked values persist.
	 *
	 * @param SentryGate_Admin_Ui $ui      UI helper.
	 * @param string      $name    Option key.
	 * @param mixed       $checked Current value.
	 * @param string      $label   Visible label.
	 */
	public static function toggle( SentryGate_Admin_Ui $ui, string $name, mixed $checked, string $label ): void {
		printf(
			'<input type="hidden" name="%s" value="0" />',
			esc_attr( self::option_name( $name ) )
		);
		$ui->toggle( self::option_name( $name ), (bool) $checked, $label, self::field_id( $name ) );
	}

	/**
	 * Field row with toggle.
	 *
	 * @param SentryGate_Admin_Ui $ui           UI helper.
	 * @param string      $name         Option key.
	 * @param mixed       $checked      Current value.
	 * @param string      $label        Field label.
	 * @param string      $toggle_label Toggle text.
	 * @param string      $help         Optional help.
	 * @param string      $tech         Optional technical name.
	 */
	public static function field_toggle( SentryGate_Admin_Ui $ui, string $name, mixed $checked, string $label, string $toggle_label, string $help = '', string $tech = '' ): void {
		echo '<div class="' . esc_attr( $ui->cls( 'field' ) ) . '">';
		echo '<div class="' . esc_attr( $ui->cls( 'field__label' ) ) . '">';
		echo esc_html( $label );
		if ( '' !== $tech ) {
			echo '<div class="' . esc_attr( $ui->cls( 'tech' ) ) . '">' . esc_html( $tech ) . '</div>';
		}
		echo '</div>';
		echo '<div class="' . esc_attr( $ui->cls( 'field__control' ) ) . '">';
		self::toggle( $ui, $name, $checked, $toggle_label );
		if ( '' !== $help ) {
			$ui->help( $help );
		}
		echo '</div></div>';
	}

	/**
	 * Number input.
	 *
	 * @param SentryGate_Admin_Ui $ui    UI helper.
	 * @param string      $name  Option key.
	 * @param mixed       $value Value.
	 * @param int         $min   Min.
	 * @param int         $max   Max.
	 * @param string      $mod   Optional input modifier (e.g. narrow).
	 */
	public static function number( SentryGate_Admin_Ui $ui, string $name, mixed $value, int $min = 0, int $max = 999999, string $mod = 'narrow' ): void {
		$id    = self::field_id( $name );
		$class = '' === $mod ? $ui->cls( 'input' ) : $ui->cls( 'input', $mod );

		printf(
			'<input type="number" class="%1$s" id="%2$s" name="%3$s" value="%4$s" min="%5$s" max="%6$s" />',
			esc_attr( $class ),
			esc_attr( $id ),
			esc_attr( self::option_name( $name ) ),
			esc_attr( (string) (int) $value ),
			esc_attr( (string) $min ),
			esc_attr( (string) $max )
		);
	}

	/**
	 * Text input.
	 *
	 * @param SentryGate_Admin_Ui $ui    UI helper.
	 * @param string      $name  Option key.
	 * @param mixed       $value Value.
	 * @param string      $type  Input type.
	 */
	public static function text( SentryGate_Admin_Ui $ui, string $name, mixed $value, string $type = 'text' ): void {
		$id = self::field_id( $name );

		printf(
			'<input type="%1$s" class="%2$s" id="%3$s" name="%4$s" value="%5$s" />',
			esc_attr( $type ),
			esc_attr( $ui->cls( 'input' ) ),
			esc_attr( $id ),
			esc_attr( self::option_name( $name ) ),
			esc_attr( (string) $value )
		);
	}

	/**
	 * Field row with number input.
	 *
	 * @param SentryGate_Admin_Ui $ui    UI helper.
	 * @param string      $name  Option key.
	 * @param mixed       $value Value.
	 * @param string      $label Label.
	 * @param string      $help  Help text.
	 * @param int         $min   Min.
	 * @param int         $max   Max.
	 */
	public static function field_number( SentryGate_Admin_Ui $ui, string $name, mixed $value, string $label, string $help, int $min, int $max ): void {
		echo '<div class="' . esc_attr( $ui->cls( 'field' ) ) . '">';
		echo '<label class="' . esc_attr( $ui->cls( 'field__label' ) ) . '" for="' . esc_attr( self::field_id( $name ) ) . '">' . esc_html( $label ) . '</label>';
		echo '<div class="' . esc_attr( $ui->cls( 'field__control' ) ) . '">';
		self::number( $ui, $name, $value, $min, $max );
		if ( '' !== $help ) {
			$ui->help( $help );
		}
		echo '</div></div>';
	}

	/**
	 * Submit row.
	 *
	 * @param SentryGate_Admin_Ui $ui    UI helper.
	 * @param string      $label Button label.
	 */
	public static function save_actions( SentryGate_Admin_Ui $ui, string $label = '' ): void {
		if ( '' === $label ) {
			$label = __( 'Save settings', 'wiedenroth-technologies-sentrygate' );
		}

		echo '<p class="' . esc_attr( $ui->cls( 'actions' ) ) . '">';
		$ui->button( $label, 'primary', 'submit' );
		echo '</p>';
	}

	/**
	 * Map traffic-light status to badge.
	 *
	 * @param SentryGate_Admin_Ui $ui     UI helper.
	 * @param string      $status active|warning|inactive.
	 */
	public static function status_badge( SentryGate_Admin_Ui $ui, string $status ): void {
		$map = array(
			'active'   => array( 'ok', __( 'Active', 'wiedenroth-technologies-sentrygate' ) ),
			'warning'  => array( 'warn', __( 'Check', 'wiedenroth-technologies-sentrygate' ) ),
			'inactive' => array( 'off', __( 'Off', 'wiedenroth-technologies-sentrygate' ) ),
		);

		$pair = $map[ $status ] ?? array( 'off', $status );
		$ui->badge( $pair[0], $pair[1] );
	}

	/**
	 * Dashboard statistic with icon.
	 *
	 * @param SentryGate_Admin_Ui $ui    UI helper.
	 * @param string      $value Display value.
	 * @param string      $label Label.
	 * @param string      $icon  shield|check|warn|x.
	 * @param string      $tone  Optional: ok|warn|off|accent.
	 */
	public static function stat( SentryGate_Admin_Ui $ui, string $value, string $label, string $icon, string $tone = '' ): void {
		echo '<div class="' . esc_attr( $ui->cls( 'stat', $tone ) ) . '">';
		echo '<span class="' . esc_attr( $ui->cls( 'stat__icon' ) ) . '" aria-hidden="true">' . self::stat_svg( $icon ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- inline SVG from this class.
		echo '<div class="' . esc_attr( $ui->cls( 'stat__body' ) ) . '">';
		echo '<p class="' . esc_attr( $ui->cls( 'stat__label' ) ) . '">' . esc_html( $label ) . '</p>';
		echo '<p class="' . esc_attr( $ui->cls( 'stat__value' ) ) . '">' . esc_html( $value ) . '</p>';
		echo '</div>';
		echo '</div>';
	}

	/**
	 * Stroke icon markup (shield|check|warn|x).
	 *
	 * @param string $icon Icon key.
	 */
	public static function stat_svg( string $icon ): string {
		$paths = array(
			'shield' => '<path d="M12 3 5 6v6c0 5 3.4 8.4 7 9.5 3.6-1.1 7-4.5 7-9.5V6z"/>',
			'check'  => '<circle cx="12" cy="12" r="9"/><path d="m8.5 12.5 2.5 2.5 4.5-5"/>',
			'warn'   => '<path d="M12 3 2.5 20h19L12 3z"/><path d="M12 9v5M12 17h.01"/>',
			'x'      => '<circle cx="12" cy="12" r="9"/><path d="m9 9 6 6M15 9l-6 6"/>',
		);

		$inner = $paths[ $icon ] ?? $paths['shield'];

		return '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' . $inner . '</svg>';
	}
}
