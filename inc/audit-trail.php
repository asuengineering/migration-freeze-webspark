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

function mfw_register_audit_export_handler() {
	add_action( 'admin_post_mfw_generate_audit_export', 'mfw_handle_audit_export' );
}
add_action( 'admin_init', 'mfw_register_audit_export_handler' );

function mfw_get_audit_history() {
	$history = get_option( MFW_OPTION_AUDIT_HISTORY, array() );
	return is_array( $history ) ? $history : array();
}

function mfw_save_audit_history( $history ) {
	$history = array_slice( array_values( $history ), 0, MFW_AUDIT_HISTORY_LIMIT );
	return update_option( MFW_OPTION_AUDIT_HISTORY, $history, false );
}

function mfw_add_audit_history_record( $record ) {
	$history = mfw_get_audit_history();
	array_unshift( $history, $record );
	mfw_save_audit_history( $history );
}

function mfw_get_audit_notice_key() {
	return 'mfw_audit_export_notice_' . get_current_user_id();
}

function mfw_set_audit_notice( $notice ) {
	set_transient( mfw_get_audit_notice_key(), $notice, 10 * MINUTE_IN_SECONDS );
}

function mfw_get_audit_notice() {
	$notice = get_transient( mfw_get_audit_notice_key() );
	if ( false !== $notice ) {
		delete_transient( mfw_get_audit_notice_key() );
	}
	return $notice;
}

function mfw_audit_is_plugin_active( $basename ) {
	$active_plugins = (array) get_option( 'active_plugins', array() );
	$sitewide       = (array) get_site_option( 'active_sitewide_plugins', array() );

	if ( in_array( $basename, $active_plugins, true ) || isset( $sitewide[ $basename ] ) ) {
		return true;
	}

	return function_exists( 'is_plugin_active' ) ? is_plugin_active( $basename ) : false;
}

function mfw_get_detected_audit_plugins() {
	$plugins = array();

	if ( mfw_audit_is_plugin_active( 'gravityforms/gravityforms.php' ) || class_exists( 'GFAPI' ) ) {
		$plugins['gravity_forms'] = array( 'label' => 'Gravity Forms', 'purpose' => 'forms', 'file' => 'gravityforms/gravityforms.php' );
	}
	if ( mfw_audit_is_plugin_active( 'wordpress-seo/wp-seo.php' ) || defined( 'WPSEO_VERSION' ) ) {
		$plugins['yoast_seo'] = array( 'label' => 'Yoast SEO', 'purpose' => 'seo', 'file' => 'wordpress-seo/wp-seo.php' );
	}
	if ( mfw_audit_is_plugin_active( 'seo-by-rank-math/rank-math.php' ) || defined( 'RANK_MATH_VERSION' ) ) {
		$plugins['rank_math'] = array( 'label' => 'Rank Math', 'purpose' => 'seo', 'file' => 'seo-by-rank-math/rank-math.php' );
	}
	if ( mfw_audit_is_plugin_active( 'redirection/redirection.php' ) || class_exists( 'Red_Item' ) ) {
		$plugins['redirection'] = array( 'label' => 'Redirection', 'purpose' => 'redirects', 'file' => 'redirection/redirection.php' );
	}

	return $plugins;
}

