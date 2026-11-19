<?php
/**
 * Internationalization.
 *
 * @package SentryGate
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SentryGate_i18n
 */
class SentryGate_i18n {

	/**
	 * Load the plugin text domain.
	 */
	public function load_plugin_textdomain(): void {
		load_plugin_textdomain(
			'wiedenroth-technologies-sentrygate',
			false,
			dirname( SENTRYGATE_PLUGIN_BASENAME ) . '/languages/'
		);
	}
}
