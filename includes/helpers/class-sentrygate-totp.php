<?php
/**
 * RFC-6238 TOTP implementation (no external dependencies).
 *
 * @package SentryGate
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SentryGate_TOTP
 */
class SentryGate_TOTP {

	private const PERIOD = 30;
	private const DIGITS = 6;
	private const ALGO   = 'sha1';

	/**
	 * Generate a random Base32 secret.
	 *
	 * @param int $length Byte length before encoding (default 20 = 160 bit).
	 */
	public static function generate_secret( int $length = 20 ): string {
		$bytes = random_bytes( $length );
		return self::base32_encode( $bytes );
	}

	/**
	 * Current TOTP code for a secret.
	 *
	 * @param string $secret Base32 secret.
	 * @param int|null $time Unix timestamp or null for now.
	 */
	public static function get_code( string $secret, ?int $time = null ): string {
		$time  = $time ?? time();
		$counter = (int) floor( $time / self::PERIOD );
		return self::hotp( $secret, $counter );
	}

	/**
	 * Verify a user-supplied code with window tolerance.
	 *
	 * @param string $secret Base32 secret.
	 * @param string $code   User code.
	 * @param int    $window Steps before/after.
	 */
	public static function verify( string $secret, string $code, int $window = 1 ): bool {
		$code = preg_replace( '/\s+/', '', $code ) ?? '';
		if ( ! preg_match( '/^\d{' . self::DIGITS . '}$/', $code ) ) {
			return false;
		}

		$time = time();
		for ( $i = -$window; $i <= $window; $i++ ) {
			$expected = self::get_code( $secret, $time + ( $i * self::PERIOD ) );
			if ( hash_equals( $expected, $code ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * otpauth:// URI for QR provisioning.
	 *
	 * @param string $secret  Base32 secret.
	 * @param string $account Account label (email/login).
	 * @param string $issuer  Issuer name.
	 */
	public static function provisioning_uri( string $secret, string $account, string $issuer = 'SentryGate' ): string {
		$label = rawurlencode( $issuer . ':' . $account );
		$query = http_build_query(
			array(
				'secret'    => $secret,
				'issuer'    => $issuer,
				'algorithm' => strtoupper( self::ALGO ),
				'digits'    => self::DIGITS,
				'period'    => self::PERIOD,
			),
			'',
			'&',
			PHP_QUERY_RFC3986
		);

		return 'otpauth://totp/' . $label . '?' . $query;
	}

	/**
	 * HOTP computation.
	 *
	 * @param string $secret  Base32 secret.
	 * @param int    $counter Counter.
	 */
	private static function hotp( string $secret, int $counter ): string {
		$key = self::base32_decode( $secret );
		$bin = pack( 'N*', 0, $counter ); // 8-byte big-endian.
		$hash = hash_hmac( self::ALGO, $bin, $key, true );
		$offset = ord( $hash[ strlen( $hash ) - 1 ] ) & 0x0f;
		$truncated = (
			( ( ord( $hash[ $offset ] ) & 0x7f ) << 24 ) |
			( ( ord( $hash[ $offset + 1 ] ) & 0xff ) << 16 ) |
			( ( ord( $hash[ $offset + 2 ] ) & 0xff ) << 8 ) |
			( ord( $hash[ $offset + 3 ] ) & 0xff )
		);
		$otp = $truncated % ( 10 ** self::DIGITS );
		return str_pad( (string) $otp, self::DIGITS, '0', STR_PAD_LEFT );
	}

	/**
	 * Base32 encode (RFC 4648).
	 *
	 * @param string $data Binary data.
	 */
	public static function base32_encode( string $data ): string {
		$alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
		$binary   = '';
		foreach ( str_split( $data ) as $char ) {
			$binary .= str_pad( decbin( ord( $char ) ), 8, '0', STR_PAD_LEFT );
		}
		$five = str_split( $binary, 5 );
		$out  = '';
		foreach ( $five as $chunk ) {
			if ( strlen( $chunk ) < 5 ) {
				$chunk = str_pad( $chunk, 5, '0', STR_PAD_RIGHT );
			}
			$out .= $alphabet[ bindec( $chunk ) ];
		}
		return $out;
	}

	/**
	 * Base32 decode.
	 *
	 * @param string $data Base32 string.
	 */
	public static function base32_decode( string $data ): string {
		$alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
		$data     = strtoupper( preg_replace( '/[^A-Z2-7]/', '', $data ) ?? '' );
		$binary   = '';
		foreach ( str_split( $data ) as $char ) {
			$pos = strpos( $alphabet, $char );
			if ( false === $pos ) {
				continue;
			}
			$binary .= str_pad( decbin( $pos ), 5, '0', STR_PAD_LEFT );
		}
		$bytes = str_split( $binary, 8 );
		$out   = '';
		foreach ( $bytes as $byte ) {
			if ( strlen( $byte ) === 8 ) {
				$out .= chr( bindec( $byte ) );
			}
		}
		return $out;
	}

	/**
	 * Generate one-time recovery codes.
	 *
	 * @param int $count Number of codes.
	 * @return array<int, string>
	 */
	public static function generate_recovery_codes( int $count = 10 ): array {
		$count = max( 8, min( 10, $count ) );
		$codes = array();
		for ( $i = 0; $i < $count; $i++ ) {
			$codes[] = strtoupper( bin2hex( random_bytes( 4 ) ) . '-' . bin2hex( random_bytes( 4 ) ) );
		}
		return $codes;
	}

	/**
	 * Hash a recovery code for storage.
	 *
	 * @param string $code Plain code.
	 */
	public static function hash_recovery_code( string $code ): string {
		return hash( 'sha256', strtoupper( preg_replace( '/\s+/', '', $code ) ?? '' ) );
	}
}
