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
	 * @var Admin\Admin|null
	 */
	public $admin = null;

	/**
	 * @var Api\RestController|null
	 */
	public $rest = null;

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

		if ( is_admin() ) {
			$this->admin = new Admin\Admin();
			$this->admin->register();
		}

		// REST (always registered — test-key/chat/feedback all live here).
		$this->rest = new Api\RestController();
		$this->rest->register();

		/*
		 * Future phases hook additional subsystems here:
		 * - Phase 5: Widget, Assets
		 * - Phase 13: Conversion tracking
		 * - Phase 15: GitHub updater
		 */
	}
}
