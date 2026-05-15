<?php
/**
 * Audit summary UI helpers.
 *
 * @package migration_freeze_webspark
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function mfw_render_audit_summary_ui_fixes() {
	if ( ! is_admin() || empty( $_GET['page'] ) || 'mfw-migration-audit-trail' !== sanitize_key( wp_unslash( $_GET['page'] ) ) ) {
		return;
	}
	?>
	<script>
	( function() {
		const noteId = 'mfw-audit-summary-refresh-note';
		const summaryTitle = 'Content views';
		const metricLabel = 'Published / Private / Future / Pending / Retained';
		const refreshMessage = 'The displayed summary comes from the latest export record stored in history, not a separate live query outside the exporter flow. Generate another report to refresh the numbers.';

		const table = Array.from( document.querySelectorAll( 'table.widefat.striped' ) ).find( (candidate) => {
			const header = candidate.querySelector( 'thead th' );
			return header && header.textContent.trim() === summaryTitle;
		} );

		if ( ! table || ! table.tBodies.length ) {
			return;
		}

		const tbody = table.tBodies[0];
		const rows = Array.from( tbody.rows );
		const contentTotalRow = rows.find( (row) => row.cells[0] && row.cells[0].textContent.trim() === 'Content total' );
		const draftTrashRow = rows.find( (row) => row.cells[0] && row.cells[0].textContent.trim() === 'Drafts + Trash' );
		const hasMetricRow = rows.some( (row) => row.cells[0] && row.cells[0].textContent.trim() === metricLabel );

		if ( contentTotalRow && ! hasMetricRow ) {
			const insertRow = contentTotalRow.cloneNode( true );
			insertRow.style.fontWeight = '';
			insertRow.style.color = '';
			insertRow.cells[0].textContent = metricLabel;
			contentTotalRow.parentNode.insertBefore( insertRow, contentTotalRow );
		}

		if ( draftTrashRow ) {
			draftTrashRow.style.color = '#b32d2e';
		}

		if ( ! document.getElementById( noteId ) ) {
			const note = document.createElement( 'p' );
			note.id = noteId;
			note.className = 'description';
			note.style.maxWidth = '900px';
			note.style.marginTop = '0.75rem';
			note.textContent = refreshMessage;
			table.insertAdjacentElement( 'afterend', note );
		}
	}() );
	</script>
	<?php
}
add_action( 'admin_footer', 'mfw_render_audit_summary_ui_fixes' );
