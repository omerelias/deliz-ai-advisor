<?php
/**
 * Thin wrapper around the Anthropic Messages API.
 *
 * @package Deliz\AI\Advisor\Services
 */

namespace Deliz\AI\Advisor\Services;

defined( 'ABSPATH' ) || exit;

class AnthropicClient {

	const API_URL            = 'https://api.anthropic.com/v1/messages';
	const ANTHROPIC_VERSION  = '2023-06-01';
	const DEFAULT_TIMEOUT    = 20;

	/** USD per 1M tokens. */
	const PRICING = array(
		'claude-haiku-4-5-20251001' => array(
			'in'  => 0.80,
			'out' => 4.00,
		),
		'claude-sonnet-4-6'         => array(
			'in'  => 3.00,
			'out' => 15.00,
		),
	);

	/**
	 * @var string
	 */
	private $api_key;

	/**
	 * @var string
	 */
	private $model;

	public function __construct( string $api_key, string $model = 'claude-haiku-4-5-20251001' ) {
		$this->api_key = $api_key;
		$this->model   = $model;
	}

	/**
	 * Send a messages request.
	 *
	 * @param array<int, array{role:string, content:string}> $messages
	 * @param string                                         $system
	 * @param int                                            $max_tokens
	 *
	 * @return array{
	 *   ok: bool,
	 *   text?: string,
	 *   tokens_in?: int,
	 *   tokens_out?: int,
	 *   cost_usd?: float,
	 *   model?: string,
	 *   latency_ms?: int,
	 *   error?: string,
	 *   status?: int
	 * }
	 */
	public function send( array $messages, string $system = '', int $max_tokens = 400 ): array {
		if ( '' === $this->api_key ) {
			return array(
				'ok'    => false,
				'error' => __( 'No API key configured.', 'deliz-ai-advisor' ),
			);
		}

		$body = array(
			'model'      => $this->model,
			'max_tokens' => $max_tokens,
			'messages'   => $messages,
		);
		if ( '' !== $system ) {
			$body['system'] = $system;
		}

		$started = microtime( true );
		$response = $this->request( $body );
		$latency = (int) round( ( microtime( true ) - $started ) * 1000 );

		// Retry once on 5xx (per spec).
		if ( ! $response['ok'] && isset( $response['status'] ) && $response['status'] >= 500 ) {
			$response = $this->request( $body );
			$latency  = (int) round( ( microtime( true ) - $started ) * 1000 );
		}

		$response['latency_ms'] = $latency;
		return $response;
	}

	/**
	 * Simple "say hi" test — returns a fast ok/error for the Test Connection button.
	 *
	 * @return array{ok:bool, latency_ms?:int, error?:string, model?:string}
	 */
	public function test(): array {
		$res = $this->send(
			array(
				array(
					'role'    => 'user',
					'content' => 'Reply with only the word "ok".',
				),
			),
			'',
			10
		);

		if ( ! $res['ok'] ) {
			return array(
				'ok'    => false,
				'error' => $res['error'] ?? __( 'Unknown error', 'deliz-ai-advisor' ),
			);
		}

		return array(
			'ok'         => true,
			'latency_ms' => $res['latency_ms'] ?? 0,
			'model'      => $this->model,
		);
	}

	/**
	 * Perform the HTTP request.
	 *
	 * @param array<string,mixed> $body
	 * @return array<string,mixed>
	 */
	private function request( array $body ): array {
		$response = wp_remote_post(
			self::API_URL,
			array(
				'timeout' => self::DEFAULT_TIMEOUT,
				'headers' => array(
					'x-api-key'         => $this->api_key,
					'anthropic-version' => self::ANTHROPIC_VERSION,
					'content-type'      => 'application/json',
				),
				'body'    => wp_json_encode( $body ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'ok'     => false,
				'error'  => $response->get_error_message(),
				'status' => 0,
			);
		}

		$status = (int) wp_remote_retrieve_response_code( $response );
		$raw    = wp_remote_retrieve_body( $response );
		$data   = json_decode( $raw, true );

		if ( $status >= 400 || ! is_array( $data ) ) {
			$msg = is_array( $data ) && isset( $data['error']['message'] )
				? (string) $data['error']['message']
				: sprintf( 'HTTP %d', $status );
			return array(
				'ok'     => false,
				'error'  => $msg,
				'status' => $status,
			);
		}

		$text       = isset( $data['content'][0]['text'] ) ? (string) $data['content'][0]['text'] : '';
		$tokens_in  = (int) ( $data['usage']['input_tokens'] ?? 0 );
		$tokens_out = (int) ( $data['usage']['output_tokens'] ?? 0 );

		return array(
			'ok'         => true,
			'text'       => $text,
			'tokens_in'  => $tokens_in,
			'tokens_out' => $tokens_out,
			'cost_usd'   => self::calc_cost( $this->model, $tokens_in, $tokens_out ),
			'model'      => $this->model,
			'status'     => $status,
		);
	}

	/**
	 * Compute USD cost from token counts + model.
	 */
	public static function calc_cost( string $model, int $tokens_in, int $tokens_out ): float {
		if ( ! isset( self::PRICING[ $model ] ) ) {
			return 0.0;
		}
		$p = self::PRICING[ $model ];
		return round(
			( $tokens_in / 1000000 * $p['in'] ) + ( $tokens_out / 1000000 * $p['out'] ),
			6
		);
	}
}
