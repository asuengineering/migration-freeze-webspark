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
	return update_option( MFW_OPTION_AUDIT_HISTORY, array_slice( array_values( $history ), 0, MFW_AUDIT_HISTORY_LIMIT ), false );
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
		$plugins['gravity_forms'] = array(
			'label'  => 'Gravity Forms',
			'purpose' => 'forms',
			'file'   => 'gravityforms/gravityforms.php',
			'active'  => true,
		);
	}
	if ( mfw_audit_is_plugin_active( 'wordpress-seo/wp-seo.php' ) || defined( 'WPSEO_VERSION' ) ) {
		$plugins['yoast_seo'] = array(
			'label'  => 'Yoast SEO',
			'purpose' => 'seo',
			'file'   => 'wordpress-seo/wp-seo.php',
			'active'  => true,
		);
	}
	if ( mfw_audit_is_plugin_active( 'seo-by-rank-math/rank-math.php' ) || defined( 'RANK_MATH_VERSION' ) ) {
		$plugins['rank_math'] = array(
			'label'  => 'Rank Math',
			'purpose' => 'seo',
			'file'   => 'seo-by-rank-math/rank-math.php',
			'active'  => true,
		);
	}
	if ( mfw_audit_is_plugin_active( 'redirection/redirection.php' ) || class_exists( 'Red_Item' ) ) {
		$plugins['redirection'] = array(
			'label'  => 'Redirection',
			'purpose' => 'redirects',
			'file'   => 'redirection/redirection.php',
			'active'  => true,
		);
	}
	return $plugins;
}

function mfw_get_audit_artifacts() {
	return array(
		'content' => array(
			'label'   => 'Content',
			'filename' => 'content.csv',
			'columns' => array( 'environment_name', 'site_id', 'record_type', 'object_type', 'object_id', 'status', 'title', 'url', 'slug', 'parent_id', 'created_at', 'modified_at', 'details_json' ),
		),
		'taxonomy_term' => array(
			'label'   => 'Taxonomies',
			'filename' => 'taxonomies.csv',
			'columns' => array( 'environment_name', 'site_id', 'record_type', 'object_type', 'object_id', 'title', 'url', 'slug', 'parent_id', 'taxonomy', 'term_id', 'details_json' ),
		),
		'taxonomy_relationship' => array(
			'label'   => 'Taxonomy Relationships',
			'filename' => 'taxonomy-relationships.csv',
			'columns' => array( 'environment_name', 'site_id', 'record_type', 'object_type', 'object_id', 'title', 'url', 'taxonomy', 'term_id', 'term_name', 'term_slug', 'related_object_id', 'related_object_type', 'details_json' ),
		),
		'media' => array(
			'label'   => 'Media',
			'filename' => 'media.csv',
			'columns' => array( 'environment_name', 'site_id', 'record_type', 'object_type', 'object_id', 'title', 'url', 'media_id', 'filename', 'mime_type', 'caption', 'alt_text', 'details_json' ),
		),
		'menu_item' => array(
			'label'   => 'Menu Items',
			'filename' => 'menu-items.csv',
			'columns' => array( 'environment_name', 'site_id', 'record_type', 'object_type', 'object_id', 'title', 'url', 'parent_id', 'related_object_id', 'related_object_type', 'menu_name', 'menu_slug', 'menu_location', 'details_json' ),
		),
		'user' => array(
			'label'   => 'Users',
			'filename' => 'users.csv',
			'columns' => array( 'environment_name', 'site_id', 'record_type', 'object_type', 'object_id', 'title', 'role', 'registered_at', 'details_json' ),
		),
		'gravity_forms' => array(
			'label'   => 'Gravity Forms',
			'filename' => 'gravity-forms.csv',
			'columns' => array(
				'environment_name',
				'site_id',
				'record_type',
				'object_type',
				'object_id',
				'form_id',
				'status',
				'title',
				'description',
				'url',
				'field_id',
				'field_type',
				'field_label',
				'has_conditional_logic',
				'notification_name',
				'confirmation_name',
				'details_json',
			),
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
		if ( array_key_exists( $key, $row ) ) {
			$row[ $key ] = is_array( $value ) || is_object( $value ) ? mfw_audit_json_encode( $value ) : (string) $value;
		}
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
	$expected   = array( 'post', 'page', 'project' );
	$unexpected = array_values( array_diff( $types, $expected ) );
	$metadata['unexpected_content_types'] = $unexpected;
	if ( ! empty( $unexpected ) ) {
		$metadata['warnings'][] = sprintf( __( 'Unexpected content types detected: %s', 'migration-freeze-webspark' ), implode( ', ', $unexpected ) );
	}
	return $types;
}

function mfw_audit_get_storage_paths( $export_prefix ) {
	$uploads = wp_upload_dir();
	if ( ! empty( $uploads['error'] ) ) {
		return new WP_Error( 'mfw_audit_uploads_error', $uploads['error'] );
	}
	$base_dir = trailingslashit( $uploads['basedir'] ) . 'mfw-audit-trail/site-' . get_current_blog_id() . '/' . sanitize_file_name( $export_prefix );
	$base_url = trailingslashit( $uploads['baseurl'] ) . 'mfw-audit-trail/site-' . get_current_blog_id() . '/' . rawurlencode( sanitize_file_name( $export_prefix ) );
	if ( ! wp_mkdir_p( $base_dir ) ) {
		return new WP_Error( 'mfw_audit_mkdir_error', __( 'Could not create the audit export directory.', 'migration-freeze-webspark' ) );
	}
	return array( 'dir' => $base_dir, 'url' => $base_url );
}

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
		'warnings'         => array(),
	);
}

