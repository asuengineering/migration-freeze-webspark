<?php
/**
 * Plugin Name:     Starter Plugin
 * Plugin URI:      http://github.com
 * Description:     (text)
 * Author:          Steve Ryan
 * Author URI:      sryan.us
 * Text Domain:     (text)
 * Domain Path:     /languages
 * Version:         0.1.0
 *
 * @package         starter_plugin
 *
 * GitHub Plugin URI: http://github.com
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

// Variable for root directory of this plugin.
define( 'STARTER_PLUGIN_BASE_PATH', plugin_dir_path( __FILE__ ) );

// Function: Activate.
// Function: Deactivate.
// Function: Execute plugin.

// TGM Plugin Activation Script. Checks for Advanced Custom Fields.
require_once STARTER_PLUGIN_BASE_PATH . '/tgmpa/class-tgm-plugin-activation.php';
require_once STARTER_PLUGIN_BASE_PATH . '/tgmpa/dependency-check.php';

// Enqueue scripts.
require_once STARTER_PLUGIN_BASE_PATH . '/inc/enqueue-scripts.php';

// ACF configurations.
require_once STARTER_PLUGIN_BASE_PATH . '/inc/acf-config.php';
require_once STARTER_PLUGIN_BASE_PATH . '/inc/acf-register-blocks.php';
