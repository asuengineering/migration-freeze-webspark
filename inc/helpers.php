<?php
/**
 * Helper functions.
 *
 * @package migration_freeze_webspark
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Return the approved migration assistant usernames.
 *
 * Usernames are derived from the prefix of each ASU email address.
 *
 * @return array<int, string>
 */
function mfw_get_migration_assistants() {
	return array(
		'cnicho74',
		'mpate362',
		'rjoshi10',
		'msompur',
		'hvyas123',
		'kosalaiv',
		'lsolom10',
		'adebna10',
		'ngadge',
	);
}

/**
 * Return the approved migration assistant list with email addresses.
 *
 * @return array<int, array{username:string,email:string}>
 */
function mfw_get_migration_assistant_directory() {
	$assistants = array();

	foreach ( mfw_get_migration_assistants() as $username ) {
		$assistants[] = array(
			'username' => $username,
			'email'    => $username . '@asu.edu',
		);
	}

	return $assistants;
}

/**
 * Return a formatted migration assistant display list.
 *
 * @return string
 */
function mfw_get_migration_assistant_summary() {
	$lines = array();

	foreach ( mfw_get_migration_assistant_directory() as $assistant ) {
		$lines[] = $assistant['username'] . ' (' . $assistant['email'] . ')';
	}

	return implode( ', ', $lines );
}

/**
 * Normalize a username to the migration assistant format.
 *
 * @param string $username Username or email.
 *
 * @return string
 */
function mfw_normalize_username( $username ) {
	$username = sanitize_user( wp_unslash( $username ), true );

	if ( false !== strpos( $username, '@' ) ) {
		$username = sanitize_user( current( explode( '@', $username ) ), true );
	}

	return $username;
}
