<?php
/**
 * Post-activation onboarding: one-time admin notice linking to Settings.
 *
 * @package Deliz\AI\Advisor\Admin
 */

namespace Deliz\AI\Advisor\Admin;

use Deliz\AI\Advisor\Models\Settings;

defined( 'ABSPATH' ) || exit;

class Onboarding {

	const FLAG_OPTION = 'deliz_ai_onboarding_seen';

	public function register(): void {
		add_action( 'admin_notices', array( $this, 'maybe_notice' ) );
		add_action( 'admin_init', array( $this, 'handle_dismiss' ) );
	}

	public function maybe_notice(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( get_option( self::FLAG_OPTION, 0 ) ) {
			return;
		}
		if ( Settings::api_key() !== '' ) {
			// User already configured — auto-dismiss silently.
			update_option( self::FLAG_OPTION, 1, false );
			return;
		}

		$settings_url = admin_url( 'admin.php?page=' . Admin::MENU_SLUG );
		$dismiss_url  = wp_nonce_url(
			add_query_arg( 'deliz_ai_dismiss_onboarding', '1', admin_url() ),
			'deliz_ai_dismiss_onboarding'
		);

		echo '<div class="notice notice-info" style="border-left-color:#7c3aed;padding:14px 20px;">';
		echo '<p style="margin:0 0 6px;font-size:14px;"><strong>✨ ' . esc_html__( 'Deliz AI Advisor is installed.', 'deliz-ai-advisor' ) . '</strong></p>';
		echo '<p style="margin:0 0 10px;">'
			. esc_html__( 'Add your Anthropic API key to start helping visitors.', 'deliz-ai-advisor' )
			. '</p>';
		echo '<p style="margin:0;">';
		echo '<a class="button button-primary" href="' . esc_url( $settings_url ) . '">'
			. esc_html__( 'Open Settings', 'deliz-ai-advisor' )
			. '</a> ';
		echo '<a class="button" href="' . esc_url( $dismiss_url ) . '">'
			. esc_html__( 'Dismiss', 'deliz-ai-advisor' )
			. '</a>';
		echo '</p>';
		echo '</div>';
	}

	public function handle_dismiss(): void {
		if ( empty( $_GET['deliz_ai_dismiss_onboarding'] ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		check_admin_referer( 'deliz_ai_dismiss_onboarding' );
		update_option( self::FLAG_OPTION, 1, false );

		wp_safe_redirect( remove_query_arg( array( 'deliz_ai_dismiss_onboarding', '_wpnonce' ) ) );
		exit;
	}
}
