<?php
/**
 * Crawl public content and export the SEO metadata rendered in each document head.
 *
 * This intentionally inspects public output instead of reading any particular SEO
 * plugin's storage. Yoast, Rank Math, Divi, theme, and core output are treated alike.
 *
 * @package migration_freeze_webspark
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Return the final URL exposed by the WordPress HTTP transport when available.
 *
 * @param array $response WordPress HTTP API response.
 * @param string $fallback Requested URL.
 * @return string
 */
function mfw_rendered_seo_get_final_url( $response, $fallback ) {
	if ( isset( $response['http_response'] ) && is_object( $response['http_response'] ) && method_exists( $response['http_response'], 'get_response_object' ) ) {
		$object = $response['http_response']->get_response_object();
		if ( is_object( $object ) && ! empty( $object->url ) ) {
			return (string) $object->url;
		}
	}
	return $fallback;
}

/**
 * Normalize a DOM value for CSV output and comparisons.
 *
 * @param string $value Raw value.
 * @return string
 */
function mfw_rendered_seo_clean_value( $value ) {
	$value = html_entity_decode( (string) $value, ENT_QUOTES, get_bloginfo( 'charset' ) ? get_bloginfo( 'charset' ) : 'UTF-8' );
	return trim( preg_replace( '/\s+/u', ' ', $value ) );
}

/**
 * Extract ordered content values for a matching XPath expression.
 *
 * @param DOMXPath $xpath XPath instance.
 * @param string   $query XPath query.
 * @param string   $attribute Attribute name, or an empty string for text content.
 * @return array
 */
function mfw_rendered_seo_xpath_values( $xpath, $query, $attribute = '' ) {
	$values = array();
	$nodes  = $xpath->query( $query );
	if ( false === $nodes ) {
		return $values;
	}
	foreach ( $nodes as $node ) {
		$value = $attribute && $node->hasAttribute( $attribute ) ? $node->getAttribute( $attribute ) : $node->textContent;
		$value = mfw_rendered_seo_clean_value( $value );
		if ( '' !== $value ) {
			$values[] = $value;
		}
	}
	return $values;
}

/**
 * Return unique values while preserving discovery order.
 *
 * @param array $values Values.
 * @return array
 */
function mfw_rendered_seo_unique_values( $values ) {
	return array_values( array_unique( array_filter( array_map( 'strval', $values ), 'strlen' ) ) );
}

/**
 * Resolve a possibly relative URL against the requested page URL.
 *
 * @param string $value URL value.
 * @param string $base Base URL.
 * @return string
 */
function mfw_rendered_seo_resolve_url( $value, $base ) {
	$value = trim( (string) $value );
	if ( '' === $value || preg_match( '#^[a-z][a-z0-9+.-]*:#i', $value ) ) {
		return $value;
	}
	$parts = wp_parse_url( $base );
	if ( empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
		return $value;
	}
	$origin = $parts['scheme'] . '://' . $parts['host'] . ( isset( $parts['port'] ) ? ':' . $parts['port'] : '' );
	if ( 0 === strpos( $value, '//' ) ) {
		return $parts['scheme'] . ':' . $value;
	}
	if ( 0 === strpos( $value, '/' ) ) {
		return $origin . $value;
	}
	$path = isset( $parts['path'] ) ? $parts['path'] : '/';
	$directory = '/' === substr( $path, -1 ) ? $path : trailingslashit( dirname( $path ) );
	return $origin . $directory . $value;
}

/**
 * Convert a rendered title to a conservative reusable pattern.
 *
 * @param string $rendered Rendered title.
 * @param string $content_title WordPress content title.
 * @param string $site_name Site name.
 * @return string
 */
function mfw_rendered_seo_title_pattern( $rendered, $content_title, $site_name ) {
	$pattern       = mfw_rendered_seo_clean_value( $rendered );
	$content_title = mfw_rendered_seo_clean_value( $content_title );
	$site_name     = mfw_rendered_seo_clean_value( $site_name );
	if ( '' !== $content_title && false !== stripos( $pattern, $content_title ) ) {
		$pattern = str_ireplace( $content_title, '{content_title}', $pattern );
	}
	if ( '' !== $site_name && false !== stripos( $pattern, $site_name ) ) {
		$pattern = str_ireplace( $site_name, '{site_name}', $pattern );
	}
	return $pattern;
}

/**
 * Parse supported metadata from an HTML response.
 *
 * @param string $html Response body.
 * @param string $base_url Final or requested page URL.
 * @param string $content_title WordPress content title.
 * @param string $site_name Site name.
 * @return array|WP_Error
 */
