<?php
/**
 * Plugin Name:       Wiedenroth Technologies SentryGate
 * Plugin URI:        https://wiedenroth.tech/software/wordpress/sentrygate
 * Description:       Production-ready WordPress security plugin by Wiedenroth Technologies GmbH.
 * Version:           1.0.1
 * Requires at least: 6.4
 * Requires PHP:      8.1
 * Author:            Wiedenroth Technologies GmbH
 * Author URI:        https://wiedenroth.tech
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wiedenroth-technologies-sentrygate
 * Domain Path:       /languages
 *
 * @package SentryGate
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * Nicht in v1:
 * - Automatisierte Core-/Plugin-Auto-Updates
 * - Malware-Scanning
 * - Multisite-Network-weites zentrales Management
 */

define( 'SENTRYGATE_VERSION', '1.0.1' );
define( 'SENTRYGATE_PLUGIN_FILE', __FILE__ );
define( 'SENTRYGATE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SENTRYGATE_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'SENTRYGATE_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'SENTRYGATE_SLUG', 'wiedenroth-technologies-sentrygate' );
define( 'SENTRYGATE_OPTION_KEY', 'sentrygate_settings' );
define( 'SENTRYGATE_DB_VERSION', '1.0.0' );

require_once SENTRYGATE_PLUGIN_DIR . 'includes/class-sentrygate-admin-ui.php';
require_once SENTRYGATE_PLUGIN_DIR . 'includes/class-sentrygate-autoloader.php';
SentryGate_Autoloader::register();

/**
 * Plugin activation.
 */
function sentrygate_activate(): void {
	require_once SENTRYGATE_PLUGIN_DIR . 'includes/class-sentrygate-activator.php';
	SentryGate_Activator::activate();
}
register_activation_hook( __FILE__, 'sentrygate_activate' );

/**
 * Plugin deactivation.
 */
function sentrygate_deactivate(): void {
	require_once SENTRYGATE_PLUGIN_DIR . 'includes/class-sentrygate-deactivator.php';
	SentryGate_Deactivator::deactivate();
}
register_deactivation_hook( __FILE__, 'sentrygate_deactivate' );

/**
 * Bootstrap the plugin.
 */
function sentrygate_run(): void {
	$plugin = SentryGate::instance();
	$plugin->run();
}
sentrygate_run();