function mfw_get_audit_artifacts() {
	return array(
		'content' => array(
			'label'      => 'Content',
			'record_type' => 'content',
			'filename'    => 'content.csv',
			'columns'     => array( 'environment_name', 'site_id', 'record_type', 'object_type', 'object_id', 'status', 'title', 'url', 'slug', 'parent_id', 'created_at', 'modified_at', 'details_json' ),
		),
		'taxonomy_term' => array(
			'label'      => 'Taxonomies',
			'record_type' => 'taxonomy_term',
			'filename'    => 'taxonomies.csv',
			'columns'     => array( 'environment_name', 'site_id', 'record_type', 'object_type', 'object_id', 'title', 'url', 'slug', 'parent_id', 'taxonomy', 'term_id', 'details_json' ),
		),
		'taxonomy_relationship' => array(
			'label'      => 'Taxonomy Relationships',
			'record_type' => 'taxonomy_relationship',
			'filename'    => 'taxonomy-relationships.csv',
			'columns'     => array( 'environment_name', 'site_id', 'record_type', 'object_type', 'object_id', 'title', 'url', 'taxonomy', 'term_id', 'term_name', 'term_slug', 'related_object_id', 'related_object_type', 'details_json' ),
		),
		'media' => array(
			'label'      => 'Media',
			'record_type' => 'media',
			'filename'    => 'media.csv',
			'columns'     => array( 'environment_name', 'site_id', 'record_type', 'object_type', 'object_id', 'title', 'url', 'media_id', 'filename', 'mime_type', 'caption', 'alt_text', 'details_json' ),
		),
		'user' => array(
			'label'      => 'Users',
			'record_type' => 'user',
			'filename'    => 'users.csv',
			'columns'     => array( 'environment_name', 'site_id', 'record_type', 'object_type', 'object_id', 'title', 'role', 'registered_at', 'details_json' ),
		),
	);
}

function mfw_get_audit_columns( $artifact_key ) {
	$artifacts = mfw_get_audit_artifacts();
	return isset( $artifacts[ $artifact_key ]['columns'] ) ? $artifacts[ $artifact_key ]['columns'] : array();
}

function mfw_audit_json_encode( $data ) {
	$json = wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	return false === $json ? '{}' : $json;
}

function mfw_audit_build_row( $artifact_key, $values ) {
	$row = array_fill_keys( mfw_get_audit_columns( $artifact_key ), '' );
	foreach ( $values as $key => $value ) {
		if ( ! array_key_exists( $key, $row ) ) {
			continue;
		}
		$row[ $key ] = is_array( $value ) || is_object( $value ) ? mfw_audit_json_encode( $value ) : (string) $value;
	}
	return $row;
}

function mfw_audit_is_system_post_type( $post_type ) {
	$excluded = array( 'attachment', 'revision', 'nav_menu_item', 'custom_css', 'customize_changeset', 'oembed_cache', 'user_request', 'wp_block', 'wp_navigation', 'wp_template', 'wp_template_part', 'wp_font_face', 'wp_font_family', 'wp_global_styles' );
	return in_array( $post_type, $excluded, true ) || 0 === strpos( $post_type, 'acf-' );
}

function mfw_audit_is_system_taxonomy( $taxonomy ) {
	return in_array( $taxonomy, array( 'nav_menu', 'link_category', 'post_format', 'wp_theme', 'wp_pattern_category', 'wp_template_part_area' ), true );
}

function mfw_audit_get_content_types( &$metadata ) {
	$post_types = get_post_types( array( 'show_ui' => true ), 'objects' );
	$types = array();
	foreach ( $post_types as $post_type => $object ) {
		if ( ! mfw_audit_is_system_post_type( $post_type ) ) {
			$types[] = $post_type;
		}
	}
	$types = array_values( array_unique( $types ) );
	sort( $types );
	$expected = array( 'post', 'page', 'project' );
	$unexpected = array_values( array_diff( $types, $expected ) );
	$metadata['unexpected_content_types'] = $unexpected;
	if ( ! empty( $unexpected ) ) {
		$metadata['warnings'][] = sprintf( __( 'Unexpected content types detected: %s', 'migration-freeze-webspark' ), implode( ', ', $unexpected ) );
	}
	return $types;
}

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
	return array( 'dir' => $base_dir, 'url' => $base_url );
}

function mfw_audit_get_export_context() {
	$current_user = wp_get_current_user();
	$site_id = get_current_blog_id();
	$site_name = get_bloginfo( 'name' );
	$site_url = home_url( '/' );
	$environment = apply_filters( 'mfw_audit_environment_name', $site_name ? $site_name : 'site-' . $site_id, $site_id, $site_url );
	return array(
		'environment_name' => $environment,
		'site_id' => $site_id,
		'site_name' => $site_name,
		'site_url' => $site_url,
		'generated_at' => current_time( 'mysql' ),
		'generated_at_gmt' => current_time( 'mysql', true ),
		'generated_by' => array( 'user_id' => $current_user->ID, 'user_login' => $current_user->user_login, 'display' => $current_user->display_name ? $current_user->display_name : $current_user->user_login ),
		'detected_plugins' => mfw_get_detected_audit_plugins(),
		'detected_cpts' => array(),
		'warnings' => array(),
	);
}