function mfw_audit_push_row( &$artifacts, $artifact_key, $row ) {
	if ( ! isset( $artifacts[ $artifact_key ] ) ) {
		$artifacts[ $artifact_key ] = array();
	}
	$artifacts[ $artifact_key ][] = mfw_audit_build_row( $artifact_key, $row );
}

function mfw_audit_collect_content_rows( &$artifacts, &$metadata, &$content_posts ) {
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
		foreach ( $posts as $post ) {
			$content_posts[] = $post;
			mfw_audit_push_row(
				$artifacts,
				'content',
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
					'details_json'     => array(
						'post_author'      => $post->post_author,
						'post_type'        => $post->post_type,
						'post_status'      => $post->post_status,
						'post_parent'      => $post->post_parent,
						'post_date_gmt'    => $post->post_date_gmt,
						'post_modified_gmt' => $post->post_modified_gmt,
						'comment_status'   => $post->comment_status,
						'ping_status'      => $post->ping_status,
					),
				)
			);
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
			mfw_audit_push_row(
				$artifacts,
				'taxonomy_term',
				array(
					'environment_name' => $metadata['environment_name'],
					'site_id'          => $metadata['site_id'],
					'record_type'      => 'taxonomy_term',
					'object_type'      => $taxonomy,
					'object_id'        => $term->term_id,
					'title'            => $term->name,
					'url'              => $archive_url,
					'slug'             => $term->slug,
					'parent_id'        => $term->parent,
					'taxonomy'         => $taxonomy,
					'term_id'          => $term->term_id,
					'details_json'     => array(
						'description' => $term->description,
						'count'       => $term->count,
						'archive_url' => $archive_url,
					),
				)
			);
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
				mfw_audit_push_row(
					$artifacts,
					'taxonomy_relationship',
					array(
						'environment_name'    => $metadata['environment_name'],
						'site_id'             => $metadata['site_id'],
						'record_type'         => 'taxonomy_relationship',
						'object_type'         => $post->post_type,
						'object_id'           => $term->term_id,
						'title'               => get_the_title( $post ),
						'url'                 => get_permalink( $post ),
						'taxonomy'            => $taxonomy,
						'term_id'             => $term->term_id,
						'term_name'           => $term->name,
						'term_slug'           => $term->slug,
						'related_object_id'   => $post->ID,
						'related_object_type'  => $post->post_type,
						'details_json'        => array(
							'related_title' => get_the_title( $post ),
							'related_url'   => get_permalink( $post ),
						),
					)
				);
			}
		}
	}
}

function mfw_audit_collect_media_rows( &$artifacts, &$metadata ) {
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
	foreach ( $attachments as $attachment ) {
		$file_path = get_attached_file( $attachment->ID );
		$file_name = $file_path ? basename( $file_path ) : '';
		$mime_type  = $attachment->post_mime_type;
		$alt_text   = 0 === strpos( $mime_type, 'image/' ) ? (string) get_post_meta( $attachment->ID, '_wp_attachment_image_alt', true ) : '';
		mfw_audit_push_row(
			$artifacts,
			'media',
			array(
				'environment_name' => $metadata['environment_name'],
				'site_id'          => $metadata['site_id'],
				'record_type'      => 'media',
				'object_type'      => 'attachment',
				'object_id'        => $attachment->ID,
				'title'            => get_the_title( $attachment ),
				'url'              => wp_get_attachment_url( $attachment->ID ),
				'media_id'         => $attachment->ID,
				'filename'         => $file_name,
				'mime_type'        => $mime_type,
				'caption'          => $attachment->post_excerpt,
				'alt_text'         => $alt_text,
				'details_json'     => array(
					'upload_path'     => $file_path,
					'attachment_meta' => wp_get_attachment_metadata( $attachment->ID ),
					'file_size'       => ( $file_path && file_exists( $file_path ) ) ? filesize( $file_path ) : 0,
				),
			)
		);
	}
}

