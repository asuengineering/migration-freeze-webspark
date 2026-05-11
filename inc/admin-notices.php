<?php
/**
 * Admin notices.
 *
 * @package migration_freeze_webspark
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Display admin notice reflecting current migration state.
 */
function mfw_render_admin_notice() {
	if ( ! current_user_can( 'edit_posts' ) ) {
		return;
	}

	$state_config = mfw_get_current_state_config();
	$state        = mfw_get_site_state();
	$class        = 'notice-info';

	switch ( $state ) {
		case MFW_STATE_ACTIVE:
			$class = 'notice-warning';
			break;

		case MFW_STATE_COMPLETE:
		case MFW_STATE_UAT_COMPLETE:
			$class = 'notice-success';
			break;

		case MFW_STATE_DECOMMISSIONED:
			$class = 'notice-error';
			break;
	}

	?>
	<div class="notice <?php echo esc_attr( $class ); ?>">
		<p><strong><?php echo esc_html( $state_config['label'] ); ?>:</strong> <?php echo esc_html( $state_config['message'] ); ?></p>
		<p><?php echo esc_html( $state_config['action'] ); ?></p>
	</div>
	<?php
}
add_action( 'admin_notices', 'mfw_render_admin_notice' );
