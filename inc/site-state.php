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
 * @return array<string, array{label:string,message:string,action:string}>
 */
function mfw_get_site_states() {
	return array(
		MFW_STATE_PENDING => array(
			'label'   => 'Pending Migration',
			'message' => 'Migration preparation is underway. A content freeze is imminent.',
			'action'  => 'No changes are made to site users yet.',
		),
		MFW_STATE_ACTIVE => array(
			'label'   => 'Migration Active',
			'message' => 'Migration is in progress. Approved migration assistants remain site admins and all other site users are demoted to subscriber.',
			'action'  => 'Promote approved assistants; demote non-exempt users to subscriber.',
		),
		MFW_STATE_COMPLETE => array(
			'label'   => 'Migration Complete',
			'message' => 'Migration is complete. The site remains available while UAT or follow-up review continues.',
			'action'  => 'Leave all current users unchanged.',
		),
		MFW_STATE_UAT_COMPLETE => array(
			'label'   => 'UAT Complete',
			'message' => 'User acceptance testing is complete. Migration assistants can now be removed from the site if needed.',
			'action'  => 'Leave users unchanged unless you choose to clean up assistants.',
		),
		MFW_STATE_DECOMMISSIONED => array(
			'label'   => 'Decommissioned',
			'message' => 'This site is scheduled for removal. All site users will be removed during decommission handling.',
			'action'  => 'Remove all users from the site.',
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

/**
 * Get the current site state configuration.
 *
 * @return array<string, string>
 */
function mfw_get_current_state_config() {
	$states = mfw_get_site_states();
	$state  = mfw_get_site_state();

	return $states[ $state ];
}
