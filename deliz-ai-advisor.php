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

// Prevent direct access.
defined( 'ABSPATH' ) || exit;

// Plugin constants.
define( 'DELIZ_AI_VERSION', '1.0.0' );
define( 'DELIZ_AI_PLUGIN_FILE', __FILE__ );
define( 'DELIZ_AI_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'DELIZ_AI_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'DELIZ_AI_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/*
 * Phase 0 scaffold — bootstrap is intentionally empty.
 * Actual initialization lands in Phase 1 (class-plugin.php + plugins_loaded hook).
 */
