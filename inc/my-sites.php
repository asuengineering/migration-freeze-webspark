<?php
/**
 * My Sites screen enhancements.
 *
 * @package migration_freeze_webspark
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Append a migration status label to each visible site row on the My Sites screen.
 *
 * @param string  $actions   Existing row actions HTML.
 * @param stdClass $user_blog Site object from get_blogs_of_user().
 *
 * @return string
 */
function mfw_label_my_sites_row( $actions, $user_blog ) {
	if ( ! is_multisite() || ! ( $user_blog instanceof stdClass ) ) {
		return $actions;
	}

	$blog_id = isset( $user_blog->userblog_id ) ? (int) $user_blog->userblog_id : 0;

	if ( $blog_id < 1 ) {
		return $actions;
	}

	$state       = get_blog_option( $blog_id, MFW_OPTION_SITE_STATE, MFW_STATE_PENDING );
	$states      = mfw_get_site_states();
	$label        = isset( $states[ $state ]['label'] ) ? $states[ $state ]['label'] : ucfirst( $state );
	$status_label = sprintf(
		'<span class="mfw-site-state mfw-site-state-%1$s">%2$s: %3$s</span>',
		esc_attr( sanitize_html_class( $state ) ),
		esc_html__( 'Migration Status', 'migration-freeze-webspark' ),
		esc_html( $label )
	);

	if ( '' === trim( wp_strip_all_tags( $actions ) ) ) {
		return $status_label;
	}

	return $actions . ' ' . $status_label;
}
add_filter( 'myblogs_blog_actions', 'mfw_label_my_sites_row', 10, 2 );
