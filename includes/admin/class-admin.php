<?php
/**
 * Admin entry point — menu, assets loader, tab routing.
 *
 * @package Deliz\AI\Advisor\Admin
 */

namespace Deliz\AI\Advisor\Admin;

defined( 'ABSPATH' ) || exit;

class Admin {

	const MENU_SLUG = 'deliz-ai';

	/**
	 * @var SettingsPage
	 */
	public $settings_page;

	/**
	 * @var LogsPage
	 */
	public $logs_page;

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_init', array( $this, 'handle_settings_save' ) );
		add_action( 'admin_init', array( 'Deliz\\AI\\Advisor\\Admin\\SettingsPage', 'handle_maintenance_action' ) );

		$this->settings_page = new SettingsPage();
		$this->logs_page     = new LogsPage();
	}

	/**
	 * Register the "Deliz AI" top-level admin menu + submenus.
	 */
	public function register_menu(): void {
		$icon = 'data:image/svg+xml;base64,' . base64_encode(
			'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="#a7aaad"><path d="M10 2l1.5 4.5L16 8l-4.5 1.5L10 14l-1.5-4.5L4 8l4.5-1.5L10 2zm6 10l.8 2.2L19 15l-2.2.8L16 18l-.8-2.2L13 15l2.2-.8L16 12z"/></svg>'
		);

		add_menu_page(
			__( 'Deliz AI Advisor', 'deliz-ai-advisor' ),
			__( 'Deliz AI', 'deliz-ai-advisor' ),
			'manage_options',
			self::MENU_SLUG,
			array( $this->settings_page, 'render' ),
			$icon,
			58
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Settings', 'deliz-ai-advisor' ),
			__( 'Settings', 'deliz-ai-advisor' ),
			'manage_options',
			self::MENU_SLUG,
			array( $this->settings_page, 'render' )
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Statistics', 'deliz-ai-advisor' ),
			__( 'Statistics', 'deliz-ai-advisor' ),
			'manage_options',
			self::MENU_SLUG . '-stats',
			array( $this, 'render_stats_placeholder' )
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Conversation Log', 'deliz-ai-advisor' ),
			__( 'Conversation Log', 'deliz-ai-advisor' ),
			'manage_options',
			self::MENU_SLUG . '-logs',
			array( $this->logs_page, 'render' )
		);
	}

	public function render_stats_placeholder(): void {
		echo '<div class="wrap"><h1>' . esc_html__( 'Statistics', 'deliz-ai-advisor' ) . '</h1>';
		echo '<p>' . esc_html__( 'Coming in Phase 12.', 'deliz-ai-advisor' ) . '</p></div>';
	}


	/**
	 * Enqueue admin CSS/JS only on plugin pages.
	 */
	public function enqueue_assets( string $hook ): void {
		if ( strpos( $hook, self::MENU_SLUG ) === false ) {
			return;
		}

		wp_enqueue_style(
			'deliz-ai-admin',
			DELIZ_AI_PLUGIN_URL . 'assets/css/admin-settings.css',
			array(),
			DELIZ_AI_VERSION
		);

		wp_enqueue_script(
			'deliz-ai-admin',
			DELIZ_AI_PLUGIN_URL . 'assets/js/admin-settings.js',
			array( 'wp-color-picker' ),
			DELIZ_AI_VERSION,
			true
		);

		wp_enqueue_style( 'wp-color-picker' );

		wp_localize_script(
			'deliz-ai-admin',
			'delizAiAdmin',
			array(
				'restUrl'    => esc_url_raw( rest_url( 'deliz-ai/v1/' ) ),
				'nonce'      => wp_create_nonce( 'wp_rest' ),
				'i18n'       => array(
					'testing'       => __( 'Testing…', 'deliz-ai-advisor' ),
					'test_ok'       => __( 'Connection OK', 'deliz-ai-advisor' ),
					'test_fail'     => __( 'Connection failed', 'deliz-ai-advisor' ),
					'enter_key'     => __( 'Please enter an API key first.', 'deliz-ai-advisor' ),
				),
			)
		);
	}

	/**
	 * Handle classic form POST from the settings page.
	 * REST is used for live actions (test-key) — this handles the save.
	 */
	public function handle_settings_save(): void {
		if ( ! isset( $_POST['deliz_ai_save'] ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'deliz-ai-advisor' ) );
		}
		check_admin_referer( 'deliz_ai_save_settings' );

		$this->settings_page->save( $_POST );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'             => self::MENU_SLUG,
					'tab'              => isset( $_POST['current_tab'] ) ? sanitize_key( wp_unslash( $_POST['current_tab'] ) ) : 'general',
					'settings-updated' => 'true',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}
}
