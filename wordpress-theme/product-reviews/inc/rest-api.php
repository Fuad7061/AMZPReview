<?php
/**
 * REST API endpoints.
 *
 *  POST /wp-json/yadfood/v1/generate   { keyword, count?, status? }
 *  POST /wp-json/yadfood/v1/click      { asin, slug? }   — affiliate click logger (no-op stub)
 *
 * @package YadFoodReviews
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function yadfood_register_rest_routes() {
	register_rest_route( 'yadfood/v1', '/generate', array(
		'methods'             => 'POST',
		'permission_callback' => function () { return current_user_can( 'edit_posts' ); },
		'callback'            => function ( WP_REST_Request $req ) {
			$kw     = sanitize_text_field( (string) $req->get_param( 'keyword' ) );
			$count  = max( 3, min( 10, (int) ( $req->get_param( 'count' ) ?: 10 ) ) );
			$status = 'publish' === $req->get_param( 'status' ) ? 'publish' : 'draft';
			$id     = yadfood_generate_review( $kw, $count, $status );
			if ( is_wp_error( $id ) ) {
				return new WP_REST_Response( array( 'error' => $id->get_error_message() ), 400 );
			}
			return array( 'id' => $id, 'edit' => get_edit_post_link( $id, '' ), 'view' => get_permalink( $id ) );
		},
	) );

	register_rest_route( 'yadfood/v1', '/click', array(
		'methods'             => 'POST',
		'permission_callback' => '__return_true',
		'callback'            => function ( WP_REST_Request $req ) {
			// Stub: extend with analytics / DB logging as needed.
			$asin = sanitize_text_field( (string) $req->get_param( 'asin' ) );
			$slug = sanitize_title( (string) $req->get_param( 'slug' ) );
			do_action( 'yadfood_affiliate_click', $asin, $slug );
			return array( 'ok' => true );
		},
	) );
}
add_action( 'rest_api_init', 'yadfood_register_rest_routes' );
