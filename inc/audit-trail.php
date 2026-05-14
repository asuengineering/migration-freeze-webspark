<?php
/**
 * Migration audit trail export and history.
 *
 * @package migration_freeze_webspark
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'MFW_OPTION_AUDIT_HISTORY' ) ) {
	define( 'MFW_OPTION_AUDIT_HISTORY', 'mfw_audit_export_history' );
}

if ( ! defined( 'MFW_AUDIT_HISTORY_LIMIT' ) ) {
	define( 'MFW_AUDIT_HISTORY_LIMIT', 10 );
}

/**
 * Register the audit trail page.
 */
function mfw_register_audit_trail_page() {
	add_options_page(
		__( 'Migration Audit Trail', 'migration-freeze-webspark' ),
		__( 'Migration Audit Trail', 'migration-freeze-webspark' ),
		'manage_options',
		'mfw-migration-audit-trail',
		'mfw_render_audit_trail_page'
	);
}
add_action( 'admin_menu', 'mfw_register_audit_trail_page' );

/**
 * Register the export handler.
 */
function mfw_register_audit_export_handler() {
	add_action( 'admin_post_mfw_generate_audit_export', 'mfw_handle_audit_export' );
}
add_action( 'admin_init', 'mfw_register_audit_export_handler' );

/**
 * Return the audit history option.
 *
 * @return array<int, array<string, mixed>>
 */
function mfw_get_audit_history() {
	$history = get_option( MFW_OPTION_AUDIT_HISTORY, array() );

	return is_array( $history ) ? $history : array();
}

/**
 * Persist the rolling audit history.
 *
 * @param array<int, array<string, mixed>> $history History records.
 *
 * @return bool
 */
function mfw_save_audit_history( $history ) {
	$history = array_slice( array_values( $history ), 0, MFW_AUDIT_HISTORY_LIMIT );

	return update_option( MFW_OPTION_AUDIT_HISTORY, $history, false );
}

/**
 * Add a new record to the rolling audit history.
 *
 * @param array<string, mixed> $record Record payload.
 *
 * @return void
 */
function mfw_add_audit_history_record( $record ) {
	$history = mfw_get_audit_history();
	array_unshift( $history, $record );
	mfw_save_audit_history( $history );
}

/**
 * Build the transient key for notices.
 *
 * @return string
 */
function mfw_get_audit_notice_key() {
	return 'mfw_audit_export_notice_' . get_current_user_id();
}

/**
 * Store a temporary audit export notice.
 *
 * @param array<string, mixed> $notice Notice payload.
 *
 * @return void
 */
function mfw_set_audit_notice( $notice ) {
	set_transient( mfw_get_audit_notice_key(), $notice, 10 * MINUTE_IN_SECONDS );
}

/**
 * Retrieve the temporary audit export notice.
 *
 * @return array<string, mixed>|false
 */
function mfw_get_audit_notice() {
	$notice = get_transient( mfw_get_audit_notice_key() );

	if ( false !== $notice ) {
		delete_transient( mfw_get_audit_notice_key() );
	}

	return $notice;
}

/**
 * Determine whether a plugin basename is active.
 *
 * @param string $basename Plugin basename.
 *
 * @return bool
 */
function mfw_audit_is_plugin_active( $basename ) {
	$active_plugins = (array) get_option( 'active_plugins', array() );
	$sitewide       = (array) get_site_option( 'active_sitewide_plugins', array() );

	if ( in_array( $basename, $active_plugins, true ) ) {
		return true;
	}

	if ( isset( $sitewide[ $basename ] ) ) {
		return true;
	}

	if ( function_exists( 'is_plugin_active' ) ) {
		return is_plugin_active( $basename );
	}

	return false;
}

/**
 * Return detected plugins that affect the export.
 *
 * @return array<string, array<string, string>>
 */
function mfw_get_detected_audit_plugins() {
	$plugins = array();

	if ( mfw_audit_is_plugin_active( 'gravityforms/gravityforms.php' ) || class_exists( 'GFAPI' ) ) {
		$plugins['gravity_forms'] = array(
			'label'   => 'Gravity Forms',
			'purpose' => 'forms',
			'file'    => 'gravityforms/gravityforms.php',
		);
	}

	if ( mfw_audit_is_plugin_active( 'wordpress-seo/wp-seo.php' ) || defined( 'WPSEO_VERSION' ) ) {
		$plugins['yoast_seo'] = array(
			'label'   => 'Yoast SEO',
			'purpose' => 'seo',
			'file'    => 'wordpress-seo/wp-seo.php',
		);
	}

	if ( mfw_audit_is_plugin_active( 'seo-by-rank-math/rank-math.php' ) || defined( 'RANK_MATH_VERSION' ) ) {
		$plugins['rank_math'] = array(
			'label'   => 'Rank Math',
			'purpose' => 'seo',
			'file'    => 'seo-by-rank-math/rank-math.php',
		);
	}

	if ( mfw_audit_is_plugin_active( 'redirection/redirection.php' ) || class_exists( 'Red_Item' ) ) {
		$plugins['redirection'] = array(
			'label'   => 'Redirection',
			'purpose' => 'redirects',
			'file'    => 'redirection/redirection.php',
		);
	}

	return $plugins;
}

/**
 * Return the audit CSV columns.
 *
 * @return array<int, string>
 */
function mfw_get_audit_csv_columns() {
	return array(
		'environment_name',
		'site_id',
		'record_type',
		'object_type',
		'object_id',
		'status',
		'title',
		'url',
		'slug',
		'parent_id',
		'taxonomy',
		'term_id',
		'related_object_id',
		'related_object_type',
		'media_id',
		'filename',
		'mime_type',
		'menu_name',
		'menu_slug',
		'menu_location',
		'role',
		'plugin',
		'created_at',
		'modified_at',
		'details_json',
	);
}