function mfw_audit_collect_menu_item_rows( &$artifacts, &$metadata ) {
	$menus = wp_get_nav_menus();
	if ( empty( $menus ) || is_wp_error( $menus ) ) {
		return;
	}
	foreach ( $menus as $menu ) {
		$menu_items = wp_get_nav_menu_items( $menu->term_id );
		if ( empty( $menu_items ) || is_wp_error( $menu_items ) ) {
			continue;
		}
		$menu_locations = array();
		$locations = get_nav_menu_locations();
		if ( is_array( $locations ) ) {
			foreach ( $locations as $location => $assigned_menu_id ) {
				if ( (int) $assigned_menu_id === (int) $menu->term_id ) {
					$menu_locations[] = $location;
				}
			}
		}
		foreach ( $menu_items as $menu_item ) {
			$related_object_id   = isset( $menu_item->object_id ) ? (int) $menu_item->object_id : 0;
			$related_object_type = isset( $menu_item->object ) ? $menu_item->object : '';
			mfw_audit_push_row(
				$artifacts,
				'menu_item',
				array(
					'environment_name'  => $metadata['environment_name'],
					'site_id'           => $metadata['site_id'],
					'record_type'       => 'menu_item',
					'object_type'       => 'menu_item',
					'object_id'         => $menu_item->ID,
					'title'             => $menu_item->title,
					'url'               => $menu_item->url,
					'parent_id'         => $menu_item->menu_item_parent,
					'related_object_id' => $related_object_id,
					'related_object_type' => $related_object_type,
					'menu_name'         => $menu->name,
					'menu_slug'         => $menu->slug,
					'menu_location'     => implode( ',', $menu_locations ),
					'details_json'      => array(
						'menu_order'  => $menu_item->menu_order,
						'target'      => $menu_item->target,
						'xfn'         => $menu_item->xfn,
						'classes'     => $menu_item->classes,
						'description' => $menu_item->description,
					),
				)
			);
		}
	}
}

function mfw_audit_collect_user_rows( &$artifacts, &$metadata ) {
	$user_args = array(
		'orderby' => 'ID',
		'order'   => 'ASC',
		'fields'  => 'all',
	);
	if ( is_multisite() ) {
		$user_args['blog_id'] = get_current_blog_id();
	}
	$users = get_users( $user_args );
	foreach ( $users as $user ) {
		$roles = is_array( $user->roles ) ? implode( ',', $user->roles ) : '';
		mfw_audit_push_row(
			$artifacts,
			'user',
			array(
				'environment_name' => $metadata['environment_name'],
				'site_id'          => $metadata['site_id'],
				'record_type'      => 'user',
				'object_type'      => 'user',
				'object_id'        => $user->ID,
				'title'            => $user->display_name ? $user->display_name : $user->user_login,
				'role'             => $roles,
				'registered_at'    => $user->user_registered,
				'details_json'     => array(
					'user_login'   => $user->user_login,
					'user_email'   => $user->user_email,
					'display_name' => $user->display_name,
					'roles'        => $user->roles,
				),
			)
		);
	}
}

function mfw_audit_is_gravity_forms_available() {
	return class_exists( 'GFAPI' );
}

function mfw_audit_get_gf_form_status( $form ) {
	return ! empty( $form['is_active'] ) ? 'active' : 'inactive';
}

function mfw_audit_gf_field_has_conditional_logic( $field ) {
	if ( is_object( $field ) ) {
		if ( ! empty( $field->conditionalLogic ) ) {
			return true;
		}
		if ( ! empty( $field->conditional_logic ) ) {
			return true;
		}
		return false;
	}

	if ( is_array( $field ) ) {
		return ! empty( $field['conditionalLogic'] ) || ! empty( $field['conditional_logic'] );
	}

	return false;
}

function mfw_audit_summarize_gf_field( $field ) {
	$label = '';
	if ( is_object( $field ) ) {
		$label = isset( $field->label ) ? $field->label : '';
	} elseif ( is_array( $field ) ) {
		$label = isset( $field['label'] ) ? $field['label'] : '';
	}

	return array(
		'id'                   => is_object( $field ) && isset( $field->id ) ? $field->id : ( is_array( $field ) && isset( $field['id'] ) ? $field['id'] : '' ),
		'type'                 => is_object( $field ) && isset( $field->type ) ? $field->type : ( is_array( $field ) && isset( $field['type'] ) ? $field['type'] : '' ),
		'label'                => $label,
		'adminLabel'           => is_object( $field ) && isset( $field->adminLabel ) ? $field->adminLabel : ( is_array( $field ) && isset( $field['adminLabel'] ) ? $field['adminLabel'] : '' ),
		'isRequired'           => is_object( $field ) && isset( $field->isRequired ) ? (bool) $field->isRequired : ( is_array( $field ) && ! empty( $field['isRequired'] ) ),
		'hasConditionalLogic'  => mfw_audit_gf_field_has_conditional_logic( $field ),
	);
}

