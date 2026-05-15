<?php
/**
 * Post status UI helpers.
 *
 * @package migration_freeze_webspark
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function mfw_render_retained_status_ui() {
	if ( ! is_admin() ) {
		return;
	}

	$current_screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $current_screen || ! in_array( $current_screen->base, array( 'post', 'edit' ), true ) ) {
		return;
	}
	?>
	<script>
	( function() {
		const statusValue = 'draft-retain';
		const statusLabel = 'Retained';

		function ensureRetainedOption( select ) {
			if ( ! select || select.tagName !== 'SELECT' ) {
				return;
			}

			const existing = Array.from( select.options ).find( ( option ) => option.value === statusValue );
			if ( existing ) {
				existing.text = statusLabel;
				return;
			}

			const option = document.createElement( 'option' );
			option.value = statusValue;
			option.textContent = statusLabel;
			select.appendChild( option );
		}

		function hydrateStatusSelects() {
			[ 'select[name="post_status"]', 'select[name="_status"]', '#post_status' ]
				.forEach( ( selector ) => {
					document.querySelectorAll( selector ).forEach( ensureRetainedOption );
				} );
		}

		document.addEventListener( 'DOMContentLoaded', hydrateStatusSelects );
		window.addEventListener( 'load', hydrateStatusSelects );
		new MutationObserver( hydrateStatusSelects ).observe( document.body, { childList: true, subtree: true } );
	}() );
	</script>
	<?php
}
add_action( 'admin_footer', 'mfw_render_retained_status_ui' );
