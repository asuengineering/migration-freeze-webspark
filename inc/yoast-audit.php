<?php
/**
 * Yoast SEO audit export helpers.
 *
 * Generates a supplemental SEO artifact by post-processing the latest export bundle.
 *
 * @package migration_freeze_webspark
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function mfw_yoast_is_available() {
	return class_exists( 'WPSEO_Options' ) || defined( 'WPSEO_VERSION' ) || mfw_audit_is_plugin_active( 'wordpress-seo/wp-seo.php' );
}

function mfw_yoast_get_export_columns() {
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
		'taxonomy',
		'term_id',
		'seo_title',
		'seo_description',
		'seo_canonical',
		'seo_noindex',
		'seo_nofollow',
		'seo_focus_keyword',
		'seo_cornerstone',
		'seo_og_title',
		'seo_og_description',
		'seo_og_image',
		'seo_twitter_title',
		'seo_twitter_description',
		'seo_twitter_image',
		'source',
		'details_json',
	);
}

function mfw_yoast_get_meta_value( $meta, $keys ) {
	foreach ( (array) $keys as $key ) {
		if ( ! isset( $meta[ $key ] ) ) {
			continue;
		}

		$value = $meta[ $key ];
		if ( is_array( $value ) ) {
			$value = reset( $value );
		}

		if ( '' !== (string) $value && null !== $value ) {
			return $value;
		}
	}

	return '';
}

function mfw_yoast_normalize_bool( $value ) {
	if ( is_bool( $value ) ) {
		return $value ? 'yes' : 'no';
	}

	if ( is_numeric( $value ) ) {
		return (int) $value ? 'yes' : 'no';
	}

	$value = strtolower( trim( (string) $value ) );
	if ( in_array( $value, array( 'yes', 'true', '1', 'on' ), true ) ) {
		return 'yes';
	}
	if ( in_array( $value, array( 'no', 'false', '0', 'off' ), true ) ) {
		return 'no';
	}

	return '';
}

function mfw_yoast_has_meaningful_seo_data( $row ) {
	$fields = array(
		'seo_title',
		'seo_description',
		'seo_canonical',
		'seo_focus_keyword',
		'seo_og_title',
		'seo_og_description',
		'seo_og_image',
		'seo_twitter_title',
		'seo_twitter_description',
		'seo_twitter_image',
	);

	foreach ( $fields as $field ) {
		if ( ! empty( $row[ $field ] ) ) {
			return true;
		}
	}

	if ( isset( $row['seo_cornerstone'] ) && 'yes' === $row['seo_cornerstone'] ) {
		return true;
	}

	if ( isset( $row['seo_noindex'] ) && 'yes' === $row['seo_noindex'] ) {
		return true;
	}

	if ( isset( $row['seo_nofollow'] ) && 'yes' === $row['seo_nofollow'] ) {
		return true;
	}

	return false;
}

function mfw_yoast_extract_post_meta( $post_id ) {
	$raw_meta = get_post_meta( $post_id );
	$filtered  = array();

	foreach ( $raw_meta as $key => $values ) {
		if ( 0 === strpos( $key, '_yoast_wpseo_' ) ) {
			$filtered[ $key ] = is_array( $values ) ? reset( $values ) : $values;
		}
	}

	return $filtered;
}

function mfw_yoast_extract_term_meta( $term_id, $taxonomy ) {
	$filtered = array();
	$raw_meta = get_term_meta( $term_id );

	foreach ( $raw_meta as $key => $values ) {
		if ( 0 === strpos( $key, '_yoast_wpseo_' ) ) {
			$filtered[ $key ] = is_array( $values ) ? reset( $values ) : $values;
		}
	}

	$taxonomy_meta = get_option( 'wpseo_taxonomy_meta', array() );
	if (
		is_array( $taxonomy_meta ) &&
		isset( $taxonomy_meta[ $taxonomy ] ) &&
		is_array( $taxonomy_meta[ $taxonomy ] ) &&
		isset( $taxonomy_meta[ $taxonomy ][ $term_id ] ) &&
		is_array( $taxonomy_meta[ $taxonomy ][ $term_id ] )
	) {
		$filtered = array_merge( $filtered, $taxonomy_meta[ $taxonomy ][ $term_id ] );
	}

	return $filtered;
}

function mfw_yoast_extract_site_settings() {
	$settings = array();

	foreach ( array( 'wpseo_titles', 'wpseo_social', 'wpseo' ) as $option_name ) {
		$value = get_option( $option_name, array() );
		if ( ! empty( $value ) ) {
			$settings[ $option_name ] = $value;
		}
	}

	return $settings;
}

function mfw_yoast_path_to_url( $path ) {
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

function mfw_yoast_build_row( $base, $overrides, $raw_meta, $source ) {
	$row = array_merge(
		array(
			'environment_name'       => '',
			'site_id'                => '',
			'record_type'            => '',
			'object_type'            => '',
			'object_id'              => '',
			'status'                 => '',
			'title'                  => '',
			'url'                    => '',
			'slug'                   => '',
			'taxonomy'               => '',
			'term_id'                => '',
			'seo_title'              => '',
			'seo_description'        => '',
			'seo_canonical'          => '',
			'seo_noindex'            => '',
			'seo_nofollow'           => '',
			'seo_focus_keyword'      => '',
			'seo_cornerstone'        => '',
			'seo_og_title'           => '',
			'seo_og_description'     => '',
			'seo_og_image'           => '',
			'seo_twitter_title'      => '',
			'seo_twitter_description' => '',
			'seo_twitter_image'      => '',
			'source'                 => $source,
			'details_json'           => array(),
		),
		$base,
		$overrides
	);

	$row['details_json'] = mfw_audit_json_encode(
		array_merge(
			array(
				'source'   => $source,
				'raw_meta' => $raw_meta,
			),
			isset( $row['details_json'] ) && is_array( $row['details_json'] ) ? $row['details_json'] : array()
		)
	);

	return $row;
}

function mfw_yoast_collect_post_rows( $context ) {
	$rows       = array();
	$post_types = get_post_types( array( 'show_ui' => true ), 'names' );
	$post_types = array_values(
		array_filter(
			$post_types,
			static function ( $post_type ) {
				return ! mfw_audit_is_system_post_type( $post_type );
			}
		)
	);

	foreach ( $post_types as $post_type ) {
		$posts = get_posts(
			array(
				'post_type'              => $post_type,
				'post_status'            => array( 'publish', 'future', 'draft', 'pending', 'private' ),
				'posts_per_page'         => -1,
				'orderby'                => 'ID',
				'order'                  => 'ASC',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		foreach ( $posts as $post ) {
			$raw_meta = mfw_yoast_extract_post_meta( $post->ID );
			if ( empty( $raw_meta ) ) {
				continue;
			}

			$row = mfw_yoast_build_row(
				array(
					'environment_name' => $context['environment_name'],
					'site_id'          => $context['site_id'],
					'record_type'      => 'yoast_post',
					'object_type'      => $post_type,
					'object_id'        => $post->ID,
					'status'           => $post->post_status,
					'title'            => get_the_title( $post ),
					'url'              => get_permalink( $post ),
					'slug'             => $post->post_name,
				),
				array(
					'seo_title'              => mfw_yoast_get_meta_value( $raw_meta, array( '_yoast_wpseo_title' ) ),
					'seo_description'        => mfw_yoast_get_meta_value( $raw_meta, array( '_yoast_wpseo_metadesc' ) ),
					'seo_canonical'          => mfw_yoast_get_meta_value( $raw_meta, array( '_yoast_wpseo_canonical' ) ),
					'seo_noindex'            => mfw_yoast_get_meta_value( $raw_meta, array( '_yoast_wpseo_meta-robots-noindex', '_yoast_wpseo_meta-robots-noindex-wpseo' ) ),
					'seo_nofollow'           => mfw_yoast_get_meta_value( $raw_meta, array( '_yoast_wpseo_meta-robots-nofollow' ) ),
					'seo_focus_keyword'      => mfw_yoast_get_meta_value( $raw_meta, array( '_yoast_wpseo_focuskw' ) ),
					'seo_cornerstone'        => mfw_yoast_normalize_bool( mfw_yoast_get_meta_value( $raw_meta, array( '_yoast_wpseo_is_cornerstone' ) ) ),
					'seo_og_title'           => mfw_yoast_get_meta_value( $raw_meta, array( '_yoast_wpseo_opengraph-title' ) ),
					'seo_og_description'     => mfw_yoast_get_meta_value( $raw_meta, array( '_yoast_wpseo_opengraph-description' ) ),
					'seo_og_image'           => mfw_yoast_get_meta_value( $raw_meta, array( '_yoast_wpseo_opengraph-image' ) ),
					'seo_twitter_title'      => mfw_yoast_get_meta_value( $raw_meta, array( '_yoast_wpseo_twitter-title' ) ),
					'seo_twitter_description' => mfw_yoast_get_meta_value( $raw_meta, array( '_yoast_wpseo_twitter-description' ) ),
					'seo_twitter_image'      => mfw_yoast_get_meta_value( $raw_meta, array( '_yoast_wpseo_twitter-image' ) ),
					'details_json'           => array(
						'post_type' => $post_type,
						'meta_keys'  => array_keys( $raw_meta ),
					),
				),
				$raw_meta,
				'post_meta'
			);

			if ( ! mfw_yoast_has_meaningful_seo_data( $row ) ) {
				continue;
			}

			$rows[] = $row;
		}
	}

	return $rows;
}

function mfw_yoast_collect_taxonomy_rows( $context ) {
	$rows      = array();
	$taxonomies = get_taxonomies( array( 'show_ui' => true ), 'names' );
	$taxonomies = array_values(
		array_filter(
			$taxonomies,
			static function ( $taxonomy ) {
				return ! mfw_audit_is_system_taxonomy( $taxonomy );
			}
		)
	);

	foreach ( $taxonomies as $taxonomy ) {
		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
			)
		);

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			continue;
		}

		foreach ( $terms as $term ) {
			$raw_meta = mfw_yoast_extract_term_meta( $term->term_id, $taxonomy );
			if ( empty( $raw_meta ) ) {
				continue;
			}

			$term_url = get_term_link( $term );
			if ( is_wp_error( $term_url ) ) {
				$term_url = '';
			}

			$row = mfw_yoast_build_row(
				array(
					'environment_name' => $context['environment_name'],
					'site_id'          => $context['site_id'],
					'record_type'      => 'yoast_term',
					'object_type'      => $taxonomy,
					'object_id'        => $term->term_id,
					'status'           => 'taxonomy',
					'title'            => $term->name,
					'url'              => $term_url,
					'slug'             => $term->slug,
					'taxonomy'         => $taxonomy,
					'term_id'          => $term->term_id,
				),
				array(
					'seo_title'              => mfw_yoast_get_meta_value( $raw_meta, array( 'wpseo_title', '_yoast_wpseo_title' ) ),
					'seo_description'        => mfw_yoast_get_meta_value( $raw_meta, array( 'wpseo_desc', '_yoast_wpseo_metadesc' ) ),
					'seo_canonical'          => mfw_yoast_get_meta_value( $raw_meta, array( 'wpseo_canonical', '_yoast_wpseo_canonical' ) ),
					'seo_noindex'            => mfw_yoast_get_meta_value( $raw_meta, array( 'wpseo_noindex', '_yoast_wpseo_meta-robots-noindex' ) ),
					'seo_nofollow'           => mfw_yoast_get_meta_value( $raw_meta, array( 'wpseo_nofollow', '_yoast_wpseo_meta-robots-nofollow' ) ),
					'seo_focus_keyword'      => mfw_yoast_get_meta_value( $raw_meta, array( 'wpseo_focuskw', '_yoast_wpseo_focuskw' ) ),
					'seo_cornerstone'        => mfw_yoast_normalize_bool( mfw_yoast_get_meta_value( $raw_meta, array( 'wpseo_is_cornerstone', '_yoast_wpseo_is_cornerstone' ) ) ),
					'seo_og_title'           => mfw_yoast_get_meta_value( $raw_meta, array( 'wpseo_opengraph-title', '_yoast_wpseo_opengraph-title' ) ),
					'seo_og_description'     => mfw_yoast_get_meta_value( $raw_meta, array( 'wpseo_opengraph-description', '_yoast_wpseo_opengraph-description' ) ),
					'seo_og_image'           => mfw_yoast_get_meta_value( $raw_meta, array( 'wpseo_opengraph-image', '_yoast_wpseo_opengraph-image' ) ),
					'seo_twitter_title'      => mfw_yoast_get_meta_value( $raw_meta, array( 'wpseo_twitter-title', '_yoast_wpseo_twitter-title' ) ),
					'seo_twitter_description' => mfw_yoast_get_meta_value( $raw_meta, array( 'wpseo_twitter-description', '_yoast_wpseo_twitter-description' ) ),
					'seo_twitter_image'      => mfw_yoast_get_meta_value( $raw_meta, array( 'wpseo_twitter-image', '_yoast_wpseo_twitter-image' ) ),
					'details_json'           => array(
						'taxonomy' => $taxonomy,
						'term_name' => $term->name,
						'meta_keys' => array_keys( $raw_meta ),
					),
				),
				$raw_meta,
				'term_meta'
			);

			if ( ! mfw_yoast_has_meaningful_seo_data( $row ) ) {
				continue;
			}

			$rows[] = $row;
		}
	}

	return $rows;
}

function mfw_yoast_collect_site_setting_rows( $context ) {
	$settings = mfw_yoast_extract_site_settings();
	if ( empty( $settings ) ) {
		return array();
	}

	return array(
		mfw_yoast_build_row(
			array(
				'environment_name' => $context['environment_name'],
				'site_id'          => $context['site_id'],
				'record_type'      => 'yoast_settings',
				'object_type'      => 'yoast_settings',
				'object_id'        => 'sitewide',
				'status'           => 'sitewide',
				'title'            => 'Yoast site settings',
			),
			array(
				'details_json' => $settings,
			),
			$settings,
			'site_option'
		),
	);
}

function mfw_yoast_collect_rows( $context ) {
	if ( ! mfw_yoast_is_available() ) {
		return array();
	}

	$rows = array_merge(
		mfw_yoast_collect_post_rows( $context ),
		mfw_yoast_collect_taxonomy_rows( $context ),
		mfw_yoast_collect_site_setting_rows( $context )
	);

	return array_values( $rows );
}

function mfw_yoast_ensure_export_support( $record ) {
	if ( empty( $record ) || ! is_array( $record ) || ! mfw_yoast_is_available() ) {
		return $record;
	}

	if ( empty( $record['files'] ) || ! is_array( $record['files'] ) ) {
		return $record;
	}

	foreach ( $record['files'] as $file ) {
		if ( ! empty( $file['type'] ) && 'yoast_seo' === $file['type'] ) {
			return $record;
		}
	}

	$context = array(
		'environment_name' => isset( $record['summary']['environment_name'] ) ? $record['summary']['environment_name'] : get_bloginfo( 'name' ),
		'site_id'          => get_current_blog_id(),
		'generated_at'     => isset( $record['generated_at'] ) ? $record['generated_at'] : current_time( 'mysql' ),
	);

	$rows = mfw_yoast_collect_rows( $context );
	if ( empty( $rows ) ) {
		return $record;
	}

	$export_dir = '';
	foreach ( $record['files'] as $file ) {
		if ( ! empty( $file['path'] ) ) {
			$export_dir = dirname( $file['path'] );
			break;
		}
	}
	if ( '' === $export_dir ) {
		return $record;
	}

	$prefix   = ! empty( $record['export_id'] ) ? $record['export_id'] : 'yoast-export';
	$csv_name = $prefix . '-seo.csv';
	$csv_path  = trailingslashit( $export_dir ) . $csv_name;
	$columns   = mfw_yoast_get_export_columns();
	$result    = mfw_write_audit_csv( $csv_path, $rows, $columns );
	if ( true !== $result ) {
		return $record;
	}

	$file_entry = array(
		'type' => 'yoast_seo',
		'name' => $csv_name,
		'path' => $csv_path,
		'url'  => mfw_yoast_path_to_url( $csv_path ),
		'size' => file_exists( $csv_path ) ? filesize( $csv_path ) : 0,
		'rows' => count( $rows ),
	);

	$record['files'][] = $file_entry;
	$record['row_counts']['yoast_seo'] = count( $rows );
	$record['row_total'] = isset( $record['row_total'] ) ? (int) $record['row_total'] + count( $rows ) : count( $rows );
	if ( isset( $record['summary'] ) && is_array( $record['summary'] ) ) {
		$record['summary']['yoast_seo_items'] = count( $rows );
	}

	$metadata_file_path = '';
	$zip_file_path      = '';
	foreach ( $record['files'] as $file ) {
		if ( ! empty( $file['type'] ) && 'metadata' === $file['type'] && ! empty( $file['path'] ) ) {
			$metadata_file_path = $file['path'];
		}
		if ( ! empty( $file['type'] ) && 'zip' === $file['type'] && ! empty( $file['path'] ) ) {
			$zip_file_path = $file['path'];
		}
	}

	if ( '' !== $metadata_file_path && is_readable( $metadata_file_path ) ) {
		$metadata_json = json_decode( file_get_contents( $metadata_file_path ), true );
		if ( is_array( $metadata_json ) ) {
			$metadata_json['files'][] = $file_entry;
			$metadata_json['row_counts']['yoast_seo'] = count( $rows );
			$metadata_json['row_total'] = isset( $metadata_json['row_total'] ) ? (int) $metadata_json['row_total'] + count( $rows ) : count( $rows );
			file_put_contents( $metadata_file_path, wp_json_encode( $metadata_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
		}
	}

	if ( '' !== $zip_file_path && class_exists( 'ZipArchive' ) ) {
		$zip = new ZipArchive();
		if ( true === $zip->open( $zip_file_path, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
			foreach ( $record['files'] as $file ) {
				if ( empty( $file['path'] ) || $file['path'] === $zip_file_path ) {
					continue;
				}
				if ( file_exists( $file['path'] ) ) {
					$zip->addFile( $file['path'], $file['name'] );
				}
			}
			$zip->close();
		}
	}

	$history = mfw_get_audit_history();
	if ( ! empty( $history ) ) {
		$history[0] = $record;
		mfw_save_audit_history( $history );
	}

	return $record;
}

function mfw_maybe_finalize_yoast_audit_export() {
	if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : '';
	if ( 'mfw_generate_audit_export' !== $action ) {
		return;
	}

	$history = mfw_get_audit_history();
	if ( empty( $history ) ) {
		return;
	}

	mfw_yoast_ensure_export_support( $history[0] );
}
add_action( 'shutdown', 'mfw_maybe_finalize_yoast_audit_export', 20 );