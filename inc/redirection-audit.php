<?php
/**
 * Redirection audit export helpers.
 *
 * Generates a supplemental redirects artifact by post-processing the latest export bundle.
 *
 * @package migration_freeze_webspark
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function mfw_redirection_is_available() {
	return mfw_audit_is_plugin_active( 'redirection/redirection.php' ) || class_exists( 'Red_Item' );
}

function mfw_redirection_get_export_columns() {
	return array(
		'environment_name',
		'site_id',
		'record_type',
		'object_type',
		'object_id',
		'status',
		'title',
		'url',
		'source_url',
		'source_path',
		'source_host',
		'target_url',
		'target_path',
		'target_host',
		'http_status',
		'is_regex',
		'query_mode',
		'match_case',
		'match_trailing_slash',
		'language',
		'group_name',
		'created_at',
		'updated_at',
		'last_used_at',
		'hit_count',
		'notes',
		'details_json',
	);
}

function mfw_redirection_normalize_row( $values ) {
	$row = array_fill_keys( mfw_redirection_get_export_columns(), '' );
	foreach ( $values as $key => $value ) {
		if ( ! array_key_exists( $key, $row ) ) {
			continue;
		}
		$row[ $key ] = is_array( $value ) || is_object( $value ) ? mfw_audit_json_encode( $value ) : (string) $value;
	}
	return $row;
}

function mfw_redirection_maybe_unserialize( $value ) {
	if ( is_array( $value ) || is_object( $value ) ) {
		return $value;
	}
	if ( ! is_string( $value ) || '' === $value ) {
		return $value;
	}

	$maybe_unserialized = maybe_unserialize( $value );
	if ( $maybe_unserialized !== $value ) {
		return $maybe_unserialized;
	}

	$json = json_decode( $value, true );
	if ( JSON_ERROR_NONE === json_last_error() ) {
		return $json;
	}

	return $value;
}

function mfw_redirection_table_exists( $table_name ) {
	global $wpdb;
	return (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) );
}

function mfw_redirection_get_group_map() {
	global $wpdb;
	$table = $wpdb->prefix . 'redirection_groups';
	if ( ! mfw_redirection_table_exists( $table ) ) {
		return array();
	}

	$groups = $wpdb->get_results( "SELECT * FROM {$table}", ARRAY_A );
	if ( empty( $groups ) || ! is_array( $groups ) ) {
		return array();
	}

	$map = array();
	foreach ( $groups as $group ) {
		$group_id = isset( $group['id'] ) ? (int) $group['id'] : 0;
		if ( ! $group_id ) {
			continue;
		}
		$map[ $group_id ] = $group;
	}
	return $map;
}

function mfw_redirection_get_rule_rows() {
	global $wpdb;
	$table = $wpdb->prefix . 'redirection_items';
	if ( ! mfw_redirection_table_exists( $table ) ) {
		return array();
	}

	$rows = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY id ASC", ARRAY_A );
	return ( is_array( $rows ) && ! empty( $rows ) ) ? $rows : array();
}

function mfw_redirection_pick_value( $row, $keys ) {
	foreach ( (array) $keys as $key ) {
		if ( ! array_key_exists( $key, $row ) ) {
			continue;
		}

		$value = $row[ $key ];
		if ( is_array( $value ) ) {
			$value = reset( $value );
		}

		if ( '' !== (string) $value && null !== $value ) {
			return $value;
		}
	}

	return '';
}

function mfw_redirection_parse_url_parts( $url ) {
	$url = trim( (string) $url );
	if ( '' === $url ) {
		return array(
			'url'  => '',
			'host' => '',
			'path' => '',
		);
	}

	$parts = wp_parse_url( $url );
	if ( false === $parts ) {
		return array(
			'url'  => $url,
			'host' => '',
			'path' => '',
		);
	}

	return array(
		'url'  => $url,
		'host' => isset( $parts['host'] ) ? $parts['host'] : '',
		'path' => isset( $parts['path'] ) ? $parts['path'] : '',
	);
}

function mfw_redirection_normalize_bool( $value ) {
	if ( is_bool( $value ) ) {
		return $value ? 'yes' : 'no';
	}
	if ( is_numeric( $value ) ) {
		return (int) $value ? 'yes' : 'no';
	}

	$value = strtolower( trim( (string) $value ) );
	if ( in_array( $value, array( 'yes', 'true', '1', 'on', 'enabled' ), true ) ) {
		return 'yes';
	}
	if ( in_array( $value, array( 'no', 'false', '0', 'off', 'disabled' ), true ) ) {
		return 'no';
	}

	return '';
}

function mfw_redirection_normalize_status( $row ) {
	$status = mfw_redirection_pick_value( $row, array( 'status', 'enabled', 'active' ) );
	if ( '' === $status ) {
		return 'enabled';
	}

	$status = strtolower( trim( (string) $status ) );
	if ( in_array( $status, array( '0', 'disabled', 'inactive', 'off', 'false' ), true ) ) {
		return 'disabled';
	}
	if ( in_array( $status, array( '1', 'enabled', 'active', 'on', 'true' ), true ) ) {
		return 'enabled';
	}

	return $status;
}

function mfw_redirection_normalize_http_status( $row, $action_data ) {
	$http_status = mfw_redirection_pick_value( $row, array( 'http_status', 'action_code', 'code', 'status_code' ) );
	if ( '' !== (string) $http_status ) {
		return (string) $http_status;
	}

	$action_type = strtolower( trim( (string) mfw_redirection_pick_value( $row, array( 'action_type', 'type' ) ) ) );
	if ( in_array( $action_type, array( 'url', 'redirect' ), true ) ) {
		return '301';
	}

	if ( is_array( $action_data ) ) {
		$action_code = mfw_redirection_pick_value( $action_data, array( 'http_status', 'code', 'status_code' ) );
		if ( '' !== (string) $action_code ) {
			return (string) $action_code;
		}
	}

	return '';
}

function mfw_redirection_extract_target_url( $row, $action_data ) {
	$candidates = array();
	if ( is_array( $action_data ) ) {
		$candidates[] = mfw_redirection_pick_value( $action_data, array( 'url', 'target', 'destination', 'to', 'location', 'redirect_to', 'link' ) );
		if ( isset( $action_data['data'] ) ) {
			$candidates[] = mfw_redirection_pick_value( $action_data['data'], array( 'url', 'target', 'destination', 'to', 'location', 'redirect_to', 'link' ) );
		}
	}
	$candidates[] = mfw_redirection_pick_value( $row, array( 'target_url', 'destination', 'action_data', 'url_to' ) );

	foreach ( $candidates as $candidate ) {
		$candidate = trim( (string) $candidate );
		if ( '' === $candidate ) {
			continue;
		}

		if ( false !== strpos( $candidate, 'http://' ) || false !== strpos( $candidate, 'https://' ) || 0 === strpos( $candidate, '/' ) ) {
			return $candidate;
		}
	}

	return '';
}

function mfw_redirection_extract_query_mode( $row, $action_data ) {
	$candidates = array(
		mfw_redirection_pick_value( $row, array( 'query_mode', 'query', 'query_string_mode' ) ),
	);
	if ( is_array( $action_data ) ) {
		$candidates[] = mfw_redirection_pick_value( $action_data, array( 'query_mode', 'query', 'passthrough', 'pass_through', 'query_string_mode' ) );
	}

	foreach ( $candidates as $candidate ) {
		$candidate = trim( (string) $candidate );
		if ( '' !== $candidate ) {
			return $candidate;
		}
	}

	return '';
}

function mfw_redirection_extract_language( $row ) {
	return (string) mfw_redirection_pick_value( $row, array( 'language', 'lang' ) );
}

function mfw_redirection_extract_group_name( $row, $group_map ) {
	$group_id = (int) mfw_redirection_pick_value( $row, array( 'group_id' ) );
	if ( $group_id && isset( $group_map[ $group_id ] ) ) {
		return isset( $group_map[ $group_id ]['name'] ) ? (string) $group_map[ $group_id ]['name'] : '';
	}
	return '';
}

function mfw_redirection_extract_source_url( $row ) {
	return (string) mfw_redirection_pick_value( $row, array( 'url', 'source_url', 'match_url', 'source' ) );
}

function mfw_redirection_extract_source_path( $source_url ) {
	$parts = mfw_redirection_parse_url_parts( $source_url );
	return isset( $parts['path'] ) ? $parts['path'] : '';
}

function mfw_redirection_build_row( $context, $row, $group_map ) {
	$action_data = mfw_redirection_maybe_unserialize( mfw_redirection_pick_value( $row, array( 'action_data' ) ) );
	$source_url  = mfw_redirection_extract_source_url( $row );
	$target_url  = mfw_redirection_extract_target_url( $row, $action_data );
	$source      = mfw_redirection_parse_url_parts( $source_url );
	$target      = mfw_redirection_parse_url_parts( $target_url );
	$notes       = trim( (string) mfw_redirection_pick_value( $row, array( 'title', 'description', 'note' ) ) );

	$details = array(
		'raw_rule'          => $row,
		'raw_action_data'    => $action_data,
		'group'             => (int) mfw_redirection_pick_value( $row, array( 'group_id' ) ),
		'source_parsed'      => $source,
		'target_parsed'      => $target,
		'http_status'        => mfw_redirection_normalize_http_status( $row, $action_data ),
		'query_mode'         => mfw_redirection_extract_query_mode( $row, $action_data ),
		'match_case'         => mfw_redirection_normalize_bool( mfw_redirection_pick_value( $row, array( 'match_case', 'case_sensitive', 'sensitive' ) ) ),
		'match_trailing_slash' => mfw_redirection_normalize_bool( mfw_redirection_pick_value( $row, array( 'match_trailing_slash', 'trailing_slash', 'trailing' ) ) ),
		'language'           => mfw_redirection_extract_language( $row ),
		'group_name'         => mfw_redirection_extract_group_name( $row, $group_map ),
	);

	return mfw_redirection_normalize_row(
		array(
			'environment_name' => $context['environment_name'],
			'site_id'          => $context['site_id'],
			'record_type'      => 'redirect_rule',
			'object_type'      => 'redirection_rule',
			'object_id'        => isset( $row['id'] ) ? (string) $row['id'] : '',
			'status'           => mfw_redirection_normalize_status( $row ),
			'title'            => '' !== $notes ? $notes : $source_url,
			'url'              => $source_url,
			'source_url'       => $source_url,
			'source_path'      => isset( $source['path'] ) ? $source['path'] : '',
			'source_host'      => isset( $source['host'] ) ? $source['host'] : '',
			'target_url'       => $target_url,
			'target_path'      => isset( $target['path'] ) ? $target['path'] : '',
			'target_host'      => isset( $target['host'] ) ? $target['host'] : '',
			'http_status'      => mfw_redirection_normalize_http_status( $row, $action_data ),
			'is_regex'         => mfw_redirection_normalize_bool( mfw_redirection_pick_value( $row, array( 'regex', 'is_regex' ) ) ),
			'query_mode'       => mfw_redirection_extract_query_mode( $row, $action_data ),
			'match_case'       => mfw_redirection_normalize_bool( mfw_redirection_pick_value( $row, array( 'match_case', 'case_sensitive', 'sensitive' ) ) ),
			'match_trailing_slash' => mfw_redirection_normalize_bool( mfw_redirection_pick_value( $row, array( 'match_trailing_slash', 'trailing_slash', 'trailing' ) ) ),
			'language'         => mfw_redirection_extract_language( $row ),
			'group_name'       => mfw_redirection_extract_group_name( $row, $group_map ),
			'created_at'       => (string) mfw_redirection_pick_value( $row, array( 'created_at', 'created', 'date_created' ) ),
			'updated_at'       => (string) mfw_redirection_pick_value( $row, array( 'updated_at', 'modified', 'last_updated', 'date_modified' ) ),
			'last_used_at'     => (string) mfw_redirection_pick_value( $row, array( 'last_used_at', 'last_access', 'last_used', 'last_hit' ) ),
			'hit_count'        => (string) mfw_redirection_pick_value( $row, array( 'hit_count', 'hits', 'last_count', 'count' ) ),
			'notes'            => $notes,
			'details_json'     => $details,
		)
	);
}

function mfw_redirection_collect_rows( $context ) {
	if ( ! mfw_redirection_is_available() ) {
		return array();
	}

	$rows      = array();
	$group_map  = mfw_redirection_get_group_map();
	$rule_rows  = mfw_redirection_get_rule_rows();
	foreach ( $rule_rows as $row ) {
		$source_url = mfw_redirection_extract_source_url( $row );
		$target_url = mfw_redirection_extract_target_url( $row, mfw_redirection_maybe_unserialize( mfw_redirection_pick_value( $row, array( 'action_data' ) ) ) );
		if ( '' === $source_url && '' === $target_url ) {
			continue;
		}

		$rows[] = mfw_redirection_build_row( $context, $row, $group_map );
	}

	return $rows;
}

function mfw_redirection_ensure_export_support( $record ) {
	if ( empty( $record ) || ! is_array( $record ) || ! mfw_redirection_is_available() ) {
		return $record;
	}

	if ( empty( $record['files'] ) || ! is_array( $record['files'] ) ) {
		return $record;
	}

	foreach ( $record['files'] as $file ) {
		if ( ! empty( $file['type'] ) && 'redirection_rules' === $file['type'] ) {
			return $record;
		}
	}

	$context = array(
		'environment_name' => isset( $record['summary']['environment_name'] ) ? $record['summary']['environment_name'] : get_bloginfo( 'name' ),
		'site_id'          => get_current_blog_id(),
		'generated_at'     => isset( $record['generated_at'] ) ? $record['generated_at'] : current_time( 'mysql' ),
	);

	$rows = mfw_redirection_collect_rows( $context );
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

	$prefix   = ! empty( $record['export_id'] ) ? $record['export_id'] : 'redirection-export';
	$csv_name = $prefix . '-redirects.csv';
	$csv_path = trailingslashit( $export_dir ) . $csv_name;
	$result   = mfw_write_audit_csv( $csv_path, $rows, mfw_redirection_get_export_columns() );
	if ( true !== $result ) {
		return $record;
	}

	$file_entry = array(
		'type' => 'redirection_rules',
		'name' => $csv_name,
		'path' => $csv_path,
		'url'  => mfw_yoast_path_to_url( $csv_path ),
		'size' => file_exists( $csv_path ) ? filesize( $csv_path ) : 0,
		'rows' => count( $rows ),
	);

	$record['files'][] = $file_entry;
	$record['row_counts']['redirection_rules'] = count( $rows );
	$record['row_total'] = isset( $record['row_total'] ) ? (int) $record['row_total'] + count( $rows ) : count( $rows );
	if ( isset( $record['summary'] ) && is_array( $record['summary'] ) ) {
		$record['summary']['redirection_rules_items'] = count( $rows );
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
			$metadata_json['row_counts']['redirection_rules'] = count( $rows );
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

function mfw_maybe_finalize_redirection_audit_export() {
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

	mfw_redirection_ensure_export_support( $history[0] );
}
add_action( 'shutdown', 'mfw_maybe_finalize_redirection_audit_export', 22 );