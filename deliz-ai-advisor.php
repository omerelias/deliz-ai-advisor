<?php
/**
 * Plugin Name:       Deliz AI Advisor
 * Plugin URI:        https://github.com/omerelias/deliz-ai-advisor
 * Description:       AI-powered chat advisor for WooCommerce delicatessen shops. Multilingual (HE/RU/AR/EN), product-aware, powered by Claude.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Requires Plugins:  woocommerce
 * Author:            Original Concepts
 * Author URI:        https://onlinestore.co.il/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       deliz-ai-advisor
 * Domain Path:       /languages
 * Update URI:        https://github.com/omerelias/deliz-ai-advisor
 * GitHub Plugin URI: omerelias/deliz-ai-advisor
 * Primary Branch:    main
 *
 * @package Deliz\AI\Advisor
 */

defined( 'ABSPATH' ) || exit;

// ---------------------------------------------------------------------------
// Constants
// ---------------------------------------------------------------------------
define( 'DELIZ_AI_VERSION', '1.0.0' );
define( 'DELIZ_AI_PLUGIN_FILE', __FILE__ );
define( 'DELIZ_AI_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'DELIZ_AI_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'DELIZ_AI_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// ---------------------------------------------------------------------------
// PSR-4-ish autoloader for the Deliz\AI\Advisor namespace
// ---------------------------------------------------------------------------
spl_autoload_register(
	function ( $class ) {
		$prefix = 'Deliz\\AI\\Advisor\\';
		if ( strpos( $class, $prefix ) !== 0 ) {
			return;
		}

		$relative = substr( $class, strlen( $prefix ) );
		$relative = str_replace( '\\', '/', $relative );

		// Split path and class; convert class name to WP filename convention.
		$parts       = explode( '/', $relative );
		$class_short = array_pop( $parts );
		$dir         = $parts ? strtolower( implode( '/', $parts ) ) . '/' : '';

		// ClassName → class-class-name.php
		$file_short = 'class-' . strtolower( preg_replace( '/([a-z])([A-Z])/', '$1-$2', $class_short ) ) . '.php';

		$path = DELIZ_AI_PLUGIN_DIR . 'includes/' . $dir . $file_short;
		if ( file_exists( $path ) ) {
			require_once $path;
		}
	}
);

// ---------------------------------------------------------------------------
// Activation / Deactivation / Uninstall hooks
// ---------------------------------------------------------------------------
register_activation_hook( __FILE__, array( 'Deliz\\AI\\Advisor\\Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Deliz\\AI\\Advisor\\Deactivator', 'deactivate' ) );

// ---------------------------------------------------------------------------
// Bootstrap
// ---------------------------------------------------------------------------
add_action(
	'plugins_loaded',
	function () {
		\Deliz\AI\Advisor\Plugin::instance()->boot();
	},
	10
);

/**
 * Convenience accessor.
 */
function deliz_ai(): \Deliz\AI\Advisor\Plugin {
	return \Deliz\AI\Advisor\Plugin::instance();
}
