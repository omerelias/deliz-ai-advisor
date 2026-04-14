<?php
/**
 * Settings value object. Thin wrapper around the deliz_ai_settings option
 * that merges stored values with defaults and provides safe getters.
 *
 * @package Deliz\AI\Advisor\Models
 */

namespace Deliz\AI\Advisor\Models;

use Deliz\AI\Advisor\Activator;
use Deliz\AI\Advisor\Services\Encryption;

defined( 'ABSPATH' ) || exit;

class Settings {

	const OPTION = 'deliz_ai_settings';

	/**
	 * In-memory cache of the merged settings.
	 *
	 * @var array<string,mixed>|null
	 */
	private static $cache = null;

	/**
	 * Get all settings, merged with defaults.
	 *
	 * @return array<string,mixed>
	 */
	public static function all(): array {
		if ( null !== self::$cache ) {
			return self::$cache;
		}

		$stored   = get_option( self::OPTION, array() );
		$defaults = Activator::default_settings();

		// Deep merge: for each top-level group, array_merge stored into defaults.
		$merged = $defaults;
		if ( is_array( $stored ) ) {
			foreach ( $defaults as $group => $group_defaults ) {
				if ( isset( $stored[ $group ] ) && is_array( $stored[ $group ] ) ) {
					$merged[ $group ] = array_merge( $group_defaults, $stored[ $group ] );
				}
			}
		}

		self::$cache = $merged;
		return $merged;
	}

	/**
	 * Get a single group.
	 *
	 * @return array<string,mixed>
	 */
	public static function group( string $name ): array {
		$all = self::all();
		return isset( $all[ $name ] ) && is_array( $all[ $name ] ) ? $all[ $name ] : array();
	}

	/**
	 * Get a scalar key from a group, with fallback.
	 *
	 * @param mixed $default
	 * @return mixed
	 */
	public static function get( string $group, string $key, $default = null ) {
		$g = self::group( $group );
		return array_key_exists( $key, $g ) ? $g[ $key ] : $default;
	}

	/**
	 * Save a full group (merges with existing).
	 *
	 * @param array<string,mixed> $values
	 */
	public static function save_group( string $group, array $values ): void {
		$all                = self::all();
		$all[ $group ]      = array_merge( $all[ $group ] ?? array(), $values );
		update_option( self::OPTION, $all, false );
		self::$cache = null; // Invalidate.
	}

	/**
	 * Decrypted API key (never expose this to frontend).
	 */
	public static function api_key(): string {
		$enc = (string) self::get( 'general', 'api_key_encrypted', '' );
		return '' === $enc ? '' : Encryption::decrypt( $enc );
	}
}
