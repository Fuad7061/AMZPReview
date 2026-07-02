<?php
/**
 * PR_Source_Scrape — Firecrawl-backed Amazon scrape, optional cookie header.
 *
 * Cookie jar pasted in admin (raw "Cookie:" header string) is stored in a
 * WP option encrypted with a key derived from AUTH_KEY.
 *
 * @package ProductReviews
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class PR_Source_Scrape implements PR_Source {

	public function id(): string    { return 'scrape'; }
	public function label(): string { return __( 'Scrape (Firecrawl + optional cookies)', 'product-reviews' ); }

	public function is_configured(): bool {
		return (bool) trim( (string) get_option( 'pr_firecrawl_api_key', '' ) );
	}

	public function search( string $keyword, int $page = 1, int $count = 10 ) {
		if ( ! $this->is_configured() ) {
			return new WP_Error( 'pr_scrape_disabled', __( 'Firecrawl API key not set.', 'product-reviews' ) );
		}
		$cache_key = 'pr_scrape_' . md5( strtolower( $keyword ) . '|' . $page );
		$cached = get_transient( $cache_key );
		if ( false !== $cached ) { return $cached; }

		$tag = pr_affiliate_tag();
		$url = 'https://www.amazon.com/s?' . http_build_query( array( 'k' => $keyword, 'page' => $page ) );

		$headers = array(
			'Authorization' => 'Bearer ' . get_option( 'pr_firecrawl_api_key', '' ),
			'Content-Type'  => 'application/json',
		);
		$cookie = pr_decrypt_option( 'pr_amazon_cookie' );
		$body   = array(
			'url'     => $url,
			'formats' => array( 'json' ),
			'jsonOptions' => array(
				'prompt' => 'Extract up to 10 Amazon product cards as an array under key "items" with fields: asin, title, brand, image, price (number), list_price, rating (number), rating_count (int), prime (bool), url.',
			),
		);
		if ( $cookie ) {
			$body['headers'] = array( 'Cookie' => $cookie );
		}

		$resp = wp_remote_post( 'https://api.firecrawl.dev/v2/scrape', array(
			'timeout' => 60,
			'headers' => $headers,
			'body'    => wp_json_encode( $body ),
		) );
		if ( is_wp_error( $resp ) ) { return $resp; }
		$code = wp_remote_retrieve_response_code( $resp );
		$j    = json_decode( wp_remote_retrieve_body( $resp ), true );
		if ( $code >= 400 || empty( $j ) ) {
			return new WP_Error( 'pr_scrape_http', "Firecrawl HTTP {$code}" );
		}
		$items = $j['json']['items'] ?? $j['data']['json']['items'] ?? array();
		if ( ! is_array( $items ) || ! $items ) {
			return new WP_Error( 'pr_scrape_empty', __( 'No items parsed from Amazon.', 'product-reviews' ) );
		}
		// Reuse Lambda normalizer for shape consistency.
		$lambda = new PR_Source_Lambda();
		$ref    = new ReflectionMethod( $lambda, 'normalize' );
		$ref->setAccessible( true );
		$rows = $ref->invoke( $lambda, array( 'items' => $items ), $count, $tag );
		foreach ( $rows as &$r ) { $r['source'] = $this->id(); }
		set_transient( $cache_key, $rows, 30 * MINUTE_IN_SECONDS );
		return $rows;
	}

	public function get_item( string $asin ) {
		$res = $this->search( $asin, 1, 1 );
		if ( is_wp_error( $res ) || empty( $res ) ) { return $res ?: new WP_Error( 'pr_scrape_not_found', '' ); }
		return $res[0];
	}

	public function health() {
		return $this->is_configured() ? true : new WP_Error( 'pr_scrape_disabled', 'No Firecrawl key' );
	}
}
