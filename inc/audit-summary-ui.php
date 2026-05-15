<?php
/**
 * Audit summary UI helpers.
 *
 * @package migration_freeze_webspark
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function mfw_fix_audit_history_summary_records( $history ) {
	if ( ! is_array( $history ) ) {
		return $history;
	}

	foreach ( $history as &$record ) {
		if ( ! is_array( $record ) ) {
			continue;
		}

		$row_counts = isset( $record['row_counts'] ) && is_array( $record['row_counts'] ) ? $record['row_counts'] : array();
		$content_rows = isset( $row_counts['content'] ) ? (int) $row_counts['content'] : 0;
		$summary = isset( $record['summary'] ) && is_array( $record['summary'] ) ? $record['summary'] : array();
		$draft_trash = isset( $summary['draft_trash_content'] ) ? (int) $summary['draft_trash_content'] : 0;
		$published_like = max( 0, $content_rows - $draft_trash );

		$summary['content_total'] = $content_rows;
		$summary['published_like_content'] = $published_like;
		$record['summary'] = $summary;
	}
	unset( $record );

	return $history;
}
add_filter( 'option_mfw_audit_export_history', 'mfw_fix_audit_history_summary_records' );

function mfw_render_audit_summary_ui_fixes() {
	if ( ! is_admin() || empty( $_GET['page'] ) || 'mfw-migration-audit-trail' !== sanitize_key( wp_unslash( $_GET['page'] ) ) ) {
		return;
	}

	$history = mfw_get_audit_history();
	$latest  = ! empty( $history ) ? $history[0] : array();
	$summary  = mfw_get_audit_record_summary( $latest );
	$summary_json = wp_json_encode( $summary, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	?>
	<script>
	( function() {
		const summary = <?php echo $summary_json ? $summary_json : '{}'; ?>;
		const noteId = 'mfw-audit-summary-refresh-note';
		const tableTitle = 'Content views';
		const labels = {
			contentTotal: 'Content total',
			published: 'Published / Private / Future / Pending / Retained',
			draftTrash: 'Drafts + Trash',
			termPages: 'Taxonomy term archive pages',
			taxonomyLanding: 'Taxonomy landing pages',
			archivePages: 'General archive / index pages'
		};
		const refreshMessage = 'The displayed summary comes from the latest export record stored in history, not a separate live query outside the exporter flow. Generate another report to refresh the numbers.';

		const table = Array.from( document.querySelectorAll( 'table.widefat.striped' ) ).find( (candidate) => {
			const header = candidate.querySelector( 'thead th' );
			return header && header.textContent.trim() === tableTitle;
		} );

		if ( ! table || ! table.tBodies.length ) {
			return;
		}

		const tbody = table.tBodies[ 0 ];
		const rows = Array.from( tbody.rows );
		const findRow = ( label ) => rows.find( (row) => row.cells[0] && row.cells[0].textContent.trim() === label );
		const makeRow = ( label, value, options = {} ) => {
			const row = document.createElement( 'tr' );
			if ( options.bold ) {
				row.style.fontWeight = '700';
			}
			if ( options.color ) {
				row.style.color = options.color;
			}
			row.innerHTML = `<td>${label}</td><td>${value}</td>`;
			return row;
		};

		const contentTotalRow = findRow( labels.contentTotal ) || makeRow( labels.contentTotal, Number( summary.content_total || 0 ), { bold: true } );
		const publishedRow = findRow( labels.published ) || makeRow( labels.published, Number( summary.published_like_content || 0 ) );
		const draftTrashRow = findRow( labels.draftTrash ) || makeRow( labels.draftTrash, Number( summary.draft_trash_content || 0 ), { color: '#b32d2e' } );
		const archiveRows = rows.filter( (row) => {
			const label = row.cells[0] ? row.cells[0].textContent.trim() : '';
			return ! [ labels.contentTotal, labels.published, labels.draftTrash ].includes( label );
		} );

		contentTotalRow.style.fontWeight = '700';
		publishedRow.style.fontWeight = '';
		draftTrashRow.style.color = '#b32d2e';

		tbody.innerHTML = '';
		tbody.appendChild( contentTotalRow );
		tbody.appendChild( publishedRow );
		archiveRows.forEach( (row) => tbody.appendChild( row ) );
		tbody.appendChild( draftTrashRow );

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
