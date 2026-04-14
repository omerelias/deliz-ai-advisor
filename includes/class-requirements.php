<?php
/**
 * Requirements check. Hard-depends on WooCommerce.
 *
 * @package Deliz\AI\Advisor
 */

namespace Deliz\AI\Advisor;

defined( 'ABSPATH' ) || exit;

class Requirements {

	const MIN_WP  = '6.0';
	const MIN_PHP = '7.4';

	/**
	 * Whether all requirements are met.
	 */
	public function met(): bool {
		return $this->woocommerce_active()
			&& version_compare( get_bloginfo( 'version' ), self::MIN_WP, '>=' )
			&& version_compare( PHP_VERSION, self::MIN_PHP, '>=' );
	}

	/**
	 * Check whether WooCommerce is active.
	 */
	public function woocommerce_active(): bool {
		return class_exists( 'WooCommerce' ) || in_array(
			'woocommerce/woocommerce.php',
			apply_filters( 'active_plugins', get_option( 'active_plugins', array() ) ),
			true
		);
	}

	/**
	 * Hook admin notice describing the missing requirement.
	 */
	public function register_notice(): void {
		add_action( 'admin_notices', array( $this, 'render_notice' ) );
	}

	public function render_notice(): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		$messages = array();

		if ( ! $this->woocommerce_active() ) {
			$messages[] = __( 'Deliz AI Advisor requires WooCommerce to be installed and active.', 'deliz-ai-advisor' );
		}

		if ( version_compare( get_bloginfo( 'version' ), self::MIN_WP, '<' ) ) {
			$messages[] = sprintf(
				/* translators: %s: required WP version */
				__( 'Deliz AI Advisor requires WordPress %s or higher.', 'deliz-ai-advisor' ),
				self::MIN_WP
			);
		}

		if ( version_compare( PHP_VERSION, self::MIN_PHP, '<' ) ) {
			$messages[] = sprintf(
				/* translators: %s: required PHP version */
				__( 'Deliz AI Advisor requires PHP %s or higher.', 'deliz-ai-advisor' ),
				self::MIN_PHP
			);
		}

		if ( empty( $messages ) ) {
			return;
		}

		echo '<div class="notice notice-error"><p><strong>Deliz AI Advisor:</strong></p><ul style="list-style:disc;padding-left:20px">';
		foreach ( $messages as $msg ) {
			echo '<li>' . esc_html( $msg ) . '</li>';
		}
		echo '</ul></div>';
	}
}
