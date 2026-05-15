<?php
/**
 * Admin notices.
 *
 * @package migration_freeze_webspark
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function mfw_should_render_state_notice() {
	return is_user_logged_in();
}

function mfw_noticeify_links( $text ) {
	return preg_replace(
		'/(https:\/\/[^\s]+)/',
		'<a href="$1" target="_blank" rel="noopener noreferrer">$1</a>',
		$text
	);
}

function mfw_render_state_notice( $context = 'admin' ) {
	if ( ! mfw_should_render_state_notice() || 'admin' !== $context ) {
		return;
	}

	$state = mfw_get_site_state();

	if ( MFW_STATE_NORMAL === $state ) {
		return;
	}

	$state_config = mfw_get_current_state_config();
	$class        = 'notice-info';
	$style        = 'margin: 1rem 0; padding: 1rem 1.25rem; border-left: 4px solid #2271b1; background: #fff; max-width: 980px; line-height: 1.65;';

	switch ( $state ) {
		case MFW_STATE_ACTIVE:
			$class = 'notice-warning';
			$style = 'margin: 1rem 0; padding: 1rem 1.25rem; border-left: 4px solid #dba617; background: #fff8e5; max-width: 980px; line-height: 1.65;';
			break;

		case MFW_STATE_COMPLETE:
		case MFW_STATE_UAT_COMPLETE:
			$class = 'notice-success';
			$style = 'margin: 1rem 0; padding: 1rem 1.25rem; border-left: 4px solid #00a32a; background: #edfaef; max-width: 980px; line-height: 1.65;';
			break;

		case MFW_STATE_DECOMMISSIONED:
			$class = 'notice-error';
			$style = 'margin: 1rem 0; padding: 1rem 1.25rem; border-left: 4px solid #d63638; background: #fcf0f1; max-width: 980px; line-height: 1.65;';
			break;
	}

	$label   = isset( $state_config['label'] ) ? $state_config['label'] : ucfirst( $state );
	$message = isset( $state_config['message'] ) ? $state_config['message'] : '';
	$cta     = isset( $state_config['notice_cta'] ) ? $state_config['notice_cta'] : '';
	?>
	<div class="notice <?php echo esc_attr( $class ); ?> mfw-state-notice mfw-state-notice-<?php echo esc_attr( sanitize_html_class( $state ) ); ?>" style="<?php echo esc_attr( $style ); ?>">
		<p style="margin-top:0; margin-bottom:0.75rem;"><strong><?php echo esc_html( $label ); ?></strong></p>
		<p style="margin-top:0; margin-bottom:0.75rem;"><?php echo esc_html( $message ); ?></p>
		<?php if ( '' !== $cta ) : ?>
			<p style="margin-top:0; margin-bottom:0;"><?php echo wp_kses_post( mfw_noticeify_links( esc_html( $cta ) ) ); ?></p>
		<?php endif; ?>
	</div>
	<?php
}

function mfw_render_admin_notice() {
	mfw_render_state_notice( 'admin' );
}
add_action( 'admin_notices', 'mfw_render_admin_notice' );