/**
 * Build a CSV row with defaults.
 *
 * @param array<string, mixed> $values Row values.
 *
 * @return array<string, string>
 */
function mfw_audit_build_row( $values ) {
	$row = array_fill_keys( mfw_get_audit_csv_columns(), '' );

	foreach ( $values as $key => $value ) {
		if ( ! array_key_exists( $key, $row ) ) {
			continue;
		}

		if ( is_array( $value ) || is_object( $value ) ) {
			$value = mfw_audit_json_encode( $value );
		}

		$row[ $key ] = (string) $value;
	}

	return $row;
}

/**
 * Encode data for the details_json column.
 *
 * @param mixed $data Data to encode.
 *
 * @return string
 */
function mfw_audit_json_encode( $data ) {
	$json = wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );

	return false === $json ? '{}' : $json;
}

/**
 * Determine if a post type is system content.
 *
 * @param string $post_type Post type name.
 *
 * @return bool
 */
function mfw_audit_is_system_post_type( $post_type ) {
	$excluded = array(
		'attachment',
		'revision',
		'nav_menu_item',
		'custom_css',
		'customize_changeset',
		'oembed_cache',
		'user_request',
		'wp_block',
		'wp_navigation',
		'wp_template',
		'wp_template_part',
		'wp_font_face',
		'wp_font_family',
		'wp_global_styles',
	);

	if ( in_array( $post_type, $excluded, true ) ) {
		return true;
	}

	if ( 0 === strpos( $post_type, 'acf-' ) ) {
		return true;
	}

	return false;
}

/**
 * Determine if a taxonomy is system-only content.
 *
 * @param string $taxonomy Taxonomy name.
 *
 * @return bool
 */
function mfw_audit_is_system_taxonomy( $taxonomy ) {
	$excluded = array(
		'nav_menu',
		'link_category',
		'post_format',
		'wp_theme',
		'wp_pattern_category',
		'wp_template_part_area',
	);

	return in_array( $taxonomy, $excluded, true );
}

/**
 * Return all audit-relevant post types.
 *
 * @param array<string, mixed> $metadata Metadata reference.
 *
 * @return array<int, string>
 */
function mfw_audit_get_content_types( &$metadata ) {
	$post_types = get_post_types( array( 'show_ui' => true ), 'objects' );
	$types      = array();

	foreach ( $post_types as $post_type => $object ) {
		if ( mfw_audit_is_system_post_type( $post_type ) ) {
			continue;
		}

		$types[] = $post_type;
	}

	$types = array_values( array_unique( $types ) );
	sort( $types );

	$expected   = array( 'post', 'page', 'project' );
	$unexpected = array_values( array_diff( $types, $expected ) );

	$metadata['unexpected_content_types'] = $unexpected;

	if ( ! empty( $unexpected ) ) {
		$metadata['warnings'][] = sprintf(
			/* translators: %s: comma-separated list of post types. */
			__( 'Unexpected content types detected: %s', 'migration-freeze-webspark' ),
			implode( ', ', $unexpected )
		);
	}

	return $types;
}

/**
 * Get the export storage paths.
 *
 * @param string $export_id Export identifier.
 *
 * @return array<string, string>|WP_Error
 */
function mfw_audit_get_storage_paths( $export_id ) {
	$uploads = wp_upload_dir();

	if ( ! empty( $uploads['error'] ) ) {
		return new WP_Error( 'mfw_audit_uploads_error', $uploads['error'] );
	}

	$base_dir = trailingslashit( $uploads['basedir'] ) . 'mfw-audit-trail/site-' . get_current_blog_id() . '/' . sanitize_file_name( $export_id );
	$base_url = trailingslashit( $uploads['baseurl'] ) . 'mfw-audit-trail/site-' . get_current_blog_id() . '/' . rawurlencode( sanitize_file_name( $export_id ) );

	if ( ! wp_mkdir_p( $base_dir ) ) {
		return new WP_Error( 'mfw_audit_mkdir_error', __( 'Could not create the audit export directory.', 'migration-freeze-webspark' ) );
	}

	return array(
		'dir' => $base_dir,
		'url' => $base_url,
	);
}

/**
 * Build the export context.
 *
 * @return array<string, mixed>
 */
function mfw_audit_get_export_context() {
	$current_user = wp_get_current_user();
	$site_id      = get_current_blog_id();
	$site_name    = get_bloginfo( 'name' );
	$site_url     = home_url( '/' );
	$environment  = apply_filters( 'mfw_audit_environment_name', $site_name ? $site_name : 'site-' . $site_id, $site_id, $site_url );

	return array(
		'environment_name' => $environment,
		'site_id'          => $site_id,
		'site_name'        => $site_name,
		'site_url'         => $site_url,
		'generated_at'     => current_time( 'mysql' ),
		'generated_at_gmt' => current_time( 'mysql', true ),
		'generated_by'     => array(
			'user_id'    => $current_user->ID,
			'user_login' => $current_user->user_login,
			'display'    => $current_user->display_name ? $current_user->display_name : $current_user->user_login,
		),
		'detected_plugins' => mfw_get_detected_audit_plugins(),
		'detected_cpts'    => array(),
		'warnings'        => array(),
	);
}

/**
 * Append a row to the export.
 *
 * @param array<int, array<string, string>> $rows Row collection.
 * @param array<string, string>             $row  Row data.
 *
 * @return void
 */
function mfw_audit_push_row( &$rows, $row ) {
	$rows[] = $row;
}

/**
 * Collect content rows.
 *
 * @param array<int, array<string, string>> $rows         Row collection.
 * @param array<string, mixed>             $metadata     Metadata reference.
 * @param array<int, WP_Post>              $content_posts Content post cache.
 *
 * @return void
 */
