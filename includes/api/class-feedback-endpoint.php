<?php
/**
 * POST /deliz-ai/v1/feedback
 *
 * @package Deliz\AI\Advisor\Api
 */

namespace Deliz\AI\Advisor\Api;

defined( 'ABSPATH' ) || exit;

class FeedbackEndpoint {

	public function register( string $namespace ): void {
		register_rest_route(
			$namespace,
			'/feedback',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'message_id' => array(
						'type'     => 'integer',
						'required' => true,
					),
					'helpful'    => array(
						'type'     => 'boolean',
						'required' => true,
					),
				),
			)
		);
	}

	public function handle( \WP_REST_Request $req ) {
		global $wpdb;
		$message_id = absint( $req->get_param( 'message_id' ) );
		$helpful    = (bool) $req->get_param( 'helpful' );

		if ( ! $message_id ) {
			return new \WP_Error( 'deliz_ai_bad_id', 'Bad message id', array( 'status' => 400 ) );
		}

		$table = $wpdb->prefix . 'deliz_ai_messages';
		$wpdb->update(
			$table,
			array( 'feedback' => $helpful ? 1 : 0 ),
			array(
				'id'   => $message_id,
				'role' => 'assistant',
			),
			array( '%d' ),
			array( '%d', '%s' )
		);

		return new \WP_REST_Response( array( 'ok' => true ), 200 );
	}
}
