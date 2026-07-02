<?php
/**
 * Site-wide compare-by-ASIN page.
 *
 * Adds a rewrite for /compare/?asins=A,B,C[,D] that renders a side-by-side
 * comparison of any reviewed Amazon products. ASINs are resolved against
 * post meta (_pr_product_table / individual product rows) across the
 * `review` CPT, so any product that ever appeared in a review can be
 * compared — without sideloading images or duplicating data.
 *
 * @package ProductReviews
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register the /compare endpoint.
 */
function pr_compare_register_rewrite() {
	add_rewrite_rule( '^compare/?$', 'index.php?pr_compare=1', 'top' );
}
add_action( 'init', 'pr_compare_register_rewrite' );

function pr_compare_query_vars( $vars ) {
	$vars[] = 'pr_compare';
	return $vars;
}
add_filter( 'query_vars', 'pr_compare_query_vars' );

/**
 * Activation helper — call once on theme switch to flush rules.
 */
function pr_compare_flush_on_switch() {
	pr_compare_register_rewrite();
	flush_rewrite_rules( false );
}
add_action( 'after_switch_theme', 'pr_compare_flush_on_switch' );

/**
 * Resolve a list of ASINs into product rows by scanning review post meta.
 *
 * Looks at `_pr_products` (array of [asin,title,rating,price,image,brand,rank,post_id])
 * or, as a fallback, the legacy `_yadfood_products` meta.
 *
 * @param string[] $asins
 * @return array<int,array>
 */
function pr_compare_resolve_asins( array $asins ) {
	$asins = array_values( array_unique( array_filter( array_map( static function ( $a ) {
		$a = strtoupper( trim( (string) $a ) );
		return preg_match( '/^[A-Z0-9]{10}$/', $a ) ? $a : '';
	}, $asins ) ) ) );

	if ( empty( $asins ) ) {
		return array();
	}

	$found = array();
	$need  = array_flip( $asins );

	$q = new WP_Query( array(
		'post_type'      => 'review',
		'post_status'    => 'publish',
		'posts_per_page' => 200,
		'orderby'        => 'modified',
		'order'          => 'DESC',
		'meta_query'     => array(
			'relation' => 'OR',
			array( 'key' => '_pr_products', 'compare' => 'EXISTS' ),
			array( 'key' => '_yadfood_products', 'compare' => 'EXISTS' ),
		),
		'no_found_rows'  => true,
		'fields'         => 'ids',
	) );

	foreach ( $q->posts as $post_id ) {
		$products = get_post_meta( $post_id, '_pr_products', true );
		if ( empty( $products ) || ! is_array( $products ) ) {
			$products = get_post_meta( $post_id, '_yadfood_products', true );
		}
		if ( empty( $products ) || ! is_array( $products ) ) {
			continue;
		}
		foreach ( $products as $p ) {
			$asin = isset( $p['asin'] ) ? strtoupper( (string) $p['asin'] ) : '';
			if ( ! $asin || ! isset( $need[ $asin ] ) || isset( $found[ $asin ] ) ) {
				continue;
			}
			$p['post_id']   = $post_id;
			$p['post_slug'] = get_post_field( 'post_name', $post_id );
			$p['post_url']  = get_permalink( $post_id );
			$found[ $asin ] = $p;
			if ( count( $found ) === count( $asins ) ) {
				break 2;
			}
		}
	}

	// Preserve user-supplied order.
	$ordered = array();
	foreach ( $asins as $a ) {
		if ( isset( $found[ $a ] ) ) {
			$ordered[] = $found[ $a ];
		}
	}
	return $ordered;
}

/**
 * Route /compare to the compare template.
 */
function pr_compare_template_include( $template ) {
	if ( (int) get_query_var( 'pr_compare' ) !== 1 ) {
		return $template;
	}
	$custom = locate_template( array( 'compare.php' ) );
	return $custom ? $custom : $template;
}
add_filter( 'template_include', 'pr_compare_template_include' );

/**
 * Generic helper used by the template + JSON-LD.
 */
function pr_compare_get_requested_products() {
	$raw = isset( $_GET['asins'] ) ? (string) wp_unslash( $_GET['asins'] ) : '';
	if ( '' === $raw ) {
		return array();
	}
	$asins = array_slice( preg_split( '/[\s,]+/', $raw ), 0, 4 );
	return pr_compare_resolve_asins( $asins );
}