function mfw_audit_collect_content_rows( &$rows, &$metadata, &$content_posts ) {
	$allowed_statuses = array( 'publish', 'future', 'draft', 'pending', 'private' );
	$content_types    = mfw_audit_get_content_types( $metadata );
	$metadata['detected_cpts'] = $content_types;

	foreach ( $content_types as $post_type ) {
		$posts = get_posts(
			array(
				'post_type'              => $post_type,
				'post_status'            => $allowed_statuses,
				'posts_per_page'         => -1,
				'orderby'                => 'ID',
				'order'                  => 'ASC',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		if ( empty( $posts ) ) {
			continue;
		}

		foreach ( $posts as $post ) {
			$content_posts[] = $post;
			mfw_audit_push_row(
				$rows,
				mfw_audit_build_row(
					array(
						'environment_name' => $metadata['environment_name'],
						'site_id'          => $metadata['site_id'],
						'record_type'      => 'content',
						'object_type'      => $post->post_type,
						'object_id'        => $post->ID,
						'status'           => $post->post_status,
						'title'            => get_the_title( $post ),
						'url'              => get_permalink( $post ),
						'slug'             => $post->post_name,
						'parent_id'        => $post->post_parent,
						'created_at'       => $post->post_date,
						'modified_at'      => $post->post_modified,
						'details_json'     => mfw_audit_json_encode(
							array(
								'post_author'   => $post->post_author,
								'post_type'     => $post->post_type,
								'post_status'   => $post->post_status,
								'post_parent'   => $post->post_parent,
								'post_date_gmt' => $post->post_date_gmt,
								'post_modified_gmt' => $post->post_modified_gmt,
								'comment_status' => $post->comment_status,
								'ping_status'   => $post->ping_status,
							)
						),
					)
				)
			);
		}
	}
}

/**
 * Collect taxonomy term rows.
 *
 * @param array<int, array<string, string>> $rows     Row collection.
 * @param array<string, mixed>             $metadata Metadata reference.
 *
 * @return void
 */
function mfw_audit_collect_taxonomy_term_rows( &$rows, &$metadata ) {
	$taxonomies = get_taxonomies( array( 'show_ui' => true ), 'objects' );

	foreach ( $taxonomies as $taxonomy => $taxonomy_object ) {
		if ( mfw_audit_is_system_taxonomy( $taxonomy ) ) {
			continue;
		}

		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
			)
		);

		if ( is_wp_error( $terms ) ) {
			$metadata['warnings'][] = sprintf(
				/* translators: %s: taxonomy name. */
				__( 'Could not read taxonomy terms for %s.', 'migration-freeze-webspark' ),
				$taxonomy
			);
			continue;
		}

		foreach ( $terms as $term ) {
			$archive_url = get_term_link( $term );

			if ( is_wp_error( $archive_url ) ) {
				$archive_url = '';
			}

			mfw_audit_push_row(
				$rows,
				mfw_audit_build_row(
					array(
						'environment_name' => $metadata['environment_name'],
						'site_id'          => $metadata['site_id'],
						'record_type'      => 'taxonomy_term',
						'object_type'      => $taxonomy,
						'object_id'        => $term->term_id,
						'status'           => 'automatic',
						'title'            => $term->name,
						'url'              => $archive_url,
						'slug'             => $term->slug,
						'parent_id'        => $term->parent,
						'taxonomy'         => $taxonomy,
						'term_id'          => $term->term_id,
						'details_json'     => mfw_audit_json_encode(
							array(
								'description' => $term->description,
								'count'       => $term->count,
								'parent'      => $term->parent,
								'archive_url' => $archive_url,
							)
						),
					)
				)
			);
		}
	}
}

/**
 * Collect taxonomy relationship rows.
 *
 * @param array<int, array<string, string>> $rows         Row collection.
 * @param array<string, mixed>             $metadata     Metadata reference.
 * @param array<int, WP_Post>              $content_posts Content post cache.
 *
 * @return void
 */
function mfw_audit_collect_taxonomy_relationship_rows( &$rows, &$metadata, $content_posts ) {
	foreach ( $content_posts as $post ) {
		$taxonomies = get_object_taxonomies( $post->post_type, 'names' );

		if ( empty( $taxonomies ) ) {
			continue;
		}

		foreach ( $taxonomies as $taxonomy ) {
			if ( mfw_audit_is_system_taxonomy( $taxonomy ) ) {
				continue;
			}

			$terms = wp_get_post_terms(
				$post->ID,
				$taxonomy,
				array( 'fields' => 'all' )
			);

			if ( is_wp_error( $terms ) || empty( $terms ) ) {
				continue;
			}

			foreach ( $terms as $term ) {
				mfw_audit_push_row(
					$rows,
					mfw_audit_build_row(
						array(
							'environment_name'    => $metadata['environment_name'],
							'site_id'             => $metadata['site_id'],
							'record_type'         => 'taxonomy_relationship',
							'object_type'         => $post->post_type,
							'object_id'           => $term->term_id,
							'status'              => $post->post_status,
							'title'               => $term->name,
							'url'                 => get_permalink( $post ),
							'slug'                => $post->post_name,
							'taxonomy'            => $taxonomy,
							'term_id'             => $term->term_id,
							'related_object_id'   => $post->ID,
							'related_object_type' => $post->post_type,
							'details_json'        => mfw_audit_json_encode(
								array(
									'related_title' => get_the_title( $post ),
									'related_url'   => get_permalink( $post ),
									'taxonomy'      => $taxonomy,
									'term_slug'     => $term->slug,
								)
							),
						)
					)
				);
			}
		}
	}
}

/**
 * Collect media rows.
 *
 * @param array<int, array<string, string>> $rows     Row collection.
 * @param array<string, mixed>             $metadata Metadata reference.
 *
 * @return void
 */