function mfw_audit_push_row( &$artifacts, $artifact_key, $row ) {
	if ( ! isset( $artifacts[ $artifact_key ] ) ) {
		$artifacts[ $artifact_key ] = array();
	}
	$artifacts[ $artifact_key ][] = mfw_audit_build_row( $artifact_key, $row );
}

function mfw_audit_collect_content_rows( &$artifacts, &$metadata, &$content_posts ) {
	$allowed_statuses = array( 'publish', 'future', 'draft', 'draft-retain', 'pending', 'private' );
	$content_types = mfw_audit_get_content_types( $metadata );
	$metadata['detected_cpts'] = $content_types;
	foreach ( $content_types as $post_type ) {
		$posts = get_posts( array( 'post_type' => $post_type, 'post_status' => $allowed_statuses, 'posts_per_page' => -1, 'orderby' => 'ID', 'order' => 'ASC', 'no_found_rows' => true, 'update_post_meta_cache' => false, 'update_post_term_cache' => false ) );
		foreach ( $posts as $post ) {
			$content_posts[] = $post;
			mfw_audit_push_row( $artifacts, 'content', array( 'environment_name' => $metadata['environment_name'], 'site_id' => $metadata['site_id'], 'record_type' => 'content', 'object_type' => $post->post_type, 'object_id' => $post->ID, 'status' => $post->post_status, 'title' => get_the_title( $post ), 'url' => get_permalink( $post ), 'slug' => $post->post_name, 'parent_id' => $post->post_parent, 'created_at' => $post->post_date, 'modified_at' => $post->post_modified, 'details_json' => array( 'post_author' => $post->post_author, 'post_type' => $post->post_type, 'post_status' => $post->post_status, 'post_parent' => $post->post_parent, 'post_date_gmt' => $post->post_date_gmt, 'post_modified_gmt' => $post->post_modified_gmt, 'comment_status' => $post->comment_status, 'ping_status' => $post->ping_status ) ) );
		}
	}
}

function mfw_audit_collect_taxonomy_term_rows( &$artifacts, &$metadata ) {
	$taxonomies = get_taxonomies( array( 'show_ui' => true ), 'objects' );
	foreach ( $taxonomies as $taxonomy => $taxonomy_object ) {
		if ( mfw_audit_is_system_taxonomy( $taxonomy ) ) {
			continue;
		}
		$terms = get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false ) );
		if ( is_wp_error( $terms ) ) {
			$metadata['warnings'][] = sprintf( __( 'Could not read taxonomy terms for %s.', 'migration-freeze-webspark' ), $taxonomy );
			continue;
		}
		foreach ( $terms as $term ) {
			$archive_url = get_term_link( $term );
			if ( is_wp_error( $archive_url ) ) {
				$archive_url = '';
			}
			mfw_audit_push_row( $artifacts, 'taxonomy_term', array( 'environment_name' => $metadata['environment_name'], 'site_id' => $metadata['site_id'], 'record_type' => 'taxonomy_term', 'object_type' => $taxonomy, 'object_id' => $term->term_id, 'title' => $term->name, 'url' => $archive_url, 'slug' => $term->slug, 'parent_id' => $term->parent, 'taxonomy' => $taxonomy, 'term_id' => $term->term_id, 'details_json' => array( 'description' => $term->description, 'count' => $term->count, 'archive_url' => $archive_url ) ) );
		}
	}
}

