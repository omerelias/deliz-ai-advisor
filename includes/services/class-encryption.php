<?php
/**
 * Symmetric encryption for secrets at rest (API keys).
 * Derives key from wp_salt('auth') so no separate key management is needed.
 *
 * @package Deliz\AI\Advisor\Services
 */

namespace Deliz\AI\Advisor\Services;

defined( 'ABSPATH' ) || exit;

class Encryption {

	const METHOD = 'AES-256-CBC';

	/**
	 * Encrypt a plaintext string. Returns base64(iv . ciphertext).
	 * Empty input returns empty string.
	 */
	public static function encrypt( string $plaintext ): string {
		if ( '' === $plaintext ) {
			return '';
		}
		$key = self::key();
		$iv  = openssl_random_pseudo_bytes( openssl_cipher_iv_length( self::METHOD ) );

		$cipher = openssl_encrypt( $plaintext, self::METHOD, $key, OPENSSL_RAW_DATA, $iv );
		if ( false === $cipher ) {
			return '';
		}
		return base64_encode( $iv . $cipher );
	}

	/**
	 * Decrypt an encrypted string produced by self::encrypt.
	 */
	public static function decrypt( string $encrypted ): string {
		if ( '' === $encrypted ) {
			return '';
		}
		$data = base64_decode( $encrypted, true );
		if ( false === $data ) {
			return '';
		}
		$iv_len = openssl_cipher_iv_length( self::METHOD );
		if ( strlen( $data ) < $iv_len ) {
			return '';
		}

		$iv     = substr( $data, 0, $iv_len );
		$cipher = substr( $data, $iv_len );
		$plain  = openssl_decrypt( $cipher, self::METHOD, self::key(), OPENSSL_RAW_DATA, $iv );

		return false === $plain ? '' : $plain;
	}

	/**
	 * Mask for admin display — shows last 4 chars only.
	 */
	public static function mask( string $plaintext ): string {
		$len = strlen( $plaintext );
		if ( $len <= 4 ) {
			return str_repeat( '•', $len );
		}
		return str_repeat( '•', 8 ) . substr( $plaintext, -4 );
	}

	private static function key(): string {
		// 32 bytes from wp_salt — stable across requests, unique per site.
		return substr( hash( 'sha256', wp_salt( 'auth' ), true ), 0, 32 );
	}
}