function mfw_audit_collect_media_rows( &$rows, &$metadata ) {
	$attachments = get_posts(
		array(
			'post_type'              => 'attachment',
			'post_status'            => 'inherit',
			'posts_per_page'         => -1,
			'orderby'                => 'ID',
			'order'                  => 'ASC',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		)
	);

	if ( empty( $attachments ) ) {
		return;
	}

	foreach ( $attachments as $attachment ) {
		$file_path = get_attached_file( $attachment->ID );
		$file_name = $file_path ? basename( $file_path ) : '';
		$file_size = ( $file_path && file_exists( $file_path ) ) ? filesize( $file_path ) : 0;
		$alt_text  = get_post_meta( $attachment->ID, '_wp_attachment_image_alt', true );
		$attachment_metadata  = wp_get_attachment_metadata( $attachment->ID );

		mfw_audit_push_row(
			$rows,
			mfw_audit_build_row(
				array(
					'environment_name' => $metadata['environment_name'],
					'site_id'          => $metadata['site_id'],
					'record_type'      => 'media',
					'object_type'      => 'attachment',
					'object_id'        => $attachment->ID,
					'status'           => $attachment->post_status,
					'title'            => get_the_title( $attachment ),
					'url'              => wp_get_attachment_url( $attachment->ID ),
					'slug'             => $attachment->post_name,
					'media_id'         => $attachment->ID,
					'filename'         => $file_name,
					'mime_type'        => $attachment->post_mime_type,
					'created_at'       => $attachment->post_date,
					'modified_at'      => $attachment->post_modified,
					'details_json'     => mfw_audit_json_encode(
						array(
							'alt_text'        => $alt_text,
							'upload_path'     => $file_path,
							'upload_metadata' => $attachment_metadata,
							'file_size'       => $file_size,
						)
					),
				)
			)
		);
	}
}

/**
 * Get menu locations for a menu term ID.
 *
 * @param int $menu_id Menu term ID.
 *
 * @return array<int, string>
 */
function mfw_audit_get_menu_locations_for_menu( $menu_id ) {
	$locations = get_nav_menu_locations();
	$matched_locations = array();

	if ( ! is_array( $locations ) ) {
		return $matched_locations;
	}

	foreach ( $locations as $location => $assigned_menu_id ) {
		if ( (int) $assigned_menu_id === (int) $menu_id ) {
			$matched_locations[] = $location;
		}
	}

	return $matched_locations;
}

/**
 * Collect menu rows.
 *
 * @param array<int, array<string, string>> $rows     Row collection.
 * @param array<string, mixed>             $metadata Metadata reference.
 *
 * @return void
 */
function mfw_audit_collect_menu_rows( &$rows, &$metadata ) {
	$menus = wp_get_nav_menus();

	if ( empty( $menus ) || is_wp_error( $menus ) ) {
		return;
	}

	foreach ( $menus as $menu ) {
		$menu_items = wp_get_nav_menu_items( $menu->term_id );
		$menu_items = is_array( $menu_items ) ? $menu_items : array();
		$locations   = mfw_audit_get_menu_locations_for_menu( $menu->term_id );

		mfw_audit_push_row(
			$rows,
			mfw_audit_build_row(
				array(
					'environment_name' => $metadata['environment_name'],
					'site_id'          => $metadata['site_id'],
					'record_type'      => 'menu',
					'object_type'      => 'nav_menu',
					'object_id'        => $menu->term_id,
					'status'           => 'active',
					'title'            => $menu->name,
					'slug'             => $menu->slug,
					'menu_name'        => $menu->name,
					'menu_slug'        => $menu->slug,
					'menu_location'    => implode( ',', $locations ),
					'details_json'     => mfw_audit_json_encode(
						array(
							'description' => $menu->description,
							'item_count'  => count( $menu_items ),
							'locations'   => $locations,
						)
					),
				)
			)
		);

		foreach ( $menu_items as $menu_item ) {
			$object_id   = isset( $menu_item->object_id ) ? (int) $menu_item->object_id : 0;
			$object_type = isset( $menu_item->object ) ? $menu_item->object : '';

			mfw_audit_push_row(
				$rows,
				mfw_audit_build_row(
					array(
						'environment_name'   => $metadata['environment_name'],
						'site_id'            => $metadata['site_id'],
						'record_type'        => 'menu_item',
						'object_type'        => 'menu_item',
						'object_id'          => $menu_item->ID,
						'status'             => 'active',
						'title'              => $menu_item->title,
						'url'                => $menu_item->url,
						'parent_id'          => $menu_item->menu_item_parent,
						'related_object_id'  => $object_id,
						'related_object_type'=> $object_type,
						'menu_name'          => $menu->name,
						'menu_slug'          => $menu->slug,
						'menu_location'      => implode( ',', $locations ),
						'details_json'       => mfw_audit_json_encode(
							array(
								'menu_order'   => $menu_item->menu_order,
								'target'       => $menu_item->target,
								'xfn'          => $menu_item->xfn,
								'classes'      => $menu_item->classes,
								'description'  => $menu_item->description,
								'object_id'    => $object_id,
								'object_type'  => $object_type,
							)
						),
					)
				)
			);
		}
	}
}

/**
 * Collect user rows.
 *
 * @param array<int, array<string, string>> $rows     Row collection.
 * @param array<string, mixed>             $metadata Metadata reference.
 *
 * @return void
 */
