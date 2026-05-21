<?php
/**
 * Menu audit export helpers.
 *
 * Rewrites the generated menu-item CSV with menu purpose metadata so the
 * audit export can distinguish CTA, social, and nav roles.
 *
 * @package migration_freeze_webspark
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function mfw_menu_audit_is_truthy( $value ) {
	if ( is_bool( $value ) ) {
		return $value;
	}
	if ( is_numeric( $value ) ) {
		return (int) $value > 0;
	}
	$value = strtolower( trim( (string) $value ) );
	return in_array( $value, array( '1', 'true', 'yes', 'on' ), true );
}

function mfw_menu_audit_normalize_color( $value ) {
	$value = strtolower( trim( (string) $value ) );
	if ( 'dark' === $value ) {
		return 'maroon';
	}
	return $value;
}

function mfw_menu_audit_path_to_url( $path ) {
	$uploads = wp_upload_dir();
	if ( empty( $uploads['basedir'] ) || empty( $uploads['baseurl'] ) ) {
		return '';
	}

	$basedir = wp_normalize_path( $uploads['basedir'] );
	$path    = wp_normalize_path( $path );
	if ( 0 !== strpos( $path, $basedir ) ) {
		return '';
	}

	$relative = ltrim( substr( $path, strlen( $basedir ) ), '/' );
	return trailingslashit( $uploads['baseurl'] ) . str_replace( '%2F', '/', rawurlencode( $relative ) );
}

function mfw_menu_audit_get_export_columns() {
	return array(
		'environment_name',
		'site_id',
		'record_type',
		'object_type',
		'object_id',
		'title',
		'url',
		'parent_id',
		'related_object_id',
		'related_object_type',
		'menu_name',
		'menu_slug',
		'menu_location',
		'menu_purpose',
		'menu_cta_button',
		'menu_cta_button_color',
		'menu_target_blank',
		'uds_menu_item_type',
		'menu_social_media_icon',
		'details_json',
	);
}

function mfw_menu_audit_get_meta_value( $post_id, $key ) {
	$value = get_post_meta( $post_id, $key, true );
	if ( is_array( $value ) || is_object( $value ) ) {
		return $value;
	}

	if ( '' !== (string) $value && null !== $value ) {
		return $value;
	}

	if ( function_exists( 'get_field' ) ) {
		$field_value = get_field( $key, $post_id );
		if ( null !== $field_value && '' !== $field_value && ! is_wp_error( $field_value ) ) {
			return $field_value;
		}
	}

	return '';
}

function mfw_menu_audit_parse_social_icon( $value ) {
	if ( is_array( $value ) ) {
		return array(
			'value' => isset( $value['value'] ) ? (string) $value['value'] : '',
			'label' => isset( $value['label'] ) ? (string) $value['label'] : '',
		);
	}

	return array(
		'value' => (string) $value,
		'label' => '',
	);
}

function mfw_menu_audit_get_purpose( $menu_item, $menu_location, $cta_button, $cta_icon, $menu_item_type ) {
	if ( ! empty( $cta_icon['value'] ) ) {
		return 'social_link';
	}

	if ( mfw_menu_audit_is_truthy( $cta_button ) ) {
		return 'cta_button';
	}

	if ( 'button' === strtolower( (string) $menu_item_type ) ) {
		return 'submenu_button';
	}

	if ( 'heading' === strtolower( (string) $menu_item_type ) ) {
		return 'submenu_heading';
	}

	$menu_location = strtolower( (string) $menu_location );
	if ( false !== strpos( $menu_location, 'social' ) ) {
		return 'social_link';
	}

	return 'nav_link';
}

function mfw_menu_audit_get_rows() {
	$rows = array();
	$menus = wp_get_nav_menus();
	if ( empty( $menus ) || is_wp_error( $menus ) ) {
		return $rows;
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

		$menu_location_string = implode( ',', $menu_locations );

		foreach ( $menu_items as $menu_item ) {
			$related_object_id   = isset( $menu_item->object_id ) ? (int) $menu_item->object_id : 0;
			$related_object_type = isset( $menu_item->object ) ? $menu_item->object : '';
			$cta_button          = mfw_menu_audit_get_meta_value( $menu_item->ID, 'menu_cta_button' );
			$cta_button_color    = mfw_menu_audit_normalize_color( mfw_menu_audit_get_meta_value( $menu_item->ID, 'menu_cta_button_color' ) );
			$cta_target_blank    = mfw_menu_audit_get_meta_value( $menu_item->ID, 'menu_target_blank' );
			$menu_item_type      = mfw_menu_audit_get_meta_value( $menu_item->ID, 'uds_menu_item_type' );
			$social_icon         = mfw_menu_audit_parse_social_icon( mfw_menu_audit_get_meta_value( $menu_item->ID, 'menu_social_media_icon' ) );
			$purpose             = mfw_menu_audit_get_purpose( $menu_item, $menu_location_string, $cta_button, $social_icon, $menu_item_type );

			$rows[] = array(
				'environment_name'   => $GLOBALS['mfw_menu_audit_environment_name'] ?? '',
				'site_id'            => $GLOBALS['mfw_menu_audit_site_id'] ?? 0,
				'record_type'        => 'menu_item',
				'object_type'        => 'menu_item',
				'object_id'          => $menu_item->ID,
				'title'              => $menu_item->title,
				'url'                => $menu_item->url,
				'parent_id'          => $menu_item->menu_item_parent,
				'related_object_id'  => $related_object_id,
				'related_object_type' => $related_object_type,
				'menu_name'          => $menu->name,
				'menu_slug'          => $menu->slug,
				'menu_location'      => $menu_location_string,
				'menu_purpose'       => $purpose,
				'menu_cta_button'    => mfw_menu_audit_is_truthy( $cta_button ) ? 'yes' : 'no',
				'menu_cta_button_color' => $cta_button_color,
				'menu_target_blank'  => mfw_menu_audit_is_truthy( $cta_target_blank ) ? 'yes' : 'no',
				'uds_menu_item_type' => (string) $menu_item_type,
				'menu_social_media_icon' => $social_icon['value'],
				'details_json'       => array(
					'menu_order'             => $menu_item->menu_order,
					'target'                 => $menu_item->target,
					'xfn'                    => $menu_item->xfn,
					'classes'                => $menu_item->classes,
					'description'            => $menu_item->description,
					'menu_cta_button'        => mfw_menu_audit_is_truthy( $cta_button ),
					'menu_cta_button_color'  => $cta_button_color,
					'menu_target_blank'      => mfw_menu_audit_is_truthy( $cta_target_blank ),
					'uds_menu_item_type'     => (string) $menu_item_type,
					'menu_social_media_icon' => $social_icon,
				),
			);
		}
	}

	return $rows;
}

function mfw_menu_audit_rewrite_export() {
	if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : '';
	if ( 'mfw_generate_audit_export' !== $action ) {
		return;
	}

	$history = mfw_get_audit_history();
	if ( empty( $history ) || empty( $history[0] ) || ! is_array( $history[0] ) ) {
		return;
	}

	$record = $history[0];
	if ( empty( $record['files'] ) || ! is_array( $record['files'] ) ) {
		return;
	}

	$menu_file = null;
	$zip_file = null;
	$metadata_file = null;
	foreach ( $record['files'] as $file ) {
		if ( ! empty( $file['type'] ) && 'menu_item' === $file['type'] ) {
			$menu_file = $file;
		}
		if ( ! empty( $file['type'] ) && 'zip' === $file['type'] ) {
			$zip_file = $file;
		}
		if ( ! empty( $file['type'] ) && 'metadata' === $file['type'] ) {
			$metadata_file = $file;
		}
	}

	if ( empty( $menu_file['path'] ) ) {
		return;
	}

	$GLOBALS['mfw_menu_audit_environment_name'] = isset( $record['summary']['environment_name'] ) ? $record['summary']['environment_name'] : ( get_bloginfo( 'name' ) ? get_bloginfo( 'name' ) : 'site-' . get_current_blog_id() );
	$GLOBALS['mfw_menu_audit_site_id'] = get_current_blog_id();

	$rows = mfw_menu_audit_get_rows();
	if ( empty( $rows ) ) {
		return;
	}

	$columns = mfw_menu_audit_get_export_columns();
	$result  = mfw_write_audit_csv( $menu_file['path'], $rows, $columns );
	if ( is_wp_error( $result ) || true !== $result ) {
		return;
	}

	$new_size = file_exists( $menu_file['path'] ) ? filesize( $menu_file['path'] ) : 0;
	foreach ( $record['files'] as &$file ) {
		if ( ! empty( $file['type'] ) && 'menu_item' === $file['type'] ) {
			$file['size'] = $new_size;
			$file['rows'] = count( $rows );
			$file['url']  = mfw_menu_audit_path_to_url( $menu_file['path'] );
		}
	}
	unset( $file );

	if ( ! empty( $metadata_file['path'] ) && is_readable( $metadata_file['path'] ) ) {
		$metadata_json = json_decode( file_get_contents( $metadata_file['path'] ), true );
		if ( is_array( $metadata_json ) ) {
			foreach ( $metadata_json['files'] as &$file ) {
				if ( ! empty( $file['type'] ) && 'menu_item' === $file['type'] ) {
					$file['size'] = $new_size;
					$file['rows'] = count( $rows );
					$file['url']  = mfw_menu_audit_path_to_url( $menu_file['path'] );
				}
			}
			unset( $file );
			file_put_contents( $metadata_file['path'], wp_json_encode( $metadata_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
		}
	}

	if ( ! empty( $zip_file['path'] ) && class_exists( 'ZipArchive' ) ) {
		$zip = new ZipArchive();
		if ( true === $zip->open( $zip_file['path'], ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
			foreach ( $record['files'] as $file ) {
				if ( empty( $file['path'] ) || $file['path'] === $zip_file['path'] ) {
					continue;
				}
				if ( file_exists( $file['path'] ) ) {
					$zip->addFile( $file['path'], $file['name'] );
				}
			}
			$zip->close();
		}
		foreach ( $record['files'] as &$file ) {
			if ( ! empty( $file['type'] ) && 'zip' === $file['type'] ) {
				$file['size'] = file_exists( $zip_file['path'] ) ? filesize( $zip_file['path'] ) : 0;
			}
		}
		unset( $file );
	}

	$history[0] = $record;
	mfw_save_audit_history( $history );
}
add_action( 'shutdown', 'mfw_menu_audit_rewrite_export', 23 );