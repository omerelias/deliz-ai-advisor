<?php
/**
 * Text domain loader.
 *
 * @package Deliz\AI\Advisor
 */

namespace Deliz\AI\Advisor;

defined( 'ABSPATH' ) || exit;

class I18n {

	public function register(): void {
		add_action( 'init', array( $this, 'load_textdomain' ) );
	}

	public function load_textdomain(): void {
		load_plugin_textdomain(
			'deliz-ai-advisor',
			false,
			dirname( DELIZ_AI_PLUGIN_BASENAME ) . '/languages'
		);
	}
}
