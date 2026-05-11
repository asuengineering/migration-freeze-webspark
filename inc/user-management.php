<?php
/**
 * User management helpers.
 *
 * @package migration_freeze_webspark
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Get the approved migration assistant user objects that exist locally.
 *
 * @return array<int, WP_User>
 */
function mfw_get_existing_migration_assistants() {
	$users = array();

	foreach ( mfw_get_migration_assistants() as $username ) {
		$user = get_user_by( 'login', $username );

		if ( $user instanceof WP_User ) {
			$users[] = $user;
		}
	}

	return $users;
}

/**
 * Placeholder for the next step in freeze behavior.
 */
function mfw_apply_state_changes() {
	// Coming in the next iteration once the settings screen is live.
}
