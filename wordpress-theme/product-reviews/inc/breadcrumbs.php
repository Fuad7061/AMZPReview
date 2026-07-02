<?php
/**
 * Breadcrumbs — visible trail + BreadcrumbList JSON-LD.
 *
 * Builds a single canonical breadcrumb trail for every front-end view and
 * exposes it as both an accessible <nav> and schema.org JSON-LD. Designed
 * to coexist with existing schema modules (runs at wp_head priority 58,
 * right after entity-graph at 57).
 *
 * @package ProductReviews
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Build the breadcrumb trail for the current request.
 *
 * @return array<int,array{name:string,url:string}>
 */
function pr_bc_trail() {
	$trail = array();
	$home  = array(
		'name' => __( 'Home', 'product-reviews' ),
		'url'  => home_url( '/' ),
	);
	$trail[] = $home;

	if ( is_front_page() ) {
		return $trail;
	}

	if ( is_singular( 'review' ) ) {
		$post_id = get_queried_object_id();
		$terms   = get_the_terms( $post_id, 'review_category' );
		if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
			$term = $terms[0];
			// Walk up the parent chain.
			$chain  = array();
			$cursor = $term;
			$guard  = 0;
			while ( $cursor && $guard < 8 ) {
				$chain[] = $cursor;
				if ( empty( $cursor->parent ) ) {
					break;
				}
				$cursor = get_term( $cursor->parent, 'review_category' );
				if ( is_wp_error( $cursor ) ) {
					break;
				}
				$guard++;
			}
			$chain = array_reverse( $chain );
			foreach ( $chain as $t ) {
				$link = get_term_link( $t );
				if ( is_wp_error( $link ) ) {
					continue;
				}
				$trail[] = array(
					'name' => $t->name,
					'url'  => $link,
				);
			}
		}
		$trail[] = array(
			'name' => get_the_title( $post_id ),
			'url'  => get_permalink( $post_id ),
		);
		return $trail;
	}

	if ( is_tax( 'review_category' ) || is_category() ) {
		$term = get_queried_object();
		if ( $term && isset( $term->term_id ) ) {
			$chain  = array();
			$cursor = $term;
			$guard  = 0;
			while ( $cursor && $guard < 8 ) {
				$chain[] = $cursor;
				if ( empty( $cursor->parent ) ) {
					break;
				}
				$cursor = get_term( $cursor->parent, $term->taxonomy );
				if ( is_wp_error( $cursor ) ) {
					break;
				}
				$guard++;
			}
			$chain = array_reverse( $chain );
			foreach ( $chain as $t ) {
				$link = get_term_link( $t );
				if ( is_wp_error( $link ) ) {
					continue;
				}
				$trail[] = array(
					'name' => $t->name,
					'url'  => $link,
				);
			}
		}
		return $trail;
	}

	if ( is_post_type_archive( 'review' ) ) {
		$trail[] = array(
			'name' => post_type_archive_title( '', false ),
			'url'  => get_post_type_archive_link( 'review' ),
		);
		return $trail;
	}

	if ( is_search() ) {
		$trail[] = array(
			'name' => sprintf( __( 'Search: %s', 'product-reviews' ), get_search_query() ),
			'url'  => home_url( '/?s=' . rawurlencode( get_search_query() ) ),
		);
		return $trail;
	}

	if ( is_page() ) {
		$id     = get_queried_object_id();
		$chain  = array();
		$cursor = $id;
		$guard  = 0;
		while ( $cursor && $guard < 8 ) {
			$chain[] = $cursor;
			$parent  = wp_get_post_parent_id( $cursor );
			if ( ! $parent ) {
				break;
			}
			$cursor = $parent;
			$guard++;
		}
		$chain = array_reverse( $chain );
		foreach ( $chain as $pid ) {
			$trail[] = array(
				'name' => get_the_title( $pid ),
				'url'  => get_permalink( $pid ),
			);
		}
		return $trail;
	}

	if ( is_singular() ) {
		$id      = get_queried_object_id();
		$trail[] = array(
			'name' => get_the_title( $id ),
			'url'  => get_permalink( $id ),
		);
		return $trail;
	}

	return $trail;
}

/**
 * Render visible breadcrumb nav. Returns HTML string or empty.
 */
function pr_bc_render_html() {
	if ( is_front_page() ) {
		return '';
	}
	$trail = pr_bc_trail();
	if ( count( $trail ) < 2 ) {
		return '';
	}
	$last = count( $trail ) - 1;
	$out  = '<nav class="pr-breadcrumbs" aria-label="' . esc_attr__( 'Breadcrumb', 'product-reviews' ) . '"><ol>';
	foreach ( $trail as $i => $item ) {
		$out .= '<li>';
		if ( $i === $last ) {
			$out .= '<span aria-current="page">' . esc_html( $item['name'] ) . '</span>';
		} else {
			$out .= '<a href="' . esc_url( $item['url'] ) . '">' . esc_html( $item['name'] ) . '</a>';
			$out .= '<span class="pr-bc-sep" aria-hidden="true">›</span>';
		}
		$out .= '</li>';
	}
	$out .= '</ol></nav>';
	return $out;
}

/**
 * Auto-inject visible breadcrumbs above the_content on singular reviews,
 * pages, and taxonomy/archive headers (via the_archive_description filter).
 * Themes can disable with: add_filter( 'pr_bc_auto_inject', '__return_false' );
 */
function pr_bc_inject_content( $content ) {
	if ( ! is_singular() || is_front_page() || is_admin() || ! in_the_loop() || ! is_main_query() ) {
		return $content;
	}
	if ( ! apply_filters( 'pr_bc_auto_inject', true ) ) {
		return $content;
	}
	$html = pr_bc_render_html();
	if ( ! $html ) {
		return $content;
	}
	return $html . $content;
}
add_filter( 'the_content', 'pr_bc_inject_content', 5 );

/**
 * Scoped styles.
 */
function pr_bc_styles() {
	echo '<style id="pr-bc-styles">'
		. '.pr-breadcrumbs{font-size:.85rem;color:#555;margin:0 0 1rem;}'
		. '.pr-breadcrumbs ol{list-style:none;margin:0;padding:0;display:flex;flex-wrap:wrap;gap:.25rem;}'
		. '.pr-breadcrumbs li{display:inline-flex;align-items:center;}'
		. '.pr-breadcrumbs a{color:inherit;text-decoration:none;border-bottom:1px dotted currentColor;}'
		. '.pr-breadcrumbs a:hover{border-bottom-style:solid;}'
		. '.pr-bc-sep{margin:0 .4rem;opacity:.6;}'
		. '.pr-breadcrumbs [aria-current="page"]{font-weight:600;color:#222;}'
		. '</style>';
}
add_action( 'wp_head', 'pr_bc_styles', 92 );

/**
 * Emit BreadcrumbList JSON-LD.
 */
function pr_bc_jsonld() {
	$trail = pr_bc_trail();
	if ( count( $trail ) < 2 ) {
		return;
	}
	$items = array();
	foreach ( $trail as $i => $item ) {
		$items[] = array(
			'@type'    => 'ListItem',
			'position' => $i + 1,
			'name'     => $item['name'],
			'item'     => $item['url'],
		);
	}
	$data = array(
		'@context'        => 'https://schema.org',
		'@type'           => 'BreadcrumbList',
		'@id'             => home_url( '/' ) . '#breadcrumb-' . md5( (string) wp_json_encode( $items ) ),
		'itemListElement' => $items,
	);
	echo '<script type="application/ld+json">' . wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>' . "\n";
}
add_action( 'wp_head', 'pr_bc_jsonld', 58 );