function mfw_rendered_seo_parse_head( $html, $base_url, $content_title, $site_name ) {
	if ( ! class_exists( 'DOMDocument' ) ) {
		return new WP_Error( 'mfw_rendered_seo_dom_missing', __( 'The PHP DOM extension is unavailable.', 'migration-freeze-webspark' ) );
	}
	$head_end = stripos( $html, '</head>' );
	if ( false !== $head_end ) {
		$html = substr( $html, 0, $head_end + 7 ) . '<body></body></html>';
	}
	$document = new DOMDocument();
	$previous = libxml_use_internal_errors( true );
	$loaded   = $document->loadHTML( '<?xml encoding="utf-8" ?>' . $html, LIBXML_NOWARNING | LIBXML_NOERROR | LIBXML_NONET );
	libxml_clear_errors();
	libxml_use_internal_errors( $previous );
	if ( ! $loaded ) {
		return new WP_Error( 'mfw_rendered_seo_invalid_html', __( 'The response HTML could not be parsed.', 'migration-freeze-webspark' ) );
	}

	$xpath = new DOMXPath( $document );
	$map   = array(
		'html_title'         => array( '//head/title', '' ),
		'meta_description'   => array( '//head/meta[translate(@name,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz")="description"]', 'content' ),
		'canonical'          => array( '//head/link[contains(concat(" ", translate(normalize-space(@rel),"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz"), " "), " canonical ")]', 'href' ),
		'robots'             => array( '//head/meta[translate(@name,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz")="robots"]', 'content' ),
		'og_title'           => array( '//head/meta[translate(@property,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz")="og:title"]', 'content' ),
		'og_description'     => array( '//head/meta[translate(@property,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz")="og:description"]', 'content' ),
		'og_image'           => array( '//head/meta[translate(@property,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz")="og:image"]', 'content' ),
		'og_image_alt'       => array( '//head/meta[translate(@property,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz")="og:image:alt"]', 'content' ),
		'og_type'            => array( '//head/meta[translate(@property,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz")="og:type"]', 'content' ),
		'og_url'             => array( '//head/meta[translate(@property,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz")="og:url"]', 'content' ),
		'twitter_card'       => array( '//head/meta[translate(@name,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz")="twitter:card" or translate(@property,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz")="twitter:card"]', 'content' ),
		'twitter_title'      => array( '//head/meta[translate(@name,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz")="twitter:title" or translate(@property,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz")="twitter:title"]', 'content' ),
		'twitter_description' => array( '//head/meta[translate(@name,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz")="twitter:description" or translate(@property,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz")="twitter:description"]', 'content' ),
		'twitter_image'      => array( '//head/meta[translate(@name,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz")="twitter:image" or translate(@property,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz")="twitter:image"]', 'content' ),
		'twitter_image_alt'  => array( '//head/meta[translate(@name,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz")="twitter:image:alt" or translate(@property,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz")="twitter:image:alt"]', 'content' ),
	);
	$all_values = array();
	$result     = array();
	foreach ( $map as $field => $selector ) {
		$all_values[ $field ] = mfw_rendered_seo_xpath_values( $xpath, $selector[0], $selector[1] );
		$result[ $field ]     = isset( $all_values[ $field ][0] ) ? $all_values[ $field ][0] : '';
	}
	foreach ( array( 'canonical', 'og_image', 'og_url', 'twitter_image' ) as $url_field ) {
		$result[ $url_field ] = mfw_rendered_seo_resolve_url( $result[ $url_field ], $base_url );
	}

	$hreflang = array();
	$links    = $xpath->query( '//head/link[contains(concat(" ", translate(normalize-space(@rel),"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz"), " "), " alternate ") and @hreflang and @href]' );
	if ( false !== $links ) {
		foreach ( $links as $link ) {
			$hreflang[] = array(
				'lang' => mfw_rendered_seo_clean_value( $link->getAttribute( 'hreflang' ) ),
				'url'  => mfw_rendered_seo_resolve_url( $link->getAttribute( 'href' ), $base_url ),
			);
		}
	}

	$robot_directives = array();
	$robot_nodes      = $xpath->query( '//head/meta[@name and @content]' );
	if ( false !== $robot_nodes ) {
		foreach ( $robot_nodes as $node ) {
			$name = strtolower( trim( $node->getAttribute( 'name' ) ) );
			if ( 'robots' === $name || in_array( $name, array( 'googlebot', 'googlebot-news', 'bingbot', 'slurp' ), true ) ) {
				$robot_directives[ $name ][] = mfw_rendered_seo_clean_value( $node->getAttribute( 'content' ) );
			}
		}
	}

	$duplicates = array();
	$conflicts  = array();
	foreach ( $all_values as $field => $values ) {
		if ( count( $values ) > 1 ) {
			$duplicates[] = $field;
		}
		if ( count( mfw_rendered_seo_unique_values( $values ) ) > 1 ) {
			$conflicts[] = $field;
		}
	}
	$result['hreflang_json']          = mfw_audit_json_encode( $hreflang );
	$result['robot_directives']       = $robot_directives;
	$result['robot_directives_json']  = mfw_audit_json_encode( $robot_directives );
	$result['duplicate_fields']       = implode( ',', $duplicates );
	$result['conflicting_fields']     = implode( ',', $conflicts );
	$result['html_title_pattern']     = mfw_rendered_seo_title_pattern( $result['html_title'], $content_title, $site_name );
	$result['og_title_pattern']       = mfw_rendered_seo_title_pattern( $result['og_title'], $content_title, $site_name );
	$result['twitter_title_pattern']  = mfw_rendered_seo_title_pattern( $result['twitter_title'], $content_title, $site_name );
	$result['all_values']             = $all_values;
	return $result;
}

