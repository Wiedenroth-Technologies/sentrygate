<?php
/**
 * Client IP helper – REMOTE_ADDR by default; optional trusted proxy headers.
 *
 * @package SentryGate
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SentryGate_IP
 */
class SentryGate_IP {

	/**
	 * Get the best-effort client IP.
	 */
	public static function get(): string {
		$remote_raw = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( (string) $_SERVER['REMOTE_ADDR'] ) ) : '';
		$remote     = self::sanitize_ip( $remote_raw );

		if ( ! SentryGate_Options::get( 'trust_proxy_headers', false ) ) {
			return $remote !== '' ? $remote : '0.0.0.0';
		}

		$keys = array(
			'HTTP_CF_CONNECTING_IP',
			'HTTP_X_FORWARDED_FOR',
			'HTTP_X_REAL_IP',
		);

		foreach ( $keys as $key ) {
			if ( empty( $_SERVER[ $key ] ) ) {
				continue;
			}

			$raw   = sanitize_text_field( wp_unslash( (string) $_SERVER[ $key ] ) );
			$parts = array_map( 'trim', explode( ',', $raw ) );
			foreach ( $parts as $part ) {
				$ip = self::sanitize_ip( $part );
				if ( '' !== $ip ) {
					return $ip;
				}
			}
		}

		return $remote !== '' ? $remote : '0.0.0.0';
	}

	/**
	 * Validate and normalize an IP string.
	 *
	 * @param string $ip Raw IP.
	 */
	private static function sanitize_ip( string $ip ): string {
		$ip = sanitize_text_field( wp_unslash( $ip ) );
		return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '';
	}

	/**
	 * Optionally truncate IP for GDPR (last octet / half of IPv6).
	 *
	 * @param string $ip       IP address.
	 * @param bool   $truncate Whether to truncate.
	 */
	public static function maybe_truncate( string $ip, bool $truncate ): string {
		if ( ! $truncate ) {
			return $ip;
		}

		if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
			$parts = explode( '.', $ip );
			if ( 4 === count( $parts ) ) {
				$parts[3] = '0';
				return implode( '.', $parts );
			}
		}

		if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) {
			$parts = explode( ':', $ip );
			$count = count( $parts );
			for ( $i = (int) floor( $count / 2 ); $i < $count; $i++ ) {
				$parts[ $i ] = '0000';
			}
			return implode( ':', $parts );
		}

		return $ip;
	}
}
