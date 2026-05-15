<?php
/**
 * Custom post statuses.
 *
 * @package migration_freeze_webspark
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register the retained draft status.
 */
function mfw_register_retained_draft_status() {
	register_post_status(
		'draft-retain',
		array(
			'label'                     => _x( 'Draft Retain', 'post status', 'migration-freeze-webspark' ),
			'public'                    => false,
			'internal'                  => false,
			'protected'                 => false,
			'private'                   => false,
			'publicly_queryable'        => false,
			'show_in_admin_all_list'    => true,
			'show_in_admin_status_list' => true,
			'exclude_from_search'       => true,
			'label_count'               => _n_noop(
				'Draft Retain <span class="count">(%s)</span>',
				'Draft Retain <span class="count">(%s)</span>',
				'migration-freeze-webspark'
			),
		)
	);
}
add_action( 'init', 'mfw_register_retained_draft_status' );
