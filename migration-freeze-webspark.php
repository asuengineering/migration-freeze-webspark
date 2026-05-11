<?php
/**
 * Plugin Name:     Migration Freeze WebSparK
 * Plugin URI:      https://github.com/asuengineering/migration-freeze-webspark
 * Description:     Manage access freezes, decommission notices, and approved admin access for multisite sites.
 * Author:          Steve Ryan
 * Author URI:      https://sryan.us
 * Text Domain:     migration-freeze-webspark
 * Domain Path:     /languages
 * Version:         0.1.0
 *
 * @package         migration_freeze_webspark
 *
 * GitHub Plugin URI: https://github.com/asuengineering/migration-freeze-webspark
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'MFW_VERSION' ) ) {
	define( 'MFW_VERSION', '0.1.0' );
}

if ( ! defined( 'MFW_PLUGIN_FILE' ) ) {
	define( 'MFW_PLUGIN_FILE', __FILE__ );
}

if ( ! defined( 'MFW_PLUGIN_PATH' ) ) {
	define( 'MFW_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'MFW_PLUGIN_URL' ) ) {
	define( 'MFW_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}

if ( ! defined( 'MFW_PLUGIN_BASENAME' ) ) {
	define( 'MFW_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
}

/**
 * Load translations and any required plugin modules.
 */
function mfw_bootstrap() {
	load_plugin_textdomain( 'migration-freeze-webspark', false, dirname( MFW_PLUGIN_BASENAME ) . '/languages' );
}
add_action( 'plugins_loaded', 'mfw_bootstrap' );

/**
 * Plugin activation hook.
 */
function mfw_activate_plugin() {
	// Activation tasks will be added here.
}
register_activation_hook( __FILE__, 'mfw_activate_plugin' );

/**
 * Plugin deactivation hook.
 */
function mfw_deactivate_plugin() {
	// Deactivation tasks will be added here.
}
register_deactivation_hook( __FILE__, 'mfw_deactivate_plugin' );
