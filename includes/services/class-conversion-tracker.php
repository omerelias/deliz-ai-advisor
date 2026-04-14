<?php
/**
 * Tracks when a chat conversation leads to add-to-cart or purchase.
 *
 * Attribution windows:
 *  - `converted = 1` when an add-to-cart fires within `conversation_timeout_minutes`
 *    (default 30) of last chat activity for that session + product.
 *  - `conversion_order_id` set on woocommerce_checkout_order_processed.
 *
 * Session linkage: we use a first-party cookie `deliz_ai_sid` seeded by the widget
 * so server-side hooks can find the active conversation.
 *
 * @package Deliz\AI\Advisor\Services
 */

namespace Deliz\AI\Advisor\Services;

use Deliz\AI\Advisor\Models\Settings;

defined( 'ABSPATH' ) || exit;

class ConversionTracker {

	const COOKIE_NAME = 'deliz_ai_sid';

	public function register(): void {
		add_action( 'woocommerce_add_to_cart', array( $this, 'on_add_to_cart' ), 10, 6 );
		add_action( 'woocommerce_checkout_order_processed', array( $this, 'on_order_processed' ), 10, 3 );
	}

	/**
	 * Mark conversation as converted when a user adds to cart after chatting.
	 *
	 * @param string $cart_item_key
	 * @param int    $product_id
	 * @param int    $quantity
	 * @param int    $variation_id
	 * @param array  $variation
	 * @param array  $cart_item_data
	 */
	public function on_add_to_cart( $cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data ): void {
		$session_id = $this->get_session_id();
		if ( ! $session_id || ! $product_id ) {
			return;
		}

		global $wpdb;
		$table   = $wpdb->prefix . 'deliz_ai_conversations';
		$window  = (int) Settings::get( 'behavior', 'conversation_timeout_minutes', 30 );
		$cutoff  = gmdate( 'Y-m-d H:i:s', time() - $window * MINUTE_IN_SECONDS );

		// Find a recent conversation for this session + product.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$conv_id = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table}
				 WHERE session_id = %s AND product_id = %d AND last_activity_at >= %s
				 ORDER BY id DESC LIMIT 1",
				$session_id,
				(int) $product_id,
				$cutoff
			)
		);
		if ( ! $conv_id ) {
			return;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			$table,
			array( 'converted' => 1 ),
			array( 'id' => $conv_id ),
			array( '%d' ),
			array( '%d' )
		);
	}

	/**
	 * Link the order to any converted conversation on this session.
	 *
	 * @param int       $order_id
	 * @param array     $posted_data
	 * @param \WC_Order $order
	 */
	public function on_order_processed( $order_id, $posted_data, $order ): void {
		$session_id = $this->get_session_id();
		if ( ! $session_id ) {
			return;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'deliz_ai_conversations';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			$table,
			array( 'conversion_order_id' => (int) $order_id ),
			array(
				'session_id' => $session_id,
				'converted'  => 1,
			),
			array( '%d' ),
			array( '%s', '%d' )
		);
	}

	/**
	 * Read session id from the first-party cookie (set by the widget JS).
	 */
	private function get_session_id(): ?string {
		if ( empty( $_COOKIE[ self::COOKIE_NAME ] ) ) {
			return null;
		}
		$raw = (string) wp_unslash( $_COOKIE[ self::COOKIE_NAME ] );
		return preg_match( '/^[a-f0-9\-]{8,64}$/i', $raw ) ? $raw : null;
	}
}
