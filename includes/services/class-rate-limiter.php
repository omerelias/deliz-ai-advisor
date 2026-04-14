<?php
/**
 * Per-IP hourly rate limit + daily USD cap.
 * Uses transients keyed by hashed IP (sha1(ip . wp_salt)) so raw IPs aren't stored.
 *
 * @package Deliz\AI\Advisor\Services
 */

namespace Deliz\AI\Advisor\Services;

use Deliz\AI\Advisor\Models\Settings;

defined( 'ABSPATH' ) || exit;

class RateLimiter {

	const IP_WINDOW_SECONDS = HOUR_IN_SECONDS;

	/**
	 * Check per-IP limit. Does NOT increment — call increment_ip() after a successful request.
	 *
	 * @return array{ok:bool, error?:string, remaining?:int}
	 */
	public static function check( string $ip ): array {
		$max = (int) Settings::get( 'behavior', 'rate_limit_per_ip_per_hour', 10 );
		if ( $max <= 0 ) {
			return array(
				'ok'        => true,
				'remaining' => PHP_INT_MAX,
			);
		}

		$key   = self::ip_key( $ip );
		$count = (int) get_transient( $key );

		if ( $count >= $max ) {
			return array(
				'ok'    => false,
				'error' => __( 'You asked too many questions recently. Please try again in an hour.', 'deliz-ai-advisor' ),
			);
		}

		return array(
			'ok'        => true,
			'remaining' => max( 0, $max - $count ),
		);
	}

	/**
	 * Increment the per-IP counter. Idempotent on the TTL (first increment sets TTL).
	 */
	public static function increment_ip( string $ip ): void {
		$key    = self::ip_key( $ip );
		$count  = (int) get_transient( $key );
		$count += 1;
		set_transient( $key, $count, self::IP_WINDOW_SECONDS );
	}

	/**
	 * Check the daily USD cap — fails fast if we're already over.
	 *
	 * @return array{ok:bool, error?:string, remaining_usd?:float}
	 */
	public static function check_daily_cap(): array {
		$cap = (float) Settings::get( 'general', 'daily_cap_usd', 5.0 );
		if ( $cap <= 0 ) {
			return array( 'ok' => true );
		}

		$spent = self::get_daily_spend();
		if ( $spent >= $cap ) {
			return array(
				'ok'    => false,
				'error' => __( 'Daily budget reached. The advisor will be back tomorrow.', 'deliz-ai-advisor' ),
			);
		}

		return array(
			'ok'            => true,
			'remaining_usd' => $cap - $spent,
		);
	}

	/**
	 * Add spend to today's counter.
	 */
	public static function add_daily_spend( float $usd ): void {
		if ( $usd <= 0 ) {
			return;
		}
		$key   = self::daily_key();
		$spent = (float) get_transient( $key );
		$spent += $usd;
		// Transient expires just after midnight UTC so it naturally rolls over.
		set_transient( $key, $spent, self::seconds_to_utc_midnight() );
	}

	public static function get_daily_spend(): float {
		return (float) get_transient( self::daily_key() );
	}

	public static function clear_all(): void {
		global $wpdb;
		// Best effort — drop all deliz_ai_ratelimit_ + daily_spend transients.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			"DELETE FROM {$wpdb->options}
			 WHERE option_name LIKE '_transient_deliz_ai_ratelimit_%'
			    OR option_name LIKE '_transient_timeout_deliz_ai_ratelimit_%'
			    OR option_name LIKE '_transient_deliz_ai_daily_spend_%'
			    OR option_name LIKE '_transient_timeout_deliz_ai_daily_spend_%'"
		);
	}

	private static function ip_key( string $ip ): string {
		return 'deliz_ai_ratelimit_' . substr( sha1( $ip . wp_salt( 'auth' ) ), 0, 16 );
	}

	private static function daily_key(): string {
		return 'deliz_ai_daily_spend_' . gmdate( 'Y_m_d' );
	}

	private static function seconds_to_utc_midnight(): int {
		$now  = time();
		$next = strtotime( gmdate( 'Y-m-d 00:00:00', $now + DAY_IN_SECONDS ) . ' UTC' );
		return max( 60, $next - $now );
	}
}
