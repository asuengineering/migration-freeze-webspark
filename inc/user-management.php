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
 * Get the current site's users.
 *
 * @return array<int, WP_User>
 */
function mfw_get_site_users() {
	return get_users(
		array(
			'blog_id' => get_current_blog_id(),
			'fields'  => 'all',
		)
	);
}

/**
 * Check whether a user is on the approved migration team list.
 *
 * @param WP_User $user User object.
 *
 * @return bool
 */
function mfw_user_is_migration_team_member( $user ) {
	if ( ! ( $user instanceof WP_User ) ) {
		return false;
	}

	return in_array( mfw_normalize_username( $user->user_login ), mfw_get_migration_assistants(), true );
}

/**
 * Determine whether the current install is multisite.
 *
 * @return bool
 */
function mfw_is_multisite_context() {
	return is_multisite();
}

/**
 * Create missing migration team users if needed.
 *
 * @return array<int, string>
 */
function mfw_ensure_migration_team_users_exist() {
	$created = array();

	foreach ( mfw_get_migration_assistants() as $username ) {
		$existing = get_user_by( 'login', $username );

		if ( $existing instanceof WP_User ) {
			continue;
		}

		$email           = $username . '@asu.edu';
		$random_password  = wp_generate_password( 32, true, true );
		$user_id         = wp_create_user( $username, $random_password, $email );

		if ( ! is_wp_error( $user_id ) ) {
			$created[] = $username;
		}
	}

	return $created;
}

/**
 * Apply the currently selected migration state.
 *
 * @param string|null $state Optional state override.
 *
 * @return array<string, mixed>
 */
function mfw_apply_state_changes( $state = null ) {
	$state = $state ? sanitize_key( $state ) : mfw_get_site_state();

	switch ( $state ) {
		case MFW_STATE_ACTIVE:
			return mfw_apply_active_state();

		case MFW_STATE_DECOMMISSIONED:
			return mfw_apply_decommissioned_state();

		case MFW_STATE_PENDING:
		case MFW_STATE_COMPLETE:
		case MFW_STATE_UAT_COMPLETE:
		default:
			return array(
				'state'         => $state,
				'changes_made'  => false,
				'created'       => array(),
				'promoted'      => array(),
				'demoted'       => array(),
				'removed'       => array(),
				'missing'       => array(),
				'message'       => __( 'No user changes were applied for this state.', 'migration-freeze-webspark' ),
			);
	}
}

/**
 * Promote the migration team and demote everyone else to subscriber.
 *
 * @return array<string, mixed>
 */
function mfw_apply_active_state() {
	$blog_id      = get_current_blog_id();
	$current_user  = get_current_user_id();
	$created       = mfw_ensure_migration_team_users_exist();
	$promoted      = array();
	$demoted       = array();
	$missing       = array();
	$team_lookup   = array_flip( mfw_get_migration_assistants() );
	$is_multisite  = mfw_is_multisite_context();

	foreach ( mfw_get_migration_assistants() as $username ) {
		$user = get_user_by( 'login', $username );

		if ( ! ( $user instanceof WP_User ) ) {
			$missing[] = $username;
			continue;
		}

		if ( $is_multisite && function_exists( 'add_user_to_blog' ) ) {
			add_user_to_blog( $blog_id, $user->ID, 'administrator' );
		} else {
			$user_obj = new WP_User( $user->ID );
			$user_obj->set_role( 'administrator' );
		}

		$promoted[] = $username;
	}

	foreach ( mfw_get_site_users() as $user ) {
		if ( (int) $user->ID === (int) $current_user ) {
			continue;
		}

		$username = mfw_normalize_username( $user->user_login );

		if ( isset( $team_lookup[ $username ] ) ) {
			continue;
		}

		$user_obj = new WP_User( $user->ID );
		$roles    = (array) $user_obj->roles;

		if ( in_array( 'subscriber', $roles, true ) && 1 === count( $roles ) ) {
			continue;
		}

		$user_obj->set_role( 'subscriber' );
		$demoted[] = $username;
	}

	return array(
		'state'        => MFW_STATE_ACTIVE,
		'changes_made' => true,
		'created'      => $created,
		'promoted'     => $promoted,
		'demoted'      => $demoted,
		'removed'      => array(),
		'missing'      => $missing,
		'message'      => __( 'Migration team promotion and subscriber demotion were applied.', 'migration-freeze-webspark' ),
	);
}

/**
 * Remove all users from the current site.
 *
 * @return array<string, mixed>
 */
function mfw_apply_decommissioned_state() {
	$blog_id      = get_current_blog_id();
	$current_user  = get_current_user_id();
	$removed      = array();

	if ( ! mfw_is_multisite_context() || ! function_exists( 'remove_user_from_blog' ) ) {
		return array(
			'state'        => MFW_STATE_DECOMMISSIONED,
			'changes_made' => false,
			'created'      => array(),
			'promoted'     => array(),
			'demoted'      => array(),
			'removed'      => array(),
			'missing'      => array(),
			'message'      => __( 'Decommissioning is skipped on single-site installs. This action is only available on multisite.', 'migration-freeze-webspark' ),
		);
	}

	foreach ( mfw_get_site_users() as $user ) {
		if ( (int) $user->ID === (int) $current_user ) {
			continue;
		}

		if ( remove_user_from_blog( $user->ID, $blog_id ) ) {
			$removed[] = mfw_normalize_username( $user->user_login );
		}
	}

	return array(
		'state'        => MFW_STATE_DECOMMISSIONED,
		'changes_made' => true,
		'created'      => array(),
		'promoted'     => array(),
		'demoted'      => array(),
		'removed'      => $removed,
		'missing'      => array(),
		'message'      => __( 'All site users except the current administrator were removed from the site.', 'migration-freeze-webspark' ),
	);
}