function mfw_audit_summarize_gf_notification( $notification ) {
	return array(
		'name'             => isset( $notification['name'] ) ? $notification['name'] : '',
		'isActive'         => isset( $notification['isActive'] ) ? (bool) $notification['isActive'] : false,
		'event'            => isset( $notification['event'] ) ? $notification['event'] : '',
		'to'               => isset( $notification['to'] ) ? $notification['to'] : '',
		'subject'          => isset( $notification['subject'] ) ? $notification['subject'] : '',
		'message'          => isset( $notification['message'] ) ? $notification['message'] : '',
		'from'             => isset( $notification['from'] ) ? $notification['from'] : '',
		'replyTo'          => isset( $notification['replyTo'] ) ? $notification['replyTo'] : '',
		'cc'               => isset( $notification['cc'] ) ? $notification['cc'] : '',
		'bcc'              => isset( $notification['bcc'] ) ? $notification['bcc'] : '',
		'conditionalLogic' => isset( $notification['conditionalLogic'] ) ? $notification['conditionalLogic'] : array(),
	);
}

function mfw_audit_summarize_gf_confirmation( $confirmation ) {
	return array(
		'name'             => isset( $confirmation['name'] ) ? $confirmation['name'] : '',
		'type'             => isset( $confirmation['type'] ) ? $confirmation['type'] : '',
		'message'          => isset( $confirmation['message'] ) ? $confirmation['message'] : '',
		'url'              => isset( $confirmation['url'] ) ? $confirmation['url'] : '',
		'pageId'           => isset( $confirmation['pageId'] ) ? $confirmation['pageId'] : '',
		'queryString'      => isset( $confirmation['queryString'] ) ? $confirmation['queryString'] : '',
		'isDefault'        => ! empty( $confirmation['isDefault'] ),
		'conditionalLogic' => isset( $confirmation['conditionalLogic'] ) ? $confirmation['conditionalLogic'] : array(),
	);
}

