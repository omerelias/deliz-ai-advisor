<?php
/**
 * POST /deliz-ai/v1/test-key
 * Admin-only. Validates an API key by sending a minimal request to Anthropic.
 *
 * @package Deliz\AI\Advisor\Api
 */

namespace Deliz\AI\Advisor\Api;

use Deliz\AI\Advisor\Models\Settings;
use Deliz\AI\Advisor\Services\AnthropicClient;
use Deliz\AI\Advisor\Services\Encryption;

defined( 'ABSPATH' ) || exit;

class TestKeyEndpoint {

	public function register( string $namespace ): void {
		register_rest_route(
			$namespace,
			'/test-key',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle' ),
				'permission_callback' => array( $this, 'permission' ),
				'args'                => array(
					'api_key' => array(
						'type'              => 'string',
						'required'          => false,
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
	}

	public function permission(): bool {
		return current_user_can( 'manage_options' );
	}

	public function handle( \WP_REST_Request $req ) {
		$posted = trim( (string) $req->get_param( 'api_key' ) );

		// If the posted value is empty or a mask, use the stored one.
		if ( '' === $posted || false !== strpos( $posted, '•' ) ) {
			$key = Settings::api_key();
		} else {
			$key = $posted;
		}

		if ( '' === $key ) {
			return new \WP_REST_Response(
				array(
					'ok'    => false,
					'error' => __( 'Enter an API key (or save one first).', 'deliz-ai-advisor' ),
				),
				200
			);
		}

		$model  = (string) Settings::get( 'general', 'model', 'claude-haiku-4-5-20251001' );
		$client = new AnthropicClient( $key, $model );
		$result = $client->test();

		return new \WP_REST_Response( $result, 200 );
	}
}
