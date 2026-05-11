<?php
/**
 * ACF Configurations.
 * - Add save/load points for JSON feature.
 *
 * @package icenine-portfolio
 */

/**
 * Register a new save point for the Local JSON feature for this plugin.
 *
 * @param  mixed $path // path to ACF safe point.
 * @return $path
 */
function starter_plugin_acf_json_save_point( $path ) {
	$path = STARTER_PLUGIN_BASE_PATH . '/acf-json';
	return $path;
}
add_filter( 'acf/settings/save_json', 'starter_plugin_acf_json_save_point' );

/**
 * Register a new loading point for the Local JSON feature for this plugin.
 *
 * @param  mixed $paths // path to ACF load point.
 * @return $paths
 */
function starter_plugin_acf_json_load_point( $paths ) {
	$paths[] = STARTER_PLUGIN_BASE_PATH . '/acf-json';
	return $paths;
}
add_filter( 'acf/settings/load_json', 'starter_plugin_acf_json_load_point' );

// // Register new options page within ACF
// if( function_exists('acf_add_options_page') ) {
// acf_add_options_sub_page(
// array(
// 'page_title'    => 'Pitchfork Blocks Options',
// 'menu_title'    => 'Pitchfork Blocks',
// 'parent_slug'   => 'tools.php',
// )
// );
// }
