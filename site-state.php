<?php
/**
 * Site state helpers.
 *
 * @package migration_freeze_webspark
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Return all valid site states.
 *
 * @return array
 */
function mfw_get_site_states() {
	return array(
		MFW_STATE_PENDING => array(
			'label'   => 'Pending Migration',
			'message' => 'Migration preparation is underway. A content freeze is imminent.',
		),
		MFW_STATE_FROZEN => array(
			'label'   => 'Frozen',
			'message' => 'This site is currently frozen while migration is in progress.',
		),
		MFW_STATE_COMPLETE => array(
			'label'   => 'Migration Complete',
			'message' => 'Migration is complete. Need access to the new site? Submit a support ticket.',
		),
	);
}

/**
 * Get current site state.
 *
 * @return string
 */
function mfw_get_site_state() {
	$state = get_option( MFW_OPTION_SITE_STATE, MFW_STATE_PENDING );

	if ( ! array_key_exists( $state, mfw_get_site_states() ) ) {
		return MFW_STATE_PENDING;
	}

	return $state;
}

/**
 * Set site state.
 *
 * @param string $state Site state.
 *
 * @return bool
 */
function mfw_set_site_state( $state ) {
	if ( ! array_key_exists( $state, mfw_get_site_states() ) ) {
		return false;
	}

	return update_option( MFW_OPTION_SITE_STATE, $state );
}