function mfw_audit_collect_taxonomy_relationship_rows( &$artifacts, &$metadata, $content_posts ) {
	foreach ( $content_posts as $post ) {
		$taxonomies = get_object_taxonomies( $post->post_type, 'names' );
		foreach ( $taxonomies as $taxonomy ) {
			if ( mfw_audit_is_system_taxonomy( $taxonomy ) ) {
				continue;
			}
			$terms = wp_get_post_terms( $post->ID, $taxonomy, array( 'fields' => 'all' ) );
			if ( is_wp_error( $terms ) || empty( $terms ) ) {
				continue;
			}
			foreach ( $terms as $term ) {
				mfw_audit_push_row( $artifacts, 'taxonomy_relationship', array( 'environment_name' => $metadata['environment_name'], 'site_id' => $metadata['site_id'], 'record_type' => 'taxonomy_relationship', 'object_type' => $post->post_type, 'object_id' => $term->term_id, 'title' => get_the_title( $post ), 'url' => get_permalink( $post ), 'taxonomy' => $taxonomy, 'term_id' => $term->term_id, 'term_name' => $term->name, 'term_slug' => $term->slug, 'related_object_id' => $post->ID, 'related_object_type' => $post->post_type, 'details_json' => array( 'related_title' => get_the_title( $post ), 'related_url' => get_permalink( $post ) ) ) );
			}
		}
	}
}

function mfw_audit_collect_media_rows( &$artifacts, &$metadata ) {
	$attachments = get_posts( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'posts_per_page' => -1, 'orderby' => 'ID', 'order' => 'ASC', 'no_found_rows' => true, 'update_post_meta_cache' => false, 'update_post_term_cache' => false ) );
	foreach ( $attachments as $attachment ) {
		$file_path = get_attached_file( $attachment->ID );
		$file_name = $file_path ? basename( $file_path ) : '';
		$mime_type = $attachment->post_mime_type;
		$alt_text = 0 === strpos( $mime_type, 'image/' ) ? (string) get_post_meta( $attachment->ID, '_wp_attachment_image_alt', true ) : '';
		mfw_audit_push_row( $artifacts, 'media', array( 'environment_name' => $metadata['environment_name'], 'site_id' => $metadata['site_id'], 'record_type' => 'media', 'object_type' => 'attachment', 'object_id' => $attachment->ID, 'title' => get_the_title( $attachment ), 'url' => wp_get_attachment_url( $attachment->ID ), 'media_id' => $attachment->ID, 'filename' => $file_name, 'mime_type' => $mime_type, 'caption' => $attachment->post_excerpt, 'alt_text' => $alt_text, 'details_json' => array( 'upload_path' => $file_path, 'attachment_meta' => wp_get_attachment_metadata( $attachment->ID ), 'file_size' => ( $file_path && file_exists( $file_path ) ) ? filesize( $file_path ) : 0 ) ) );
	}
}

function mfw_audit_collect_user_rows( &$artifacts, &$metadata ) {
	$user_args = array( 'orderby' => 'ID', 'order' => 'ASC', 'fields' => 'all' );
	if ( is_multisite() ) {
		$user_args['blog_id'] = get_current_blog_id();
	}
	$users = get_users( $user_args );
	foreach ( $users as $user ) {
		$roles = is_array( $user->roles ) ? implode( ',', $user->roles ) : '';
		mfw_audit_push_row( $artifacts, 'user', array( 'environment_name' => $metadata['environment_name'], 'site_id' => $metadata['site_id'], 'record_type' => 'user', 'object_type' => 'user', 'object_id' => $user->ID, 'title' => $user->display_name ? $user->display_name : $user->user_login, 'role' => $roles, 'registered_at' => $user->user_registered, 'details_json' => array( 'user_login' => $user->user_login, 'user_email' => $user->user_email, 'display_name' => $user->display_name, 'roles' => $user->roles ) ) );
	}
}

