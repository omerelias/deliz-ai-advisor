<?php
/**
 * Writes conversations + messages to the custom tables.
 *
 * @package Deliz\AI\Advisor\Services
 */

namespace Deliz\AI\Advisor\Services;

defined( 'ABSPATH' ) || exit;

class ConversationLogger {

	/**
	 * Log a full turn (user message + assistant reply) for a session.
	 *
	 * @param array{
	 *   session_id: string,
	 *   product_id: int,
	 *   language: string,
	 *   ip: string,
	 *   user_id: int|null,
	 *   question: string,
	 *   answer: string,
	 *   tokens_in: int,
	 *   tokens_out: int,
	 *   cost_usd: float,
	 *   cache_hit: bool
	 * } $args
	 *
	 * @return array{conversation_id:int|null, message_id:int|null}
	 */
	public static function log( array $args ): array {
		global $wpdb;

		$conversations_table = $wpdb->prefix . 'deliz_ai_conversations';
		$messages_table      = $wpdb->prefix . 'deliz_ai_messages';

		$now    = current_time( 'mysql', true );
		$ip_bin = self::pack_ip( $args['ip'] );

		// Find or create conversation (by session + product).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$conversation_id = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$conversations_table} WHERE session_id = %s AND product_id = %d LIMIT 1",
				$args['session_id'],
				$args['product_id']
			)
		);

		if ( ! $conversation_id ) {
			$wpdb->insert(
				$conversations_table,
				array(
					'session_id'       => $args['session_id'],
					'visitor_ip'       => $ip_bin,
					'user_id'          => $args['user_id'],
					'product_id'       => $args['product_id'],
					'language'         => substr( $args['language'], 0, 5 ),
					'message_count'    => 0,
					'total_tokens_in'  => 0,
					'total_tokens_out' => 0,
					'total_cost_usd'   => 0,
					'started_at'       => $now,
					'last_activity_at' => $now,
				),
				array( '%s', '%s', '%d', '%d', '%s', '%d', '%d', '%d', '%f', '%s', '%s' )
			);
			$conversation_id = (int) $wpdb->insert_id;
		}

		// Insert user turn.
		$wpdb->insert(
			$messages_table,
			array(
				'conversation_id' => $conversation_id,
				'role'            => 'user',
				'content'         => $args['question'],
				'tokens_in'       => null,
				'tokens_out'      => null,
				'cost_usd'        => null,
				'cache_hit'       => 0,
				'created_at'      => $now,
			),
			array( '%d', '%s', '%s', null, null, null, '%d', '%s' )
		);

		// Insert assistant turn.
		$wpdb->insert(
			$messages_table,
			array(
				'conversation_id' => $conversation_id,
				'role'            => 'assistant',
				'content'         => $args['answer'],
				'tokens_in'       => (int) $args['tokens_in'],
				'tokens_out'      => (int) $args['tokens_out'],
				'cost_usd'        => (float) $args['cost_usd'],
				'cache_hit'       => ! empty( $args['cache_hit'] ) ? 1 : 0,
				'created_at'      => $now,
			),
			array( '%d', '%s', '%s', '%d', '%d', '%f', '%d', '%s' )
		);
		$message_id = (int) $wpdb->insert_id;

		// Update conversation counters.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$conversations_table}
				SET message_count = message_count + 2,
				    total_tokens_in = total_tokens_in + %d,
				    total_tokens_out = total_tokens_out + %d,
				    total_cost_usd = total_cost_usd + %f,
				    last_activity_at = %s
				WHERE id = %d",
				(int) $args['tokens_in'],
				(int) $args['tokens_out'],
				(float) $args['cost_usd'],
				$now,
				$conversation_id
			)
		);

		return array(
			'conversation_id' => $conversation_id,
			'message_id'      => $message_id,
		);
	}

	/**
	 * Pack an IP to VARBINARY(16) for storage.
	 */
	private static function pack_ip( string $ip ): string {
		$bin = @inet_pton( $ip );
		return false === $bin ? str_repeat( "\0", 16 ) : $bin;
	}

	/**
	 * Unpack a VARBINARY(16) IP for display.
	 */
	public static function unpack_ip( string $bin ): string {
		if ( '' === $bin ) {
			return '';
		}
		$ip = @inet_ntop( $bin );
		return false === $ip ? '' : $ip;
	}
}
