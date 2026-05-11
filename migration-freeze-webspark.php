<?php
/**
 * Plugin Name:     Pitchfork - Migration Freeze
 * Plugin URI:      https://github.com/asuengineering/migration-freeze-webspark
 * Description:     Manage migration states, approved admin access, and user freezes for multisite sites.
 * Author:          Steve Ryan
 * Author URI:      https://comm.engineering.asu.edu
 * Text Domain:     migration-freeze-webspark
 * Domain Path:     /languages
 * Version:         0.2.0
 *
 * @package         migration_freeze_webspark
 *
 * GitHub Plugin URI: https://github.com/asuengineering/migration-freeze-webspark
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'MFW_VERSION' ) ) {
	define( 'MFW_VERSION', '0.2.0' );
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

if ( ! defined( 'MFW_OPTION_SITE_STATE' ) ) {
	define( 'MFW_OPTION_SITE_STATE', 'mfw_site_state' );
}

if ( ! defined( 'MFW_STATE_PENDING' ) ) {
	define( 'MFW_STATE_PENDING', 'pending' );
}

if ( ! defined( 'MFW_STATE_ACTIVE' ) ) {
	define( 'MFW_STATE_ACTIVE', 'active' );
}

if ( ! defined( 'MFW_STATE_COMPLETE' ) ) {
	define( 'MFW_STATE_COMPLETE', 'complete' );
}

if ( ! defined( 'MFW_STATE_UAT_COMPLETE' ) ) {
	define( 'MFW_STATE_UAT_COMPLETE', 'uat_complete' );
}

if ( ! defined( 'MFW_STATE_DECOMMISSIONED' ) ) {
	define( 'MFW_STATE_DECOMMISSIONED', 'decommissioned' );
}

require_once MFW_PLUGIN_PATH . 'inc/helpers.php';
require_once MFW_PLUGIN_PATH . 'inc/site-state.php';
require_once MFW_PLUGIN_PATH . 'inc/admin-notices.php';
require_once MFW_PLUGIN_PATH . 'inc/my-sites.php';
require_once MFW_PLUGIN_PATH . 'inc/user-management.php';
require_once MFW_PLUGIN_PATH . 'inc/settings.php';

/**
 * Load translations.
 */
function mfw_bootstrap() {
	load_plugin_textdomain( 'migration-freeze-webspark', false, dirname( MFW_PLUGIN_BASENAME ) . '/languages' );
}
add_action( 'plugins_loaded', 'mfw_bootstrap' );

/**
 * Plugin activation hook.
 */
function mfw_activate_plugin() {
	if ( false === get_option( MFW_OPTION_SITE_STATE, false ) ) {
		add_option( MFW_OPTION_SITE_STATE, MFW_STATE_PENDING );
	}
}
register_activation_hook( __FILE__, 'mfw_activate_plugin' );

/**
 * Plugin deactivation hook.
 */
function mfw_deactivate_plugin() {
	// No-op for now.
}
register_deactivation_hook( __FILE__, 'mfw_deactivate_plugin' );
