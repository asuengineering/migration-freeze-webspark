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
 * Normalize punctuation before comparing values for reusable patterns.
 *
 * @param string $value Value to normalize.
 * @return string
 */
function mfw_rendered_seo_normalize_pattern_text( $value ) {
	$value = mfw_rendered_seo_clean_value( $value );
	return strtr(
		$value,
		array(
			'’' => "'",
			'‘' => "'",
			'“' => '"',
			'”' => '"',
			'–' => '-',
			'—' => '-',
		)
	);
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
	$pattern       = mfw_rendered_seo_normalize_pattern_text( $rendered );
	$content_title = mfw_rendered_seo_normalize_pattern_text( $content_title );
	$site_name     = mfw_rendered_seo_normalize_pattern_text( $site_name );
	if ( '' !== $content_title && false !== stripos( $pattern, $content_title ) ) {
		$pattern = str_ireplace( $content_title, '{content_title}', $pattern );
	}
	if ( '' !== $site_name && false !== stripos( $pattern, $site_name ) ) {
		$pattern = str_ireplace( $site_name, '{site_name}', $pattern );
	}
	return $pattern;
}

/**
 * Normalize a URL for canonical comparisons.
 *
 * @param string $url URL to normalize.
 * @return array|false
 */
function mfw_rendered_seo_normalize_url( $url ) {
	$parts = wp_parse_url( trim( (string) $url ) );
	if ( ! is_array( $parts ) || empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
		return false;
	}
	$scheme = strtolower( $parts['scheme'] );
	$host   = strtolower( rtrim( $parts['host'], '.' ) );
	$port   = isset( $parts['port'] ) ? (int) $parts['port'] : 0;
	if ( ( 'http' === $scheme && 80 === $port ) || ( 'https' === $scheme && 443 === $port ) ) {
		$port = 0;
	}
	$path = isset( $parts['path'] ) && '' !== $parts['path'] ? $parts['path'] : '/';
	$path = preg_replace_callback(
		'/%[0-9A-Fa-f]{2}/',
		static function ( $match ) {
			$character = rawurldecode( $match[0] );
			return preg_match( '/[A-Za-z0-9\-._~]/', $character ) ? $character : strtoupper( $match[0] );
		},
		$path
	);
	if ( '/' !== $path ) {
		$path = untrailingslashit( $path );
	}
	return array(
		'scheme' => $scheme,
		'host'   => $host,
		'port'   => $port,
		'path'   => $path,
		'query'  => isset( $parts['query'] ) ? $parts['query'] : '',
	);
}

/**
 * Classify a canonical relative to the crawled page.
 *
 * @param string $canonical Canonical URL.
 * @param string $page_url Final page URL.
 * @return string
 */
function mfw_rendered_seo_classify_canonical( $canonical, $page_url ) {
	if ( '' === trim( (string) $canonical ) ) {
		return 'missing';
	}
	$canonical_parts = mfw_rendered_seo_normalize_url( $canonical );
	$page_parts      = mfw_rendered_seo_normalize_url( $page_url );
	if ( false === $canonical_parts || false === $page_parts ) {
		return 'invalid';
	}
	$canonical_identity = array( $canonical_parts['host'], $canonical_parts['port'], $canonical_parts['path'], $canonical_parts['query'] );
	$page_identity      = array( $page_parts['host'], $page_parts['port'], $page_parts['path'], $page_parts['query'] );
	if ( $canonical_identity === $page_identity ) {
		return 'self_referencing';
	}
	$internal_hosts = (array) apply_filters( 'mfw_rendered_seo_internal_hosts', array( $page_parts['host'] ), $page_url );
	$internal_hosts = array_map( 'strtolower', array_map( 'strval', $internal_hosts ) );
	return in_array( $canonical_parts['host'], $internal_hosts, true ) ? 'internal_override' : 'external_override';
}

/**
 * Normalize robots values into one stable, order-independent directive set.
 *
 * @param array $values Robots directive values.
 * @return string
 */