function mfw_audit_collect_user_rows( &$rows, &$metadata ) {
	$users = get_users(
		array(
			'orderby' => 'ID',
			'order'   => 'ASC',
			'fields'  => 'all',
		)
	);

	foreach ( $users as $user ) {
		$roles = is_array( $user->roles ) ? implode( ',', $user->roles ) : '';

		mfw_audit_push_row(
			$rows,
			mfw_audit_build_row(
				array(
					'environment_name' => $metadata['environment_name'],
					'site_id'          => $metadata['site_id'],
					'record_type'      => 'user',
					'object_type'      => 'user',
					'object_id'        => $user->ID,
					'status'           => 'active',
					'title'            => $user->display_name ? $user->display_name : $user->user_login,
					'role'             => $roles,
					'created_at'       => $user->user_registered,
					'details_json'     => mfw_audit_json_encode(
						array(
							'user_login'   => $user->user_login,
							'user_email'   => $user->user_email,
							'display_name' => $user->display_name,
							'roles'        => $user->roles,
							'registered_at'=> $user->user_registered,
						)
					),
				)
			)
		);
	}
}

/**
 * Collect Gravity Forms rows.
 *
 * @param array<int, array<string, string>> $rows     Row collection.
 * @param array<string, mixed>             $metadata Metadata reference.
 *
 * @return void
 */
function mfw_audit_collect_gravity_forms_rows( &$rows, &$metadata ) {
	if ( ! class_exists( 'GFAPI' ) ) {
		return;
	}

	$forms = GFAPI::get_forms();

	if ( empty( $forms ) || is_wp_error( $forms ) ) {
		return;
	}

	foreach ( $forms as $form_stub ) {
		$form_id = isset( $form_stub['id'] ) ? (int) $form_stub['id'] : 0;
		$form    = GFAPI::get_form( $form_id );

		if ( empty( $form ) || is_wp_error( $form ) ) {
			continue;
		}

		mfw_audit_push_row(
			$rows,
			mfw_audit_build_row(
				array(
					'environment_name' => $metadata['environment_name'],
					'site_id'          => $metadata['site_id'],
					'record_type'      => 'gravity_form',
					'object_type'      => 'gravity_form',
					'object_id'        => $form_id,
					'status'           => ! empty( $form['is_active'] ) ? 'active' : 'inactive',
					'title'            => isset( $form['title'] ) ? $form['title'] : '',
					'plugin'           => 'gravity_forms',
					'details_json'     => mfw_audit_json_encode(
						array(
							'description'          => isset( $form['description'] ) ? $form['description'] : '',
							'button'               => isset( $form['button'] ) ? $form['button'] : array(),
							'labelPlacement'       => isset( $form['labelPlacement'] ) ? $form['labelPlacement'] : '',
							'descriptionPlacement' => isset( $form['descriptionPlacement'] ) ? $form['descriptionPlacement'] : '',
							'cssClass'             => isset( $form['cssClass'] ) ? $form['cssClass'] : '',
							'field_count'          => isset( $form['fields'] ) && is_array( $form['fields'] ) ? count( $form['fields'] ) : 0,
						)
					),
				)
			)
		);

		if ( ! empty( $form['notifications'] ) && is_array( $form['notifications'] ) ) {
			foreach ( $form['notifications'] as $notification ) {
				mfw_audit_push_row(
					$rows,
					mfw_audit_build_row(
						array(
							'environment_name' => $metadata['environment_name'],
							'site_id'          => $metadata['site_id'],
							'record_type'      => 'gravity_notification',
							'object_type'      => 'gravity_notification',
							'object_id'        => $form_id . ':' . md5( maybe_serialize( $notification ) ),
							'status'           => ! empty( $notification['isActive'] ) ? 'active' : 'inactive',
							'title'            => isset( $notification['name'] ) ? $notification['name'] : '',
							'plugin'           => 'gravity_forms',
							'details_json'     => mfw_audit_json_encode( $notification ),
						)
					)
				);
			}
		}

		if ( ! empty( $form['confirmations'] ) && is_array( $form['confirmations'] ) ) {
			foreach ( $form['confirmations'] as $confirmation ) {
				mfw_audit_push_row(
					$rows,
					mfw_audit_build_row(
						array(
							'environment_name' => $metadata['environment_name'],
							'site_id'          => $metadata['site_id'],
							'record_type'      => 'gravity_confirmation',
							'object_type'      => 'gravity_confirmation',
							'object_id'        => $form_id . ':' . md5( maybe_serialize( $confirmation ) ),
							'status'           => ! empty( $confirmation['isDefault'] ) ? 'active' : 'inactive',
							'title'            => isset( $confirmation['name'] ) ? $confirmation['name'] : '',
							'plugin'           => 'gravity_forms',
							'details_json'     => mfw_audit_json_encode( $confirmation ),
						)
					)
				);
			}
		}
	}
}

/**
 * Collect SEO rows.
 *
 * @param array<int, array<string, string>> $rows         Row collection.
 * @param array<string, mixed>             $metadata     Metadata reference.
 * @param array<int, WP_Post>              $content_posts Content post cache.
 *
 * @return void
 */