/**
 * Test a comma-separated collection of robot directives.
 *
 * @param array $values Directive values.
 * @param string $directive Directive to find.
 * @return string
 */
function mfw_rendered_seo_has_robot_directive( $values, $directive ) {
	foreach ( $values as $value ) {
		$tokens = preg_split( '/[\s,]+/', strtolower( $value ) );
		if ( in_array( $directive, $tokens, true ) || ( in_array( $directive, array( 'noindex', 'nofollow' ), true ) && in_array( 'none', $tokens, true ) ) ) {
			return 'yes';
		}
	}
	return 'no';
}

/**
 * Annotate successful rows with observed title-pattern frequency and scope.
 *
 * A pattern is recommended only when it contains a recognized placeholder and
 * covers at least 80 percent of the site or an individual content type.
 *
 * @param array $rows Rendered SEO rows, by reference.
 * @return array Pattern summary for audit metadata.
 */
function mfw_rendered_seo_annotate_patterns( &$rows ) {
	$site_counts = array();
	$type_counts = array();
	$type_totals = array();
	$total       = 0;
	foreach ( $rows as $row ) {
		if ( 'success' !== $row['crawl_status'] || '' === $row['html_title_pattern'] ) {
			continue;
		}
		$pattern = $row['html_title_pattern'];
		$type    = $row['object_type'];
		$site_counts[ $pattern ] = isset( $site_counts[ $pattern ] ) ? $site_counts[ $pattern ] + 1 : 1;
		if ( ! isset( $type_counts[ $type ] ) ) {
			$type_counts[ $type ] = array();
		}
		$type_counts[ $type ][ $pattern ] = isset( $type_counts[ $type ][ $pattern ] ) ? $type_counts[ $type ][ $pattern ] + 1 : 1;
		$type_totals[ $type ] = isset( $type_totals[ $type ] ) ? $type_totals[ $type ] + 1 : 1;
		$total++;
	}

	foreach ( $rows as &$row ) {
		$pattern = $row['html_title_pattern'];
		$type    = $row['object_type'];
		if ( 'success' !== $row['crawl_status'] || '' === $pattern ) {
			continue;
		}
		$site_count = isset( $site_counts[ $pattern ] ) ? $site_counts[ $pattern ] : 0;
		$type_count = isset( $type_counts[ $type ][ $pattern ] ) ? $type_counts[ $type ][ $pattern ] : 0;
		$has_token  = false !== strpos( $pattern, '{content_title}' ) || false !== strpos( $pattern, '{site_name}' );
		$scope      = 'override';
		if ( $has_token && $total >= 2 && ( $site_count / $total ) >= 0.8 ) {
			$scope = 'sitewide_default_candidate';
		} elseif ( $has_token && isset( $type_totals[ $type ] ) && $type_totals[ $type ] >= 2 && ( $type_count / $type_totals[ $type ] ) >= 0.8 ) {
			$scope = 'content_type_default_candidate';
		}
		$row['html_title_pattern_site_count'] = $site_count . '/' . $total;
		$row['html_title_pattern_type_count'] = $type_count . '/' . $type_totals[ $type ];
		$row['html_title_pattern_scope']      = $scope;
	}
	unset( $row );

	arsort( $site_counts );
	$summary = array();
	foreach ( $site_counts as $pattern => $count ) {
		if ( 1 === $count && false === strpos( $pattern, '{content_title}' ) && false === strpos( $pattern, '{site_name}' ) ) {
			continue;
		}
		$summary[] = array(
			'pattern'    => $pattern,
			'count'      => $count,
			'population' => $total,
			'percentage' => $total ? round( ( $count / $total ) * 100, 2 ) : 0,
		);
	}
	return $summary;
}

/**
 * Crawl published public content and add rendered SEO rows to the audit dataset.
 *
 * @param array $artifacts Export artifacts, by reference.
 * @param array $metadata Export context, by reference.
 * @param array $content_posts Posts already selected by the content audit.
 * @return void
 */
