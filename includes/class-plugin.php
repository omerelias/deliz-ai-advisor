<?php
/**
 * Main plugin singleton. Bootstraps all subsystems.
 *
 * @package Deliz\AI\Advisor
 */

namespace Deliz\AI\Advisor;

defined( 'ABSPATH' ) || exit;

final class Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * @var Requirements
	 */
	public $requirements;

	/**
	 * @var I18n
	 */
	public $i18n;

	/**
	 * Get the singleton instance.
	 */
	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {}

	/**
	 * Run on `plugins_loaded`. Initializes everything.
	 */
	public function boot(): void {
		$this->i18n = new I18n();
		$this->i18n->register();

		$this->requirements = new Requirements();
		if ( ! $this->requirements->met() ) {
			// WC missing — show notice and stop initialization.
			$this->requirements->register_notice();
			return;
		}

		/*
		 * Future phases hook additional subsystems here:
		 * - Phase 2: Admin, SettingsPage
		 * - Phase 3: Encryption, AnthropicClient
		 * - Phase 4: REST Controller, ChatEndpoint
		 * - Phase 5: Widget, Assets
		 * - ...
		 */
	}
}