function mfw_build_audit_export_dataset() {
	$context = mfw_audit_get_export_context();
	$artifacts = array();
	$content_posts = array();
	mfw_audit_collect_content_rows( $artifacts, $context, $content_posts );
	mfw_audit_collect_taxonomy_term_rows( $artifacts, $context );
	mfw_audit_collect_taxonomy_relationship_rows( $artifacts, $context, $content_posts );
	mfw_audit_collect_media_rows( $artifacts, $context );
	mfw_audit_collect_user_rows( $artifacts, $context );

	$row_counts = array();
	foreach ( mfw_get_audit_artifacts() as $artifact_key => $definition ) {
		$row_counts[ $artifact_key ] = isset( $artifacts[ $artifact_key ] ) ? count( $artifacts[ $artifact_key ] ) : 0;
	}
	$context['row_counts'] = $row_counts;
	$context['row_total'] = array_sum( $row_counts );

	$metadata = array( 'environment_name' => $context['environment_name'], 'site_id' => $context['site_id'], 'site_name' => $context['site_name'], 'site_url' => $context['site_url'], 'generated_at' => $context['generated_at'], 'generated_at_gmt' => $context['generated_at_gmt'], 'generated_by' => $context['generated_by'], 'detected_plugins' => $context['detected_plugins'], 'detected_cpts' => $context['detected_cpts'], 'unexpected_content_types' => isset( $context['unexpected_content_types'] ) ? $context['unexpected_content_types'] : array(), 'row_total' => $context['row_total'], 'row_counts' => $row_counts, 'warnings' => $context['warnings'], 'files' => array() );

	return array( 'context' => $context, 'artifacts' => $artifacts, 'metadata' => $metadata );
}

function mfw_write_audit_csv( $path, $rows, $columns ) {
	$handle = fopen( $path, 'w' );
	if ( false === $handle ) {
		return new WP_Error( 'mfw_audit_csv_open_failed', __( 'Could not open the CSV export for writing.', 'migration-freeze-webspark' ) );
	}
	fputcsv( $handle, $columns );
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

function mfw_generate_audit_export_bundle() {
	$export_id = 'audit-' . get_current_blog_id() . '-' . gmdate( 'Ymd-His' );
	$dataset = mfw_build_audit_export_dataset();
	$context = $dataset['context'];
	$artifacts = $dataset['artifacts'];
	$metadata = $dataset['metadata'];
	$storage = mfw_audit_get_storage_paths( $export_id );
	if ( is_wp_error( $storage ) ) {
		return $storage;
	}

	$bundle_files = array();
	foreach ( mfw_get_audit_artifacts() as $artifact_key => $definition ) {
		if ( empty( $artifacts[ $artifact_key ] ) ) {
			continue;
		}
		$file_name = $definition['filename'];
		$file_path = trailingslashit( $storage['dir'] ) . $file_name;
		$file_url = trailingslashit( $storage['url'] ) . rawurlencode( $file_name );
		$result = mfw_write_audit_csv( $file_path, $artifacts[ $artifact_key ], $definition['columns'] );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$bundle_files[] = array( 'type' => $artifact_key, 'name' => $file_name, 'path' => $file_path, 'url' => $file_url, 'size' => file_exists( $file_path ) ? filesize( $file_path ) : 0, 'rows' => isset( $context['row_counts'][ $artifact_key ] ) ? (int) $context['row_counts'][ $artifact_key ] : 0 );
	}

	$metadata_file_name = 'metadata.json';
	$metadata_file_path = trailingslashit( $storage['dir'] ) . $metadata_file_name;
	$metadata_file_url = trailingslashit( $storage['url'] ) . rawurlencode( $metadata_file_name );
	$metadata['files'] = $bundle_files;
	$metadata_result = mfw_write_audit_json( $metadata_file_path, $metadata );
	if ( is_wp_error( $metadata_result ) ) {
		return $metadata_result;
	}
	$bundle_files[] = array( 'type' => 'metadata', 'name' => $metadata_file_name, 'path' => $metadata_file_path, 'url' => $metadata_file_url, 'size' => file_exists( $metadata_file_path ) ? filesize( $metadata_file_path ) : 0, 'rows' => 1 );

	$zip_file_name = 'export.zip';
	$zip_file_path = trailingslashit( $storage['dir'] ) . $zip_file_name;
	$zip_file_url = trailingslashit( $storage['url'] ) . rawurlencode( $zip_file_name );
	$zip_created = mfw_create_audit_zip( $zip_file_path, array_map( static function ( $file ) { return array( 'name' => $file['name'], 'path' => $file['path'] ); }, $bundle_files ) );
	if ( $zip_created ) {
		$bundle_files[] = array( 'type' => 'zip', 'name' => $zip_file_name, 'path' => $zip_file_path, 'url' => $zip_file_url, 'size' => file_exists( $zip_file_path ) ? filesize( $zip_file_path ) : 0, 'rows' => 0 );
	}

	$record = array( 'export_id' => $export_id, 'generated_at' => $context['generated_at'], 'generated_at_gmt' => $context['generated_at_gmt'], 'generated_by' => $context['generated_by'], 'row_total' => $context['row_total'], 'row_counts' => $context['row_counts'], 'detected_plugins' => $context['detected_plugins'], 'detected_cpts' => $context['detected_cpts'], 'unexpected_content_types' => isset( $context['unexpected_content_types'] ) ? $context['unexpected_content_types'] : array(), 'warnings' => $context['warnings'], 'files' => $bundle_files );
	mfw_add_audit_history_record( $record );
	return array( 'record' => $record, 'files' => $bundle_files );
}

function mfw_handle_audit_export() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have permission to generate audit exports.', 'migration-freeze-webspark' ) );
	}
	check_admin_referer( 'mfw_generate_audit_export', 'mfw_audit_nonce' );
	$result = mfw_generate_audit_export_bundle();
	mfw_set_audit_notice( is_wp_error( $result ) ? array( 'type' => 'error', 'message' => $result->get_error_message() ) : array( 'type' => 'success', 'message' => __( 'Audit export generated successfully.', 'migration-freeze-webspark' ) ) );
	wp_safe_redirect( add_query_arg( array( 'page' => 'mfw-migration-audit-trail', 'mfw_exported' => is_wp_error( $result ) ? 0 : 1 ), admin_url( 'options-general.php' ) ) );
	exit;
}

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

