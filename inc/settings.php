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
 * Render the settings page.
 */
function mfw_render_settings_page() {
	$state        = mfw_get_site_state();
	$states       = mfw_get_site_states();
	$assistants   = mfw_get_migration_assistant_directory();
	$current_name = isset( $states[ $state ]['label'] ) ? $states[ $state ]['label'] : '';
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Pitchfork Migration', 'migration-freeze-webspark' ); ?></h1>
		<p><?php esc_html_e( 'Use this page to review the current state of the site and the approved migration team list.', 'migration-freeze-webspark' ); ?></p>

		<h2><?php esc_html_e( 'Current State', 'migration-freeze-webspark' ); ?></h2>
		<p><strong><?php echo esc_html( $current_name ); ?></strong></p>

		<table class="widefat striped" style="max-width: 900px;">
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