function mfw_rendered_seo_normalize_robots( $values ) {
	$directives = array();
	foreach ( $values as $value ) {
		foreach ( preg_split( '/[\s,]+/', strtolower( (string) $value ) ) as $directive ) {
			$directive = trim( $directive );
			if ( '' !== $directive ) {
				$directives[] = $directive;
			}
		}
	}
	if ( in_array( 'none', $directives, true ) ) {
		$directives[] = 'noindex';
		$directives[] = 'nofollow';
		$directives   = array_diff( $directives, array( 'none' ) );
	}
	if ( in_array( 'all', $directives, true ) ) {
		$directives[] = 'index';
		$directives[] = 'follow';
		$directives   = array_diff( $directives, array( 'all' ) );
	}
	$directives = array_values( array_unique( $directives ) );
	sort( $directives, SORT_STRING );
	return implode( ',', $directives );
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
 * Build a compact source identity for analysis matches.
 *
 * @param array $row Rendered SEO row.
 * @return array
 */
function mfw_rendered_seo_analysis_identity( $row ) {
	return array(
		'object_type' => $row['object_type'],
		'object_id'   => $row['object_id'],
		'url'         => $row['requested_url'],
	);
}

/**
 * Rank title patterns by observed coverage without applying a policy threshold.
 *
 * @param array  $rows Rendered SEO rows.
 * @param string $object_type Optional content type filter.
 * @return array
 */
function mfw_rendered_seo_title_candidates( $rows, $object_type = '' ) {
	$groups     = array();
	$population = 0;
	foreach ( $rows as $row ) {
		if ( 'success' !== $row['crawl_status'] || '' === $row['html_title_pattern'] || ( '' !== $object_type && $object_type !== $row['object_type'] ) ) {
			continue;
		}
		$population++;
		$pattern = $row['html_title_pattern'];
		if ( ! isset( $groups[ $pattern ] ) ) {
			$groups[ $pattern ] = array();
		}
		$groups[ $pattern ][] = mfw_rendered_seo_analysis_identity( $row );
	}
	$candidates = array();
	foreach ( $groups as $pattern => $matches ) {
		$candidates[] = array(
			'pattern'    => $pattern,
			'matches'    => count( $matches ),
			'population' => $population,
			'coverage'   => $population ? round( ( count( $matches ) / $population ) * 100, 2 ) : 0,
			'objects'    => $matches,
		);
	}
	usort(
		$candidates,
		static function ( $a, $b ) {
			if ( $a['matches'] === $b['matches'] ) {
				return strcmp( $a['pattern'], $b['pattern'] );
			}
			return $a['matches'] > $b['matches'] ? -1 : 1;
		}
	);
	foreach ( $candidates as $index => &$candidate ) {
		$candidate = array_merge( array( 'rank' => $index + 1 ), $candidate );
	}
	unset( $candidate );
	return $candidates;
}

/**
 * Summarize one categorical row field.
 *
 * @param array  $rows Rendered SEO rows.
 * @param string $field Field to summarize.
 * @param string $missing_label Label used for an empty value.
 * @return array
 */
function mfw_rendered_seo_categorical_summary( $rows, $field, $missing_label = 'missing' ) {
	$groups     = array();
	$population = 0;
	foreach ( $rows as $row ) {
		if ( 'success' !== $row['crawl_status'] ) {
			continue;
		}
		$population++;
		$value = isset( $row[ $field ] ) && '' !== $row[ $field ] ? $row[ $field ] : $missing_label;
		if ( ! isset( $groups[ $value ] ) ) {
			$groups[ $value ] = array();
		}
		$groups[ $value ][] = mfw_rendered_seo_analysis_identity( $row );
	}
	$summary = array();
	foreach ( $groups as $value => $matches ) {
		$summary[] = array(
			'value'      => $value,
			'matches'    => count( $matches ),
			'population' => $population,
			'coverage'   => $population ? round( ( count( $matches ) / $population ) * 100, 2 ) : 0,
			'objects'    => $matches,
		);
	}
	usort(
		$summary,
		static function ( $a, $b ) {
			if ( $a['matches'] === $b['matches'] ) {
				return strcmp( $a['value'], $b['value'] );
			}
			return $a['matches'] > $b['matches'] ? -1 : 1;
		}
	);
	foreach ( $summary as $index => &$item ) {
		$item = array_merge( array( 'rank' => $index + 1 ), $item );
	}
	unset( $item );
	return $summary;
}

/**
 * Summarize presence for fields that do not benefit from pattern inference.
 *
 * @param array $rows Rendered SEO rows.
 * @param array $fields Fields to summarize.
 * @return array
 */
function mfw_rendered_seo_completeness_summary( $rows, $fields ) {
	$summary    = array();
	$successful = array_values(
		array_filter(
			$rows,
			static function ( $row ) {
				return 'success' === $row['crawl_status'];
			}
		)
	);
	foreach ( $fields as $field ) {
		$present = 0;
		foreach ( $successful as $row ) {
			if ( isset( $row[ $field ] ) && '' !== $row[ $field ] && '[]' !== $row[ $field ] && '{}' !== $row[ $field ] ) {
				$present++;
			}
		}
		$summary[ $field ] = array(
			'present'    => $present,
			'missing'    => count( $successful ) - $present,
			'population' => count( $successful ),
		);
	}
	return $summary;
}

/**
 * Summarize robots directive sets separately for each declared crawler agent.
 *
 * @param array $rows Rendered SEO rows.
 * @return array
 */
function mfw_rendered_seo_robot_agent_summary( $rows ) {
	$agent_names = array();
	$records     = array();
	foreach ( $rows as $row ) {
		if ( 'success' !== $row['crawl_status'] ) {
			continue;
		}
		$declared = json_decode( isset( $row['robot_directives_json'] ) ? $row['robot_directives_json'] : '{}', true );
		if ( ! is_array( $declared ) ) {
			$declared = array();
		}
		if ( ! empty( $row['x_robots_tag'] ) ) {
			$declared['x-robots-tag'] = array( $row['x_robots_tag'] );
		}
		foreach ( array_keys( $declared ) as $agent ) {
			$agent_names[ $agent ] = true;
		}
		$records[] = array( 'row' => $row, 'declared' => $declared );
	}
	ksort( $agent_names );
	$summary = array();
	foreach ( array_keys( $agent_names ) as $agent ) {
		$sets = array();
		foreach ( $records as $record ) {
			$set = isset( $record['declared'][ $agent ] ) ? mfw_rendered_seo_normalize_robots( (array) $record['declared'][ $agent ] ) : 'missing';
			if ( ! isset( $sets[ $set ] ) ) {
				$sets[ $set ] = array();
			}
			$sets[ $set ][] = mfw_rendered_seo_analysis_identity( $record['row'] );
		}
		$population = count( $records );
		$items = array();
		foreach ( $sets as $set => $matches ) {
			$items[] = array(
				'value'      => $set,
				'matches'    => count( $matches ),
				'population' => $population,
				'coverage'   => $population ? round( ( count( $matches ) / $population ) * 100, 2 ) : 0,
				'objects'    => $matches,
			);
		}
		usort(
			$items,
			static function ( $a, $b ) {
				return $a['matches'] === $b['matches'] ? strcmp( $a['value'], $b['value'] ) : ( $a['matches'] > $b['matches'] ? -1 : 1 );
			}
		);
		foreach ( $items as $index => &$item ) {
			$item = array_merge( array( 'rank' => $index + 1 ), $item );
		}
		unset( $item );
		$summary[ $agent ] = $items;
	}
	return $summary;
}

/**
 * Classify whether one field is missing, inherited-equivalent, or different.
 *
 * @param array  $row Rendered SEO row.
 * @param string $field Field to classify.
 * @param array  $references Ordered comparison fields and labels.
 * @return string
 */
function mfw_rendered_seo_equivalence_value( $row, $field, $references ) {
	$value = isset( $row[ $field ] ) ? mfw_rendered_seo_normalize_pattern_text( $row[ $field ] ) : '';
	if ( '' === $value ) {
		return 'missing';
	}
	foreach ( $references as $reference => $label ) {
		$reference_value = isset( $row[ $reference ] ) ? mfw_rendered_seo_normalize_pattern_text( $row[ $reference ] ) : '';
		if ( '' !== $reference_value && $value === $reference_value ) {
			return $label;
		}
	}
	return 'explicitly_different';
}

/**
 * Build machine-readable aggregate analysis for the Drupal importer.
 *
 * @param array $rows Rendered SEO rows.
 * @param array $metadata Audit context.
 * @return array
 */
function mfw_rendered_seo_build_analysis( $rows, $metadata ) {
	$successful = 0;
	$failed     = 0;
	$types      = array();
	$equivalence_rows = array();
	foreach ( $rows as $row ) {
		if ( 'success' === $row['crawl_status'] ) {
			$successful++;
			$types[ $row['object_type'] ] = true;
		} else {
			$failed++;
		}
		$copy = $row;
		$copy['og_title_equivalence'] = mfw_rendered_seo_equivalence_value( $row, 'og_title', array( 'html_title' => 'same_as_html_title' ) );
		$copy['og_description_equivalence'] = mfw_rendered_seo_equivalence_value( $row, 'og_description', array( 'meta_description' => 'same_as_meta_description' ) );
		$copy['twitter_title_equivalence'] = mfw_rendered_seo_equivalence_value( $row, 'twitter_title', array( 'og_title' => 'same_as_og_title', 'html_title' => 'same_as_html_title' ) );
		$copy['twitter_description_equivalence'] = mfw_rendered_seo_equivalence_value( $row, 'twitter_description', array( 'og_description' => 'same_as_og_description', 'meta_description' => 'same_as_meta_description' ) );
		$copy['twitter_image_equivalence'] = mfw_rendered_seo_equivalence_value( $row, 'twitter_image', array( 'og_image' => 'same_as_og_image' ) );
		$equivalence_rows[] = $copy;
	}
	ksort( $types );
	$type_candidates = array();
	foreach ( array_keys( $types ) as $type ) {
		$type_candidates[ $type ] = mfw_rendered_seo_title_candidates( $rows, $type );
	}
	return array(
		'schema_version' => 1,
		'environment_name' => $metadata['environment_name'],
		'site_id'        => $metadata['site_id'],
		'site_name'      => $metadata['site_name'],
		'site_url'       => $metadata['site_url'],
		'generated_at'   => $metadata['generated_at'],
		'population'     => array(
			'total'      => count( $rows ),
			'successful' => $successful,
			'failed'     => $failed,
		),
		'titles'         => array(
			'sitewide_candidates'    => mfw_rendered_seo_title_candidates( $rows ),
			'content_type_candidates' => $type_candidates,
		),
		'canonicals'     => array(
			'classifications' => mfw_rendered_seo_categorical_summary( $rows, 'canonical_class' ),
		),
		'robots'         => array(
			'directive_sets' => mfw_rendered_seo_categorical_summary( $rows, 'normalized_robots' ),
			'agents'         => mfw_rendered_seo_robot_agent_summary( $rows ),
		),
		'completeness'   => mfw_rendered_seo_completeness_summary(
			$rows,
			array( 'meta_description', 'og_title', 'og_description', 'og_image', 'twitter_card', 'twitter_title', 'twitter_description', 'twitter_image', 'hreflang_json' )
		),
		'equivalence'    => array(
			'og_title'            => mfw_rendered_seo_categorical_summary( $equivalence_rows, 'og_title_equivalence' ),
			'og_description'      => mfw_rendered_seo_categorical_summary( $equivalence_rows, 'og_description_equivalence' ),
			'twitter_title'       => mfw_rendered_seo_categorical_summary( $equivalence_rows, 'twitter_title_equivalence' ),
			'twitter_description' => mfw_rendered_seo_categorical_summary( $equivalence_rows, 'twitter_description_equivalence' ),
			'twitter_image'       => mfw_rendered_seo_categorical_summary( $equivalence_rows, 'twitter_image_equivalence' ),
		),
	);
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
		$base['canonical_class'] = mfw_rendered_seo_classify_canonical( $parsed['canonical'], $final_url );
		$base['normalized_robots'] = mfw_rendered_seo_normalize_robots( $all_robots );
		$base['noindex']      = mfw_rendered_seo_has_robot_directive( $all_robots, 'noindex' );
		$base['nofollow']     = mfw_rendered_seo_has_robot_directive( $all_robots, 'nofollow' );
		foreach ( array( 'html_title', 'meta_description', 'canonical', 'robots', 'robot_directives_json', 'og_title', 'og_description', 'og_image', 'og_image_alt', 'og_type', 'og_url', 'twitter_card', 'twitter_title', 'twitter_description', 'twitter_image', 'twitter_image_alt', 'hreflang_json', 'duplicate_fields', 'conflicting_fields', 'html_title_pattern' ) as $field ) {
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
		$metadata['rendered_seo_analysis'] = mfw_rendered_seo_build_analysis( $artifacts['rendered_seo'], $metadata );
	}
}
