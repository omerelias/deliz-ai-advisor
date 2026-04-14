<?php
/**
 * Response cache for Q&A pairs.
 * Key = sha1(product_id + language + normalized_question).
 *
 * @package Deliz\AI\Advisor\Services
 */

namespace Deliz\AI\Advisor\Services;

use Deliz\AI\Advisor\Models\Settings;

defined( 'ABSPATH' ) || exit;

class ResponseCache {

	/**
	 * Get cached answer if fresh, else null.
	 */
	public static function get( int $product_id, string $language, string $question ): ?string {
		global $wpdb;
		$table = $wpdb->prefix . 'deliz_ai_cache';
		$hash  = self::hash( $product_id, $language, $question );
		$ttl   = (int) Settings::get( 'behavior', 'cache_ttl_days', 7 );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT answer, created_at FROM {$table} WHERE hash = %s LIMIT 1",
				$hash
			)
		);

		if ( ! $row ) {
			return null;
		}

		// TTL expired?
		$age_days = ( time() - strtotime( $row->created_at . ' UTC' ) ) / DAY_IN_SECONDS;
		if ( $age_days > $ttl ) {
			// Stale — delete it.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->delete( $table, array( 'hash' => $hash ), array( '%s' ) );
			return null;
		}

		// Bump hit count.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table} SET hit_count = hit_count + 1, last_hit_at = %s WHERE hash = %s",
				current_time( 'mysql', true ),
				$hash
			)
		);

		return (string) $row->answer;
	}

	/**
	 * Store a Q&A pair.
	 */
	public static function put( int $product_id, string $language, string $question, string $answer ): void {
		global $wpdb;
		$table = $wpdb->prefix . 'deliz_ai_cache';
		$hash  = self::hash( $product_id, $language, $question );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$table}
					(hash, product_id, language, question, answer, hit_count, created_at)
					VALUES (%s, %d, %s, %s, %s, 0, %s)
					ON DUPLICATE KEY UPDATE answer = VALUES(answer), created_at = VALUES(created_at)",
				$hash,
				$product_id,
				substr( $language, 0, 5 ),
				$question,
				$answer,
				current_time( 'mysql', true )
			)
		);
	}

	/**
	 * Wipe the entire cache.
	 *
	 * @return int Rows deleted.
	 */
	public static function clear(): int {
		global $wpdb;
		$table = $wpdb->prefix . 'deliz_ai_cache';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange
		return (int) $wpdb->query( "DELETE FROM {$table}" );
	}

	/**
	 * Normalize a question for stable caching:
	 * lowercase, collapse whitespace, strip trailing punctuation.
	 */
	private static function normalize( string $q ): string {
		$q = trim( mb_strtolower( $q, 'UTF-8' ) );
		$q = preg_replace( '/\s+/u', ' ', $q );
		$q = preg_replace( '/[\s\p{P}]+$/u', '', $q );
		return (string) $q;
	}

	private static function hash( int $product_id, string $language, string $question ): string {
		return sha1( $product_id . '|' . $language . '|' . self::normalize( $question ) );
	}
}
