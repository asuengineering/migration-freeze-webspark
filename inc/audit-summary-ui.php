<?php
/**
 * Audit summary UI helpers.
 *
 * @package migration_freeze_webspark
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function mfw_get_latest_gravity_forms_scope_count( $record = null ) {
	if ( null === $record ) {
		$history = mfw_get_audit_history();
		$record  = ! empty( $history ) ? $history[0] : array();
	}

	if ( empty( $record['files'] ) || ! is_array( $record['files'] ) ) {
		return 0;
	}

	$gravity_file = '';
	foreach ( $record['files'] as $file ) {
		if ( ! empty( $file['type'] ) && 'gravity_forms' === $file['type'] && ! empty( $file['path'] ) ) {
			$gravity_file = $file['path'];
			break;
		}
	}

	if ( '' === $gravity_file || ! is_readable( $gravity_file ) ) {
		return 0;
	}

	$handle = fopen( $gravity_file, 'r' );
	if ( false === $handle ) {
		return 0;
	}

	$header = fgetcsv( $handle );
	if ( false === $header || ! is_array( $header ) ) {
		fclose( $handle );
		return 0;
	}

	$record_type_index = array_search( 'record_type', $header, true );
	if ( false === $record_type_index ) {
		fclose( $handle );
		return 0;
	}

	$count = 0;
	$allowed = array( 'gravity_form', 'gravity_notification', 'gravity_confirmation' );

	while ( false !== ( $row = fgetcsv( $handle ) ) ) {
		if ( ! isset( $row[ $record_type_index ] ) ) {
			continue;
		}
		if ( in_array( $row[ $record_type_index ], $allowed, true ) ) {
			$count++;
		}
	}

	fclose( $handle );
	return $count;
}

function mfw_render_audit_summary_gravity_forms_row() {
	if ( ! is_admin() || empty( $_GET['page'] ) || 'mfw-migration-audit-trail' !== sanitize_key( wp_unslash( $_GET['page'] ) ) ) {
		return;
	}

	$count = mfw_get_latest_gravity_forms_scope_count();
	?>
	<script>
	( function() {
		const label = 'Gravity Forms (forms, notifications, confirmations)';
		const value = <?php echo (int) $count; ?>;
		const table = Array.from( document.querySelectorAll( 'table.widefat.striped' ) ).find( (candidate) => {
			const header = candidate.querySelector( 'thead th' );
			return header && header.textContent.trim() === 'Content views';
		} );

		if ( ! table || ! table.tBodies.length ) {
			return;
		}

		const tbody = table.tBodies[0];
		const totalRow = Array.from( tbody.rows ).find( (row) => row.cells[0] && row.cells[0].textContent.trim() === 'Content views' );
		if ( ! totalRow ) {
			return;
		}

		if ( Array.from( tbody.rows ).some( (row) => row.cells[0] && row.cells[0].textContent.trim() === label ) ) {
			return;
		}

		const row = document.createElement( 'tr' );
		row.innerHTML = '<td>' + label + '</td><td>' + value + '</td>';
		tbody.insertBefore( row, totalRow );
	}() );
	</script>
	<?php
}
add_action( 'admin_footer', 'mfw_render_audit_summary_gravity_forms_row' );
