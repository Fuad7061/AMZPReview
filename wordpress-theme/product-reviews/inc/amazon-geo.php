<?php
/**
 * Amazon geo-routing — auto-swap amazon.com → local marketplace for the visitor's country,
 * using per-locale Associates tags (OneLink-style fallback).
 *
 * Detection order: CF-IPCountry header → CloudFront-Viewer-Country →
 * Accept-Language → site default.
 *
 * Per-locale tag map lives in option `pr_amazon_locale_tags` (assoc array).
 * Admin can edit via Reviews → Settings (see admin-page.php). If a locale
 * has no configured tag, falls back to .com with the default tag.
 *
 * @package ProductReviews
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Locale → [host, currency, region]. Covers all PA-API marketplaces. */
function pr_amazon_locales(): array {
	return array(
		'US' => array( 'host' => 'www.amazon.com',    'currency' => 'USD', 'region' => 'us-east-1' ),
		'CA' => array( 'host' => 'www.amazon.ca',     'currency' => 'CAD', 'region' => 'us-east-1' ),
		'MX' => array( 'host' => 'www.amazon.com.mx', 'currency' => 'MXN', 'region' => 'us-east-1' ),
		'BR' => array( 'host' => 'www.amazon.com.br', 'currency' => 'BRL', 'region' => 'us-east-1' ),
		'GB' => array( 'host' => 'www.amazon.co.uk',  'currency' => 'GBP', 'region' => 'eu-west-1' ),
		'DE' => array( 'host' => 'www.amazon.de',     'currency' => 'EUR', 'region' => 'eu-west-1' ),
		'FR' => array( 'host' => 'www.amazon.fr',     'currency' => 'EUR', 'region' => 'eu-west-1' ),
		'IT' => array( 'host' => 'www.amazon.it',     'currency' => 'EUR', 'region' => 'eu-west-1' ),
		'ES' => array( 'host' => 'www.amazon.es',     'currency' => 'EUR', 'region' => 'eu-west-1' ),
		'NL' => array( 'host' => 'www.amazon.nl',     'currency' => 'EUR', 'region' => 'eu-west-1' ),
		'SE' => array( 'host' => 'www.amazon.se',     'currency' => 'SEK', 'region' => 'eu-west-1' ),
		'PL' => array( 'host' => 'www.amazon.pl',     'currency' => 'PLN', 'region' => 'eu-west-1' ),
		'TR' => array( 'host' => 'www.amazon.com.tr', 'currency' => 'TRY', 'region' => 'eu-west-1' ),
		'AE' => array( 'host' => 'www.amazon.ae',     'currency' => 'AED', 'region' => 'eu-west-1' ),
		'SA' => array( 'host' => 'www.amazon.sa',     'currency' => 'SAR', 'region' => 'eu-west-1' ),
		'IN' => array( 'host' => 'www.amazon.in',     'currency' => 'INR', 'region' => 'eu-west-1' ),
		'JP' => array( 'host' => 'www.amazon.co.jp',  'currency' => 'JPY', 'region' => 'us-west-2' ),
		'SG' => array( 'host' => 'www.amazon.sg',     'currency' => 'SGD', 'region' => 'us-west-2' ),
		'AU' => array( 'host' => 'www.amazon.com.au', 'currency' => 'AUD', 'region' => 'us-west-2' ),
	);
}

/** Country code of the current visitor. Empty string if unknown. */
function pr_visitor_country(): string {
	static $cached = null;
	if ( $cached !== null ) { return $cached; }
	$headers = array( 'HTTP_CF_IPCOUNTRY', 'HTTP_CLOUDFRONT_VIEWER_COUNTRY', 'HTTP_X_VERCEL_IP_COUNTRY', 'HTTP_X_COUNTRY_CODE' );
	foreach ( $headers as $h ) {
		if ( ! empty( $_SERVER[ $h ] ) ) {
			$c = strtoupper( substr( sanitize_text_field( wp_unslash( $_SERVER[ $h ] ) ), 0, 2 ) );
			if ( ctype_alpha( $c ) ) { return $cached = $c; }
		}
	}
	if ( ! empty( $_SERVER['HTTP_ACCEPT_LANGUAGE'] ) ) {
		if ( preg_match( '/-([A-Z]{2})/i', (string) wp_unslash( $_SERVER['HTTP_ACCEPT_LANGUAGE'] ), $m ) ) {
			return $cached = strtoupper( $m[1] );
		}
	}
	return $cached = '';
}

/** Resolve the marketplace for the visitor: array{host,currency,region,country}. */
function pr_visitor_marketplace(): array {
	$locales = pr_amazon_locales();
	$country = pr_visitor_country();
	$default = strtoupper( (string) get_option( 'pr_amazon_default_country', 'US' ) );
	$key     = isset( $locales[ $country ] ) ? $country : ( isset( $locales[ $default ] ) ? $default : 'US' );
	return array_merge( $locales[ $key ], array( 'country' => $key ) );
}

/** Per-locale Associates tag, falls back to default tag if not configured. */
function pr_locale_tag( string $country ): string {
	$map = get_option( 'pr_amazon_locale_tags', array() );
	if ( is_array( $map ) && ! empty( $map[ $country ] ) ) {
		return sanitize_text_field( $map[ $country ] );
	}
	return pr_affiliate_tag();
}

/**
 * Hook the helpers’ amazon URL builder and rewrite host + tag for the visitor.
 * Original yadfood_amazon_url() already calls apply_filters('pr_amazon_outbound_url').
 */
add_filter( 'pr_amazon_outbound_url', function ( $url, $asin, $subtag ) {
	if ( ! is_string( $url ) || $url === '' ) { return $url; }
	$mk  = pr_visitor_marketplace();
	$tag = pr_locale_tag( $mk['country'] );
	// Replace host
	$url = preg_replace( '#https?://www\.amazon\.[a-z\.]+/#i', 'https://' . $mk['host'] . '/', $url );
	// Replace tag query arg
	$url = remove_query_arg( 'tag', $url );
	$url = add_query_arg( array( 'tag' => $tag ), $url );
	return $url;
}, 5, 3 );

/** Currency formatter aware of the visitor marketplace. */
function pr_format_price_localized( $price ): string {
	if ( ! is_numeric( $price ) ) { return ''; }
	$mk     = pr_visitor_marketplace();
	$symbols = array(
		'USD' => '$', 'CAD' => 'CA$', 'MXN' => 'MX$', 'BRL' => 'R$',
		'GBP' => '£', 'EUR' => '€', 'SEK' => 'kr', 'PLN' => 'zł',
		'TRY' => '₺', 'AED' => 'AED ', 'SAR' => 'SAR ', 'INR' => '₹',
		'JPY' => '¥', 'SGD' => 'S$', 'AUD' => 'A$',
	);
	$sym = $symbols[ $mk['currency'] ] ?? '$';
	$dec = in_array( $mk['currency'], array( 'JPY' ), true ) ? 0 : 2;
	return $sym . number_format_i18n( (float) $price, $dec );
}
