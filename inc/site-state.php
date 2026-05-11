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
			'label'   => 'Migration Pending',
			'message' => 'ASU Engineering is migrating websites from WordPress to the university-supported Drupal platform, Webspark. Site owners and site operators should have received email communication regarding migration timelines, training opportunities, and support office hours.',
			'action'  => 'Further details can be found at https://comm.engineering.asu.edu/web-services/webspark/ .',
		),
		MFW_STATE_ACTIVE => array(
			'label'   => 'Migration Active',
			'message' => 'This website is currently being migrated to ASU’s university-supported Webspark platform. During the migration process, editing access to this website has been temporarily limited while content and functionality are reviewed and transferred.',
			'action'  => 'Further details can be found at https://comm.engineering.asu.edu/web-services/webspark/ .',
		),
		MFW_STATE_COMPLETE => array(
			'label'   => 'Migration Complete',
			'message' => 'This website has been migrated to ASU’s university-supported Webspark platform. The previous WordPress site is currently being retained temporarily while final review and transition activities are completed.',
			'action'  => 'Further details can be found at https://comm.engineering.asu.edu/web-services/webspark/ .',
		),
		MFW_STATE_UAT_COMPLETE => array(
			'label'   => 'Migration Complete',
			'message' => 'This website has been migrated to ASU’s university-supported Webspark platform, and the site domain has already transitioned to the new environment. If you still require access to the migrated website or need assistance locating project resources, please submit a project request through the ASU Engineering communications team.',
			'action'  => 'Further details can be found at https://comm.engineering.asu.edu/web-services/webspark/ .',
		),
		MFW_STATE_DECOMMISSIONED => array(
			'label'   => 'Site Scheduled for Decommission',
			'message' => 'This legacy WordPress website is scheduled for permanent decommission and removal. If this site needs to be retained for any reason, please contact the ASU Engineering communications team as soon as possible.',
			'action'  => 'Further details can be found at https://comm.engineering.asu.edu/web-services/webspark/ .',
		),
	);
}

function mfw_get_site_state() {
	$state = get_option( MFW_OPTION_SITE_STATE, MFW_STATE_PENDING );

	if ( ! array_key_exists( $state, mfw_get_site_states() ) ) {
		return MFW_STATE_PENDING;
	}

	return $state;
}

function mfw_set_site_state( $state ) {
	if ( ! array_key_exists( $state, mfw_get_site_states() ) ) {
		return false;
	}

	return update_option( MFW_OPTION_SITE_STATE, $state );
}

function mfw_get_current_state_config() {
	$states = mfw_get_site_states();
	$state  = mfw_get_site_state();

	return $states[ $state ];
}