function mfw_rendered_seo_collect_rows( &$artifacts, &$metadata, $content_posts ) {
	foreach ( $content_posts as $post ) {
		if ( 'publish' !== $post->post_status ) {
			continue;
		}
		$url = get_permalink( $post );
		if ( ! $url ) {
			continue;
		}
		$base = array(
			'environment_name' => $metadata['environment_name'],
			'site_id'          => $metadata['site_id'],
			'record_type'      => 'rendered_seo',
			'object_type'      => $post->post_type,
			'object_id'        => $post->ID,
			'source_title'     => get_the_title( $post ),
			'requested_url'    => $url,
			'final_url'        => $url,
			'crawl_status'     => 'error',
		);
		$response = wp_safe_remote_get(
			$url,
			array(
				'timeout'             => (int) apply_filters( 'mfw_rendered_seo_request_timeout', 15 ),
				'redirection'         => 5,
				'limit_response_size' => 1048576,
				'user-agent'          => 'MigrationAuditTrail/' . ( defined( 'MFW_VERSION' ) ? MFW_VERSION : 'unknown' ) . '; ' . home_url( '/' ),
			)
		);
		if ( is_wp_error( $response ) ) {
			$base['crawl_error'] = $response->get_error_message();
			$base['details_json'] = array( 'error_code' => $response->get_error_code() );
			mfw_audit_push_row( $artifacts, 'rendered_seo', $base );
			continue;
		}

		$status       = (int) wp_remote_retrieve_response_code( $response );
		$content_type = (string) wp_remote_retrieve_header( $response, 'content-type' );
		$final_url    = mfw_rendered_seo_get_final_url( $response, $url );
		$x_robots     = wp_remote_retrieve_header( $response, 'x-robots-tag' );
		$x_robots     = is_array( $x_robots ) ? array_map( 'strval', $x_robots ) : array_filter( array( (string) $x_robots ) );
		$base['final_url']   = $final_url;
		$base['http_status'] = $status;
		$base['content_type'] = $content_type;
		if ( $status < 200 || $status >= 400 ) {
			$base['crawl_error'] = sprintf( __( 'Unexpected HTTP status %d.', 'migration-freeze-webspark' ), $status );
			$base['details_json'] = array( 'response_message' => wp_remote_retrieve_response_message( $response ) );
			mfw_audit_push_row( $artifacts, 'rendered_seo', $base );
			continue;
		}
		if ( false === stripos( $content_type, 'text/html' ) ) {
			$base['crawl_error'] = __( 'The response was not HTML.', 'migration-freeze-webspark' );
			mfw_audit_push_row( $artifacts, 'rendered_seo', $base );
			continue;
		}

		$parsed = mfw_rendered_seo_parse_head( wp_remote_retrieve_body( $response ), $final_url, get_the_title( $post ), $metadata['site_name'] );
		if ( is_wp_error( $parsed ) ) {
			$base['crawl_error'] = $parsed->get_error_message();
			mfw_audit_push_row( $artifacts, 'rendered_seo', $base );
			continue;
		}
		$all_robots = array();
		foreach ( $parsed['robot_directives'] as $directive_values ) {
			$all_robots = array_merge( $all_robots, $directive_values );
		}
		$all_robots = array_merge( $all_robots, $x_robots );
		$base['crawl_status'] = 'success';
		$base['x_robots_tag'] = implode( ' | ', $x_robots );
		$base['noindex']      = mfw_rendered_seo_has_robot_directive( $all_robots, 'noindex' );
		$base['nofollow']     = mfw_rendered_seo_has_robot_directive( $all_robots, 'nofollow' );
		foreach ( array( 'html_title', 'meta_description', 'canonical', 'robots', 'robot_directives_json', 'og_title', 'og_description', 'og_image', 'og_image_alt', 'og_type', 'og_url', 'twitter_card', 'twitter_title', 'twitter_description', 'twitter_image', 'twitter_image_alt', 'hreflang_json', 'duplicate_fields', 'conflicting_fields', 'html_title_pattern', 'og_title_pattern', 'twitter_title_pattern' ) as $field ) {
			$base[ $field ] = isset( $parsed[ $field ] ) ? $parsed[ $field ] : '';
		}
		$base['details_json'] = array(
			'all_values'       => $parsed['all_values'],
			'headers'          => array( 'x-robots-tag' => $x_robots ),
			'response_message' => wp_remote_retrieve_response_message( $response ),
		);
		mfw_audit_push_row( $artifacts, 'rendered_seo', $base );
	}
	if ( ! empty( $artifacts['rendered_seo'] ) ) {
		$metadata['rendered_seo_patterns'] = mfw_rendered_seo_annotate_patterns( $artifacts['rendered_seo'] );
	}
}