function mfw_audit_collect_seo_rows( &$rows, &$metadata, $content_posts ) {
	$detected = mfw_get_detected_audit_plugins();
	$yoast_on  = isset( $detected['yoast_seo'] );
	$rank_on   = isset( $detected['rank_math'] );

	if ( ! $yoast_on && ! $rank_on ) {
		return;
	}

	foreach ( $content_posts as $post ) {
		$post_meta = get_post_meta( $post->ID );

		if ( $yoast_on ) {
			$yoast_meta = array();

			foreach ( $post_meta as $meta_key => $meta_values ) {
				if ( 0 === strpos( $meta_key, '_yoast_wpseo_' ) || 0 === strpos( $meta_key, '_yoast_indexnow_' ) ) {
					$yoast_meta[ $meta_key ] = $meta_values;
				}
			}

			if ( ! empty( $yoast_meta ) ) {
				mfw_audit_push_row(
					$rows,
					mfw_audit_build_row(
						array(
							'environment_name' => $metadata['environment_name'],
							'site_id'          => $metadata['site_id'],
							'record_type'      => 'seo',
							'object_type'      => $post->post_type,
							'object_id'        => $post->ID,
							'status'           => $post->post_status,
							'title'            => get_the_title( $post ),
							'url'              => get_permalink( $post ),
							'plugin'           => 'yoast_seo',
							'details_json'     => mfw_audit_json_encode( array( 'meta' => $yoast_meta ) ),
						)
					)
				);
			}
		}

		if ( $rank_on ) {
			$rank_meta = array();

			foreach ( $post_meta as $meta_key => $meta_values ) {
				if ( 0 === strpos( $meta_key, 'rank_math_' ) ) {
					$rank_meta[ $meta_key ] = $meta_values;
				}
			}

			if ( ! empty( $rank_meta ) ) {
				mfw_audit_push_row(
					$rows,
					mfw_audit_build_row(
						array(
							'environment_name' => $metadata['environment_name'],
							'site_id'          => $metadata['site_id'],
							'record_type'      => 'seo',
							'object_type'      => $post->post_type,
							'object_id'        => $post->ID,
							'status'           => $post->post_status,
							'title'            => get_the_title( $post ),
							'url'              => get_permalink( $post ),
							'plugin'           => 'rank_math',
							'details_json'     => mfw_audit_json_encode( array( 'meta' => $rank_meta ) ),
						)
					)
				);
			}
		}
	}
}

/**
 * Collect redirect rows.
 *
 * @param array<int, array<string, string>> $rows     Row collection.
 * @param array<string, mixed>             $metadata Metadata reference.
 *
 * @return void
 */
function mfw_audit_collect_redirect_rows( &$rows, &$metadata ) {
	if ( ! ( mfw_audit_is_plugin_active( 'redirection/redirection.php' ) || class_exists( 'Red_Item' ) ) ) {
		return;
	}

	global $wpdb;

	$items_table  = $wpdb->prefix . 'redirection_items';
	$groups_table = $wpdb->prefix . 'redirection_groups';
	$items_found  = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $items_table ) );

	if ( empty( $items_found ) ) {
		$metadata['warnings'][] = __( 'Redirection plugin detected, but its storage table was not found.', 'migration-freeze-webspark' );
		return;
	}

	$group_map = array();
	$groups_found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $groups_table ) );

	if ( ! empty( $groups_found ) ) {
		$groups = $wpdb->get_results( "SELECT id, name FROM {$groups_table}", ARRAY_A );

		if ( is_array( $groups ) ) {
			foreach ( $groups as $group ) {
				$group_map[ (int) $group['id'] ] = $group['name'];
			}
		}
	}

	$items = $wpdb->get_results( "SELECT * FROM {$items_table} ORDER BY position ASC, id ASC", ARRAY_A );

	if ( empty( $items ) || ! is_array( $items ) ) {
		return;
	}

	foreach ( $items as $item ) {
		$group_id   = isset( $item['group_id'] ) ? (int) $item['group_id'] : 0;
		$group_name = isset( $group_map[ $group_id ] ) ? $group_map[ $group_id ] : '';
		$enabled    = isset( $item['status'] ) ? (int) $item['status'] : 0;
		$source_url = isset( $item['url'] ) ? $item['url'] : '';
		$target_url = '';

		if ( isset( $item['action_data'] ) ) {
			$action_data = maybe_unserialize( $item['action_data'] );
			if ( is_array( $action_data ) && isset( $action_data['url'] ) ) {
				$target_url = $action_data['url'];
			} elseif ( is_string( $action_data ) ) {
				$target_url = $action_data;
			}
		}

		mfw_audit_push_row(
			$rows,
			mfw_audit_build_row(
				array(
					'environment_name'    => $metadata['environment_name'],
					'site_id'             => $metadata['site_id'],
					'record_type'         => 'redirect',
					'object_type'         => 'redirect',
					'object_id'           => isset( $item['id'] ) ? $item['id'] : '',
					'status'              => $enabled ? 'enabled' : 'disabled',
					'title'               => $source_url,
					'url'                 => $source_url,
					'plugin'              => 'redirection',
					'details_json'        => mfw_audit_json_encode(
						array(
							'source_url'  => $source_url,
							'target_url'  => $target_url,
							'action_type' => isset( $item['action_type'] ) ? $item['action_type'] : '',
							'match_type'  => isset( $item['match_type'] ) ? $item['match_type'] : '',
							'group_id'    => $group_id,
							'group_name'  => $group_name,
							'position'    => isset( $item['position'] ) ? $item['position'] : '',
							'hits'        => isset( $item['hits'] ) ? $item['hits'] : '',
							'regex'       => isset( $item['regex'] ) ? $item['regex'] : '',
							'action_data' => isset( $item['action_data'] ) ? maybe_unserialize( $item['action_data'] ) : array(),
						)
					),
				)
			)
		);
	}
}

/**
 * Collect all audit rows.
 *
 * @return array<string, mixed>
 */
function mfw_build_audit_export_dataset() {
	$context       = mfw_audit_get_export_context();
	$rows          = array();
	$content_posts = array();

	mfw_audit_collect_content_rows( $rows, $context, $content_posts );
	mfw_audit_collect_taxonomy_term_rows( $rows, $context );
	mfw_audit_collect_taxonomy_relationship_rows( $rows, $context, $content_posts );
	mfw_audit_collect_media_rows( $rows, $context );
	mfw_audit_collect_menu_rows( $rows, $context );
	mfw_audit_collect_user_rows( $rows, $context );
	mfw_audit_collect_gravity_forms_rows( $rows, $context );
	mfw_audit_collect_seo_rows( $rows, $context, $content_posts );
	mfw_audit_collect_redirect_rows( $rows, $context );

	$row_counts = array();

	foreach ( $rows as $row ) {
		$type = isset( $row['record_type'] ) ? $row['record_type'] : 'unknown';

		if ( ! isset( $row_counts[ $type ] ) ) {
			$row_counts[ $type ] = 0;
		}

		$row_counts[ $type ]++;
	}

	$context['row_counts'] = $row_counts;
	$context['row_total']  = count( $rows );

	return array(
		'context' => $context,
		'rows'    => $rows,
	);
}