function mfw_render_audit_trail_page() {
	$history = mfw_get_audit_history();
	$notice = mfw_get_audit_notice();
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Migration Audit Trail', 'migration-freeze-webspark' ); ?></h1>
		<p><?php esc_html_e( 'Generate separate export artifacts for content, taxonomies, taxonomy relationships, media, and users.', 'migration-freeze-webspark' ); ?></p>
		<?php if ( ! empty( $notice ) ) : ?>
			<div class="notice <?php echo esc_attr( 'error' === $notice['type'] ? 'notice-error' : 'notice-success' ); ?> is-dismissible"><p><?php echo esc_html( $notice['message'] ); ?></p></div>
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
				<thead><tr><th><?php esc_html_e( 'Generated', 'migration-freeze-webspark' ); ?></th><th><?php esc_html_e( 'By', 'migration-freeze-webspark' ); ?></th><th><?php esc_html_e( 'Rows', 'migration-freeze-webspark' ); ?></th><th><?php esc_html_e( 'Files', 'migration-freeze-webspark' ); ?></th><th><?php esc_html_e( 'Warnings', 'migration-freeze-webspark' ); ?></th></tr></thead>
				<tbody>
					<?php foreach ( $history as $record ) : ?>
						<tr>
							<td><strong><?php echo esc_html( isset( $record['generated_at'] ) ? $record['generated_at'] : '' ); ?></strong><?php if ( ! empty( $record['export_id'] ) ) : ?><br /><code><?php echo esc_html( $record['export_id'] ); ?></code><?php endif; ?></td>
							<td><?php echo esc_html( isset( $record['generated_by']['display'] ) ? $record['generated_by']['display'] : '' ); ?></td>
							<td><?php echo esc_html( mfw_get_audit_record_total( $record ) ); ?></td>
							<td><?php if ( ! empty( $record['files'] ) && is_array( $record['files'] ) ) { foreach ( $record['files'] as $file ) { if ( empty( $file['url'] ) ) { continue; } echo '<div><a href="' . esc_url( $file['url'] ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( strtoupper( $file['type'] ) ) . '</a>' . ( ! empty( $file['size'] ) ? ' <span>(' . esc_html( size_format( (int) $file['size'] ) ) . ')</span>' : '' ) . '</div>'; } } ?></td>
							<td><?php if ( ! empty( $record['warnings'] ) && is_array( $record['warnings'] ) ) { echo '<ul style="margin:0; padding-left: 1.2rem;">'; foreach ( $record['warnings'] as $warning ) { echo '<li>' . esc_html( $warning ) . '</li>'; } echo '</ul>'; } else { esc_html_e( 'None', 'migration-freeze-webspark' ); } ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>
	<?php
}
