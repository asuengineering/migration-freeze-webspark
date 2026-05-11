<?php
/**
 * Admin and front-end notices.
 *
 * @package migration_freeze_webspark
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Determine whether the migration notice should be rendered.
 *
 * @return bool
 */
function mfw_should_render_state_notice() {
	return is_user_logged_in();
}

/**
 * Render the migration state notice.
 *
 * @param string $context Optional context: admin or frontend.
 *
 * @return void
 */
function mfw_render_state_notice( $context = 'admin' ) {
	if ( ! mfw_should_render_state_notice() ) {
		return;
	}

	$state_config = mfw_get_current_state_config();
	$state        = mfw_get_site_state();
	$class        = 'notice-info';
	$style        = 'margin: 1rem 0; padding: 1rem; border-left: 4px solid #2271b1; background: #fff;';

	switch ( $state ) {
		case MFW_STATE_ACTIVE:
			$class = 'notice-warning';
			$style = 'margin: 1rem 0; padding: 1rem; border-left: 4px solid #dba617; background: #fff8e5;';
			break;

		case MFW_STATE_COMPLETE:
		case MFW_STATE_UAT_COMPLETE:
			$class = 'notice-success';
			$style = 'margin: 1rem 0; padding: 1rem; border-left: 4px solid #00a32a; background: #edfaef;';
			break;

		case MFW_STATE_DECOMMISSIONED:
			$class = 'notice-error';
			$style = 'margin: 1rem 0; padding: 1rem; border-left: 4px solid #d63638; background: #fcf0f1;';
			break;
	}

	$label   = isset( $state_config['label'] ) ? $state_config['label'] : ucfirst( $state );
	$message = isset( $state_config['message'] ) ? $state_config['message'] : '';
	$link    = isset( $state_config['action'] ) ? $state_config['action'] : '';
	?>
	<div class="notice <?php echo esc_attr( $class ); ?> mfw-state-notice mfw-state-notice-<?php echo esc_attr( sanitize_html_class( $state ) ); ?>" style="<?php echo esc_attr( $style ); ?>">
		<p><strong><?php echo esc_html( $label ); ?></strong></p>
		<p><?php echo esc_html( $message ); ?></p>
		<?php if ( '' !== $link ) : ?>
			<p><a href="<?php echo esc_url( $link ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $link ); ?></a></p>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * Display the notice in wp-admin.
 */
function mfw_render_admin_notice() {
	mfw_render_state_notice( 'admin' );
}
add_action( 'admin_notices', 'mfw_render_admin_notice' );

/**
 * Display the notice on the front end as a logged-in user.
 */
function mfw_render_frontend_state_notice() {
	mfw_render_state_notice( 'frontend' );
}
add_action( 'wp_body_open', 'mfw_render_frontend_state_notice' );
add_action( 'wp_footer', 'mfw_render_frontend_state_notice' );