/**
 * Write the CSV file.
 *
 * @param string $path CSV file path.
 * @param array<int, array<string,string>> $rows CSV rows.
 *
 * @return bool|WP_Error
 */
function mfw_write_audit_csv( $path, $rows ) {
	$handle = fopen( $path, 'w' );

	if ( false === $handle ) {
		return new WP_Error( 'mfw_audit_csv_open_failed', __( 'Could not open the CSV export for writing.', 'migration-freeze-webspark' ) );
	}

	fputcsv( $handle, mfw_get_audit_csv_columns() );

	$columns = mfw_get_audit_csv_columns();

	foreach ( $rows as $row ) {
		$ordered = array();

		foreach ( $columns as $column ) {
			$ordered[] = isset( $row[ $column ] ) ? $row[ $column ] : '';
		}

		fputcsv( $handle, $ordered );
	}

	fclose( $handle );

	return true;
}

/**
 * Write the JSON metadata file.
 *
 * @param string $path JSON file path.
 * @param array<string, mixed> $metadata Metadata payload.
 *
 * @return bool|WP_Error
 */
function mfw_write_audit_json( $path, $metadata ) {
	$json = wp_json_encode( $metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );

	if ( false === $json ) {
		return new WP_Error( 'mfw_audit_json_failed', __( 'Could not encode the audit metadata JSON.', 'migration-freeze-webspark' ) );
	}

	$bytes = file_put_contents( $path, $json );

	if ( false === $bytes ) {
		return new WP_Error( 'mfw_audit_json_write_failed', __( 'Could not write the audit metadata JSON.', 'migration-freeze-webspark' ) );
	}

	return true;
}

/**
 * Create a ZIP archive if supported.
 *
 * @param string $zip_path Archive path.
 * @param array<int, array{name:string,path:string}> $files Files to add.
 *
 * @return bool
 */
function mfw_create_audit_zip( $zip_path, $files ) {
	if ( ! class_exists( 'ZipArchive' ) ) {
		return false;
	}

	$zip = new ZipArchive();

	if ( true !== $zip->open( $zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
		return false;
	}

	foreach ( $files as $file ) {
		$zip->addFile( $file['path'], $file['name'] );
	}

	$zip->close();

	return file_exists( $zip_path );
}

/**
 * Generate the audit export files.
 *
 * @return array<string, mixed>|WP_Error
 */
function mfw_generate_audit_export_bundle() {
	$export_id = 'audit-' . get_current_blog_id() . '-' . gmdate( 'Ymd-His' );
	$dataset   = mfw_build_audit_export_dataset();
	$context   = $dataset['context'];
	$rows      = $dataset['rows'];
	$storage   = mfw_audit_get_storage_paths( $export_id );

	if ( is_wp_error( $storage ) ) {
		return $storage;
	}

	$base_name = sanitize_file_name( $export_id );
	$csv_name  = $base_name . '.csv';
	$json_name  = $base_name . '.json';
	$zip_name   = $base_name . '.zip';
	$csv_path   = trailingslashit( $storage['dir'] ) . $csv_name;
	$json_path  = trailingslashit( $storage['dir'] ) . $json_name;
	$zip_path   = trailingslashit( $storage['dir'] ) . $zip_name;
	$csv_url    = trailingslashit( $storage['url'] ) . rawurlencode( $csv_name );
	$json_url   = trailingslashit( $storage['url'] ) . rawurlencode( $json_name );
	$zip_url    = trailingslashit( $storage['url'] ) . rawurlencode( $zip_name );
	$csv_result = mfw_write_audit_csv( $csv_path, $rows );

	if ( is_wp_error( $csv_result ) ) {
		return $csv_result;
	}

	$metadata = array(
		'environment_name'        => $context['environment_name'],
		'site_id'                 => $context['site_id'],
		'site_name'               => $context['site_name'],
		'site_url'                => $context['site_url'],
		'export_id'               => $export_id,
		'generated_at'            => $context['generated_at'],
		'generated_at_gmt'        => $context['generated_at_gmt'],
		'generated_by'            => $context['generated_by'],
		'detected_plugins'        => $context['detected_plugins'],
		'detected_cpts'           => $context['detected_cpts'],
		'unexpected_content_types' => isset( $context['unexpected_content_types'] ) ? $context['unexpected_content_types'] : array(),
		'row_total'               => $context['row_total'],
		'row_counts'              => $context['row_counts'],
		'warnings'                => $context['warnings'],
		'files'                   => array(),
	);

	$json_result = mfw_write_audit_json( $json_path, $metadata );

	if ( is_wp_error( $json_result ) ) {
		return $json_result;
	}

	$files = array(
		array(
			'type' => 'csv',
			'name' => $csv_name,
			'url'  => $csv_url,
			'size' => file_exists( $csv_path ) ? filesize( $csv_path ) : 0,
		),
		array(
			'type' => 'json',
			'name' => $json_name,
			'url'  => $json_url,
			'size' => file_exists( $json_path ) ? filesize( $json_path ) : 0,
		),
	);

	$zip_created = mfw_create_audit_zip(
		$zip_path,
		array(
			array(
				'name' => $csv_name,
				'path' => $csv_path,
			),
			array(
				'name' => $json_name,
				'path' => $json_path,
			),
		)
	);

	if ( $zip_created ) {
		$files[] = array(
			'type' => 'zip',
			'name' => $zip_name,
			'url'  => $zip_url,
			'size' => file_exists( $zip_path ) ? filesize( $zip_path ) : 0,
		);
	}

	$record = array(
		'export_id'               => $export_id,
		'generated_at'            => $context['generated_at'],
		'generated_at_gmt'        => $context['generated_at_gmt'],
		'generated_by'            => $context['generated_by'],
		'row_total'               => $context['row_total'],
		'row_counts'              => $context['row_counts'],
		'detected_plugins'        => $context['detected_plugins'],
		'detected_cpts'           => $context['detected_cpts'],
		'unexpected_content_types' => isset( $context['unexpected_content_types'] ) ? $context['unexpected_content_types'] : array(),
		'warnings'                => $context['warnings'],
		'files'                   => $files,
	);

	mfw_add_audit_history_record( $record );

	return array(
		'record'  => $record,
		'files'   => $files,
		'metadata'=> array_merge( $metadata, array( 'files' => $files ) ),
	);
}

/**
 * Handle export generation.
 *
 * @return void
 */
function mfw_handle_audit_export() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have permission to generate audit exports.', 'migration-freeze-webspark' ) );
	}

	check_admin_referer( 'mfw_generate_audit_export', 'mfw_audit_nonce' );

	$result = mfw_generate_audit_export_bundle();

	if ( is_wp_error( $result ) ) {
		mfw_set_audit_notice(
			array(
				'type'    => 'error',
				'message' => $result->get_error_message(),
			)
		);
	}
	else {
		mfw_set_audit_notice(
			array(
				'type'    => 'success',
				'message' => __( 'Audit export generated successfully.', 'migration-freeze-webspark' ),
			)
		);
	}

	wp_safe_redirect(
		add_query_arg(
			array(
				'page'         => 'mfw-migration-audit-trail',
				'mfw_exported' => is_wp_error( $result ) ? 0 : 1,
			),
			admin_url( 'options-general.php' )
		)
	);
	exit;
}