function mfw_audit_collect_gravity_forms_rows( &$artifacts, &$metadata ) {
	if ( ! mfw_audit_is_gravity_forms_available() ) {
		return;
	}

	$total = 0;
	$forms = GFAPI::get_forms( null, null, null, $total );
	if ( is_wp_error( $forms ) || ! is_array( $forms ) ) {
		return;
	}

	foreach ( $forms as $form_summary ) {
		$form_id = isset( $form_summary['id'] ) ? absint( $form_summary['id'] ) : 0;
		if ( ! $form_id ) {
			continue;
		}

		$form = GFAPI::get_form( $form_id );
		if ( is_wp_error( $form ) || empty( $form ) ) {
			continue;
		}

		$form_status = mfw_audit_get_gf_form_status( $form );
		$form_url     = admin_url( 'admin.php?page=gf_edit_forms&id=' . $form_id );
		$fields       = isset( $form['fields'] ) && is_array( $form['fields'] ) ? $form['fields'] : array();
		$notifications = isset( $form['notifications'] ) && is_array( $form['notifications'] ) ? $form['notifications'] : array();
		$confirmations  = isset( $form['confirmations'] ) && is_array( $form['confirmations'] ) ? $form['confirmations'] : array();

		mfw_audit_push_row(
			$artifacts,
			'gravity_forms',
			array(
				'environment_name' => $metadata['environment_name'],
				'site_id'          => $metadata['site_id'],
				'record_type'      => 'gravity_form',
				'object_type'      => 'gravity_form',
				'object_id'        => $form_id,
				'form_id'          => $form_id,
				'status'           => $form_status,
				'title'            => isset( $form['title'] ) ? $form['title'] : '',
				'description'      => isset( $form['description'] ) ? wp_strip_all_tags( (string) $form['description'] ) : '',
				'url'              => $form_url,
				'field_id'         => '',
				'field_type'       => '',
				'field_label'      => '',
				'has_conditional_logic' => '',
				'notification_name' => '',
				'confirmation_name' => '',
				'details_json'     => array(
					'button'              => isset( $form['button'] ) ? $form['button'] : array(),
					'labelPlacement'      => isset( $form['labelPlacement'] ) ? $form['labelPlacement'] : '',
					'descriptionPlacement' => isset( $form['descriptionPlacement'] ) ? $form['descriptionPlacement'] : '',
					'cssClass'            => isset( $form['cssClass'] ) ? $form['cssClass'] : '',
					'is_active'           => ! empty( $form['is_active'] ),
					'fields'              => array_map( 'mfw_audit_summarize_gf_field', $fields ),
					'notifications_count'  => count( $notifications ),
					'confirmations_count'  => count( $confirmations ),
				),
			)
		);

		foreach ( $fields as $field ) {
			$field_id    = is_object( $field ) && isset( $field->id ) ? $field->id : ( is_array( $field ) && isset( $field['id'] ) ? $field['id'] : '' );
			$field_type  = is_object( $field ) && isset( $field->type ) ? $field->type : ( is_array( $field ) && isset( $field['type'] ) ? $field['type'] : '' );
			$field_label = is_object( $field ) && isset( $field->label ) ? $field->label : ( is_array( $field ) && isset( $field['label'] ) ? $field['label'] : '' );
			$has_logic   = mfw_audit_gf_field_has_conditional_logic( $field ) ? 'yes' : 'no';

			mfw_audit_push_row(
				$artifacts,
				'gravity_forms',
				array(
					'environment_name' => $metadata['environment_name'],
					'site_id'          => $metadata['site_id'],
					'record_type'      => 'gravity_field',
					'object_type'      => 'gravity_field',
					'object_id'        => $form_id . ':' . $field_id,
					'form_id'          => $form_id,
					'status'           => $form_status,
					'title'            => $field_label,
					'description'      => '',
					'url'              => $form_url,
					'field_id'         => $field_id,
					'field_type'       => $field_type,
					'field_label'      => $field_label,
					'has_conditional_logic' => $has_logic,
					'notification_name' => '',
					'confirmation_name' => '',
					'details_json'     => array(
						'isRequired'           => is_object( $field ) && isset( $field->isRequired ) ? (bool) $field->isRequired : ( is_array( $field ) && ! empty( $field['isRequired'] ) ),
						'adminLabel'           => is_object( $field ) && isset( $field->adminLabel ) ? $field->adminLabel : ( is_array( $field ) && isset( $field['adminLabel'] ) ? $field['adminLabel'] : '' ),
						'visibility'           => is_object( $field ) && isset( $field->visibility ) ? $field->visibility : '',
						'placeholder'          => is_object( $field ) && isset( $field->placeholder ) ? $field->placeholder : '',
						'description'          => is_object( $field ) && isset( $field->description ) ? $field->description : '',
						'choices'              => is_object( $field ) && isset( $field->choices ) ? $field->choices : ( is_array( $field ) && isset( $field['choices'] ) ? $field['choices'] : array() ),
						'conditionalLogic'     => is_object( $field ) && isset( $field->conditionalLogic ) ? $field->conditionalLogic : ( is_array( $field ) && isset( $field['conditionalLogic'] ) ? $field['conditionalLogic'] : array() ),
						'conditional_logic'    => is_object( $field ) && isset( $field->conditional_logic ) ? $field->conditional_logic : ( is_array( $field ) && isset( $field['conditional_logic'] ) ? $field['conditional_logic'] : array() ),
					),
				)
			);
		}

		foreach ( $notifications as $notification_key => $notification ) {
			$notification_name = isset( $notification['name'] ) ? $notification['name'] : $notification_key;
			$enabled           = ! empty( $notification['isActive'] ) ? 'active' : 'inactive';

			mfw_audit_push_row(
				$artifacts,
				'gravity_forms',
				array(
					'environment_name' => $metadata['environment_name'],
					'site_id'          => $metadata['site_id'],
					'record_type'      => 'gravity_notification',
					'object_type'      => 'gravity_notification',
					'object_id'        => $form_id . ':' . $notification_key,
					'form_id'          => $form_id,
					'status'           => $enabled,
					'title'            => $notification_name,
					'description'      => '',
					'url'              => $form_url,
					'field_id'         => '',
					'field_type'       => '',
					'field_label'      => '',
					'has_conditional_logic' => ! empty( $notification['conditionalLogic'] ) ? 'yes' : 'no',
					'notification_name' => $notification_name,
					'confirmation_name' => '',
					'details_json'     => mfw_audit_summarize_gf_notification( $notification ),
				)
			);
		}

		foreach ( $confirmations as $confirmation_key => $confirmation ) {
			$confirmation_name = isset( $confirmation['name'] ) ? $confirmation['name'] : $confirmation_key;
			$confirmation_type = isset( $confirmation['type'] ) ? $confirmation['type'] : 'custom';

			mfw_audit_push_row(
				$artifacts,
				'gravity_forms',
				array(
					'environment_name' => $metadata['environment_name'],
					'site_id'          => $metadata['site_id'],
					'record_type'      => 'gravity_confirmation',
					'object_type'      => 'gravity_confirmation',
					'object_id'        => $form_id . ':' . $confirmation_key,
					'form_id'          => $form_id,
					'status'           => $confirmation_type,
					'title'            => $confirmation_name,
					'description'      => '',
					'url'              => $form_url,
					'field_id'         => '',
					'field_type'       => '',
					'field_label'      => '',
					'has_conditional_logic' => ! empty( $confirmation['conditionalLogic'] ) ? 'yes' : 'no',
					'notification_name' => '',
					'confirmation_name' => $confirmation_name,
					'details_json'     => mfw_audit_summarize_gf_confirmation( $confirmation ),
				)
			);
		}
	}
}

