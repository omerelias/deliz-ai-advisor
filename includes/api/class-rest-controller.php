<?php
/**
 * Registers all REST routes under the deliz-ai/v1 namespace.
 *
 * @package Deliz\AI\Advisor\Api
 */

namespace Deliz\AI\Advisor\Api;

defined( 'ABSPATH' ) || exit;

class RestController {

	const NAMESPACE_URL = 'deliz-ai/v1';

	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes(): void {
		$test_key = new TestKeyEndpoint();
		$test_key->register( self::NAMESPACE_URL );

		$chat = new ChatEndpoint();
		$chat->register( self::NAMESPACE_URL );

		$feedback = new FeedbackEndpoint();
		$feedback->register( self::NAMESPACE_URL );
	}
}
