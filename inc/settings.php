<?php
/**
 * Settings screen.
 *
 * @package migration_freeze_webspark
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register the Pitchfork Migration settings page.
 */
function mfw_register_settings_page() {
	add_options_page(
		__( 'Pitchfork Migration', 'migration-freeze-webspark' ),
		__( 'Pitchfork Migration', 'migration-freeze-webspark' ),
		'manage_options',
		'mfw-pitchfork-migration',
		'mfw_render_settings_page'
	);
}
add_action( 'admin_menu', 'mfw_register_settings_page' );

/**
 * Register the settings form handler.
 */
function mfw_register_state_update_handler() {
	add_action( 'admin_post_mfw_update_site_state', 'mfw_handle_state_update' );
}
add_action( 'admin_init', 'mfw_register_state_update_handler' );

/**
 * Build a transient key for storing the latest action report.
 *
 * @return string
 */
function mfw_get_state_report_key() {
	return 'mfw_state_report_' . get_current_user_id();
}

/**
 * Handle a state update submission.
 */
function mfw_handle_state_update() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have permission to update this site.', 'migration-freeze-webspark' ) );
	}

	check_admin_referer( 'mfw_update_site_state', 'mfw_state_nonce' );

	$new_state = isset( $_POST['mfw_site_state'] ) ? sanitize_key( wp_unslash( $_POST['mfw_site_state'] ) ) : MFW_STATE_PENDING;

	if ( ! array_key_exists( $new_state, mfw_get_site_states() ) ) {
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => 'mfw-pitchfork-migration',
					'mfw_err' => 'invalid_state',
				),
				admin_url( 'options-general.php' )
			)
		);
		exit;
	}

	update_option( MFW_OPTION_SITE_STATE, $new_state );
	$report = mfw_apply_state_changes( $new_state );
	set_transient( mfw_get_state_report_key(), $report, 10 * MINUTE_IN_SECONDS );

	wp_safe_redirect(
		add_query_arg(
			array(
				'page'        => 'mfw-pitchfork-migration',
				'mfw_updated' => 1,
			),
			admin_url( 'options-general.php' )
		)
	);
	exit;
}

/**
 * Render the settings page.
 */
function mfw_render_settings_page() {
	$state        = mfw_get_site_state();
	$states       = mfw_get_site_states();
	$assistants   = mfw_get_migration_assistant_directory();
	$current_name = isset( $states[ $state ]['label'] ) ? $states[ $state ]['label'] : '';
	$report       = get_transient( mfw_get_state_report_key() );

	if ( $report ) {
		delete_transient( mfw_get_state_report_key() );
	}
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Pitchfork Migration', 'migration-freeze-webspark' ); ?></h1>
		<p><?php esc_html_e( 'Use this page to review the current state of the site and the approved migration team list.', 'migration-freeze-webspark' ); ?></p>

		<?php if ( isset( $_GET['mfw_updated'] ) ) : ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Site state updated.', 'migration-freeze-webspark' ); ?></p></div>
		<?php endif; ?>

		<?php if ( ! empty( $report ) ) : ?>
			<div class="notice notice-info is-dismissible">
				<p><strong><?php echo esc_html( $report['message'] ); ?></strong></p>
				<?php if ( ! empty( $report['promoted'] ) ) : ?>
					<p><?php echo esc_html( sprintf( __( 'Promoted: %s', 'migration-freeze-webspark' ), implode( ', ', $report['promoted'] ) ) ); ?></p>
				<?php endif; ?>
				<?php if ( ! empty( $report['demoted'] ) ) : ?>
					<p><?php echo esc_html( sprintf( __( 'Demoted: %s', 'migration-freeze-webspark' ), implode( ', ', $report['demoted'] ) ) ); ?></p>
				<?php endif; ?>
				<?php if ( ! empty( $report['removed'] ) ) : ?>
					<p><?php echo esc_html( sprintf( __( 'Removed: %s', 'migration-freeze-webspark' ), implode( ', ', $report['removed'] ) ) ); ?></p>
				<?php endif; ?>
				<?php if ( ! empty( $report['missing'] ) ) : ?>
					<p><?php echo esc_html( sprintf( __( 'Missing users: %s', 'migration-freeze-webspark' ), implode( ', ', $report['missing'] ) ) ); ?></p>
				<?php endif; ?>
			</div>
		<?php endif; ?>

		<h2><?php esc_html_e( 'Current State', 'migration-freeze-webspark' ); ?></h2>
		<p><strong><?php echo esc_html( $current_name ); ?></strong></p>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'mfw_update_site_state', 'mfw_state_nonce' ); ?>
			<input type="hidden" name="action" value="mfw_update_site_state" />
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="mfw_site_state"><?php esc_html_e( 'Site Status', 'migration-freeze-webspark' ); ?></label></th>
					<td>
						<select name="mfw_site_state" id="mfw_site_state">
							<?php foreach ( $states as $state_key => $state_config ) : ?>
								<option value="<?php echo esc_attr( $state_key ); ?>" <?php selected( $state, $state_key ); ?>><?php echo esc_html( $state_config['label'] ); ?></option>
							<?php endforeach; ?>
						</select>
						<p class="description"><?php esc_html_e( 'Saving a new state will apply the associated user-management behavior immediately.', 'migration-freeze-webspark' ); ?></p>
					</td>
					</tr>
				</table>
			<?php submit_button( __( 'Update Site State', 'migration-freeze-webspark' ) ); ?>
		</form>

		<table class="widefat striped" style="max-width: 900px; margin-top: 2rem;">
			<thead>
				<tr>
					<th><?php esc_html_e( 'State', 'migration-freeze-webspark' ); ?></th>
					<th><?php esc_html_e( 'Description', 'migration-freeze-webspark' ); ?></th>
					<th><?php esc_html_e( 'Action', 'migration-freeze-webspark' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $states as $state_key => $state_config ) : ?>
					<tr <?php echo $state === $state_key ? 'class="active-row"' : ''; ?>>
						<td><strong><?php echo esc_html( $state_config['label'] ); ?></strong></td>
						<td><?php echo esc_html( $state_config['message'] ); ?></td>
						<td><?php echo esc_html( $state_config['action'] ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<h2 style="margin-top: 2rem;"><?php esc_html_e( 'Approved Migration Team', 'migration-freeze-webspark' ); ?></h2>
		<p><?php echo esc_html( mfw_get_migration_assistant_summary() ); ?></p>

		<table class="widefat striped" style="max-width: 600px;">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Username', 'migration-freeze-webspark' ); ?></th>
					<th><?php esc_html_e( 'Email', 'migration-freeze-webspark' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $assistants as $assistant ) : ?>
					<tr>
						<td><?php echo esc_html( $assistant['username'] ); ?></td>
						<td><?php echo esc_html( $assistant['email'] ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<?php
}
