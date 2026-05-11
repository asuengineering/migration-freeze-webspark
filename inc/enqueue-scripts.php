<?php
/**
 * - Enqueue plugin scripts.
 * - Register additional scripts to be loaded with individual blocks.
 *
 * @package icenine-portfolio
 */

// add_action('admin_enqueue_scripts', 'starter_plugin_enqueue_admin_scripts');
// function starter_plugin_enqueue_admin_scripts() {

// 	// Get the theme data.
// 	$the_plugin     = get_plugin_data( STARTER_PLUGIN_BASE_PATH );
// 	$plugin_version = $the_plugin['Version'];

// }

add_action( 'enqueue_block_editor_assets', 'starter_plugin_enqueue_block_scripts' );
function starter_plugin_enqueue_block_scripts() {

		$the_plugin     = get_plugin_data( plugin_dir_path( __DIR__ ) . 'icenine-portfolio.php' );
		$the_version    = $the_plugin['Version'];
		$plugin_version = $the_version . '.' . filemtime( plugin_dir_path( __DIR__ ) . 'js/block-variations.js' );
	
	wp_enqueue_script( 'uds-block-variations', plugin_dir_url( __DIR__ ) . 'js/block-variations.js',  array( 'wp-blocks', 'wp-dom' ), $plugin_version , true );

}