function mfw_audit_get_public_taxonomy_landing_count() {
	$count = 0;
	foreach ( get_taxonomies( array( 'public' => true, 'show_ui' => true ), 'objects' ) as $taxonomy => $object ) {
		if ( ! mfw_audit_is_system_taxonomy( $taxonomy ) ) {
			$count++;
		}
	}
	return $count;
}

function mfw_audit_get_general_archive_count() {
	$count = 0;
	foreach ( get_post_types( array( 'public' => true ), 'objects' ) as $post_type => $object ) {
		if ( ! mfw_audit_is_system_post_type( $post_type ) && ! empty( $object->has_archive ) ) {
			$count++;
		}
	}
	if ( 'posts' === get_option( 'show_on_front' ) || get_option( 'page_for_posts' ) ) {
		$count++;
	}
	return $count;
}

function mfw_audit_count_posts_by_statuses( $post_types, $statuses ) {
	if ( empty( $post_types ) || empty( $statuses ) ) {
		return 0;
	}

	$query = new WP_Query(
		array(
			'post_type'              => $post_types,
			'post_status'            => $statuses,
			'posts_per_page'         => -1,
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		)
	);

	return (int) $query->post_count;
}

function mfw_build_audit_export_dataset() {
	$context       = mfw_audit_get_export_context();
	$artifacts     = array();
	$content_posts = array();

	mfw_audit_collect_content_rows( $artifacts, $context, $content_posts );
	mfw_audit_collect_taxonomy_term_rows( $artifacts, $context );
	mfw_audit_collect_taxonomy_relationship_rows( $artifacts, $context, $content_posts );
	mfw_audit_collect_media_rows( $artifacts, $context );
	mfw_audit_collect_menu_item_rows( $artifacts, $context );
	mfw_audit_collect_user_rows( $artifacts, $context );
	mfw_audit_collect_gravity_forms_rows( $artifacts, $context );

	$row_counts = array();
	foreach ( mfw_get_audit_artifacts() as $artifact_key => $definition ) {
		$row_counts[ $artifact_key ] = isset( $artifacts[ $artifact_key ] ) ? count( $artifacts[ $artifact_key ] ) : 0;
	}
	$context['row_counts'] = $row_counts;
	$context['row_total']   = array_sum( $row_counts );

	$content_types   = ! empty( $context['detected_cpts'] ) ? $context['detected_cpts'] : mfw_audit_get_content_types( $context );
	$published_like  = 0;
	if ( isset( $artifacts['content'] ) ) {
		foreach ( $artifacts['content'] as $row ) {
			$status = isset( $row['status'] ) ? $row['status'] : '';
			if ( in_array( $status, array( 'publish', 'private', 'future', 'pending' ), true ) ) {
				$published_like++;
			}
		}
	}
	$draft_trash = mfw_audit_count_posts_by_statuses( $content_types, array( 'draft', 'trash' ) );

	$taxonomy_term_pages    = isset( $row_counts['taxonomy_term'] ) ? (int) $row_counts['taxonomy_term'] : 0;
	$taxonomy_landing_pages = mfw_audit_get_public_taxonomy_landing_count();
	$general_archive_pages  = mfw_audit_get_general_archive_count();
	$content_views_total     = $published_like + $taxonomy_term_pages + $taxonomy_landing_pages + $general_archive_pages;

	$summary = array(
		'published_like_content' => $published_like,
		'taxonomy_term_pages'    => $taxonomy_term_pages,
		'taxonomy_landing_pages' => $taxonomy_landing_pages,
		'general_archive_pages'  => $general_archive_pages,
		'content_total'         => $content_views_total,
		'draft_trash_content'   => $draft_trash,
	);

	$metadata = array(
		'environment_name'        => $context['environment_name'],
		'site_id'                 => $context['site_id'],
		'site_name'               => $context['site_name'],
		'site_url'                => $context['site_url'],
		'generated_at'            => $context['generated_at'],
		'generated_at_gmt'        => $context['generated_at_gmt'],
		'generated_by'            => $context['generated_by'],
		'detected_plugins'        => $context['detected_plugins'],
		'detected_cpts'           => $context['detected_cpts'],
		'unexpected_content_types' => isset( $context['unexpected_content_types'] ) ? $context['unexpected_content_types'] : array(),
		'row_total'               => $context['row_total'],
		'row_counts'              => $row_counts,
		'summary'                 => $summary,
		'warnings'                => $context['warnings'],
		'files'                   => array(),
	);

	return array(
		'context'   => $context,
		'artifacts' => $artifacts,
		'metadata'  => $metadata,
		'summary'   => $summary,
	);
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

function mfw_audit_get_site_slug() {
	$site_name = get_bloginfo( 'name' );
	if ( '' === trim( $site_name ) ) {
		$host      = wp_parse_url( home_url( '/' ), PHP_URL_HOST );
		$site_name = $host ? $host : 'site';
	}
	return sanitize_title( $site_name );
}

function mfw_audit_get_export_prefix() {
	return mfw_audit_get_site_slug() . '-' . current_time( 'Y-m-d-His' );
}

function mfw_generate_audit_export_bundle() {
	$export_prefix = mfw_audit_get_export_prefix();
	$dataset       = mfw_build_audit_export_dataset();
	$context       = $dataset['context'];
	$artifacts     = $dataset['artifacts'];
	$metadata      = $dataset['metadata'];
	$summary       = $dataset['summary'];
	$storage       = mfw_audit_get_storage_paths( $export_prefix );
	if ( is_wp_error( $storage ) ) {
		return $storage;
	}

	$bundle_files = array();
	foreach ( mfw_get_audit_artifacts() as $artifact_key => $definition ) {
		if ( empty( $artifacts[ $artifact_key ] ) ) {
			continue;
		}
		$file_name = $export_prefix . '-' . $definition['filename'];
		$file_path = trailingslashit( $storage['dir'] ) . $file_name;
		$file_url  = trailingslashit( $storage['url'] ) . rawurlencode( $file_name );
		$result    = mfw_write_audit_csv( $file_path, $artifacts[ $artifact_key ], $definition['columns'] );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$bundle_files[] = array(
			'type' => $artifact_key,
			'name' => $file_name,
			'path' => $file_path,
			'url'  => $file_url,
			'size' => file_exists( $file_path ) ? filesize( $file_path ) : 0,
			'rows' => isset( $context['row_counts'][ $artifact_key ] ) ? (int) $context['row_counts'][ $artifact_key ] : 0,
		);
	}

	$metadata_file_name = $export_prefix . '-metadata.json';
	$metadata_file_path = trailingslashit( $storage['dir'] ) . $metadata_file_name;
	$metadata_file_url  = trailingslashit( $storage['url'] ) . rawurlencode( $metadata_file_name );
	$metadata['files']  = $bundle_files;
	$metadata['summary'] = $summary;
	$metadata_result     = mfw_write_audit_json( $metadata_file_path, $metadata );
	if ( is_wp_error( $metadata_result ) ) {
		return $metadata_result;
	}
	$bundle_files[] = array(
		'type' => 'metadata',
		'name' => $metadata_file_name,
		'path' => $metadata_file_path,
		'url'  => $metadata_file_url,
		'size' => file_exists( $metadata_file_path ) ? filesize( $metadata_file_path ) : 0,
		'rows' => 1,
	);

	$zip_file_name = $export_prefix . '.zip';
	$zip_file_path = trailingslashit( $storage['dir'] ) . $zip_file_name;
	$zip_file_url  = trailingslashit( $storage['url'] ) . rawurlencode( $zip_file_name );
	$zip_created   = mfw_create_audit_zip(
		$zip_file_path,
		array_map(
			static function ( $file ) {
				return array(
					'name' => $file['name'],
					'path' => $file['path'],
				);
			},
			$bundle_files
		)
	);
	if ( $zip_created ) {
		$bundle_files[] = array(
			'type' => 'zip',
			'name' => $zip_file_name,
			'path' => $zip_file_path,
			'url'  => $zip_file_url,
			'size' => file_exists( $zip_file_path ) ? filesize( $zip_file_path ) : 0,
			'rows' => 0,
		);
	}

	$record = array(
		'export_id'               => $export_prefix,
		'generated_at'            => $context['generated_at'],
		'generated_at_gmt'        => $context['generated_at_gmt'],
		'generated_by'            => $context['generated_by'],
		'row_total'               => $context['row_total'],
		'row_counts'              => $context['row_counts'],
		'summary'                 => $summary,
		'detected_plugins'        => $context['detected_plugins'],
		'detected_cpts'           => $context['detected_cpts'],
		'unexpected_content_types' => isset( $context['unexpected_content_types'] ) ? $context['unexpected_content_types'] : array(),
		'warnings'                => $context['warnings'],
		'files'                   => $bundle_files,
	);
	mfw_add_audit_history_record( $record );
	return array(
		'record'  => $record,
		'files'   => $bundle_files,
		'summary' => $summary,
	);
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

function mfw_get_audit_record_summary( $record ) {
	$summary    = isset( $record['summary'] ) && is_array( $record['summary'] ) ? $record['summary'] : array();
	$row_counts = isset( $record['row_counts'] ) && is_array( $record['row_counts'] ) ? $record['row_counts'] : array();

	$summary['published_like_content'] = isset( $summary['published_like_content'] ) ? (int) $summary['published_like_content'] : 0;
	$summary['taxonomy_term_pages']    = isset( $summary['taxonomy_term_pages'] ) ? (int) $summary['taxonomy_term_pages'] : ( isset( $row_counts['taxonomy_term'] ) ? (int) $row_counts['taxonomy_term'] : 0 );
	$summary['taxonomy_landing_pages'] = isset( $summary['taxonomy_landing_pages'] ) ? (int) $summary['taxonomy_landing_pages'] : mfw_audit_get_public_taxonomy_landing_count();
	$summary['general_archive_pages']  = isset( $summary['general_archive_pages'] ) ? (int) $summary['general_archive_pages'] : mfw_audit_get_general_archive_count();
	$summary['draft_trash_content']    = isset( $summary['draft_trash_content'] ) ? (int) $summary['draft_trash_content'] : 0;
	$summary['content_total']          = isset( $summary['content_total'] ) ? (int) $summary['content_total'] : ( $summary['published_like_content'] + $summary['taxonomy_term_pages'] + $summary['taxonomy_landing_pages'] + $summary['general_archive_pages'] );

	return $summary;
}

function mfw_render_audit_summary_table( $summary ) {
	if ( empty( $summary ) || ! is_array( $summary ) ) {
		return;
	}
	?>
	<table class="widefat striped" style="max-width: 900px; margin: 1rem 0 2rem;">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Content views', 'migration-freeze-webspark' ); ?></th>
				<th><?php esc_html_e( 'Count', 'migration-freeze-webspark' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<tr>
				<td><?php esc_html_e( 'Published / Private / Future / Pending', 'migration-freeze-webspark' ); ?></td>
				<td><?php echo esc_html( $summary['published_like_content'] ); ?></td>
			</tr>
			<tr>
				<td><?php esc_html_e( 'Taxonomy term archive pages', 'migration-freeze-webspark' ); ?></td>
				<td><?php echo esc_html( $summary['taxonomy_term_pages'] ); ?></td>
			</tr>
			<tr>
				<td><?php esc_html_e( 'Taxonomy landing pages', 'migration-freeze-webspark' ); ?></td>
				<td><?php echo esc_html( $summary['taxonomy_landing_pages'] ); ?></td>
			</tr>
			<tr>
				<td><?php esc_html_e( 'General archive / index pages', 'migration-freeze-webspark' ); ?></td>
				<td><?php echo esc_html( $summary['general_archive_pages'] ); ?></td>
			</tr>
			<tr style="font-weight:700;">
				<td><?php esc_html_e( 'Content views', 'migration-freeze-webspark' ); ?></td>
				<td><?php echo esc_html( $summary['content_total'] ); ?></td>
			</tr>
			<tr style="color:#b32d2e;">
				<td><?php esc_html_e( 'Drafts + Trash', 'migration-freeze-webspark' ); ?></td>
				<td><?php echo esc_html( $summary['draft_trash_content'] ); ?></td>
			</tr>
		</tbody>
	</table>
	<p class="description" style="max-width: 900px; margin-top: 0.75rem;">The displayed summary comes from the latest export record stored in history, not a separate live query outside the exporter flow. Generate another report to refresh the numbers.</p>
	<?php
}

function mfw_render_audit_trail_page() {
	$history = mfw_get_audit_history();
	$notice  = mfw_get_audit_notice();
	$latest  = ! empty( $history ) ? $history[0] : array();
	$summary = mfw_get_audit_record_summary( $latest );
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Migration Audit Trail', 'migration-freeze-webspark' ); ?></h1>
		<p><?php esc_html_e( 'Generate separate export artifacts for content, taxonomies, taxonomy relationships, media, menu items, Gravity Forms, and users.', 'migration-freeze-webspark' ); ?></p>

		<?php if ( ! empty( $notice ) ) : ?>
			<div class="notice <?php echo esc_attr( 'error' === $notice['type'] ? 'notice-error' : 'notice-success' ); ?> is-dismissible"><p><?php echo esc_html( $notice['message'] ); ?></p></div>
		<?php endif; ?>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin: 1rem 0 2rem;">
			<?php wp_nonce_field( 'mfw_generate_audit_export', 'mfw_audit_nonce' ); ?>
			<input type="hidden" name="action" value="mfw_generate_audit_export" />
			<?php submit_button( __( 'Generate Audit Export', 'migration-freeze-webspark' ), 'primary', 'submit', false ); ?>
		</form>

		<?php mfw_render_audit_summary_table( $summary ); ?>

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
								<?php if ( ! empty( $record['export_id'] ) ) : ?><br /><code><?php echo esc_html( $record['export_id'] ); ?></code><?php endif; ?>
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
										echo '<div><a href="' . esc_url( $file['url'] ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( strtoupper( $file['type'] ) ) . '</a>' . ( ! empty( $file['size'] ) ? ' <span>(' . esc_html( size_format( (int) $file['size'] ) ) . ')</span>' : '' ) . '</div>';
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
