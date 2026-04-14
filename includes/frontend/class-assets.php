<?php
/**
 * Frontend assets (widget JS + CSS).
 *
 * @package Deliz\AI\Advisor\Frontend
 */

namespace Deliz\AI\Advisor\Frontend;

use Deliz\AI\Advisor\Models\Settings;

defined( 'ABSPATH' ) || exit;

class Assets {

	/**
	 * @var Widget
	 */
	private $widget;

	public function __construct( Widget $widget ) {
		$this->widget = $widget;
	}

	public function register(): void {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	public function enqueue(): void {
		if ( ! $this->widget->should_render() ) {
			return;
		}

		wp_enqueue_style(
			'deliz-ai-widget',
			DELIZ_AI_PLUGIN_URL . 'assets/css/chat-widget.css',
			array(),
			self::asset_version( 'assets/css/chat-widget.css' )
		);

		wp_enqueue_script(
			'deliz-ai-widget',
			DELIZ_AI_PLUGIN_URL . 'assets/js/chat-widget.js',
			array(),
			self::asset_version( 'assets/js/chat-widget.js' ),
			true
		);

		$behavior = Settings::group( 'behavior' );

		wp_localize_script(
			'deliz-ai-widget',
			'delizAi',
			array(
				'restUrl'            => esc_url_raw( rest_url( 'deliz-ai/v1/' ) ),
				'nonce'              => wp_create_nonce( 'wp_rest' ),
				'maxMessageLength'   => (int) ( $behavior['max_message_length'] ?? 500 ),
				'showFeedback'       => ! empty( $behavior['show_feedback_buttons'] ),
				'i18n'               => array(
					'typing'      => __( 'Typing…', 'deliz-ai-advisor' ),
					'error'       => __( 'Something went wrong. Please try again.', 'deliz-ai-advisor' ),
					'rate_limit'  => __( 'You asked too many questions. Please try again in an hour.', 'deliz-ai-advisor' ),
					'daily_cap'   => __( 'The advisor is unavailable right now. Please try again tomorrow.', 'deliz-ai-advisor' ),
					'retry'       => __( 'Retry', 'deliz-ai-advisor' ),
					'helpful'     => __( 'Helpful', 'deliz-ai-advisor' ),
					'not_helpful' => __( 'Not helpful', 'deliz-ai-advisor' ),
				),
			)
		);
	}

	/**
	 * Cache-bust: use filemtime under WP_DEBUG for zero-caching dev reloads,
	 * otherwise stick with the plugin version so production still caches.
	 */
	private static function asset_version( string $relative_path ): string {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$abs = DELIZ_AI_PLUGIN_DIR . $relative_path;
			if ( file_exists( $abs ) ) {
				return (string) filemtime( $abs );
			}
		}
		return DELIZ_AI_VERSION;
	}
}
