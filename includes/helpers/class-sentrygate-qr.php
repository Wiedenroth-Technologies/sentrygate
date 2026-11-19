<?php
/**
 * Offline QR SVG helper (vendored MIT QRCode lib).
 *
 * @package SentryGate
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SentryGate_QR
 */
class SentryGate_QR {

	/**
	 * Render a scannable QR code as SVG markup.
	 *
	 * @param string $data Payload (e.g. otpauth URI).
	 * @param int    $module_size Pixel size per module.
	 */
	public static function svg( string $data, int $module_size = 4 ): string {
		if ( '' === $data ) {
			return '';
		}

		if ( ! class_exists( 'QRCode', false ) ) {
			require_once SENTRYGATE_PLUGIN_DIR . 'includes/lib/qrcode.php';
		}

		try {
			$qr = QRCode::getMinimumQRCode( $data, QR_ERROR_CORRECT_LEVEL_M );
			$svg = $qr->getSVG( max( 2, min( 8, $module_size ) ) );
			return is_string( $svg ) ? $svg : '';
		} catch ( Throwable $e ) {
			return '';
		}
	}
}
