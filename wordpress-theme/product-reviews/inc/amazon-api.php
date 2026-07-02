<?php
/**
 * Amazon Product Advertising API 5.0 client (SearchItems).
 *
 * Uses AWS Signature V4 to call PA-API directly — no SDK needed, no external
 * dependencies (works on any cPanel host with PHP 8+).
 *
 * Cached for 12h per query to stay under PA-API quota.
 *
 * @package YadFoodReviews
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Public entry point used by older modules. Now delegates to the
 * PR_Source_Manager driver chain (Lambda → Scrape → Creators → PA-API).
 *
 * The original SigV4 PA-API implementation is preserved as
 * yadfood_amazon_search_legacy() so the PA-API driver can call it.
 *
 * @param string $keyword
 * @param int    $count
 * @return array<int,array<string,mixed>>|WP_Error
 */
function yadfood_amazon_search( $keyword, $count = 10 ) {
	if ( defined( 'PR_BYPASS_MANAGER' ) && PR_BYPASS_MANAGER ) {
		return yadfood_amazon_search_legacy( $keyword, $count );
	}
	return PR_Source_Manager::search( (string) $keyword, 1, (int) $count );
}

/**
 * Legacy SigV4 PA-API 5.0 implementation. Kept for the PAAPI driver.
 */
function yadfood_amazon_search_legacy( $keyword, $count = 10 ) {
	$cache_key = 'yadfood_paapi_' . md5( strtolower( $keyword ) . '|' . $count );
	$cached    = get_transient( $cache_key );
	if ( false !== $cached ) {
		return $cached;
	}

	$access = trim( get_option( 'yadfood_paapi_access_key', '' ) );
	$secret = trim( get_option( 'yadfood_paapi_secret_key', '' ) );
	$tag    = yadfood_amazon_tag();
	$region = get_option( 'yadfood_paapi_region', 'us-east-1' );
	$host   = 'webservices.amazon.com'; // US marketplace
	$path   = '/paapi5/searchitems';

	if ( empty( $access ) || empty( $secret ) ) {
		return new WP_Error( 'paapi_missing_keys', __( 'Amazon PA-API keys not configured. Set them in Appearance → Customize → Amazon Product API.', 'yadfood-reviews' ) );
	}

	$payload = wp_json_encode( array(
		'Keywords'      => $keyword,
		'Resources'     => array(
			'Images.Primary.Large',
			'ItemInfo.Title',
			'ItemInfo.Features',
			'ItemInfo.ByLineInfo',
			'Offers.Listings.Price',
			'CustomerReviews.StarRating',
			'CustomerReviews.Count',
		),
		'PartnerTag'    => $tag,
		'PartnerType'   => 'Associates',
		'Marketplace'   => get_option( 'yadfood_paapi_marketplace', 'www.amazon.com' ),
		'ItemCount'     => max( 1, min( 10, (int) $count ) ),
	) );

	$amz_date     = gmdate( 'Ymd\THis\Z' );
	$date_stamp   = gmdate( 'Ymd' );
	$content_hash = hash( 'sha256', $payload );

	$canonical_headers = "content-encoding:amz-1.0\n"
		. "content-type:application/json; charset=UTF-8\n"
		. "host:{$host}\n"
		. "x-amz-date:{$amz_date}\n"
		. "x-amz-target:com.amazon.paapi5.v1.ProductAdvertisingAPIv1.SearchItems\n";
	$signed_headers = 'content-encoding;content-type;host;x-amz-date;x-amz-target';

	$canonical_request = "POST\n{$path}\n\n{$canonical_headers}\n{$signed_headers}\n{$content_hash}";

	$algorithm        = 'AWS4-HMAC-SHA256';
	$credential_scope = "{$date_stamp}/{$region}/ProductAdvertisingAPI/aws4_request";
	$string_to_sign   = "{$algorithm}\n{$amz_date}\n{$credential_scope}\n" . hash( 'sha256', $canonical_request );

	$k_date    = hash_hmac( 'sha256', $date_stamp, 'AWS4' . $secret, true );
	$k_region  = hash_hmac( 'sha256', $region, $k_date, true );
	$k_service = hash_hmac( 'sha256', 'ProductAdvertisingAPI', $k_region, true );
	$k_signing = hash_hmac( 'sha256', 'aws4_request', $k_service, true );
	$signature = hash_hmac( 'sha256', $string_to_sign, $k_signing );

	$authorization = "{$algorithm} Credential={$access}/{$credential_scope}, SignedHeaders={$signed_headers}, Signature={$signature}";

	$response = wp_remote_post( "https://{$host}{$path}", array(
		'headers' => array(
			'Content-Encoding' => 'amz-1.0',
			'Content-Type'     => 'application/json; charset=UTF-8',
			'Host'             => $host,
			'X-Amz-Date'       => $amz_date,
			'X-Amz-Target'     => 'com.amazon.paapi5.v1.ProductAdvertisingAPIv1.SearchItems',
			'Authorization'    => $authorization,
		),
		'body'    => $payload,
		'timeout' => 30,
	) );

	if ( is_wp_error( $response ) ) {
		return $response;
	}
	$code = wp_remote_retrieve_response_code( $response );
	$body = json_decode( wp_remote_retrieve_body( $response ), true );
	if ( $code >= 400 || empty( $body['SearchResult']['Items'] ) ) {
		$msg = isset( $body['Errors'][0]['Message'] ) ? $body['Errors'][0]['Message'] : ( "HTTP {$code}" );
		return new WP_Error( 'paapi_error', $msg, $body );
	}

	$items = array();
	foreach ( $body['SearchResult']['Items'] as $idx => $it ) {
		$items[] = array(
			'rank'         => $idx + 1,
			'asin'         => isset( $it['ASIN'] ) ? $it['ASIN'] : '',
			'title'        => isset( $it['ItemInfo']['Title']['DisplayValue'] ) ? $it['ItemInfo']['Title']['DisplayValue'] : '',
			'image'        => isset( $it['Images']['Primary']['Large']['URL'] ) ? $it['Images']['Primary']['Large']['URL'] : '',
			'price'        => isset( $it['Offers']['Listings'][0]['Price']['Amount'] ) ? $it['Offers']['Listings'][0]['Price']['Amount'] : '',
			'rating'       => isset( $it['CustomerReviews']['StarRating']['Value'] ) ? $it['CustomerReviews']['StarRating']['Value'] : '',
			'review_count' => isset( $it['CustomerReviews']['Count'] ) ? $it['CustomerReviews']['Count'] : '',
			'features'     => isset( $it['ItemInfo']['Features']['DisplayValues'] ) ? $it['ItemInfo']['Features']['DisplayValues'] : array(),
			'brand'        => isset( $it['ItemInfo']['ByLineInfo']['Brand']['DisplayValue'] ) ? $it['ItemInfo']['ByLineInfo']['Brand']['DisplayValue'] : '',
		);
	}

	set_transient( $cache_key, $items, 12 * HOUR_IN_SECONDS );
	return $items;
}
