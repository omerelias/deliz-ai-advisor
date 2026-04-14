<?php
/**
 * POST /deliz-ai/v1/chat
 *
 * Built incrementally:
 * - Phase 4: core Anthropic call + basic sanitization.
 * - Phase 6: conversation logging.
 * - Phase 7: rate limiting + daily cap + input validation.
 * - Phase 8: response cache.
 * - Phase 11: language detection.
 *
 * @package Deliz\AI\Advisor\Api
 */

namespace Deliz\AI\Advisor\Api;

use Deliz\AI\Advisor\Models\Settings;
use Deliz\AI\Advisor\Services\AnthropicClient;
use Deliz\AI\Advisor\Services\ConversationLogger;
use Deliz\AI\Advisor\Services\LanguageDetector;
use Deliz\AI\Advisor\Services\PromptBuilder;
use Deliz\AI\Advisor\Services\RateLimiter;
use Deliz\AI\Advisor\Services\ResponseCache;

defined( 'ABSPATH' ) || exit;

class ChatEndpoint {

	public function register( string $namespace ): void {
		register_rest_route(
			$namespace,
			'/chat',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle' ),
				'permission_callback' => array( $this, 'permission' ),
				'args'                => array(
					'message'    => array(
						'type'     => 'string',
						'required' => true,
					),
					'product_id' => array(
						'type'     => 'integer',
						'required' => true,
					),
					'session_id' => array(
						'type'     => 'string',
						'required' => true,
					),
					'history'    => array(
						'type'     => 'array',
						'required' => false,
						'default'  => array(),
					),
				),
			)
		);
	}

	/**
	 * Public endpoint guarded by nonce (X-WP-Nonce header).
	 * WP handles nonce validation automatically when the header is sent.
	 */
	public function permission(): bool {
		// wp_rest nonce is validated by WP core when the header is present.
		return true;
	}

	public function handle( \WP_REST_Request $req ) {
		$behavior  = Settings::group( 'behavior' );
		$general   = Settings::group( 'general' );

		if ( empty( $general['enabled'] ) ) {
			return new \WP_Error( 'deliz_ai_disabled', __( 'Advisor is disabled.', 'deliz-ai-advisor' ), array( 'status' => 503 ) );
		}

		$message    = trim( (string) $req->get_param( 'message' ) );
		$product_id = absint( $req->get_param( 'product_id' ) );
		$session_id = (string) $req->get_param( 'session_id' );
		$history    = (array) $req->get_param( 'history' );

		// Basic input validation (Phase 4 minimum, hardened in Phase 7).
		if ( '' === $message ) {
			return new \WP_Error( 'deliz_ai_empty', __( 'Message cannot be empty.', 'deliz-ai-advisor' ), array( 'status' => 400 ) );
		}
		$max_len = (int) ( $behavior['max_message_length'] ?? 500 );
		if ( mb_strlen( $message ) > $max_len ) {
			return new \WP_Error( 'deliz_ai_too_long', __( 'Message is too long.', 'deliz-ai-advisor' ), array( 'status' => 400 ) );
		}
		if ( ! preg_match( '/^[a-f0-9\-]{8,64}$/i', $session_id ) ) {
			return new \WP_Error( 'deliz_ai_bad_session', __( 'Invalid session id.', 'deliz-ai-advisor' ), array( 'status' => 400 ) );
		}
		if ( ! $product_id || ! function_exists( 'wc_get_product' ) || ! wc_get_product( $product_id ) ) {
			return new \WP_Error( 'deliz_ai_bad_product', __( 'Invalid product.', 'deliz-ai-advisor' ), array( 'status' => 400 ) );
		}

		$message = sanitize_textarea_field( wp_strip_all_tags( $message ) );

		// --- Rate limit + daily cap (Phase 7) ---
		if ( class_exists( RateLimiter::class ) ) {
			$limit = RateLimiter::check( $this->visitor_ip() );
			if ( ! $limit['ok'] ) {
				return new \WP_Error( 'deliz_ai_rate', $limit['error'], array( 'status' => 429 ) );
			}
			$cap = RateLimiter::check_daily_cap();
			if ( ! $cap['ok'] ) {
				return new \WP_Error( 'deliz_ai_cap', $cap['error'], array( 'status' => 503 ) );
			}
		}

		// --- Language detection (Phase 11) ---
		$language = class_exists( LanguageDetector::class )
			? LanguageDetector::detect( $message )
			: (string) ( $behavior['default_language'] ?? 'he' );

		// --- Cache lookup (Phase 8) ---
		$cache_hit = false;
		if ( ! empty( $behavior['enable_cache'] ) && empty( $history ) && class_exists( ResponseCache::class ) ) {
			$cached = ResponseCache::get( $product_id, $language, $message );
			if ( null !== $cached ) {
				$cache_hit = true;
				return new \WP_REST_Response(
					array(
						'reply'      => $cached,
						'language'   => $language,
						'cache_hit'  => true,
						'tokens_in'  => 0,
						'tokens_out' => 0,
						'cost_usd'   => 0,
					),
					200
				);
			}
		}

		// --- Build prompt ---
		$prompt_builder = new PromptBuilder();
		$system         = $prompt_builder->build_system( $product_id, $language );
		$messages       = $prompt_builder->build_messages( $history, $message );

		// --- Call Anthropic ---
		$api_key = Settings::api_key();
		if ( '' === $api_key ) {
			return new \WP_Error( 'deliz_ai_no_key', __( 'API key is not configured.', 'deliz-ai-advisor' ), array( 'status' => 500 ) );
		}

		$model      = (string) ( $general['model'] ?? 'claude-haiku-4-5-20251001' );
		$max_tokens = (int) ( $general['max_tokens_per_response'] ?? 400 );

		$client = new AnthropicClient( $api_key, $model );
		$result = $client->send( $messages, $system, $max_tokens );

		if ( ! $result['ok'] ) {
			return new \WP_Error( 'deliz_ai_api', $result['error'] ?? 'API error', array( 'status' => 500 ) );
		}

		$reply      = (string) ( $result['text'] ?? '' );
		$tokens_in  = (int) ( $result['tokens_in'] ?? 0 );
		$tokens_out = (int) ( $result['tokens_out'] ?? 0 );
		$cost_usd   = (float) ( $result['cost_usd'] ?? 0 );

		// --- Cache write (Phase 8) ---
		if ( ! empty( $behavior['enable_cache'] ) && empty( $history ) && class_exists( ResponseCache::class ) ) {
			ResponseCache::put( $product_id, $language, $message, $reply );
		}

		// --- Log conversation (Phase 6) ---
		$conversation_id = null;
		$message_id      = null;
		if ( class_exists( ConversationLogger::class ) && ! empty( Settings::get( 'advanced', 'log_all_requests', true ) ) ) {
			$logged          = ConversationLogger::log(
				array(
					'session_id' => $session_id,
					'product_id' => $product_id,
					'language'   => $language,
					'ip'         => $this->visitor_ip(),
					'user_id'    => get_current_user_id() ?: null,
					'question'   => $message,
					'answer'     => $reply,
					'tokens_in'  => $tokens_in,
					'tokens_out' => $tokens_out,
					'cost_usd'   => $cost_usd,
					'cache_hit'  => $cache_hit,
				)
			);
			$conversation_id = $logged['conversation_id'] ?? null;
			$message_id      = $logged['message_id'] ?? null;
		}

		// --- Daily spend counter ---
		if ( class_exists( RateLimiter::class ) ) {
			RateLimiter::add_daily_spend( $cost_usd );
			RateLimiter::increment_ip( $this->visitor_ip() );
		}

		return new \WP_REST_Response(
			array(
				'reply'           => $reply,
				'conversation_id' => $conversation_id,
				'message_id'      => $message_id,
				'language'        => $language,
				'tokens_in'       => $tokens_in,
				'tokens_out'      => $tokens_out,
				'cost_usd'        => $cost_usd,
				'cache_hit'       => $cache_hit,
			),
			200
		);
	}

	/**
	 * Client IP, raw. Hashed/anonymized by downstream components as needed.
	 */
	private function visitor_ip(): string {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : '';
		return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '0.0.0.0';
	}
}
