<?php
/**
 * Detect language from a message using Unicode script ranges.
 * Good enough for ~95% of cases per spec section 13.
 *
 * @package Deliz\AI\Advisor\Services
 */

namespace Deliz\AI\Advisor\Services;

use Deliz\AI\Advisor\Models\Settings;

defined( 'ABSPATH' ) || exit;

class LanguageDetector {

	/**
	 * Return 'he', 'ru', 'ar', or 'en' based on script dominance.
	 */
	public static function detect( string $text ): string {
		$enabled = (array) Settings::get( 'behavior', 'enabled_languages', array( 'he', 'ru', 'ar', 'en' ) );
		$default = (string) Settings::get( 'behavior', 'default_language', 'he' );

		// Integrate with WPML / Polylang if available — page context wins ties.
		$site_lang = self::site_language();

		$counts = array(
			'he' => preg_match_all( '/[\x{0590}-\x{05FF}]/u', $text ),
			'ar' => preg_match_all( '/[\x{0600}-\x{06FF}\x{0750}-\x{077F}\x{FB50}-\x{FDFF}\x{FE70}-\x{FEFF}]/u', $text ),
			'ru' => preg_match_all( '/[\x{0400}-\x{04FF}]/u', $text ),
			'en' => preg_match_all( '/[a-zA-Z]/', $text ),
		);

		// Only consider enabled languages.
		$counts = array_intersect_key( $counts, array_flip( $enabled ) );

		if ( empty( $counts ) || array_sum( $counts ) === 0 ) {
			return in_array( $default, $enabled, true ) ? $default : ( $enabled[0] ?? 'en' );
		}

		arsort( $counts );
		$best = array_key_first( $counts );

		// Tie between top two? prefer site language if it's in the tie.
		$top_val    = reset( $counts );
		$second_val = next( $counts );
		if ( $second_val && $top_val === $second_val && in_array( $site_lang, array_keys( $counts ), true ) ) {
			return $site_lang;
		}

		return (string) $best;
	}

	/**
	 * Determine site language via WPML / Polylang if present.
	 * Falls back to WP locale.
	 */
	public static function site_language(): string {
		// WPML.
		if ( function_exists( 'apply_filters' ) ) {
			$wpml = apply_filters( 'wpml_current_language', null );
			if ( is_string( $wpml ) && '' !== $wpml ) {
				return self::normalize_code( $wpml );
			}
		}

		// Polylang.
		if ( function_exists( 'pll_current_language' ) ) {
			$pll = pll_current_language();
			if ( is_string( $pll ) && '' !== $pll ) {
				return self::normalize_code( $pll );
			}
		}

		// WP locale.
		$locale = determine_locale();
		return self::normalize_code( substr( $locale, 0, 2 ) );
	}

	/**
	 * Coerce anything we might receive into our 2-char codes.
	 */
	private static function normalize_code( string $code ): string {
		$code = strtolower( substr( $code, 0, 2 ) );
		return in_array( $code, array( 'he', 'ru', 'ar', 'en' ), true ) ? $code : 'en';
	}
}
