<?php
/**
 * Tiny Mailchimp v3 REST client. Uses wp_remote_request, Basic auth, and
 * derives the data-center from the API key suffix (e.g. "...-us21").
 *
 * @package ProductReviews
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

function pr_mc_dc( $api_key ) {
	$pos = strrpos( (string) $api_key, '-' );
	return ( false === $pos ) ? '' : substr( $api_key, $pos + 1 );
}

function pr_mc_request( $api_key, $method, $path, $body = null ) {
	$dc = pr_mc_dc( $api_key );
	if ( '' === $dc ) { return new WP_Error( 'mc_bad_key', 'Mailchimp API key missing data-center suffix.' ); }
	$url  = 'https://' . $dc . '.api.mailchimp.com/3.0/' . ltrim( $path, '/' );
	$args = array(
		'method'  => strtoupper( $method ),
		'timeout' => 15,
		'headers' => array(
			'Authorization' => 'Basic ' . base64_encode( 'anystring:' . $api_key ),
			'Content-Type'  => 'application/json',
			'Accept'        => 'application/json',
		),
	);
	if ( null !== $body ) { $args['body'] = wp_json_encode( $body ); }

	for ( $i = 0; $i < 2; $i++ ) {
		$res = wp_remote_request( $url, $args );
		if ( is_wp_error( $res ) ) { return $res; }
		$code = wp_remote_retrieve_response_code( $res );
		if ( 429 === $code ) { sleep( 1 ); continue; }
		$raw  = wp_remote_retrieve_body( $res );
		$json = json_decode( $raw, true );
		if ( $code >= 200 && $code < 300 ) { return $json ? $json : array(); }
		$err  = isset( $json['detail'] ) ? $json['detail'] : ( 'HTTP ' . $code );
		return new WP_Error( 'mc_http_' . $code, $err, $json );
	}
	return new WP_Error( 'mc_retry_exhausted', 'Mailchimp rate-limited twice.' );
}

function pr_mc_ping( $api_key ) {
	return pr_mc_request( $api_key, 'GET', 'ping' );
}
function pr_mc_lists( $api_key ) {
	$r = pr_mc_request( $api_key, 'GET', 'lists?count=100&fields=lists.id,lists.name' );
	if ( is_wp_error( $r ) ) { return $r; }
	return isset( $r['lists'] ) ? $r['lists'] : array();
}