/**
 * Return the total rows for a record.
 *
 * @param array<string, mixed> $record History record.
 *
 * @return int
 */
function mfw_get_audit_record_total( $record ) {
	if ( ! empty( $record['row_total'] ) ) {
		return (int) $record['row_total'];
	}

	$total = 0;

	if ( ! empty( $record['row_counts'] ) && is_array( $record['row_counts'] ) ) {
		foreach ( $record['row_counts'] as $count ) {
			$total += (int) $count;
		}
	}

	return $total;
}

/**
 * Render the audit trail page.
 *
 * @return void
 */
function mfw_render_audit_trail_page() {
	$history = mfw_get_audit_history();
	$notice  = mfw_get_audit_notice();
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Migration Audit Trail', 'migration-freeze-webspark' ); ?></h1>
		<p><?php esc_html_e( 'Generate a comprehensive export of the site for migration audit, QA, and UAT reconciliation.', 'migration-freeze-webspark' ); ?></p>

		<?php if ( ! empty( $notice ) ) : ?>
			<div class="notice <?php echo esc_attr( 'error' === $notice['type'] ? 'notice-error' : 'notice-success' ); ?> is-dismissible">
				<p><?php echo esc_html( $notice['message'] ); ?></p>
			</div>
		<?php endif; ?>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin: 1rem 0 2rem;">
			<?php wp_nonce_field( 'mfw_generate_audit_export', 'mfw_audit_nonce' ); ?>
			<input type="hidden" name="action" value="mfw_generate_audit_export" />
			<?php submit_button( __( 'Generate Audit Export', 'migration-freeze-webspark' ), 'primary', 'submit', false ); ?>
		</form>

		<?php if ( empty( $history ) ) : ?>
			<p><?php esc_html_e( 'No audit exports have been generated yet.', 'migration-freeze-webspark' ); ?></p>
		<?php else : ?>
			<table class="widefat striped" style="max-width: 1200px;">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Generated', 'migration-freeze-webspark' ); ?></th>
						<th><?php esc_html_e( 'By', 'migration-freeze-webspark' ); ?></th>
						<th><?php esc_html_e( 'Rows', 'migration-freeze-webspark' ); ?></th>
						<th><?php esc_html_e( 'Files', 'migration-freeze-webspark' ); ?></th>
						<th><?php esc_html_e( 'Warnings', 'migration-freeze-webspark' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $history as $record ) : ?>
						<tr>
							<td>
								<strong><?php echo esc_html( isset( $record['generated_at'] ) ? $record['generated_at'] : '' ); ?></strong>
								<?php if ( ! empty( $record['export_id'] ) ) : ?>
									<br /><code><?php echo esc_html( $record['export_id'] ); ?></code>
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( isset( $record['generated_by']['display'] ) ? $record['generated_by']['display'] : '' ); ?></td>
							<td><?php echo esc_html( mfw_get_audit_record_total( $record ) ); ?></td>
							<td>
								<?php
								if ( ! empty( $record['files'] ) && is_array( $record['files'] ) ) {
									foreach ( $record['files'] as $file ) {
										if ( empty( $file['url'] ) ) {
											continue;
										}

										echo '<div><a href="' . esc_url( $file['url'] ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( strtoupper( $file['type'] ) ) . '</a>';
										echo ! empty( $file['size'] ) ? ' <span>(' . esc_html( size_format( (int) $file['size'] ) ) . ')</span>' : '';
										echo '</div>';
									}
								}
								?>
							</td>
							<td>
								<?php
								if ( ! empty( $record['warnings'] ) && is_array( $record['warnings'] ) ) {
									echo '<ul style="margin:0; padding-left: 1.2rem;">';
									foreach ( $record['warnings'] as $warning ) {
										echo '<li>' . esc_html( $warning ) . '</li>';
									}
									echo '</ul>';
								} else {
									esc_html_e( 'None', 'migration-freeze-webspark' );
								}
								?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>
	<?php
}
