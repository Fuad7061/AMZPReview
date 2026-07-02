<?php
/**
 * Search-as-you-type REST endpoint + frontend autocomplete bootstrap.
 *
 * Public route: GET /wp-json/yadfood/v1/suggest?q=...
 *
 * @package ProductReviews
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function pr_suggest_register_route() {
	register_rest_route( 'yadfood/v1', '/suggest', array(
		'methods'             => WP_REST_Server::READABLE,
		'permission_callback' => '__return_true',
		'args'                => array(
			'q'     => array( 'type' => 'string', 'required' => true ),
			'limit' => array( 'type' => 'integer', 'default' => 8 ),
		),
		'callback' => 'pr_suggest_handle',
	) );
}
add_action( 'rest_api_init', 'pr_suggest_register_route' );

function pr_suggest_handle( WP_REST_Request $req ) {
	$raw = trim( (string) $req->get_param( 'q' ) );
	if ( strlen( $raw ) < 2 ) {
		return new WP_REST_Response( array( 'q' => $raw, 'results' => array() ), 200 );
	}
	$norm  = function_exists( 'yadfood_normalize_query' ) ? yadfood_normalize_query( $raw ) : $raw;
	$limit = max( 1, min( 20, (int) $req->get_param( 'limit' ) ) );

	$cache_key = 'pr_suggest_' . md5( strtolower( $norm ) . '|' . $limit );
	$cached    = get_transient( $cache_key );
	if ( false !== $cached ) {
		return new WP_REST_Response( $cached, 200 );
	}

	$results = array();

	// 1) Review post matches.
	$q = new WP_Query( array(
		'post_type'      => 'review',
		'post_status'    => 'publish',
		'posts_per_page' => $limit,
		's'              => $norm,
		'no_found_rows'  => true,
	) );
	foreach ( $q->posts as $post ) {
		$results[] = array(
			'type'  => 'review',
			'title' => get_the_title( $post ),
			'url'   => get_permalink( $post ),
			'meta'  => get_the_date( '', $post ),
		);
	}

	// 2) review_category term matches.
	if ( count( $results ) < $limit ) {
		$terms = get_terms( array(
			'taxonomy'   => 'review_category',
			'hide_empty' => true,
			'number'     => $limit - count( $results ),
			'name__like' => $norm,
		) );
		if ( ! is_wp_error( $terms ) ) {
			foreach ( $terms as $t ) {
				$link = get_term_link( $t );
				if ( is_wp_error( $link ) || ! is_string( $link ) || '' === $link ) {
					continue;
				}
				$results[] = array(
					'type'  => 'category',
					'title' => $t->name,
					'url'   => $link,
					'meta'  => sprintf( _n( '%d review', '%d reviews', $t->count, 'product-reviews' ), $t->count ),
				);
			}
		}
	}

	// 3) Product-data fallback/live suggestions so a fresh install still feels useful.
	if ( count( $results ) < $limit && function_exists( 'pr_product_search' ) ) {
		$products = pr_product_search( $norm, $limit - count( $results ) );
		if ( is_wp_error( $products ) && function_exists( 'pr_default_products_for_keyword' ) ) {
			$products = pr_default_products_for_keyword( $norm, $limit - count( $results ) );
		}
		if ( is_array( $products ) ) {
			foreach ( $products as $p ) {
				if ( empty( $p['title'] ) ) { continue; }
				$asin = isset( $p['asin'] ) ? (string) $p['asin'] : '';
				$url  = ! empty( $p['url'] ) ? (string) $p['url'] : ( $asin && function_exists( 'yadfood_amazon_url' ) ? yadfood_amazon_url( $asin, sanitize_title( $norm ) ) : home_url( '/?s=' . rawurlencode( $norm ) ) );
				$meta = array();
				if ( ! empty( $p['rating'] ) ) { $meta[] = number_format( (float) $p['rating'], 1 ) . '★'; }
				if ( ! empty( $p['review_count'] ) ) { $meta[] = number_format_i18n( (int) $p['review_count'] ) . ' reviews'; }
				$results[] = array(
					'type'  => 'product',
					'title' => (string) $p['title'],
					'url'   => $url,
					'meta'  => implode( ' · ', $meta ),
				);
				if ( count( $results ) >= $limit ) { break; }
			}
		}
	}

	$payload = array( 'q' => $raw, 'normalized' => $norm, 'results' => $results );
	set_transient( $cache_key, $payload, 5 * MINUTE_IN_SECONDS );

	return new WP_REST_Response( $payload, 200 );
}

/**
 * Expose REST root + nonce to the autocomplete script.
 */
function pr_suggest_enqueue() {
	if ( is_admin() ) return;
	wp_register_script( 'pr-suggest', PR_THEME_URI . '/assets/js/suggest.js', array(), PR_VERSION, true );
	wp_localize_script( 'pr-suggest', 'PR_SUGGEST', array(
		'endpoint' => esc_url_raw( rest_url( 'yadfood/v1/suggest' ) ),
		'searchUrl' => esc_url_raw( home_url( '/?s=' ) ),
	) );
	wp_enqueue_script( 'pr-suggest' );
}
add_action( 'wp_enqueue_scripts', 'pr_suggest_enqueue' );
