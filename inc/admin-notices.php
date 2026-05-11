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

	$state  = mfw_get_site_state();
	$states = mfw_get_site_states();

	if ( empty( $states[ $state ] ) ) {
		return;
	}

	$message = $states[ $state ]['message'];
	$label   = $states[ $state ]['label'];

	$class = 'notice-info';

	switch ( $state ) {
		case MFW_STATE_FROZEN:
			$class = 'notice-warning';
			break;

		case MFW_STATE_COMPLETE:
			$class = 'notice-success';
			break;
	}

	?>
	<div class="notice <?php echo esc_attr( $class ); ?>">
		<p>
			<strong><?php echo esc_html( $label ); ?>:</strong>
			<?php echo esc_html( $message ); ?>
		</p>
	</div>
	<?php
}
add_action( 'admin_notices', 'mfw_render_admin_notice' );
